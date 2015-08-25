<?php namespace nyx\utils\str;

// Internal dependencies
use nyx\utils;

/**
 * Is
 *
 * Helper methods for detecting the format/contents of a string.
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
     * Determines whether the given string is a serialized representation of a value.
     *
     * @param   string  $str    The string to check.
     * @return  bool
     */
    public static function serialized(string $str) : bool
    {
        return !($str !== 'b:0;' && false === $value = @unserialize($str));
    }
}
