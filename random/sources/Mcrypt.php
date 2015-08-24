<?php namespace nyx\utils\random\sources;

// Internal dependencies
use nyx\utils\random\interfaces;
use nyx\utils;

/**
 * Mcrypt Source
 *
 * Uses /dev/urandom to generate random bytes via mcrypt_create_iv(), which has a suitable fallback
 * on Windows systems where /dev/urandom is not available (CryptGenRandom).
 *
 * @package     Nyx\Utils
 * @version     0.0.5
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/random.html
 */
class Mcrypt implements interfaces\Source
{
    /**
     * {@inheritDoc}
     */
    public static function strength()
    {
        return utils\Random::STRENGTH_STRONG;
    }

    /**
     * Constructs a new Mcrypt Source instance.
     *
     * @throws  \RuntimeException   When the OpenSSL extension is not available on this platform.
     */
    public function __construct()
    {
        if (!function_exists('mcrypt_create_iv')) {
            throw new \InvalidArgumentException('The Mcrypt PHP extension is not available.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function generate(int $length) : string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('The expected number of random bytes must be at least 1.');
        }

        $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);

        if (false === $bytes || $length !== mb_strlen($bytes, '8bit')) {
            throw new \RuntimeException('Failed to generate sufficiently random bytes with a length of ['.$length.'].');
        }

        return $bytes;
    }
}
