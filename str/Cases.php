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
     * Converts the string to camelCase using whitespace as case delimiter.
     *
     * @param   string      $str        The string to convert.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The converted string.
     */
    public static function camel(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: utils\Str::encoding($str);

        return static::lowerFirst(static::studly($str, $encoding), $encoding);
    }

    /**
     * Delimits the given string on spaces, underscores and dashes and before uppercase characters using the
     * given delimiter string. The resulting string will also be trimmed and lower-cased.
     *
     * @param   string          $str        The string to delimit.
     * @param   string          $delimiter  The delimiter to use. Can be a sequence of multiple characters.
     * @param   string|null     $encoding   The encoding to use.
     * @return  string                      The resulting string.
     * @todo    Decide whether to keep the trimming and case change in here (too much responsibility).
     */
    public static function delimit(string $str, string $delimiter, string $encoding = null) : string
    {
        $encoding = $encoding ?: utils\Str::encoding($str);

        // Keep track of the internal encoding as we'll change it temporarily and then revert back to it.
        $internalEncoding = mb_regex_encoding();

        // Swap out the internal encoding for what we want...
        mb_regex_encoding($encoding);

        // ... trim the input string, convert it to lowercase, insert the delimiter.
        $str = mb_ereg_replace('\B([A-Z])', '-\1', mb_ereg_replace("^[[:space:]]+|[[:space:]]+\$", '', $str));
        $str = mb_strtolower($str, $encoding);
        $str = mb_ereg_replace('[-_\s]+', $delimiter, $str);

        // Restore the initial internal encoding.
        mb_regex_encoding($internalEncoding);

        return $str;
    }

    /**
     * Converts all characters in the given string to lowercase.
     *
     * @param   string      $str        The string to convert.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The converted string.
     */
    public static function lower(string $str, string $encoding = null) : string
    {
        return mb_strtolower($str, $encoding ?: utils\Str::encoding($str));
    }

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

    /**
     * Converts the string to StudlyCaps using whitespace as case delimiter. This is, essentially,
     * simply a variant of camelCase which starts with a capital letter.
     *
     * @param   string      $str        The string to convert.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The converted string.
     */
    public static function studly(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: utils\Str::encoding($str);

        // Convert dashes and underscores to spaces, then convert the string to title case (ie. ucwords()).
        // Note: We are using a simple str_replace here since we are looking for exact characters known
        // to not be multi-byte.
        $str = mb_convert_case(str_replace(['-', '_'], ' ', $str), MB_CASE_TITLE, $encoding);

        // Lastly we are going to remove *all* whitespace characters, including multi-byte whitespace, tabs,
        // newlines etc., which will effectively trim and collapse the string.
        return utils\Str::replace($str, '[[:space:]]', ' ', 'msr', $encoding);
    }

    /**
     * Converts the given string to title case. Multi-byte-safe equivalent of ucwords().
     *
     * @param   string      $str        The string to convert.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The converted string.
     */
    public static function title(string $str, string $encoding = null) : string
    {
        return mb_convert_case($str, MB_CASE_TITLE, $encoding ?: utils\Str::encoding($str));
    }

    /**
     * Converts all characters in the given string to uppercase.
     *
     * @param   string      $str        The string to convert.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The converted string.
     */
    public static function upper(string $str, string $encoding = null) : string
    {
        return mb_strtoupper($str, $encoding ?: utils\Str::encoding($str));
    }

    /**
     * Converts the first character in the given string to uppercase.
     *
     * @param   string      $str        The string to convert.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The converted string.
     */
    public static function upperFirst(string $str, string $encoding = null) : string
    {
        $encoding = $encoding ?: utils\Str::encoding($str);

        // Uppercase the first character and append the remainder.
        return mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding) . mb_substr($str, 1, null, $encoding);
    }
}
