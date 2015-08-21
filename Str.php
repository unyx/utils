<?php namespace nyx\utils;

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
 * Requires:
 * - Extension: mb
 * - Extension: intl (Normalizer)
 * - Extension: iconv
 * - Extension: openssl (random strings)
 *
 * (If your PHP installation does not have the mb, intl and iconv extensions and you can not modify the installation,
 * take a look at https://github.com/nicolas-grekas/Patchwork-UTF8).
 *
 * @package     Nyx\Utils\Strings
 * @version     0.0.1
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/strings.html
 * @todo        Snake case, camel case, studly caps, dashed, underscored?
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
     * @var array   The transliteration table used by the toAscii() method once fetched from a file.
     */
    protected static $ascii;

    /**
     * Returns a Closure which will return the subsequent given value (argument to this method( on each call.
     * While this is primarily meant for strings, it can be used with any type of values.
     *
     * When the Closure gets called with false as its argument, it will return the current internal value without
     * alternating the next time (ie. the same value will be returned with the next call).
     *
     * @param   mixed       $first      Two or more values to alternate between, given as separate arguments.
     * @param   mixed       $second     See above. More than two arguments can be given.
     * @return  \Closure
     */
    public static function alternator($first, $second)
    {
        $values = func_get_args();

        return function ($next = true) use ($values) {
            static $i = 0;
            return $values[($next ? $i++ : $i) % count($values)];
        };
    }

    /**
     * Returns the base class name of a class contained in a namespace.
     *
     * @param   string|object   $value  Either a class name or an object of which the class name will be determined.
     * @return  string                  The base class name.
     */
    public static function baseClass($value)
    {
        return basename(str_replace('\\', '/', is_object($value) ? get_class($value) : $value));
    }

    /**
     * Ensures the given string begins with a single instance of a given substring.
     *
     * @param   string  $str    The string to cap.
     * @param   string  $with   The substring to begin with.
     * @return  string          The resulting string.
     */
    public static function begin($str, $with)
    {
        return ltrim($str, $with).$with;
    }

    /**
     * Trims the given string and replaces multiple consecutive whitespaces with a single space.
     *
     * @param   string  $str    The string to clean.
     * @return  string          The resulting string.
     */
    public static function clean($str)
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
    public static function contains($haystack, $needle, $encoding = null, $all = false, $strict = true)
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
    public static function encoding($str = null)
    {
        // If a string was given, we attempt to detect the encoding of the string. If we succeed, just return it.
        if (null !== $str and false !== $encoding = mb_detect_encoding($str)) {
            return $encoding;
        }

        // Otherwise let's return one of the defaults.
        return static::$encoding ?: 'utf-8';
    }

    /**
     * Determines if the given string ends with the given needle or one of the given needles in an array is provided.
     *
     * @param   string          $haystack   The string to search in.
     * @param   string|array    $needles    The needle(s) to look for.
     * @param   string          $encoding   The encoding to use.
     * @return  bool                        True when the string ends with one of the given needles, false otherwise.
     */
    public static function endsWith($haystack, $needles, $encoding = null)
    {
        $encoding = $encoding ?: static::encoding($haystack);

        foreach ((array) $needles as $needle) {
            if ($needle != '' and $needle == mb_substr($haystack, -mb_strlen($needle, $encoding), $encoding)) {
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
    public static function finish($str, $with)
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
    public static function insert($str, $substring, $index, $encoding = null)
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
    public static function lcfirst($str, $encoding = null)
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
    public static function length($str, $encoding = null)
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
    public static function limit($str, $limit = 100, $encoding = null, $end = '...')
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
    public static function lower($str, $encoding = null)
    {
        return mb_strtolower($str, $encoding ?: static::encoding($str));
    }

    /**
     * Creates a string of the specified length containing random alpha-numeric characters.
     *
     * @param   int     $length     the expected length of the resulting string.
     * @return  string              the resulting string,
     * @throws  \RuntimeException   When generation of random bytes fails.
     */
    public static function random($length = 16)
    {
        // Make sure this succeeds.
        if (false === $bytes = openssl_random_pseudo_bytes($length * 2)) {
            throw new \RuntimeException('Failed to generate the random string. Is the OpenSSL extension installed and configured?');
        }

        return substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $length);
    }

    /**
     * Removes the given string(s) from a string.
     *
     * @param   string          $str    The string to remove from.
     * @param   string|array    $what   What to remove from the string.
     * @return  string                  The resulting string.
     */
    public static function remove($str, $what)
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
    public static function replace($str, $what, $with)
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
    public static function reverse($str, $encoding = null)
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
    public static function shuffle($str, $encoding = null)
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
     * @param   string  $str        The string to sluggify.
     * @param   string  $separator  The separator to use instead of non-alphanumeric characters.
     * @return  string              The resulting slug.
     */
    public static function slug($str, $separator = '-')
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
    public static function startsWith($haystack, $needles, $encoding = null)
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
    public static function sub($str, $start, $length = null, $encoding = null)
    {
        return mb_substr($str, $start, $length, $encoding ?: static::encoding($str));
    }

    /**
     * Transliterates an UTF-8 encoded string to its ASCII equivalent.
     *
     * @param   string  $str    The UTF-8 encoded string to transliterate.
     * @return  string          The ASCII equivalent of the input string.
     */
    public static function toAscii($str)
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
    public static function toSpaces($str, $length = 4)
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
    public static function toTabs($str, $length = 4)
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
    public static function ucfirst($str, $encoding = null)
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
    public static function ucwords($str, $encoding = null)
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
    public static function upper($str, $encoding = null)
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
    public static function words($str, $words = 100, $encoding = null, $end = '...')
    {
        $encoding = $encoding ?: static::encoding($str);

        preg_match('/^\s*+(?:\S++\s*+){1,'.$words.'}/u', $str, $matches);

        if (!isset($matches[0])) {
            return $str;
        }

        if (mb_strlen($str, $encoding) === mb_strlen($matches[0], $encoding)) {
            return $str;
        }

        return rtrim($matches[0]).$end;
    }
}
