<?php namespace nyx\utils\random\interfaces;

/**
 * Source
 *
 * Represents a source of pseudorandom numbers. Each concrete source defines its strength on its
 * own.
 *
 * Note - the constants representing the strength are in utils\Random::STRENGTH_* since that
 * class represents the entry point and in the overwhelming majority of use cases this interface
 * will never actually get used, so we're merely avoiding an additional include).
 *
 * @package     Nyx\Utils
 * @version     0.0.5
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/random.html
 */
interface Source
{
    /**
     * Returns the strength of this source - one of the utils\Random::STRENGTH_* constants.
     *
     * @return  int
     */
    public static function strength() : int;

    /**
     * Generates a sequence of pseudo-random bytes of the given $length.
     *
     * @param   int     $length             The length of the random string of bytes that should be generated.
     * @return  string                      The resulting string in binary format.
     * @throws  \InvalidArgumentException   When the expected $length is less than 1.
     * @throws  \RuntimeException           When failing to generate random data matching the expected strength
     *                                      and length.
     */
    public function generate(int $length) : string;
}
