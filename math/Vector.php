<?php namespace nyx\utils\math;

/**
 * Vector
 *
 * Represents an immutable Euclidean vector of n dimensions.
 *
 * @package     Nyx\Utils\Math
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/math.html
 */
class Vector
{
    /**
     * @var float[]     The components of the Vector.
     */
    protected $components;

    /**
     * Creates a zero-length vector of the given dimension.
     *
     * @param   int     $dimension          The dimension of the Vector to create. Must be >= 0.
     * @return  Vector                      A zero-length vector of the given dimension.
     * @throws  \InvalidArgumentException   When $dimension is less than 0.
     */
    public static function null(int $dimension = 0) : static
    {
        if ($dimension === 0) {
            return new static([]);
        }

        if ($dimension < 0) {
            throw new \InvalidArgumentException('Expected dimension to be at least 0, got ['.$dimension.'] instead.');
        }

        return new static(array_fill(0, $dimension, 0));
    }

    /**
     * Constructs a new Vector.
     *
     * @param   float[]     The components of the Vector.
     */
    public function __construct(array $components)
    {
        $this->components = $components;
    }

    /**
     * Returns the length of the Vector.
     *
     * @return  float
     */
    public function length() : float
    {
        static $result;

        return null !== $result ? $result : $result = sqrt($this->lengthSquared());
    }

    /**
     * Returns the square of the Vector's length.
     *
     * @return  float
     */
    public function lengthSquared() : float
    {
        static $result;

        // Return the cached result if it's available.
        if ($result !== null) {
            return $result;
        }

        // Compute the square.
        $sum = 0;

        foreach ($this->components as $component) {
            $sum += pow($component, 2);
        }

        return $result = $sum;
    }
}
