<?php namespace nyx\utils\str;

// Internal dependencies
use nyx\utils;

/**
 * Is
 *
 * Helper methods for detecting the format/contents of a string.
 *
 * This class should *not* be used for validation, even though you can use it to perform initial detection
 * of certain types of values in strings.
 *
 * Suggestions:
 *  - ext-libxml (for detecting XML strings)
 *
 * @package     Nyx\Utils\Strings
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/strings.html
 * @todo        Detect HTML (fixed list of tags?)
 */
class Is
{
    /**
     * The traits of the Is class.
     */
    use utils\traits\StaticallyExtendable;

    /**
     * Checks whether the string contains *only* alphabetic characters.
     *
     * @param   string      $str        The string to match,
     * @param   string|null $encoding   The encoding to use.
     * @return  bool
     */
    public static function alphabetic(string $str, string $encoding = null) : bool
    {
        return static::matchesPattern($str, '^[[:alpha:]]*$', $encoding);
    }

    /**
     * Checks whether the string contains *only* alphanumeric characters.
     *
     * @param   string  $str            The string to match,
     * @param   string|null $encoding   The encoding to use.
     * @return  bool
     */
    public static function alphanumeric(string $str, string $encoding = null) : bool
    {
        return static::matchesPattern($str, '^[[:alnum:]]*$', $encoding);
    }

    /**
     * Checks whether the string contains *only* whitespace characters.
     *
     * @param   string  $str            The string to match,
     * @param   string|null $encoding   The encoding to use.
     * @return  bool
     */
    public static function blank(string $str, string $encoding = null) : bool
    {
        return static::matchesPattern($str, '^[[:space:]]*$', $encoding);
    }

    /**
     * Determines whether the given string represents a valid email address.
     *
     * @param   string  $str    The string to check.
     * @return  bool
     */
    public static function email(string $str) : bool
    {
        return false !== filter_var($str, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Checks whether the string contains *only* hexadecimal characters.
     *
     * @param   string  $str            The string to match,
     * @param   string|null $encoding   The encoding to use.
     * @return  bool
     */
    public static function hexadecimal(string $str, string $encoding = null) : bool
    {
        return static::matchesPattern($str, '^[[:xdigit:]]*$', $encoding);
    }

    /**
     * Checks whether the string contains *only* numeric characters.
     *
     * Note: Basically an encoding-aware alias for is_numeric(). In the vast majority of cases
     * you should simply stick to is_numeric() for performance reasons.
     *
     * @param   string      $str        The string to match,
     * @param   string|null $encoding   The encoding to use.
     * @return  bool
     */
    public static function numeric(string $str, string $encoding = null) : bool
    {
        return static::matchesPattern($str, '^[[:digit:]]*$', $encoding);
    }

    /**
     * Determines whether the given string represents a valid IP address.
     *
     * @param   string  $str    The string to check.
     * @return  bool
     */
    public static function ip(string $str) : bool
    {
        return false !== filter_var($str, FILTER_VALIDATE_IP);
    }

    /**
     * Determines whether the given string is JSON-encoded.
     *
     * @param   string  $str    The string to check.
     * @return  bool
     */
    public static function json(string $str) : bool
    {
        json_decode($str);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Checks whether the string contains *only* lowercase characters.
     *
     * @param   string  $str            The string to match,
     * @param   string|null $encoding   The encoding to use.
     * @return  bool
     */
    public static function lowercase(string $str, string $encoding = null) : bool
    {
        return static::matchesPattern($str, '^[[:lower:]]*$', $encoding);
    }

    /**
     * Determines whether the given string is a serialized representation of a value.
     *
     * @param   string  $str    The string to check.
     * @return  bool
     */
    public static function serialized(string $str) : bool
    {
        return $str === 'b:0;' || false !== @unserialize($str);
    }

    /**
     * Checks whether the string contains *only* uppercase characters.
     *
     * @param   string  $str            The string to match,
     * @param   string|null $encoding   The encoding to use.
     * @return  bool
     */
    public static function uppercase(string $str, string $encoding = null) : bool
    {
        return static::matchesPattern($str, '^[[:upper:]]*$', $encoding);
    }

    /**
     * Determines whether the given string represents a valid URL address.
     *
     * @param   string  $str    The string to check.
     * @return  bool
     */
    public static function url(string $str) : bool
    {
        return false !== filter_var($str, FILTER_VALIDATE_URL);
    }

    /**
     * Determines whether the given string is in a valid XML format.
     *
     * Note: Requires ext-libxml.
     *
     * @param   string  $str    The string to check.
     * @return  bool
     */
    public static function xml(string $str) : bool
    {
        $initialSetting = libxml_use_internal_errors();
        libxml_use_internal_errors(true);
        $result = simplexml_load_string($str) !== false;
        libxml_use_internal_errors($initialSetting);

        return $result;
    }

    /**
     * Checks whether the given string matches the given pattern.
     *
     * @param   string  $haystack       The string to match,
     * @param   string  $pattern        The regexp pattern to match against.
     * @param   string|null $encoding   The encoding to use.
     * @return  bool                    True if the string matches the pattern, false otherwise.
     */
    protected static function matchesPattern(string $haystack, string $pattern, string $encoding = null) : bool
    {
        $initialEncoding = mb_regex_encoding();
        mb_regex_encoding($encoding ?: utils\Str::encoding($haystack));
        $result = mb_ereg_match($pattern, $haystack);
        mb_regex_encoding($initialEncoding);

        return $result;
    }
}
