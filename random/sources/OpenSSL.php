<?php namespace nyx\utils\random\sources;

// Internal dependencies
use nyx\utils\random\interfaces;
use nyx\utils;

/**
 * OpenSSL Source
 *
 * Uses RAND_bytes() from the OpenSSL lib to generate random bytes.
 *
 * @package     Nyx\Utils
 * @version     0.0.5
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/random.html
 */
class OpenSSL implements interfaces\Source
{
    /**
     * {@inheritDoc}
     */
    public static function strength() : int
    {
        return utils\Random::STRENGTH_STRONG;
    }

    /**
     * Constructs a new OpenSSL Source instance.
     *
     * @throws  \RuntimeException   When the OpenSSL extension is not available on this platform.
     */
    public function __construct()
    {
        if (!function_exists('openssl_random_pseudo_bytes')) {
            throw new \InvalidArgumentException('The OpenSSL PHP extension is not available.');
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

        $bytes = openssl_random_pseudo_bytes($length, $strong);

        if (false === $bytes || false === $strong || $length !== mb_strlen($bytes, '8bit')) {
            throw new \RuntimeException('Failed to generate sufficiently random bytes with a length of ['.$length.'].');
        }

        return $bytes;
    }
}
