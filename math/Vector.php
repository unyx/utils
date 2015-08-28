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
     * Adds this Vector to $that Vector and returns the result as a new Vector.
     *
     * @param   Vector|number    $that      The Vector or (numeric) bias to add.
     * @return  Vector                      The sum of the two vectors.
     * @throws  \DomainException
     */
    public function add($that) : Vector
    {
        $result = [];

        if ($that instanceof Vector) {
            if (!$this->isSameDimension($that)) {
                throw new \DomainException('The given input Vector is not in the same dimension as this Vector.');
            }

            foreach ($this->components as $i => $component) {
                $result[$i] = $component + $that->components[$i];
            }
        } elseif (is_numeric($that)) {
            // We're accepting all numeric values but will be casting to a float, so be aware of potential
            // precision loss.
            $that = (float) $that;

            foreach ($this->components as $i => $component) {
                $result[$i] = $component + $that;
            }
        }

        // Not having a result at this point simply means the input was neither a Vector nor a numeric.
        if (!empty($result)) {
            return new static($result);
        }

        throw new \InvalidArgumentException('Unknown type to add given - can only add other Vectors or numbers to Vectors.');
    }

    /**
     * Returns the components of the Vector.
     *
     * @return  float[]
     */
    public function components() : array
    {
        return $this->components;
    }

    /**
     * Returns the dimension of the Vector.
     *
     * @return  int
     */
    public function dimension() : int
    {
        return count($this->components);
    }

    /**
     * Divides the Vector by the given scale and returns the result as a new Vector.
     *
     * @param   float   $scale              The scale to divide by.
     * @return  Vector                      The result of the division.
     * @throws  exceptions\DivisionByZero   When $scale is 0.f.
     */
    public function divide(float $scale) : Vector
    {
        if ($scale == 0) {
            throw new exceptions\DivisionByZero;
        }

        return $this->multiply(1.0 / $scale);
    }

    /**
     * Checks whether this Vector is of the same dimension as $that Vector.
     *
     * @param   Vector  $that   The Vector to check against.
     * @return  bool            True when the Vectors are of the same dimension, false otherwise.
     */
    public function isSameDimension(Vector $that) : bool
    {
        return count($this->components) === count($that->components);
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

    /**
     * Multiplies the Vector by the given scale and returns the result as a new Vector.
     *
     * @param   float   $scale  The scale to multiply by.
     * @return  Vector          The result of the multiplication.
     */
    public function multiply(float $scale) : Vector
    {
        $result = [];

        foreach ($this->components as $i => $component) {
            $result[$i] = $component * $scale;
        }

        return new static($result);
    }
}
