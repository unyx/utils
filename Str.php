<?php namespace nyx\utils;

// External includes
use nyx\core;

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
 * - Extension: mbstring
 * - Extension: intl (Normalizer)
 * - Extension: iconv
 *
 * (If your PHP installation does not have the mb, intl and iconv extensions and you can not modify the installation,
 * take a look at https://github.com/nicolas-grekas/Patchwork-UTF8).
 *
 * @package     Nyx\Utils\Strings
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/strings.html
 * @todo        Snake case, camel case, studly caps, dashed, underscored?
 * @todo        Decide on support for Stringable and/or simply loosening the type hints.
 * @todo        Split contains() into containsAll() and containsAny() to avoid the complexity?
 */
class Str
{
    /**
     * The traits of the Str class.
     */
    use traits\StaticallyExtendable;

    /**
     * Flags used to combine different characters into a set via self::buildCharacterSet()
     */
    const CHARS_UPPER       = 1;   // Uppercase letters.
    const CHARS_LOWER       = 2;   // Lowercase letters.
    const CHARS_ALPHA       = 3;   // CHARS_UPPER and CHARS_LOWER.
    const CHARS_NUMERIC     = 4;   // Digits.
    const CHARS_ALPHANUM    = 7;   // CHARS_ALPHA and CHARS_NUMERIC.
    const CHARS_HEX_UPPER   = 12;  // Uppercase hexadecimal symbols - CHARS_DIGITS and 8.
    const CHARS_HEX_LOWER   = 20;  // Lowercase hexadecimal symbols - CHARS_DIGITS and 16.
    const CHARS_BASE64      = 39;  // CHARS_ALPHANUM and 32.
    const CHARS_SYMBOLS     = 64;  // Additional symbols ($%& etc.) accessible on most if not all keyboards.
    const CHARS_BRACKETS    = 128; // Brackets.
    const CHARS_PUNCTUATION = 256; // Punctuation marks.

    /**
     * @const Special character flag for alphanumeric characters excluding characters which tend
     *        to be hard to distinguish from each other.
     */
    const CHARS_LEGIBLE     = 512;

    /**
     * @var string  The default encoding to use when we fail to determine it based on a given string. UTF-8 will
     *              be used when the above is true and this property is null.
     */
    public static $encoding;

    /**
     * @var array   A map of CHARS_* flags to their actual character lists. @todo Make writable, handle cache?
     */
    protected static $characterSetsMap = [
        self::CHARS_UPPER       => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        self::CHARS_LOWER       => 'abcdefghijklmnopqrstuvwxyz',
        self::CHARS_NUMERIC     => '0123456789',
        self::CHARS_HEX_UPPER   => 'ABCDEF',
        self::CHARS_HEX_LOWER   => 'abcdef',
        self::CHARS_BASE64      => '+/',
        self::CHARS_SYMBOLS     => '!"#$%&\'()* +,-./:;<=>?@[\]^_`{|}~',
        self::CHARS_BRACKETS    => '()[]{}<>',
        self::CHARS_PUNCTUATION => ',.;:',
        self::CHARS_LEGIBLE     => 'DQO0B8|I1lS5Z2G6()[]{}:;,.' // Somewhat unintuitive as this is actually an
                                                                // exclusion map containing ambiguous characters.
    ];

    /**
     * @var array   Cached character sets built via self::buildCharacterSet() in a $bitmask => Sset format,
     *              where $bitmask are the flags used to build the Sset character list.
     */
    protected static $characterSetsBuilt;

    /**
     * @var array   The transliteration table used by the toAscii() method once fetched from a file.
     */
    protected static $ascii;

    /**
     * Finds the first occurrence of $needle within $haystack and returns the part of $haystack
     * after the $needle (excluding the $needle).
     *
     * @param   string  $haystack   The string to search in.
     * @param   string  $needle     The substring to search for.
     * @param   bool    $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The part of $haystack from where $needle starts.
     * @throws  \RuntimeException   Upon failing to find $needle in $haystack at all.
     */
    public static function after(string $haystack, string $needle, bool $strict = true, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($haystack);

        // We're gonna use strstr internally to grab the part of $haystack starting at $needle
        // and including the $needle, and then simply remove the starting $needle.
        // Note: Removing 1 from the length of $needle since we're using it as start offset
        // for mb_substr, and that is 0-indexed.
        return mb_substr(static::partOf($haystack, $needle, $strict, false, $encoding), mb_strlen($needle, $encoding) - 1);
    }

    /**
     * Returns the character at the specified $index (0-indexed).
     *
     * @param   int     $index  The requested index. If a negative index is given, this method will return
     *                          the $index-th character counting from the end of the string.
     * @return  string          The character at the specified $index.
     */
    public static function at(string $str, int $index, string $encoding = null)
    {
        return static::sub($str, $index, 1, $encoding);
    }

    /**
     * Finds the first occurrence of $needle within $haystack and returns the part of $haystack
     * *before* the $needle.
     *
     * @param   string  $haystack   The string to search in.
     * @param   string  $needle     The substring to search for.
     * @param   bool    $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The part of $haystack before $needle.
     * @throws  \RuntimeException   Upon failing to find $needle in $haystack at all.
     */
    public static function before(string $haystack, string $needle, bool $strict = true, string $encoding = null) : string
    {
        return static::partOf($haystack, $needle, $strict, true, $encoding);
    }

    /**
     * Ensures the given string begins with a single instance of a given substring.
     *
     * @param   string  $str    The string to cap.
     * @param   string  $with   The substring to begin with.
     * @return  string          The resulting string.
     */
    public static function begin(string $str, string $with) : string
    {
        return $with.ltrim($str, $with);
    }

    /**
     * Returns the substring of $haystack between $firstNeedle and $secondNeedle.
     *
     * If all you need is to return part of a string when the offsets you are looking for are known,
     * you should use Str::sub() instead.
     *
     * @param   string  $haystack           The string to search in.
     * @param   string  $firstNeedle        The needle marking the start of the substring to return.
     * @param   string  $secondNeedle       The needle marking the end of the substring to return.
     * @param   int     $offset             The 0-based index at which to start the search for the first needle.
     *                                      Can be negative, in which case the search will start $offset characters
     *                                      from the end of the $haystack.
     * @param   bool    $strict             Whether to use strict comparisons when searching for the needles.
     * @param   string  $encoding           The encoding to use.
     * @return  string                      The substring between the needles.
     * @throws  \InvalidArgumentException   When $haystack or either of the needles is an empty string.
     * @throws  \OutOfBoundsException       Upon failing to find either $firstNeedle or $secondNeedle in $haystack.
     */
    public static function between(string $haystack, string $firstNeedle, string $secondNeedle, int $offset = 0, bool $strict = true, string $encoding = null) : string
    {
        // Note: We're throwing here because this method will return unpredictable results otherwise.
        // If you want to omit $firstNeedle or $secondNeedle, turn to self::after(), self::before()
        // and self::from() instead.
        // We're not validating input args any further than the below, however, as there are a lot of
        // error-inducing combinations of invalid input which all will be caught when we attempt
        // to actually look for the indices of the needles. Anything else is just way too much overhead.
        if ($haystack === '' || $firstNeedle === '' || $secondNeedle === '') {

            $secondNeedle === '' && $arg = '$secondNeedle';
            $firstNeedle  === '' && $arg = '$firstNeedle';
            $haystack     === '' && $arg = '$haystack';

            throw new \InvalidArgumentException($arg.' must not be an empty string.');
        }

        $encoding = $encoding ?: static::encoding($haystack);

        // mb_strpos does not natively support negative offsets, so we'll add the negative offset
        // to the length of the $haystack to get the offset from its end.
        if ($offset < 0) {
            $offset = mb_strlen($haystack, $encoding) + $offset;
        }

        $funcIndexOf = $strict ? 'mb_strpos' : 'mb_stripos';

        // Find the offset of the first needle.
        if (false === $firstIndex = $funcIndexOf($haystack, $firstNeedle, $offset, $encoding)) {
            throw new \OutOfBoundsException('Failed to find $firstNeedle ['.$firstNeedle.'] in $haystack ['.static::truncate($haystack, 20, '...', $encoding).'].');
        }

        // We're going to adjust the offset for the position of the first needle, ie. we're gonna
        // start searching for the second one right at the end of the first one.
        $offset = $firstIndex + mb_strlen($firstNeedle, $encoding);

        // Find the offset of the second needle.
        if (false === $secondIndex = $funcIndexOf($haystack, $secondNeedle, $offset, $encoding)) {
            throw new \OutOfBoundsException('Failed to find $secondNeedle ['.$secondNeedle.'] in $haystack ['.static::truncate($haystack, 20, '...', $encoding).'].');
        }

        // Return the substring between the needles.
        return mb_substr($haystack, $offset, $secondIndex - $offset);
    }

    /**
     * Creates a list of characters based on a set of flags (CHARS_* class constants) given.
     *
     * @param   int|core\Mask   $from       The combination of CHARS_* flags (see the class constants) to use. Can be
     *                                      passed in either as an integer or as an instance of nyx\core\Mask.
     * @return  string                      The resulting list of characters.
     * @throws  \InvalidArgumentException   When an invalid $from mask was given.
     */
    public static function buildCharacterSet($from) : string
    {
        // Unpack the actual mask if we got a core\Mask (builder) instance.
        if ($from instanceof core\Mask) {
            $from = $from->get();
        } else if (!is_int($from)) {
            throw new \InvalidArgumentException('Expected an integer or an instance of \nyx\core\Mask, got ['.gettype($from).'] instead.');
        }

        if ($from <= 0) {
            return '';
        }

        // If all we get is the ambiguous exclusion flag, we need a base set of characters
        // to work with (an exclude from).
        if ($from === self::CHARS_LEGIBLE) {
            $from |= self::CHARS_ALPHANUM;
        }

        // Return a cached set if we've got one. Can't do this before the check for CHARS_LEGIBLE
        // above as the flag for alphanumeric chars gets applied to them first regardless of user given
        // params.
        if (isset(static::$characterSetsBuilt[$from])) {
            return static::$characterSetsBuilt[$from];
        }

        $result = '';

        // Iterate over all defined sets and build up the string for set flags.
        foreach (static::$characterSetsMap as $flag => $characters) {
            // Ambiguous chars may get special exclusion treatment (see post loop).
            if ($flag === self::CHARS_LEGIBLE) {
                continue;
            }

            if (($from & $flag) === $flag) {
                $result .= $characters;
            }
        }

        // Remove all known ambiguous characters from the set, if CHARS_LEGIBLE is set.
        if ($from & self::CHARS_LEGIBLE) {
            $result = str_replace(str_split(static::$characterSetsMap[self::CHARS_LEGIBLE]), '', $result);
        }

        // In mode 3 count_chars() returns only unique characters. Cache the result for the
        // flag set given so we can avoid the loops later on for the exact same mask.
        return static::$characterSetsBuilt[$from] = count_chars($result, 3);
    }

    /**
     * Returns an array containing the characters of the given string. Multi-byte safe.
     *
     * @param   string  $str        The string to iterate over.
     * @param   string  $encoding   The encoding to use.
     * @return  array
     */
    public static function characters(string $str, string $encoding = null) : array
    {
        $result = [];
        $length = mb_strlen($str, $encoding ?: static::encoding($str));

        for ($idx = 0; $idx < $length; $idx++) {
            $result[] = static::at($str, $idx);
        }

        return $result;
    }

    /**
     * Trims the given string and replaces multiple consecutive whitespaces with a single space.
     *
     * @param   string  $str    The string to clean.
     * @return  string          The resulting string.
     */
    public static function clean(string $str) : string
    {
        return preg_replace('/\s+/u', ' ', trim($str));
    }

    /**
     * Determines if a given string contains a given sub-string or at least one of the values if the $needle is
     * an array.
     *
     * @param   string          $haystack   The string to check in.
     * @param   string|array    $needle     A string or an array of strings. If an array is given, the method returns
     *                                      true if at least one of the values is contained within the $haystack.
     * @param   bool            $all        Set this to true to ensure all elements of the $needle array (if provided)
     *                                      are contained within the haystack.
     * @param   bool            $strict     Whether to use case-sensitive comparisons.
     * @param   string          $encoding   The encoding to use.
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
     * Attempts to determine the encoding of a string if a string is given. Upon failure/when no string is given,
     * returns the static encoding set in this class or if that is not set, the hardcoded default of 'utf-8'.
     *
     * @param   string  $str
     * @return  string
     */
    public static function encoding(string $str = null) : string
    {
        // If a string was given, we attempt to detect the encoding of the string. If we succeed, just return it.
        if (null !== $str and false !== $encoding = mb_detect_encoding($str)) {
            return $encoding;
        }

        // Otherwise let's return one of the defaults.
        return static::$encoding ?: 'utf-8';
    }

    /**
     * Determines if the given string ends with the given needle or one of the given needles if an array
     * of needles is provided. The comparison is case sensitive.
     *
     * @param   string          $haystack   The string to search in.
     * @param   string|array    $needles    The needle(s) to look for.
     * @param   string          $encoding   The encoding to use.
     * @return  bool                        True when the string ends with one of the given needles, false otherwise.
     */
    public static function endsWith(string $haystack, $needles, string $encoding = null) : bool
    {
        $encoding = $encoding ?: static::encoding($haystack);

        foreach ((array) $needles as $needle) {
            if ($needle != '' and $needle === mb_substr($haystack, -mb_strlen($needle, $encoding), $encoding)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensures the given string ends with a single instance of a given substring.
     *
     * @param   string  $str    The string to cap.
     * @param   string  $with   The substring to cap with.
     * @return  string          The resulting string.
     */
    public static function finish(string $str, string $with) : string
    {
        return rtrim($str, $with).$with;
    }

    /**
     * Finds the first occurrence of $needle within $haystack and returns the part of $haystack
     * starting where the $needle starts (ie. including the $needle).
     *
     * @param   string  $haystack   The string to search in.
     * @param   string  $needle     The substring to search for.
     * @param   bool    $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The part of $haystack from where $needle starts.
     * @throws  \RuntimeException   Upon failing to find $needle in $haystack at all.
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
     * @param   string  $haystack   The string to search in.
     * @param   string  $needle     The substring to search for.
     * @param   int     $offset     The offset from which to search. Negative offsets will start the search $offset
     *                              characters from the end of the $haystack. 0-indexed.
     * @param   bool    $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   string  $encoding   The encoding to use.
     * @return  int                 The index of the first occurrence if found, -1 otherwise.
     */
    public static function indexOf(string $haystack, string $needle, int $offset = 0, bool $strict = true, string $encoding = null) : int
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
     * Inserts the given substring into the string at the provided index.
     *
     * @param   string  $str                The string to insert into.
     * @param   string  $substring          The string to be inserted.
     * @param   int     $index              The index at which to insert the substring (>= 0). Can be 0, but you're
     *                                      introducing needless overhead over simply prepending your string if you
     *                                      do that.
     * @param   string  $encoding           The encoding to use.
     * @return  string                      The resulting string.
     * @throws  \InvalidArgumentException   When trying to insert a substring at a negative index.
     * @throws  \OutOfBoundsException       When trying to insert a substring at an index above the length of the
     *                                      initial string.
     */
    public static function insert(string $str, string $substring, int $index, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($str);

        if ($index < 0) {
            throw new \InvalidArgumentException('Cannot insert a string at a negative index.');
        }

        if ($index > $length = mb_strlen($str, $encoding)) {
            throw new \OutOfBoundsException('Cannot insert a string at a negative index.');
        }

        return mb_substr($str, 0, $index, $encoding) . $substring . mb_substr($str, $index, $length, $encoding);
    }

    /**
     * Determines the length of a given multibyte string.
     *
     * @param   string  $str        The string to count.
     * @param   string  $encoding   The encoding to use.
     * @return  int                 The character count.
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
     * Converts the given string to lowercase.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The converted string.
     */
    public static function lowercase(string $str, string $encoding = null) : string
    {
        return mb_strtolower($str, $encoding ?: static::encoding($str));
    }

    /**
     * Converts the first character in the given string to lowercase.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The converted string.
     */
    public static function lowercaseFirst(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($str);

        // Lowercase the first character and append the remainder.
        return mb_strtolower(mb_substr($str, 0, 1, $encoding), $encoding) . mb_substr($str, 1, null, $encoding);
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
        static $map = [[
            '/\x{2026}/u',
            '/[\x{201C}\x{201D}]/u',
            '/[\x{2018}\x{2019}]/u',
            '/[\x{2013}\x{2014}]/u',
        ], [
            '...',
            '"',
            "'",
            '-',
        ]];

        if (null === $result = preg_replace($map[0], $map[1], $str)) {
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
     * @param   string  $haystack           The string to search in.
     * @param   string  $needle             The substring to search for.
     * @param   int     $offset             The offset from which to start the search. Can be negative, in which
     *                                      case this method will start searching for the occurrences $offset
     *                                      characters from the end of the $haystack. Starts from 0 by default.
     * @param   bool    $strict             Whether to use case-sensitive comparisons. True by default.
     * @param   string  $encoding           The encoding to use.
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
        if ((abs($offset) + 1) > $length) {
            throw new \OutOfBoundsException('The requested $offset ['.$offset.'] does not exist within the string ["'.$haystack.'"].');
        }

        $func   = $strict ? 'mb_strpos' : 'mb_stripos';
        $result = [];

        while (false !== $offset = $func($haystack, $needle, $offset, $encoding)) {
            $result[] = $offset;

            // We could count the length of $needle here but just going +1 ensures we don't catch
            // the same needle again while at the same time we're avoiding the overhead of mb_strlen.
            $offset++;
        }

        return $result;
    }

    /** --
     *   Do *not* use this method in a cryptographic context without passing in a higher $strength
     *   parameter or better yet, use Random::string() directly instead.
     *  --
     *
     * Generates a pseudo-random string of the specified length using random alpha-numeric characters
     * or the characters provided.
     *
     * @see     Random::string()

     * @param   int         $length         The expected length of the generated string.
     * @param   string|int  $characters     The character list to use. Can be either a string
     *                                      with the characters to use or an int | nyx\core\Mask
     *                                      to generate a list (@see Str::buildCharacterSet()).
     *                                      If not provided or an invalid mask, the method will fall
     *                                      back to a base alphanumeric character set.
     * @param   int         $strength       The requested strength of entropy (one of the Random::STRENGTH_*
     *                                      class constants)
     * @return  float                       The generated string.
     */
    public static function random(int $length = 8, string $characters = null, int $strength = Random::STRENGTH_NONE) : string
    {
        // Note: We're duplicating ourselves by specifying the character pool directly instead of
        // relying on self::buildCharacterSet(), but this skips this process in Random::string()
        // and is therefore faster.
        return Random::string($length, $characters ?: '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $strength);
    }

    /**
     * Removes the given substring(s) from a string.
     *
     * @param   string          $str    The string to remove from.
     * @param   string|array    $what   What to remove from the string.
     * @return  string                  The resulting string.
     */
    public static function remove(string $str, string $what) : string
    {
        return static::replace($str, $what, '');
    }

    /**
     * Removes the given substring from the beginning (only) of the string. Only the first occurrence of the substring
     * will be removed. If the string does not start with the specified substring, nothing will be removed.
     *
     * @param   string  $from       The string to remove from.
     * @param   string  $what       The substring to remove from the beginning.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The resulting string.
     */
    public static function removeLeft(string $from, string $what, string $encoding = null) : string
    {
        // Early return in obvious circumstances.
        if ($from === '' || $what === '') {
            return $from;
        }

        $encoding = $encoding ?: static::encoding($from);

        // This is a non-DRY version of self::startsWith(). If $from doesn't even start
        // with the given substring, we'll just return $from.
        if (0 !== mb_strpos($from, $what, 0, $encoding) || 0 === $whatLen = mb_strlen($what, $encoding)) {
            return $from;
        }

        // Grab a substring of the full initial string starting from the end of the prefix
        // we're cutting off... and return it.
        return mb_substr($from, $whatLen, null, $encoding);
    }

    /**
     * Removes the given substring from the end (only) of the string. Only the last occurrence of the substring
     * will be removed. If the string does not end with the specified substring, nothing will be removed.
     *
     * @param   string  $from       The string to remove from.
     * @param   string  $what       The substring to remove from the end.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The resulting string.
     */
    public static function removeRight(string $from, string $what, string $encoding = null) : string
    {
        // Early return in obvious circumstances.
        if ($from === '' || $what === '') {
            return $from;
        }

        $encoding = $encoding ?: static::encoding($from);
        $whatLen  = mb_strlen($what, $encoding);

        // This is a non-DRY version of self::endsFrom(). If $from doesn't even end
        // with the given substring, we'll just return $from.
        if (0 === $whatLen || $what !== mb_substr($from, -$whatLen, null, $encoding)) {
            return $from;
        }

        // Grab a substring of the full initial string ending at the beginning of the suffix
        // we're cutting off... and return it.
        return mb_substr($from, 0, mb_strlen($from, $encoding) - $whatLen, $encoding);
    }

    /**
     * Replaces the $what within the given string with the $with.
     *
     * @param   string          $str    The string to remove from.
     * @param   string|array    $what   What to replace in the string (a single string or an array of strings).
     * @param   string          $with   The replacement value.
     * @return  string                  The resulting string.
     */
    public static function replace(string $str, $what, string $with) : string
    {
        // If multiple values to replace were passed.
        if (is_array($what)) {
            $what = '(' .implode('|', $what). ')';
        }

        return mb_ereg_replace($what, $with, $str);
    }

    /**
     * Reverses a string. Multibyte equivalent of strrev().
     *
     * @param   string  $str        The string to reverse.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The resulting string.
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
     * Randomizes the order of the characters in the given string. A multibyte-safe equivalent
     * of str_shuffle().
     *
     * @param   string  $str        The string to shuffle.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The resulting string.
     */
    public static function shuffle(string $str, string $encoding = null) : string
    {
        if ($str === '') {
            return $str;
        }

        $result   = '';
        $encoding = $encoding ?: static::encoding($str);
        $length   = mb_strlen($str, $encoding);
        $indices  = range(0, $length - 1);

        shuffle($indices);

        foreach ($indices as $i) {
            $result .= mb_substr($str, $i, 1, $encoding);
        }

        return $result;
    }

    /**
     * Generates an URL-friendly slug from the given string.
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
     * Determines if the given string starts with the given needle or one of the given needles if an array is provided.
     *
     * @param   string          $haystack   The string to search in.
     * @param   string|array    $needles    The needle(s) to look for.
     * @param   string          $encoding   The encoding to use.
     * @return  bool                        True when the string starts with one of the given needles, false otherwise.
     */
    public static function startsWith(string $haystack, $needles, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($haystack);

        foreach ((array) $needles as $needle) {
            if ($needle != '' and mb_strpos($haystack, $needle, 0, $encoding) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a part of the given string starting at the given index.
     *
     * @param   string    $str              The input string.
     * @param   int       $start            The index at which to start the slice. If a negative index is given,
     *                                      the slice will start at the $start-th character counting from the end
     *                                      of the input string.
     * @param   int|null  $length           The length of the slice. Must be a positive integer or null. If null,
     *                                      the full string starting from $start will be returned. If a length
     *                                      which is longer than the input string is requested the method will
     *                                      silently ignore this and will act as if null was passed as length.
     * @param   string    $encoding         The encoding to use.
     * @return  string                      The resulting string.
     * @throws  \InvalidArgumentException   When $length is negative.
     * @throws  \OutOfBoundsException       When the $start index is not contained in the input string.
     */
    public static function sub(string $str, int $start, int $length = null, string $encoding = null) : string
    {
        if ($length === 0) {
            return $str;
        }

        // We could silently return the initial string, but a negative $length may be an indicator of
        // mismatching $start with $length in the method call.
        if ($length < 0) {
            throw new \InvalidArgumentException('The length of the requested substring must be > 0, ['.$length.'] requested.');
        }

        $encoding = $encoding ?: static::encoding($str);

        // Check if the absolute starting index (to account for negative indexes) + 1 (since it's 0-indexed
        // while length is > 1 at this point) is within the length of the string.
        if ((abs($start) + 1) > mb_strlen($str, $encoding)) {
            throw new \OutOfBoundsException('The requested $start index ['.$start.'] is not within the string ["'.$str.'"].');
        }

        return mb_substr($str, $start, $length, $encoding);
    }

    /**
     * Surrounds the given string with the specified substring. Works consistent with Str::begin()
     * and Str::finish() in that it ensures only a single instance of the substring $with will be
     * present at the beginning and end of the string.
     *
     * @param   string  $str    The string to surround.
     * @param   string  $with   The substring to surround the string with.
     * @return  string          The resulting string.
     */
    public static function surround(string $str, string $with) : string
    {
        return $with.ltrim(rtrim($str, $with).$with, $with);
    }

    /**
     * Converts the given string to title case. The equivalent of ucwords() albeit for multibyte strings.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The converted string.
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
                static::$ascii = unserialize(file_get_contents(__DIR__ . '/resources/transliteration_table.ser'));
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

        $key = static::lowerCase($str);

        if (isset($map[$key])) {
            return $map[$key];
        }

        return (bool) trim($str);
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
     * Trims the string to the given length, replacing the cut off characters from the end with an optional
     * substring ("..." by default). The final length of the string, including the optionally appended $end
     * substring, will not exceed $limit.
     *
     * @param   string  $str                The string to truncate.
     * @param   int     $limit              The maximal number of characters to be contained in the string. Must be
     *                                      a positive integer. If 0 is given, an empty string will be returned.
     * @param   string  $end                The replacement.
     * @param   bool    $preserveWords      Whether to preserve words, ie. allow splitting only on whitespace
     *                                      characters.
     * @param   string  $encoding           The encoding to use.
     * @return  string                      The resulting string.
     * @throws  \InvalidArgumentException   When $limit is a negative integer.
     */
    public static function truncate(string $str, int $limit = 100, string $end = '...', bool $preserveWords = false, string $encoding = null) : string
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('The limit must be a positive integer, but ['.$limit.'] was given.');
        }

        if ($limit === 0) {
            return '';
        }

        $encoding = $encoding ?: static::encoding($str);

        // Is there anything to actually truncate?
        if (mb_strlen($str, $encoding) <= $limit) {
            return $str;
        }

        // Determine the final length of the substring of $str we might return.
        $length = $limit - mb_strlen($end, $encoding);

        // $result = mb_substr($str, 0, $limit - mb_strlen($end, $encoding), $encoding).$end;
        $result = mb_substr($str, 0, $length, $encoding);

        // If we are to preserve words, see whether the last word got truncated by checking if
        // the truncated string would've been directly followed by a whitespace or not. If not,
        // we're going to get the position of the last whitespace in the truncated string and
        // cut the whole thing off at that offset instead.
        if (true === $preserveWords && $length !== mb_strpos($str, ' ', $length - 1, $encoding)) {
            $result = mb_substr($result, 0, mb_strrpos($result, ' ', 0, $encoding), $encoding);
        }

        return $result;
    }

    /**
     * Converts the given string to uppercase.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string
     */
    public static function uppercase(string $str, string $encoding = null) : string
    {
        return mb_strtoupper($str, $encoding ?: static::encoding($str));
    }

    /**
     * Converts the first character in the given string to uppercase.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The converted string.
     */
    public static function uppercaseFirst(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($str);

        // Uppercase the first character and append the remainder.
        return mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding) . mb_substr($str, 1, null, $encoding);
    }

    /**
     * Limits the number of words in the given string.
     *
     * @param   string  $str        The string to limit.
     * @param   int     $words      The maximal number of words to be contained in the string, not counting
     *                              the replacement.
     * @param   string  $encoding   The encoding to use.
     * @param   string  $end        The replacement.
     * @return  string              The resulting string.
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
     * @param   string  $haystack   The string to search in.
     * @param   string  $needle     The substring to search for.
     * @param   bool    $strict     Whether to use case-sensitive comparisons. True by default.
     * @param   bool    $before     Whether to return the part of $haystack before $needle or part of
     *                              $haystack starting at $needle and including $needle.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The part of $haystack before $needle.
     * @throws  \RuntimeException   Upon failing to find $needle in $haystack at all.
     */
    protected static function partOf(string $haystack, string $needle, bool $strict, bool $before, string $encoding = null) : string
    {
        $func     = $strict ? 'mb_strstr' : 'mb_stristr';
        $encoding = $encoding ?: static::encoding($haystack);

        if (false === $result = $func($haystack, $needle, $before, $encoding)) {
            throw new \RuntimeException('Failed to find $needle ['.$needle.'] in $haystack ['.static::truncate($haystack, 20, '...', $encoding).'].');
        }

        return $result;
    }
}
