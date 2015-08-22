<?php namespace nyx\utils;

/**
 * Random
 *
 * Utilities for generating and dealing with (pseudo-)random values.
 *
 * If you need an utility for generating random/fake real-world data,
 * you should take a look at Faker {@see https://github.com/fzaninotto/Faker}
 *
 * @package     Nyx\Utils
 * @version     0.0.5
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/random.html
 */
class Random
{
    /**
     * The traits of the Str class.
     */
    use traits\StaticallyExtendable;

    /**
     * Generates a sequence of pseudo-random bytes of the given $length.
     *
     * Note: This is just a wrapper for random_bytes() provided for completeness of the API.
     *       However, as opposed to the native function simply returning false and raising a warning,
     *       we bump this up to an Exception to be on the safe side.
     *       Please {@see http://php.net/manual/en/function.random-bytes.php} for when this may be the case.
     *
     * @param   int     $length     The length of the random string of bytes that should be generated.
     * @return  string              The resulting string in binary format.
     * @throws  \RuntimeException   When the platform specific RNG couldn't be used for some reason.
     */
    public static function bytes(int $length) : string
    {
        if (false === $result = random_bytes($length)) {
            throw new \RuntimeException('No sufficient source of randomness is available on this platform.');
        }

        return $result;
    }

    /**
     * Generates a pseudo-random integer in the specified range. {0 .. PHP_INT_MAX} by default.
     *
     * Note: This is just a wrapper for random_bytes() provided for completeness of the API.
     *       However, as opposed to the native function simply returning false and raising a warning,
     *       we bump this up to an Exception to be on the safe side.
     *       Please {@see http://php.net/manual/en/function.random-int.php} for when this may be the case.
     *
     * @param   int     $min        The minimal value of the generated integer. Must be >= than PHP_INT_MIN.
     * @param   int     $max        The maximal value of the generated integer. Must be <= than PHP_INT_MAX.
     * @return  int                 The generated integer.
     * @throws  \RuntimeException   When the platform specific RNG couldn't be used for some reason.
     */
    public static function int(int $min = 0, int $max = PHP_INT_MAX) : int
    {
        if (false === $result = random_int($min, $max)) {
            throw new \RuntimeException('No sufficient source of randomness is available on this platform.');
        }

        return $result;
    }

    /**
     * Generates a pseudo-random float in the specified range
     *
     * @param   int     $min        The minimal value of the generated float. Must be >= than PHP_INT_MIN.
     * @param   int     $max        The maximal value of the generated float. Must be <= than PHP_INT_MAX.
     * @return  float               The generated float.
     */
    public static function float(int $min = 0, int $max = 1) : float
    {
        return $min + static::int() / PHP_INT_MAX * ($max - $min);
    }

    /**
     * Generates a pseudo-random string.
     *
     * @param   int     $length     The expected length of the generated string.
     * @param   string  $characters The character list to use. If not given, the method will fall back
     *                              to the Base64 character set.
     * @return  float               The generated string.
     * @throws  \DomainException    When the platform specific RNG couldn't be used for some reason.
     */
    public static function string(int $length = 8, string $characters = null) : string
    {
        if ($length < 1) {
            throw new \DomainException('The expected length of the generated string must be at least 1.');
        }

        // Expected most common use case is using the base64 character set, ie. when no
        // character list was given, so let's handle it right away.
        if (empty($characters)) {
            $bytes = static::bytes((int) ceil($length * 0.75));

            return substr(rtrim(base64_encode($bytes), '='), 0, $length);
        }

        // If only one character was given, we're gonna repeat it $length times
        // in a *totally random* order, d'oh.
        if (1 === $charactersLen = strlen($characters)) {
            return str_repeat($charactersLen, $length);
        }

        $result = '';
        $bytes  = static::bytes($length);
        $pos    = 0;

        // Generate one character at a time until we reach the expected length.
        for ($idx = 0; $idx < $length; $idx++) {
            $pos     = ($pos + ord($bytes[$idx])) % $charactersLen;
            $result .= $characters[$pos];
        }

        return $result;
    }

    /**
     * Generates a pseudo-random boolean value.
     *
     * @return  bool    The resulting boolean.
     */
    public static function bool() : bool
    {
        return (bool) (ord(static::bytes(1)) % 2);
    }
}
