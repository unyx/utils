<?php namespace nyx\utils\str;

// External includes
use nyx\core;

// Internal includes
use nyx\utils;

/**
 * Character
 *
 * @package     Nyx\Utils\Strings
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/strings.html
 */
class Character
{
    /**
     * The traits of the Character class.
     */
    use utils\traits\StaticallyExtendable;

    /**
     * Returns the character at the specified $offset (0-indexed) in $haystack.
     *
     * @param   string      $haystack   The string to search in.
     * @param   int         $offset     The requested index. If a negative index is given, this method will return
     *                                  the $offset-th character counting from the end of the string.
     * @param   string|null $encoding   The encoding to use.
     * @return  string                  The character at the specified $index.
     */
    public static function at(string $haystack, int $offset, string $encoding = null) : string
    {
        $encoding = $encoding ?: utils\Str::encoding($haystack);

        // Check if the absolute starting index (to account for negative indexes) + 1 (since it's 0-indexed
        // while length is > 1 at this point) is within the length of the string.
        if (abs($offset) >= mb_strlen($haystack, $encoding)) {
            throw new \OutOfBoundsException('The given $offset ['.$offset.'] does not exist within the string ['.utils\Str::truncate($haystack, 20, '...', $encoding).'].');
        }

        return mb_substr($haystack, $offset, 1, $encoding);
    }
}
