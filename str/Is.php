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
}
