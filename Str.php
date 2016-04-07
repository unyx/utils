<?php namespace nyx\utils;

/**
 * Str
 *
 * Helper methods for dealing with strings. The class is based on Laravel, FuelPHP, Patchwork/UTF-8 and a few others.
 * Some minor performance-related improvements were made.
 *
 * Note: Many of the methods can be circumvented by falling back directly to the builtin functions of the mbstring
 * extension as long as you don't need the additional layer of abstraction and feel comfortable managing the
 * encoding and input parameter validity on your own.
 *
 * Suggestions:
 *   If you need an instance-based fluent OO wrapper for strings with similar manipulation capabilities. then
 *   you should take a look at Stringy {@see https://github.com/danielstjules/Stringy}
 *
 * Requires:
 * - ext-mbstring
 * - ext-intl (Normalizer)
 * - ext-iconv
 *
 * @package     Nyx\Utils\Strings
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/strings.html
 * @todo        Snake case, camel case, studly caps, dashed, underscored?
 * @todo        Add afterFirst/Last, beforeFirst/Last instead of the current after/before?
 */
class Str
{
    /**
     * The traits of the Str class.
     */
    use traits\StaticallyExtendable;

    /**
     * @var string  The default encoding to use when we fail to determine it based on a given string. UTF-8 will
     *              be used when the above is true and this property is null.
     */
    public static $encoding;

    /**
     * @var bool    Whether self::encoding(), used internally by all methods which accept an encoding,
     *              should attempt to determine the encoding from the string given to it, if it's not
     *              explicitly specified. Note: This adds (relatively big) overhead to most methods and
     *              is therefore set to false, since the default of UTF-8 should satisfy most use-cases.
     */
    public static $autoDetectEncoding = false;

    /**
     * @var array   The transliteration table used by the toAscii() method once fetched from a file.
     */
    protected static $ascii;

    /**
     * Finds the first occurrence of $needle within $haystack and returns the part of $haystack
     * after the $needle (excluding the $needle).
     *
     * @param   string      $haystack   The string to search in.
     * @param   string      $needle     The substring to search for.
     * @param   bool        $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The part of $haystack after $needle.
     * @throws  \RuntimeException       Upon failing to find $needle in $haystack at all.
     */
    public static function after(string $haystack, string $needle, bool $strict = true, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($haystack);

        // We're gonna use strstr internally to grab the part of $haystack starting at $needle
        // and including the $needle, and then simply remove the starting $needle.
        // Note: Removing 1 from the length of $needle since we're using it as start offset
        // for mb_substr which is 0-indexed.
        return mb_substr(static::partOf($haystack, $needle, $strict, false, $encoding), mb_strlen($needle, $encoding) - 1);
    }

    /**
     * Finds the first occurrence of $needle within $haystack and returns the part of $haystack
     * *before* the $needle.
     *
     * @param   string      $haystack   The string to search in.
     * @param   string      $needle     The substring to search for.
     * @param   bool        $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The part of $haystack before $needle.
     * @throws  \RuntimeException       Upon failing to find $needle in $haystack at all.
     */
    public static function before(string $haystack, string $needle, bool $strict = true, string $encoding = null) : string
    {
        return static::partOf($haystack, $needle, $strict, true, $encoding);
    }

    /**
     * Ensures the given $haystack begins with a single instance of the given $needle. For single characters
     * use PHP's native ltrim() instead.
     *
     * @param   string  $haystack   The string to cap.
     * @param   string  $needle     The substring to begin with.
     * @return  string              The resulting string.
     */
    public static function beginWith(string $haystack, string $needle) : string
    {
        return $needle . preg_replace('/^(?:'.preg_quote($needle, '/').')+/', '', $haystack);
    }

    /**
     * Returns the substring of $haystack between $startNeedle and $endNeedle.
     *
     * If all you need is to return part of a string when the offsets you are looking for are known,
     * you should use {@see self::sub()} instead.
     *
     * @param   string      $haystack       The string to search in.
     * @param   string      $startNeedle    The needle marking the start of the substring to return.
     * @param   string      $endNeedle      The needle marking the end of the substring to return.
     * @param   int         $offset         The 0-based index at which to start the search for the first needle.
     *                                      Can be negative, in which case the search will start $offset characters
     *                                      from the end of the $haystack.
     * @param   bool        $strict         Whether to use strict comparisons when searching for the needles.
     * @param   string|null $encoding       The encoding to use.
     * @return  string                      The substring between the needles.
     * @throws  \InvalidArgumentException   When $haystack or either of the needles is an empty string.
     * @throws  \OutOfBoundsException       Upon failing to find either $startNeedle or $endNeedle in $haystack.
     */
    public static function between(string $haystack, string $startNeedle, string $endNeedle, int $offset = 0, bool $strict = true, string $encoding = null) : string
    {
        // Note: We're throwing here because this method will return unpredictable results otherwise.
        // If you want to omit $firstNeedle or $secondNeedle, turn to self::after(), self::before()
        // and self::from() instead.
        // We're not validating input args any further than the below, however, as there are a lot of
        // error-inducing combinations of invalid input which all will be caught when we attempt
        // to actually look for the indices of the needles. Anything else is just way too much overhead.
        if ($haystack === '' || $startNeedle === '' || $endNeedle === '') {
            $endNeedle   === '' && $arg = '$endNeedle';
            $startNeedle === '' && $arg = '$startNeedle';
            $haystack    === '' && $arg = '$haystack';

            throw new \InvalidArgumentException($arg.' must not be an empty string.');
        }

        $encoding    = $encoding ?: static::encoding($haystack);
        $funcIndexOf = $strict ? 'mb_strpos' : 'mb_stripos';

        // mb_strpos does not natively support negative offsets, so we'll add the negative offset
        // to the length of the $haystack to get the offset from its end.
        if ($offset < 0) {
            $offset = mb_strlen($haystack, $encoding) + $offset;
        }

        // Find the offset of the first needle.
        if (false === $firstIndex = $funcIndexOf($haystack, $startNeedle, $offset, $encoding)) {
            throw new \OutOfBoundsException('Failed to find $startNeedle ['.$startNeedle.'] in $haystack ['.static::truncate($haystack, 20, '...', false, $encoding).'].');
        }

        // We're going to adjust the offset for the position of the first needle, ie. we're gonna
        // start searching for the second one right at the end of the first one.
        $offset = $firstIndex + mb_strlen($startNeedle, $encoding);

        // Find the offset of the second needle.
        if (false === $secondIndex = $funcIndexOf($haystack, $endNeedle, $offset, $encoding)) {
            throw new \OutOfBoundsException('Failed to find $endNeedle ['.$endNeedle.'] in $haystack ['.static::truncate($haystack, 20, '...', false, $encoding).'].');
        }

        // Return the substring between the needles.
        return mb_substr($haystack, $offset, $secondIndex - $offset);
    }

    /**
     * Returns the characters of the given $haystack as an array, in an offset => character format.
     *
     * @param   string      $haystack   The string to iterate over.
     * @param   string|null $encoding   The encoding to use.
     * @return  array                   The array of characters.
     */
    public static function characters(string $haystack, string $encoding = null) : array
    {
        $result = [];
        $length = mb_strlen($haystack, $encoding ?: static::encoding($haystack));

        for ($idx = 0; $idx < $length; $idx++) {
            $result[] = mb_substr($haystack, $idx, 1, $encoding);
        }

        return $result;
    }

    /**
     * Trims the given string and replaces multiple consecutive whitespaces with a single whitespace.
     *
     * Note: This includes multi-byte whitespace characters, tabs and newlines, which effectively means
     * that the string may also be collapsed down. This is mostly a utility for processing natural language
     * user input for displaying.
     *
     * @param   string          $str        The string to collapse.
     * @param   string|null     $encoding   The encoding to use.
     * @return  string                      The resulting string.
     */
    public static function collapse(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($str);

        return static::trim(static::replace($str, '[[:space:]]+', ' ', 'msr', $encoding), null, $encoding);
    }

    /**
     * Determines whether the given $haystack contains the given $needle.
     *
     * If $needle is an array, this method will check whether at least one of the substrings is contained
     * in the $haystack. If $all is set to true, all needles in the $needle array must be contained in the
     * $haystack for this method to return true.
     *
     * @param   string          $haystack   The string to check in.
     * @param   string|array    $needle     A string or an array of strings. If an array is given, the method returns
     *                                      true if at least one of the values is contained within the $haystack.
     * @param   bool            $all        Set this to true to ensure all elements of the $needle array (if provided)
     *                                      are contained within the $haystack.
     * @param   bool            $strict     Whether to use case-sensitive comparisons.
     * @param   string|null     $encoding   The encoding to use.
     * @return  bool
     */
    public static function contains(string $haystack, $needle, bool $all = false, bool $strict = true, string $encoding = null) : bool
    {
        $func     = $strict ? 'mb_strpos' : 'mb_stripos';
        $encoding = $encoding ?: static::encoding($haystack);

        foreach ((array) $needle as $value) {
            if ($func($haystack, $value, 0, $encoding) === false) {
                if ($all) {
                    return false;
                }
            } elseif (!$all) {
                return true;
            }
        }

        // When we reach this point, if we were ensuring all needles are contained within the haystack, it means
        // that we didn't fail on a single one. However, when looking for at least one of them, it means that none
        // returned true up to this point, so none of them is contained in the haystack.
        return $all ? true : false;
    }

    /**
     * Determines whether the given $haystack contains all of the $needles. Alias for self::contains() with an
     * array of needles and the $all parameter set to true.
     *
     * @see Str::contains()
     */
    public static function containsAll(string $haystack, array $needles, bool $strict = true, string $encoding = null) : bool
    {
        return static::contains($haystack, $needles, true, $strict, $encoding);
    }

    /**
     * Determines whether the given $haystack contains any of the $needles. Alias for self::contains() with an
     * array of needles and the $all parameter set to false.
     *
     * @see Str::contains()
     */
    public static function containsAny(string $haystack, array $needles, bool $strict = true, string $encoding = null) : bool
    {
        return static::contains($haystack, $needles, false, $strict, $encoding);
    }

    /**
     * Runs the given callable over each line of the given string and returns the resulting string.
     *
     * The callable should accept two arguments (in this order): the contents of the line (string)
     * and the line's number (int). Additional arguments may also be added and will be appended to the
     * callable in the order given. The callable should return a string or a value castable to a string.
     *
     * @param   string      $str        The string over which to run the callable.
     * @param   callable    $callable   The callable to apply.
     * @param   mixed       ...$args    Additional arguments to pass to the callable.
     * @return  string                  The string after applying the callable to each of its lines.
     */
    public static function eachLine(string $str, callable $callable, ...$args) : string
    {
        if ($str === '') {
            return $str;
        }

        $lines = mb_split('[\r\n]{1,2}', $str);

        foreach ($lines as $number => &$line) {
            $lines[$number] = (string) call_user_func($callable, $line, $number, ...$args);
        }

        return implode("\n", $lines);
    }

    /**
     * Attempts to determine the encoding of a string if a string is given.
     *
     * Upon failure or when no string is given, returns the static encoding set in this class or if that is not set,
     * the hardcoded default of 'utf-8'.
     *
     * @param   string|null $str
     * @return  string
     */
    public static function encoding(string $str = null) : string
    {
        // If a string was given, we attempt to detect the encoding of the string if we are told to do so.
        // If we succeed, just return the determined type.
        if (true === static::$autoDetectEncoding && null !== $str && false !== $encoding = mb_detect_encoding($str)) {
            return $encoding;
        }

        // Otherwise let's return one of the defaults.
        return static::$encoding ?: 'utf-8';
    }

    /**
     * Determines whether the given $haystack ends with the given needle or one of the given needles
     * if $needles is an array.
     *
     * @param   string          $haystack   The string to search in.
     * @param   string|array    $needles    The needle(s) to look for.
     * @param   bool            $strict     Whether to use case-sensitive comparisons.
     * @param   string|null     $encoding   The encoding to use.
     * @return  bool                        True when the string ends with one of the given needles, false otherwise.
     */
    public static function endsWith(string $haystack, $needles, bool $strict = true, string $encoding = null) : bool
    {
        if ($haystack === '') {
            return false;
        }

        $encoding = $encoding ?: static::encoding($haystack);

        foreach ((array) $needles as $needle) {

            // Empty needles are invalid. For philosophical reasons.
            if ($needle === '') {
                continue;
            }

            // Grab the substring of the haystack at an offset $needle would be at if the haystack
            // ended with it.
            $end = mb_substr($haystack, -mb_strlen($needle, $encoding), null, $encoding);

            // For case-insensitive comparisons we need a common denominator.
            if (!$strict) {
                $needle = mb_strtolower($needle, $encoding);
                $end    = mb_strtolower($end, $encoding);
            }

            // Stop looping on the first hit. Obviously we're not checking whether the $haystack
            // ends with *all* $needles, d'oh.
            if ($needle === $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensures the given $haystack ends with a single instance of the given $needle. For single characters
     * use PHP's native rtrim() instead.
     *
     * @param   string  $haystack   The string to cap.
     * @param   string  $needle     The substring to end with.
     * @return  string              The resulting string.
     */
    public static function finishWith(string $haystack, string $needle) : string
    {
        return preg_replace('/(?:'.preg_quote($needle, '/').')+$/', '', $haystack) . $needle;
    }

    /**
     * Finds the first occurrence of $needle within $haystack and returns the part of $haystack
     * starting where the $needle starts (ie. including the $needle).
     *
     * @param   string      $haystack   The string to search in.
     * @param   string      $needle     The substring to search for.
     * @param   bool        $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The part of $haystack from where $needle starts, including $needle.
     * @throws  \RuntimeException       Upon failing to find $needle in $haystack at all.
     */
    public static function from(string $haystack, string $needle, bool $strict = true, string $encoding = null) : string
    {
        return static::partOf($haystack, $needle, $strict, false, $encoding);
    }

    /**
     * Returns the index (0-indexed) of the first occurrence of $needle in the $haystack.
     *
     * Important note: Differs from native PHP strpos() in that if the $needle could not be found, it returns -1
     * instead of false.
     *
     * @param   string      $haystack   The string to search in.
     * @param   string      $needle     The substring to search for.
     * @param   int         $offset     The offset from which to search. Negative offsets will start the search $offset
     *                                  characters from the end of the $haystack. 0-indexed.
     * @param   bool        $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string|null $encoding   The encoding to use.
     * @return  int                     The index of the first occurrence if found, -1 otherwise.
     */
    public static function indexOf(string $haystack, string $needle, int $offset = 0, bool $strict = true, string $encoding = null) : int
    {
        $func     = $strict ? 'mb_strpos' : 'mb_stripos';
        $encoding = $encoding ?: static::encoding($haystack);

        if ($offset < 0) {
            $offset = mb_strlen($haystack, $encoding) + $offset;
        }

        if (false === $result = $func($haystack, $needle, $offset, $encoding)) {
            return -1;
        }

        return $result;
    }

    /**
     * Returns the index (0-indexed) of the last occurrence of $needle in the $haystack.
     *
     * Important note: Differs from native PHP strrpos() in that if the $needle could not be found, it returns -1
     * instead of false.
     *
     * @param   string  $haystack   The string to search in.
     * @param   string  $needle     The substring to search for.
     * @param   int     $offset     The offset from which to search. Negative offsets will start the search $offset
     *                              characters from the end of the $haystack. 0-indexed.
     * @param   bool    $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string  $encoding   The encoding to use.
     * @return  int                 The index of the last occurrence if found, -1 otherwise.
     */
    public static function indexOfLast(string $haystack, string $needle, int $offset = 0, bool $strict = true, string $encoding = null) : int
    {
        $func     = $strict ? 'mb_strrpos' : 'mb_strripos';
        $encoding = $encoding ?: static::encoding($haystack);

        if ($offset < 0) {
            $offset = mb_strlen($haystack, $encoding) + $offset;
        }

        if (false === $result = $func($haystack, $needle, $offset, $encoding)) {
            return -1;
        }

        return $result;
    }

    /**
     * Inserts the given $needle into the $haystack at the provided offset.
     *
     * @param   string      $haystack       The string to insert into.
     * @param   string      $needle         The string to be inserted.
     * @param   int         $offset         The offset at which to insert the substring. If negative, the $substring
     *                                      will be inserted $offset characters from the end of $str.
     * @param   string|null $encoding       The encoding to use.
     * @return  string                      The resulting string.
     * @throws  \OutOfBoundsException       When trying to insert a substring at an index above the length of the
     *                                      initial string.
     */
    public static function insert(string $haystack, string $needle, int $offset, string $encoding = null) : string
    {
        if ($needle === '') {
            return $haystack;
        }

        $encoding = $encoding ?: static::encoding($haystack);
        $length   = mb_strlen($haystack, $encoding);

        // Make sure the offset is contained in the initial string.
        if (abs($offset) >= $length) {
            throw new \OutOfBoundsException('The given $offset ['.$offset.'] does not exist within the string ['.static::truncate($haystack, 20, '...', false, $encoding).'].');
        }

        // With a negative offset, we'll convert it to a positive one for the initial part (before the inserted
        // substring), since we'll be using that as a length actually.
        return mb_substr($haystack, 0, $offset < 0 ? $length + $offset : $offset, $encoding) . $needle . mb_substr($haystack, $offset, null, $encoding);
    }

    /**
     * Determines the length of a given string. Counts multi-byte characters as single characters.
     *
     * @param   string  $str        The string to count characters in.
     * @param   string  $encoding   The encoding to use.
     * @return  int                 The length of the string.
     */
    public static function length(string $str, string $encoding = null) : int
    {
        return mb_strlen($str, $encoding ?: static::encoding($str));
    }

    /**
     * Returns all lines contained in the given string as an array, ie. splits the string on newline characters
     * into separate strings as items in an array.
     *
     * @param   string  $str    The string to split into separate lines.
     * @param   int     $limit  The maximal number of lines to return. If null, all lines will be returned.
     * @return  array           An array of all lines contained in $str. An empty array when $str contains
     *                          no lines.
     */
    public static function lines(string $str, int $limit = null) : array
    {
        return mb_split('[\r\n]{1,2}', $str, $limit);
    }

    /**
     * Determines whether the given string matches the given pattern. Asterisks are translated into zero or more
     * regexp wildcards, allowing for glob-style patterns.
     *
     * @param   string  $str        The string to match.
     * @param   string  $pattern    The pattern to match the string against.
     * @return  bool
     */
    public static function matches(string $str, string $pattern) : bool
    {
        if ($pattern === $str) {
            return true;
        }

        return (bool) preg_match('#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'\z'.'#', $str);
    }

    /**
     * Returns a string with common Windows-125* (used in MS Office documents) characters replaced
     * by their ASCII counterparts.
     *
     * @param   string  $str        The string to normalize.
     * @return  string              The normalized string.
     * @throws  \RuntimeException   Upon failing to replace the characters.
     */
    public static function normalizeCopypasta(string $str) : string
    {
        static $map = [
            'from' => [
                '/\x{2026}/u',
                '/[\x{201C}\x{201D}]/u',
                '/[\x{2018}\x{2019}]/u',
                '/[\x{2013}\x{2014}]/u',
            ],
            'to' => [
                '...',
                '"',
                "'",
                '-',
            ]
        ];

        if (null === $result = preg_replace($map['from'], $map['to'], $str)) {
            throw new \RuntimeException('Failed to normalize the string ['.$str.'].');
        }

        return $result;
    }

    /**
     * Returns an array containing the offsets of all occurrences of $needle in $haystack. The offsets
     * are 0-indexed. If no occurrences could be found, an empty array will be returned.
     *
     * If all you need is to count the number of occurrences, mb_substr_count() should be your
     * function of choice. Str::occurrences() however has got you covered if you need case-(in)sensitivity
     * or a count from a specific offset. To count the number of occurrences of $needle in $haystack using
     * this method, a simple `count(Str::occurrences($needle, $haystack));` will do the trick.
     *
     * @param   string      $haystack      The string to search in.
     * @param   string      $needle        The substring to search for.
     * @param   int         $offset        The offset from which to start the search. Can be negative, in which
     *                                      case this method will start searching for the occurrences $offset
     *                                      characters from the end of the $haystack. Starts from 0 by default.
     * @param   bool        $strict         Whether to use case-sensitive comparisons. True by default.
     * @param   string|null $encoding       The encoding to use.
     * @return  array                       An array containing the 0-indexed offsets of all found occurrences
     *                                      or an empty array if none were found.
     * @throws  \OutOfBoundsException       When the $offset index is not contained in the input string.
     */
    public static function occurrences(string $haystack, string $needle, int $offset = 0, bool $strict = true, string $encoding = null) : array
    {
        // Early return in obvious circumstances.
        if ($haystack === '' || $needle === '') {
            return [];
        }

        $encoding = $encoding ?: static::encoding($haystack);
        $length   = mb_strlen($haystack, $encoding);

        // With a negative offset, we'll be starting at $offset characters from the end of $haystack.
        // mb_strpos does not natively support negative offsets, which is why we're simply converting
        // the negative one to a positive one.
        if ($offset < 0) {
            $offset = $length + $offset;
        }

        // Make sure the offset given exists within the $haystack.
        if (abs($offset) >= $length) {
            throw new \OutOfBoundsException('The given $offset ['.$offset.'] does not exist within the string ['.static::truncate($haystack, 20, '...', false, $encoding).'].');
        }

        $func   = $strict ? 'mb_strpos' : 'mb_stripos';
        $result = [];

        while (false !== $offset = $func($haystack, $needle, $offset, $encoding)) {
            // We could count the length of $needle here but just going +1 ensures we don't catch
            // the same needle again while at the same time we're avoiding the overhead of mb_strlen.
            $result[] = $offset++;
        }

        return $result;
    }

    /**
     * Pads the given string to a given $length using $with as padding. This has no effect on input strings which are
     * longer than or equal in length to the requested $length.
     *
     * Padding can be applied to the left, the right or both sides of the input string simultaneously depending
     * on the $type chosen (one of PHP's native STR_PAD_* constants).
     *
     * @param   string      $str                The string to pad.
     * @param   int         $length             The desired length of the string after padding.
     * @param   string      $with               The character(s) to pad the string with.
     * @param   int         $type               One of the STR_PAD_* constants supported by PHP's native str_pad().
     * @param   string|null $encoding           The encoding to use.
     * @return  string                          The resulting string.
     * @throws  \InvalidArgumentException       when an unrecognized $type is given.
     */
    public static function pad(string $str, int $length, string $with = ' ', int $type = STR_PAD_RIGHT, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($str);

        // Get the length of the input string - we'll need it to determine how much padding to apply.
        // Note: We're not returning early when the input string is empty - this is acceptable input for this
        // method. We will, however, return early when $with (the padding) is empty.
        $strLen  = mb_strlen($str, $encoding);
        $padding = $length - $strLen;

        // Determine how much padding to apply to either of the sides depending on which padding type
        // we were asked to perform.
        switch ($type) {
            case STR_PAD_LEFT:
                $left  = $padding;
                $right = 0;
                break;

            case STR_PAD_RIGHT:
                $left  = 0;
                $right = $padding;
                break;

            case STR_PAD_BOTH:
                $left  = floor($padding / 2);
                $right = ceil($padding / 2);
                break;

            default:
                throw new \InvalidArgumentException('Expected $type to be one of [STR_PAD_RIGHT|STR_PAD_LEFT|STR_PAD_BOTH], got ['.$type.'] instead.');
        }

        // If there's no actual padding or if the final length of the string would be longer than the
        // input string, we'll do nothing and return the input string.
        if (0 === $padLen = mb_strlen($with, $encoding) || $strLen >= $paddedLength = $strLen + $left + $right) {
            return $str;
        }

        // Construct the requested padding strings.
        $leftPadding  = 0 === $left  ? '' : mb_substr(str_repeat($with, ceil($left / $padLen)), 0, $left, $encoding);
        $rightPadding = 0 === $right ? '' : mb_substr(str_repeat($with, ceil($right / $padLen)), 0, $right, $encoding);

        // Apply the padding and return the glued string.
        return $leftPadding . $str . $rightPadding;
    }

    /**
     * Alias for self::pad() with the $type set to apply padding to both sides of the input string.
     *
     * @see Str::pad()
     */
    public static function padBoth(string $str, int $length, string $with = ' ', string $encoding = null) : string
    {
        return static::pad($str, $length, $with, STR_PAD_BOTH, $encoding);
    }

    /**
     * Alias for self::pad() with the $type set to apply padding only to the left side of the input string.
     *
     * @see Str::pad()
     */
    public static function padLeft(string $str, int $length, string $with = ' ', string $encoding = null) : string
    {
        return static::pad($str, $length, $with, STR_PAD_LEFT, $encoding);
    }

    /**
     * Alias for self::pad() with the $type set to apply padding only to the left side of the input string.
     *
     * Note: Self::pad() performs padding on the right side only by default - this alias is provided mostly
     * for consistency and verbosity.
     *
     * @see Str::pad()
     */
    public static function padRight(string $str, int $length, string $with = ' ', string $encoding = null) : string
    {
        return static::pad($str, $length, $with, STR_PAD_RIGHT, $encoding);
    }

    /** --
     *   Do *not* use this method in a cryptographic context without passing in a higher $strength
     *   parameter or better yet, use Random::string() directly instead.
     *  --
     *
     * Generates a pseudo-random string of the specified length using alpha-numeric characters
     * or the characters provided.
     *
     * @see     Random::string()
     *
     * @param   int         $length         The expected length of the generated string.
     * @param   string|int  $characters     The character list to use. Can be either a string
     *                                      with the characters to use or an int | nyx\core\Mask
     *                                      to generate a list (@see self::buildCharacterSet()).
     *                                      If not provided or an invalid mask, the method will fall
     *                                      back to a base alphanumeric character set.
     * @param   int         $strength       The requested strength of entropy (one of the Random::STRENGTH_*
     *                                      class constants)
     * @return  string                      The generated string.
     */
    public static function random(int $length = 8, $characters = null, int $strength = Random::STRENGTH_NONE) : string
    {
        // Note: We're duplicating ourselves by specifying the character pool directly instead of
        // relying on self::buildCharacterSet(), but this skips this process in Random::string()
        // and is therefore faster.
        return Random::string($length, $characters ?: '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $strength);
    }

    /**
     * Removes the given $needle(s) from the $haystack.
     *
     * @param   string          $haystack   The string to remove from.
     * @param   string|string[] $needles    The substrings to remove.
     * @return  string                      The resulting string.
     */
    public static function remove(string $haystack, $needles) : string
    {
        return static::replace($haystack, $needles, '');
    }

    /**
     * Removes the given $needle from the beginning (only) of the $haystack. Only the first occurrence of the $needle
     * will be removed. If the $haystack does not start with the specified $needle, nothing will be removed.
     *
     * @param   string      $haystack   The string to remove from.
     * @param   string      $needle     The substring to remove from the beginning.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The resulting string.
     */
    public static function removeLeft(string $haystack, string $needle, string $encoding = null) : string
    {
        // Early return in obvious circumstances.
        if ($haystack === '' || $needle === '') {
            return $haystack;
        }

        $encoding = $encoding ?: static::encoding($haystack);

        // This is a non-DRY version of self::startsWith(). If $haystack doesn't even start
        // with the given substring, we'll just return $haystack.
        if (0 !== mb_strpos($haystack, $needle, 0, $encoding) || 0 === $needleLen = mb_strlen($needle, $encoding)) {
            return $haystack;
        }

        // Grab a substring of the full initial string starting from the end of the prefix
        // we're cutting off... and return it.
        return mb_substr($haystack, $needleLen, null, $encoding);
    }

    /**
     * Removes the given $needle from the end (only) of the $haystack. Only the last occurrence of the $needle
     * will be removed. If the $haystack does not end with the specified $needle, nothing will be removed.
     *
     * @param   string      $haystack   The string to remove from.
     * @param   string      $needle     The substring to remove from the end.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The resulting string.
     */
    public static function removeRight(string $haystack, string $needle, string $encoding = null) : string
    {
        // Early return in obvious circumstances.
        if ($haystack === '' || $needle === '') {
            return $haystack;
        }

        $encoding  = $encoding ?: static::encoding($haystack);
        $needleLen = mb_strlen($needle, $encoding);

        // This is a non-DRY version of self::endsWith(). If $haystack doesn't even end
        // with the given substring, we'll just return $haystack.
        if (0 === $needleLen || $needle !== mb_substr($haystack, -$needleLen, null, $encoding)) {
            return $haystack;
        }

        // Grab a substring of the full initial string ending at the beginning of the suffix
        // we're cutting off... and return it.
        return mb_substr($haystack, 0, mb_strlen($haystack, $encoding) - $needleLen, $encoding);
    }

    /**
     * Replaces the $needles within the given $haystack with the $replacement. If $needles is a string,
     * it will be treated as a regular expression. If it is an array, it will be treated as an array
     * of substrings to replace (an appropriate regular expression will be constructed).
     *
     * Acts as an utility alias for mb_ereg_replace().
     *
     * @param   string          $haystack       The string to replace $needles in.
     * @param   string|string[] $needles        What to replace in the string.
     * @param   string          $replacement    The replacement value.
     * @param   string          $options        The matching conditions as a string.
     *                                          {@link http://php.net/manual/en/function.mb-ereg-replace.php}
     * @param   string|null     $encoding       The encoding to use.
     * @return  string                          The resulting string.
     */
    public static function replace(string $haystack, $needles, string $replacement, string $options = 'msr', string $encoding = null) : string
    {
        // If multiple values to replace were passed.
        if (is_array($needles)) {
            $needles = '(' .implode('|', $needles). ')';
        }

        // Keep track of the internal encoding as we'll change it temporarily and then revert back to it.
        $internalEncoding = mb_regex_encoding();

        // Swap out the internal encoding for what we want...
        mb_regex_encoding($encoding ?: static::encoding($haystack));

        // ... and perform the replacement.
        $result = mb_ereg_replace($needles, $replacement, $haystack, $options);

        // Restore the initial internal encoding.
        mb_regex_encoding($internalEncoding);

        return $result;
    }

    /**
     * Replaces the first occurrence of each of the $needles in $haystack with $replacement.
     *
     * This method will search from the beginning of $haystack after processing each needle and replacing it,
     * meaning subsequent iterations may replace substrings resulting from previous iterations.
     *
     * @param   string          $haystack       The string to replace $needles in.
     * @param   string|string[] $needles        What to replace in the string.
     * @param   string          $replacement    The replacement value for each found (first) needle.
     * @param   bool            $strict         Whether to use case-sensitive comparisons.
     * @param   string|null     $encoding       The encoding to use.
     * @return  string                          The resulting string.
     */
    public static function replaceFirst(string $haystack, $needles, string $replacement, bool $strict = true, string $encoding = null) : string
    {
        return static::replaceOccurrence($haystack, $needles, $replacement, $strict, true, $encoding);
    }

    /**
     * Replaces the last occurrence of each of the $needles in $haystack with $replacement.
     *
     * This method will search from the end of $haystack after processing each needle and replacing it,
     * meaning subsequent iterations may replace substrings resulting from previous iterations.
     *
     * @param   string          $haystack       The string to replace $needles in.
     * @param   string|string[] $needles        What to replace in the string.
     * @param   string          $replacement    The replacement value for each found (last) needle.
     * @param   bool            $strict         Whether to use case-sensitive comparisons.
     * @param   string|null     $encoding       The encoding to use.
     * @return  string                          The resulting string.
     */
    public static function replaceLast(string $haystack, $needles, string $replacement, bool $strict = true, string $encoding = null) : string
    {
        return static::replaceOccurrence($haystack, $needles, $replacement, $strict, false, $encoding);
    }

    /**
     * Reverses a string. Multi-byte-safe equivalent of strrev().
     *
     * @param   string      $str        The string to reverse.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The resulting string.
     */
    public static function reverse(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($str);
        $length   = mb_strlen($str, $encoding);
        $result   = '';

        // Return early under obvious circumstances.
        if ($length === 0) {
            return $result;
        }

        // Reverse one character after the other, counting from the end.
        for ($i = $length - 1; $i >= 0; $i--) {
            $result .= mb_substr($str, $i, 1, $encoding);
        }

        return $result;
    }

    /**
     * Randomizes the order of characters in the given string. Multi-byte-safe equivalent
     * of str_shuffle().
     *
     * @param   string      $str        The string to shuffle.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The resulting string.
     */
    public static function shuffle(string $str, string $encoding = null) : string
    {
        if ($str === '') {
            return $str;
        }

        $result   = '';
        $encoding = $encoding ?: static::encoding($str);
        $indices  = range(0, mb_strlen($str, $encoding) - 1);

        shuffle($indices);

        foreach ($indices as $i) {
            $result .= mb_substr($str, $i, 1, $encoding);
        }

        return $result;
    }

    /**
     * Generates a URL-friendly slug from the given string.
     *
     * @param   string  $str        The string to slugify.
     * @param   string  $separator  The separator to use instead of non-alphanumeric characters.
     * @return  string              The resulting slug.
     */
    public static function slug(string $str, string $separator = '-') : string
    {
        $str = static::toAscii($str);

        // Remove all characters that are neither alphanumeric, nor the separator nor a whitespace.
        $str = preg_replace('![^'.preg_quote($separator).'\pL\pN\s]+!u', '', mb_strtolower($str));

        // Standardize the separator.
        $flip = $separator == '-' ? '_' : '-';
        $str = preg_replace('!['.preg_quote($flip).']+!u', $separator, $str);

        // Replace all separator characters and whitespace by a single separator.
        $str = preg_replace('!['.preg_quote($separator).'\s]+!u', $separator, $str);

        return trim($str, $separator);
    }

    /**
     * Determines whether the given $haystack starts with the given needle or one of the given needles
     * if $needles is an array.
     *
     * @param   string          $haystack   The string to search in.
     * @param   string|string[] $needles    The needle(s) to look for.
     * @param   bool            $strict     Whether to use case-sensitive comparisons.
     * @param   string|null     $encoding   The encoding to use.
     * @return  bool                        True when the string starts with one of the given needles, false otherwise.
     */
    public static function startsWith(string $haystack, $needles, bool $strict = true, string $encoding = null) : bool
    {
        if ($haystack === '') {
            return false;
        }

        $encoding = $encoding ?: static::encoding($haystack);
        $func     = $strict ? 'mb_strpos' : 'mb_stripos';

        foreach ((array) $needles as $needle) {
            if ($needle !== '' && 0 === $func($haystack, $needle, 0, $encoding)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a part of the given $haystack starting at the given $offset.
     *
     * @param   string      $haystack       The input string.
     * @param   int         $offset         The offset at which to start the slice. If a negative offset is given,
     *                                      the slice will start at the $offset-th character counting from the end
     *                                      of the input string.
     * @param   int|null    $length         The length of the slice. Must be a positive integer or null. If null,
     *                                      the full string starting from $offset will be returned. If a length
     *                                      which is longer than the input string is requested the method will
     *                                      silently ignore this and will act as if null was passed as length.
     * @param   string|null $encoding       The encoding to use.
     * @return  string                      The resulting string.
     * @throws  \InvalidArgumentException   When $length is negative.
     * @throws  \OutOfBoundsException       When the $start index is not contained in the input string.
     */
    public static function sub(string $haystack, int $offset, int $length = null, string $encoding = null) : string
    {
        if ($length === 0) {
            return '';
        }

        // We could silently return the initial string, but a negative $length may be an indicator of
        // mismatching $start with $length in the method call.
        if ($length < 0) {
            throw new \InvalidArgumentException('The length of the requested substring must be > 0, ['.$length.'] requested.');
        }

        $encoding = $encoding ?: static::encoding($haystack);

        // Check if the absolute starting index (to account for negative indices) + 1 (since it's 0-indexed
        // while length is > 1 at this point) is within the length of the string.
        if (abs($offset) >= mb_strlen($haystack, $encoding)) {
            throw new \OutOfBoundsException('The given $offset ['.$offset.'] does not exist within the string ['.static::truncate($haystack, 20, '...', false, $encoding).'].');
        }

        return mb_substr($haystack, $offset, $length, $encoding);
    }

    /**
     * Surrounds the $haystack with the given $needle.
     *
     * Works consistent with Str::begin() and Str::finish() in that it ensures only single instances of
     * the $needle will be present at the beginning and end of the $haystack.
     *
     * @param   string  $haystack   The string to surround.
     * @param   string  $needle     The substring to surround the string with.
     * @return  string              The resulting string.
     */
    public static function surroundWith(string $haystack, string $needle) : string
    {
        return static::beginWith($haystack, $needle) . $needle . static::finishWith($haystack, $needle);
    }

    /**
     * Converts the given string to title case. Multi-byte-safe equivalent of ucwords().
     *
     * @param   string      $str        The string to convert.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The converted string.
     */
    public static function titleCase(string $str, string $encoding = null) : string
    {
        return mb_convert_case($str, MB_CASE_TITLE, $encoding ?: static::encoding($str));
    }

    /**
     * Alias for @see Str::characters()
     */
    public static function toArray(string $str, string $encoding = null) : array
    {
        return static::characters($str, $encoding);
    }

    /**
     * Transliterates an UTF-8 encoded string to its ASCII equivalent.
     *
     * @param   string  $str    The UTF-8 encoded string to transliterate.
     * @return  string          The ASCII equivalent of the input string.
     */
    public static function toAscii(string $str) : string
    {
        if (preg_match("/[\x80-\xFF]/", $str)) {
            // Grab the transliteration table since we'll need it.
            if (null === static::$ascii) {
                static::$ascii = unserialize(file_get_contents(__DIR__ . '/str/resources/transliteration_table.ser'));
            }

            $str = \Normalizer::normalize($str, \Normalizer::NFKD);
            $str = preg_replace('/\p{Mn}+/u', '', $str);
            $str = str_replace(static::$ascii[0], static::$ascii[1], $str);
            $str = iconv('UTF-8', 'ASCII' . ('glibc' !== ICONV_IMPL ? '//IGNORE' : '') . '//TRANSLIT', $str);
        }

        return $str;
    }

    /**
     * Checks whether the given string represents a boolean value. Case insensitive.
     *
     * Works different than simply casting a string to a bool in that strings like "yes"/"no"
     * and "on"/"off", "1"/"0" and "true"/"false" are interpreted based on the natural language
     * value they represent.
     *
     * Numeric strings are left as in native PHP typecasting, ie. only 0 represents false. Every
     * other numeric string, including negative numbers, will be treated as a truthy value.
     *
     * Non-empty strings containing only whitespaces, tabs or newlines will also be interpreted
     * as empty (false) strings.
     *
     * @param   string  $str    The string to check.
     * @return  bool            True when the string represents a boolean value, false otherwise.
     * @todo    Grab the map from a separate method to allow easier extending with locale specific values?
     * @todo    Rename to asBool() to make a more explicit distinction between this and normal typecasting?
     */
    public static function toBool(string $str) : bool
    {
        static $map = [
            'true'  => true,
            'false' => false,
            '1'     => true,
            '0'     => false,
            'on'    => true,
            'off'   => false,
            'yes'   => true,
            'no'    => false
        ];

        return $map[mb_strtolower($str)] ?? (bool) trim($str);
    }

    /**
     * Converts each tab in the given string to the defined number of spaces (4 by default).
     *
     * @param   string  $str        The string in which to convert the tabs to whitespaces.
     * @param   int     $length     The number of spaces to replace each tab with.
     * @return  string              The resulting string.
     */
    public static function toSpaces(string $str, int $length = 4) : string
    {
        return str_replace("\t", str_repeat(' ', $length), $str);
    }

    /**
     * Converts each occurrence of the defined consecutive number of spaces (4 by default) to a tab.
     *
     * @param   string  $str        The string in which to convert the whitespaces to tabs.
     * @param   int     $length     The number of consecutive spaces to replace with a tab.
     * @return  string              The resulting string.
     */
    public static function toTabs(string $str, int $length = 4) : string
    {
        return str_replace(str_repeat(' ', $length), "\t", $str);
    }

    /**
     * Removes whitespaces (or other characters, if given) from the beginning and end of the given string.
     * Handles multi-byte whitespaces.
     *
     * Note: If you simply want to remove whitespace and multi-byte whitespaces are of no concern,
     * use PHP's native trim() instead, for obvious performance reasons.
     *
     * @param   string      $str        The string in which to convert the whitespaces to tabs.
     * @param   string      $characters Optional characters to strip off (instead of the default whitespace chars).
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The resulting string.
     */
    public static function trim(string $str, string $characters = null, string $encoding = null) : string
    {
        $characters = null !== $characters ? preg_quote($characters) : '[:space:]';

        return static::replace($str, "^[$characters]+|[$characters]+\$", '', 'msr', $encoding);
    }

    /**
     * Removes whitespaces (or other characters, if given) from the beginning of the given string.
     * Handles multi-byte whitespaces.
     *
     * Note: If you simply want to remove whitespace and multi-byte whitespaces are of no concern,
     * use PHP's native ltrim() instead, for obvious performance reasons.
     *
     * @param   string      $str        The string in which to convert the whitespaces to tabs.
     * @param   string      $characters Optional characters to strip off (instead of the default whitespace chars).
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The resulting string.
     */
    public static function trimLeft(string $str, string $characters = null, string $encoding = null) : string
    {
        $characters = null !== $characters ? preg_quote($characters) : '[:space:]';

        return static::replace($str, "^[$characters]+", '', 'msr', $encoding);
    }

    /**
     * Removes whitespaces (or other characters, if given) from the end of the given string.
     * Handles multi-byte whitespaces.
     *
     * Note: If you simply want to remove whitespace and multi-byte whitespaces are of no concern,
     * use PHP's native rtrim() instead, for obvious performance reasons.
     *
     * @param   string      $str        The string in which to convert the whitespaces to tabs.
     * @param   string      $characters Optional characters to strip off (instead of the default whitespace chars).
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The resulting string.
     */
    public static function trimRight(string $str, string $characters = null, string $encoding = null) : string
    {
        $characters = null !== $characters ? preg_quote($characters) : '[:space:]';

        return static::replace($str, "[$characters]+\$", '', 'msr', $encoding);
    }

    /**
     * Trims the string to the given length, replacing the cut off characters from the end with an optional
     * substring ("..." by default). The final length of the string, including the optionally appended $end
     * substring, will not exceed $limit.
     *
     * @param   string      $str            The string to truncate.
     * @param   int         $limit          The maximal number of characters to be contained in the string. Must be
     *                                      a positive integer. If 0 is given, an empty string will be returned.
     * @param   string      $end            The replacement for the whole of the cut off string (if any).
     * @param   bool        $preserveWords  Whether to preserve words, ie. allow splitting only on whitespace
     *                                      characters.
     * @param   string|null $encoding       The encoding to use.
     * @return  string                      The resulting string.
     * @throws  \InvalidArgumentException   When $limit is a negative integer.
     */
    public static function truncate(string $str, int $limit = 100, string $end = '...', bool $preserveWords = false, string $encoding = null) : string
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('The $limit must be a positive integer but ['.$limit.'] was given.');
        }

        if ($limit === 0) {
            return '';
        }

        $encoding = $encoding ?: static::encoding($str);

        // Is there anything to actually truncate?
        if (mb_strlen($str, $encoding) <= $limit) {
            return $str;
        }

        // Determine the final length of the substring of $str we might return and grab it.
        $length = $limit - mb_strlen($end, $encoding);
        $result = mb_substr($str, 0, $length, $encoding);

        // If we are to preserve words, see whether the last word got truncated by checking if
        // the truncated string would've been directly followed by a whitespace or not. If not,
        // we're going to get the position of the last whitespace in the truncated string and
        // cut the whole thing off at that offset instead.
        if (true === $preserveWords && $length !== mb_strpos($str, ' ', $length - 1, $encoding)) {
            $result = mb_substr($result, 0, mb_strrpos($result, ' ', 0, $encoding), $encoding);
        }

        return $result . $end;
    }

    /**
     * Limits the number of words in the given string.
     *
     * @param   string      $str        The string to limit.
     * @param   int         $words      The maximal number of words to be contained in the string, not counting
     *                                  the replacement.
     * @param   string|null $encoding   The encoding to use.
     * @param   string      $end        The replacement for the whole of the cut off string (if any).
     * @return  string                  The resulting string.
     */
    public static function words(string $str, int $words = 100, string $encoding = null, string $end = '...') : string
    {
        $encoding = $encoding ?: static::encoding($str);

        preg_match('/^\s*+(?:\S++\s*+){1,'.$words.'}/u', $str, $matches);

        if (!isset($matches[0]) || mb_strlen($str, $encoding) === mb_strlen($matches[0], $encoding)) {
            return $str;
        }

        return rtrim($matches[0]).$end;
    }

    /**
     * Finds the first occurrence of $needle within $haystack and returns the part of $haystack
     * before up to the beginning of the $needle or from the beginning of the $needle and including it,
     * depending on whether $before is true or false.
     *
     * Used internally by {@see self::after()}, {@see self::before()} and {@see self::from()}.
     *
     * @param   string      $haystack   The string to search in.
     * @param   string      $needle     The substring to search for.
     * @param   bool        $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   bool        $before     Whether to return the part of $haystack before $needle or part of
     *                                  $haystack starting at $needle and including $needle.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The part of $haystack before/from $needle.
     * @throws  \RuntimeException       Upon failing to find $needle in $haystack at all.
     */
    protected static function partOf(string $haystack, string $needle, bool $strict, bool $before, string $encoding = null) : string
    {
        $func     = $strict ? 'mb_strstr' : 'mb_stristr';
        $encoding = $encoding ?: static::encoding($haystack);

        if (false === $result = $func($haystack, $needle, $before, $encoding)) {
            throw new \RuntimeException('Failed to find $needle ['.$needle.'] in $haystack ['.static::truncate($haystack, 20, '...', false, $encoding).'].');
        }

        return $result;
    }

    /**
     * Replaces a single occurrence of each of the $needles in $haystack with $replacement - either the first or
     * the last occurrence, depending whether $first is true or false.
     *
     * This method will search from the beginning/end of $haystack after processing each needle and replacing it,
     * meaning subsequent iterations may replace substrings resulting from previous iterations.
     *
     * Used internally by {@see self::replaceFirst()} and {@see self::replaceLast()}.
     *
     * @param   string          $haystack       The string to replace $needles in.
     * @param   string|string[] $needles        What to replace in the string.
     * @param   string          $replacement    The replacement value for each found (last) needle.
     * @param   bool            $strict         Whether to use case-sensitive comparisons.
     * @param   bool            $first          Whether to replace the first or the last occurrence of the needle(s).
     * @param   string|null     $encoding       The encoding to use.
     * @return  string                          The resulting string.
     */
    protected static function replaceOccurrence(string $haystack, $needles, string $replacement, bool $strict, bool $first, string $encoding = null) : string
    {
        if ($haystack === '') {
            return '';
        }

        $encoding = $encoding ?: static::encoding($haystack);
        $method   = $first    ? 'indexOf' : 'indexOfLast';

        foreach ((array) $needles as $needle) {

            // Pass to the next needle if this one is an empty string or if it couldn't be found
            // in the haystack at all.
            if ($needle === '' || -1 === $offset = static::$method($haystack, $needle, 0, $strict, $encoding) || 0 === $needleLen = mb_strlen($needle, $encoding)) {
                continue;
            }

            // Grab the substrings before and after the needle occurs, insert the replacement in between
            // and glue it together omitting the needle.
            $haystack = mb_substr($haystack, 0, $offset, $encoding) . $replacement . mb_substr($haystack, $offset + $needleLen, null, $encoding);
        }

        return $haystack;
    }
}
