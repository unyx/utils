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
     * @var array   A map of CHARS_* flags to their actual character lists. @todo Make writable, handle cache?
     */
    protected static $setsMap = [
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
     * @var array   Cached character sets built via self::buildCharacterSet() in a $bitmask => $set format,
     *              where $bitmask are the flags used to build the $set character list.
     */
    protected static $setsBuilt;

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

    /**
     * Creates a list of characters based on a set of flags (CHARS_* class constants) given.
     *
     * @param   int|core\Mask   $mask       The combination of CHARS_* flags (see the class constants) to use. Can be
     *                                      passed in either as an integer or as an instance of nyx\core\Mask.
     * @return  string                      The resulting list of characters.
     * @throws  \InvalidArgumentException   When an invalid $from mask was given or the supposed bitmask is <= 0.
     */
    public static function buildSet($mask) : string
    {
        // Unpack the actual mask if we got a core\Mask (builder) instance.
        if ($mask instanceof core\Mask) {
            $mask = $mask->get();
        } else if (!is_int($mask)) {
            throw new \InvalidArgumentException('Expected an integer or an instance of \nyx\core\Mask, got ['.gettype($mask).'] instead.');
        }

        if ($mask <= 0) {
            throw new \InvalidArgumentException('Expected a bitmask, got an integer with a value of ['.$mask.'] instead.');
        }

        // If all we get is the ambiguous exclusion flag, we need a base set of characters
        // to work with (an exclude from).
        if ($mask === self::CHARS_LEGIBLE) {
            $mask |= self::CHARS_ALPHANUM;
        }

        // Return a cached set if we've got one. Can't do this before the check for CHARS_LEGIBLE
        // above as the flag for alphanumeric chars gets applied to them first regardless of user given
        // params.
        if (isset(static::$setsBuilt[$mask])) {
            return static::$setsBuilt[$mask];
        }

        $result = '';

        // Iterate over all defined sets and build up the string for set flags.
        foreach (static::$setsMap as $flag => $characters) {
            // Ambiguous chars may get special exclusion treatment (see post loop).
            if ($flag === self::CHARS_LEGIBLE) {
                continue;
            }

            if (($mask & $flag) === $flag) {
                $result .= $characters;
            }
        }

        // Remove all known ambiguous characters from the set, if CHARS_LEGIBLE is set.
        if ($mask & self::CHARS_LEGIBLE) {
            $result = str_replace(str_split(static::$setsMap[self::CHARS_LEGIBLE]), '', $result);
        }

        // In mode 3 count_chars() returns only unique characters. Cache the result for the
        // flag set given so we can avoid the loops later on for the exact same mask.
        return static::$setsBuilt[$mask] = count_chars($result, 3);
    }

    /**
     * Returns the binary string representation of the given character. Multi-byte safe.
     *
     * @param   string  $character    The character to represent.
     * @return  string
     */
    public static function toBinaryString(string $character) : string
    {
        $result = null;
        $length = strlen($character); // Note: We want the raw length, not the mb length.

        for ($i = 0; $i < $length; ++$i) {
            $result .= sprintf('%08b', ord($character[$i]));
        }

        return $result;
    }

    /**
     * Returns the decimal code representation of the given character. Multi-byte safe.
     *
     * @param   string  $character    The character to represent.
     * @return  int
     */
    public static function toDecimalCode(string $character) : int
    {
        $code = ord($character[0]);

        // Single byte / 0xxxxxxx
        if (!($code & 0x80)) {
            return $code;
        }

        $bytes = 1;

        // 2 bytes / 110xxxxx
        if (0xc0 === ($code & 0xe0)) {
            $code  = $code & ~0xc0;
            $bytes = 2;
        // 3 bytes / 1110xxxx
        } elseif (0xe0 === ($code & 0xf0)) {
            $code  = $code & ~0xe0;
            $bytes = 3;
        // 4 bytes / 11110xxx
        } elseif (0xf0 === ($code & 0xf8)) {
            $code  = $code & ~0xf0;
            $bytes = 4;
        }

        for ($i = 2; $i <= $bytes; $i++) {
            $code = ($code << 6) + (ord($character[$i - 1]) & ~0x80);
        }

        return $code;
    }
}
