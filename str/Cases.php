<?php namespace nyx\utils\str;

// Internal includes
use nyx\utils;

/**
 * Cases
 *
 * Multi-byte safe utility for manipulating the character case in strings.
 *
 * @package     Nyx\Utils\Strings
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/strings.html
 */
class Cases
{
    /**
     * The traits of the Cases class.
     */
    use utils\traits\StaticallyExtendable;

    /**
     * Converts the first character in the given string to lowercase.
     *
     * @param   string      $str        The string to convert.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The converted string.
     */
    public static function lowerFirst(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: utils\Str::encoding($str);

        // Lowercase the first character and append the remainder.
        return mb_strtolower(mb_substr($str, 0, 1, $encoding), $encoding) . mb_substr($str, 1, null, $encoding);
    }
}
