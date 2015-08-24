<?php namespace nyx\utils\random\sources;

// Internal dependencies
use nyx\utils\random\interfaces;
use nyx\utils;

/**
 * OpenSSL Source
 *
 * Uses RAND_bytes() from the OpenSSL lib to generate random bytes. We classify this as a medium
 * strength source since it's a userspace CSPRNG. See Thomas Ptacek's explanation:
 * http://sockpuppet.org/blog/2014/02/25/safely-generate-random-numbers
 *
 * RAND_bytes() still *can* of course be used in a cryptography context - it only means that if
 * security is of utmost concern, there are better sources.
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
    public static function strength()
    {
        return utils\Random::STRENGTH_MEDIUM;
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

        if (false === $strong || $length !== mb_strlen($bytes, '8bit')) {
            throw new \RuntimeException('Failed to generate sufficiently random bytes with a length of ['.$length.'].');
        }

        return $bytes;
    }
}
