<?php namespace nyx\utils;

// External includes
use nyx\core;

/**
 * Str
 *
 * Helper methods for dealing with strings. The class is based on Laravel, FuelPHP, Patchwork/UTF-8 and a few others.
 * Some minor performance-related improvements were made.
 *
 * Note: Many of the methods can be circumvented by falling back directly to the builtin functions of the mb
 * extension as long as you don't need the additional layer of abstraction and feel comfortable managing the
 * encoding on your own.
 *
 * Suggestions:
 *   If you need an instance-based fluent OO wrapper for strings with similar manipulation capabilities. then
 *   you should take a look at Stringy {@see https://github.com/danielstjules/Stringy}
 *
 * Requires:
 * - Extension: mb
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
     *        to be hard to distinguish from each other (@see self::AMBIGUOUS_CHARS).
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
     * Ensures the given string begins with a single instance of a given substring.
     *
     * @param   string  $str    The string to cap.
     * @param   string  $with   The substring to begin with.
     * @return  string          The resulting string.
     */
    public static function begin(string $str, string $with) : string
    {
        return ltrim($str, $with).$with;
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
     * @param   string          $encoding   The encoding to use.
     * @param   bool            $all        Set this to true to ensure all elements of the $needle array (if provided)
     *                                      are contained within the haystack.
     * @param   bool            $strict     Set this to false to use case-insensitive comparisons.
     * @return  bool
     */
    public static function contains(string $haystack, $needle, string $encoding = null, bool $all = false, bool $strict = true) : bool
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
     * Inserts the given substring into the string at the provided index.
     *
     * @param   string  $str        The string to insert into.
     * @param   string  $substring  The string to be inserted.
     * @param   int     $index      The index at which to insert the substring.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The resulting string.
     */
    public static function insert(string $str, string $substring, int $index, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($str);

        // Return the initial string when the index exceeds the length of the string.
        if ($index > mb_strlen($str, $encoding)) {
            return $str;
        }

        return mb_substr($str, 0, $index, $encoding) . $substring . mb_substr($str, $index, mb_strlen($str, $encoding), $encoding);
    }

    /**
     * Converts the first character in the given string to lower case.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The converted string.
     */
    public static function lcfirst(string $str, string $encoding = null) : string
    {
        // Need to check for the existence of the first character to avoid notices.
        if (isset($str[0])) {
            $str[0] = mb_strtolower($str[0], $encoding ?: static::encoding($str));
        }

        return $str;
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
     * Trims the string to the given length, replacing the cut off characters from the end with another string.
     *
     * @param   string  $str        The string to limit.
     * @param   int     $limit      The maximal number of characters to be contained in the string, not counting
     *                              the replacement.
     * @param   string  $encoding   The encoding to use.
     * @param   string  $end        The replacement.
     * @return  string              The resulting string.
     */
    public static function limit(string $str, int $limit = 100, string $encoding = null, string $end = '...') : string
    {
        $encoding = $encoding ?: static::encoding($str);

        if (mb_strlen($str, $encoding) <= $limit) {
            return $str;
        }

        return mb_substr($str, 0, $limit, $encoding).$end;
    }

    /**
     * Converts the given string to lower case.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string
     */
    public static function lower(string $str, string $encoding = null) : string
    {
        return mb_strtolower($str, $encoding ?: static::encoding($str));
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
     * Removes the given string(s) from a string.
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

        $length = mb_strlen($str, $encoding);
        $reversed = '';

        // One char after another, from the end.
        for ($i = $length - 1; $i >= 0; $i--) {
            $reversed .= mb_substr($str, $i, 1, $encoding);
        }

        return $reversed;
    }

    /**
     * Randomizes the order of the characters in the given string. A multibyte equivalent of str_shuffle().
     *
     * @param   string  $str        The string to shuffle.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The resulting string.
     */
    public static function shuffle(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: static::encoding($str);

        $indexes = range(0, mb_strlen($str, $encoding) - 1);

        shuffle($indexes);

        $shuffledStr = '';

        array_map(function ($i) use ($str, &$shuffledStr, $encoding) {
            $shuffledStr .= mb_substr($str, $i, 1, $encoding);
        }, $indexes);

        return $shuffledStr;
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
     * @param   string    $str          The input string.
     * @param   int       $start        The index at which to start the slice.
     * @param   int|null  $length       The length of the slice.
     * @param   string    $encoding     The encoding to use.
     * @return  string                  The resulting string.
     */
    public static function sub(string $str, int $start, int $length = null, string $encoding = null) : string
    {
        return mb_substr($str, $start, $length, $encoding ?: static::encoding($str));
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
     * Converts the first character in the given string to upper case.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The converted string.
     */
    public static function ucfirst(string $str, string $encoding = null) : string
    {
        // Need to check for the existence of the first character to avoid notices.
        if (isset($str[0])) {
            $str[0] = mb_strtoupper($str[0], $encoding ?: static::encoding($str));
        }

        return $str;
    }

    /**
     * Converts the given string to title case. The equivalent of ucwords() albeit for multibyte strings.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string              The converted string.
     */
    public static function ucwords(string $str, string $encoding = null) : string
    {
        return mb_convert_case($str, MB_CASE_TITLE, $encoding ?: static::encoding($str));
    }

    /**
     * Converts the given string to upper case.
     *
     * @param   string  $str        The string to convert.
     * @param   string  $encoding   The encoding to use.
     * @return  string
     */
    public static function upper(string $str, string $encoding = null) : string
    {
        return mb_strtoupper($str, $encoding ?: static::encoding($str));
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
}
