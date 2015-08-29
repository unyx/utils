<?php namespace nyx\utils\math;

/**
 * Vector
 *
 * Represents an immutable Euclidean vector of n dimensions with floating point precision.
 *
 * Note: Not using SplFixedArray internally despite always having a known array length since the possible
 *       performance/memory benefit would only materialize with a Vector of several thousand dimensions.
 *
 * @package     Nyx\Utils\Math
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/math.html
 * @todo        Make Vectors actually mutable depending on use cases (considerable overhead per-tick currently)?
 */
class Vector implements \ArrayAccess
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
     * Constructs a new n-dimensional Vector.
     *
     * @param   float[] $components         The components of the Vector. All values must be numeric and will
     *                                      be cast to floats.
     * @throws  \InvalidArgumentException   When any of the components is not a numeric value.
     */
    public function __construct(array $components)
    {
        // Validate all components and cast them to floats.
        foreach ($components as $d => &$component) {
            if (!is_numeric($component)) {
                throw new \InvalidArgumentException('The value of the component ['.$d.'] is not numeric.');
            }

            $component = (float) $component;
        }

        $this->components = $components;
    }

    /**
     * Returns the absolute value of this Vector as a new Vector instance.
     *
     * @return  Vector
     */
    public function abs() : Vector
    {
        $result = [];

        foreach ($this->components as $i => $component) {
            $result[$i] = abs($component);
        }

        return new static($result);
    }

    /**
     * Returns the smallest of the components compared as absolute values. This is *not* the
     * absolute minimum (@see Vector::min() for that).
     *
     * @return  float
     */
    public function absMin() : float
    {
        $result = 0.0;

        foreach ($this->components as $component) {
            if ($result > $abs = abs($component)) {
                $result = $abs;
            }
        }

        return $result;
    }

    /**
     * Returns the biggest of the components compared as absolute values. This is *not* the
     * absolute maximum (@see Vector::max() for that).
     *
     * @return  float
     */
    public function absMax() : float
    {
        $result = 0.0;

        foreach ($this->components as $component) {
            if ($result < $abs = abs($component)) {
                $result = $abs;
            }
        }

        return $result;
    }

    /**
     * Adds $that Vector/number to this Vector and returns the result as a new Vector.
     *
     * @param   Vector|number    $that      The Vector or (numeric) bias to add to this Vector.
     * @return  Vector                      The sum of the two vectors.
     * @throws  \DomainException            When a Vector is given as input and it is not in the same space.
     * @throws  \InvalidArgumentException   When the value to add is neither a Vector nor numeric.
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
        } else {
            throw new \InvalidArgumentException('Unknown type to add given - can only add other Vectors or numbers to Vectors.');
        }

        return new static($result);
    }

    /**
     * Computes the dot product of two Vectors (A | B).
     *
     * @param   Vector  $that       The Vector to compute the dot product against.
     * @return  float               The dot product of the two Vectors.
     * @throws  \DomainException    When the given Vector is not in the same space as this Vector.
     */
    public function dotProduct(Vector $that) : float
    {
        if (!$this->isSameDimension($that)) {
            throw new \DomainException('The given Vector is not in the same dimension as this Vector.');
        }

        $result = 0;

        foreach ($this->components as $i => $component) {
            $result += $component * $that->components[$i];
        }

        return (float) $result;
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
     * Returns a new Vector instance comprised of the biggest respective components
     * out of this Vector and $that Vector.
     *
     * @param   Vector  $that       The Vector to compare to.
     * @return  Vector
     * @throws  \DomainException    When the given Vector is not in the same space as this Vector.
     */
    public function componentMax(Vector $that) : Vector
    {
        if (!$this->isSameDimension($that)) {
            throw new \DomainException('The given Vector is not in the same dimension as this Vector.');
        }

        $result = [];

        foreach ($this->components as $i => $component) {
            $result[$i] = max($component, $that->components[$i]);
        }

        return new static($result);
    }

    /**
     * Returns a new Vector instance comprised of the smallest respective components
     * out of this Vector and $that Vector.
     *
     * @param   Vector  $that       The Vector to compare to.
     * @return  Vector
     * @throws  \DomainException    When the given Vector is not in the same space as this Vector.
     */
    public function componentMin(Vector $that) : Vector
    {
        if (!$this->isSameDimension($that)) {
            throw new \DomainException('The given Vector is not in the same dimension as this Vector.');
        }

        $result = [];

        foreach ($this->components as $i => $component) {
            $result[$i] = min($component, $that->components[$i]);
        }

        return new static($result);
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
     * Checks whether this Vector equals the given Vector, within the optional $tolerance.
     *
     * @param   Vector  $that               The Vector to compare to.
     * @param   float   $tolerance          The optional tolerance (to account for precision errors). Must be >= 0.
     * @return  bool
     * @throws  \InvalidArgumentException   When $tolerance is less than 0.
     * @throws  \DomainException            When the given Vector is not in the same space as this Vector.
     */
    public function equals(Vector $that, float $tolerance = 0.0) : bool
    {
        if (!$this->isSameDimension($that)) {
            throw new \DomainException('The given Vector is not in the same dimension as this Vector.');
        }

        if ($tolerance < 0) {
            throw new \InvalidArgumentException('The $tolerance must be greater than or equal to 0, got ['.$tolerance.'] instead.');
        }

        foreach ($this->components as $i => $component) {
            if (abs($component - $that->components[$i]) > $tolerance) {
                return false;
            }
        }

        return true;
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

        // Compute the square sum.
        $sum = 0;

        foreach ($this->components as $component) {
            $sum += $component * $component;
        }

        return $result = $sum;
    }

    /**
     * Returns the smallest of the components.
     *
     * @return  float
     */
    public function min() : float
    {
        return min($this->components);
    }

    /**
     * Returns the biggest of the components.
     *
     * @return  float
     */
    public function max() : float
    {
        return max($this->components);
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

    /**
     * Returns the normalized Vector, ie. a Vector with the same direction but a length of 1.
     *
     * @return  Vector                      The normalized vector.
     * @throws  exceptions\DivisionByZero   When the Vector's length is zero.
     */
    public function normalize() : Vector
    {
        return $this->divide($this->length());
    }

    /**
     * Projects this Vector onto another Vector.
     *
     * @param   Vector  $that   The vector to project this vector onto.
     * @return  Vector
     */
    public function projectOnto(Vector $that) : Vector
    {
        $that = $that->normalize();

        return $that->multiply($this->dotProduct($that));
    }

    /**
     * Reverses the direction of this Vector.
     *
     * @return  Vector
     */
    public function reverse()
    {
        $result = [];

        foreach ($this->components as $i => $component) {
            $result[$i] = $component * -1;
        }

        return new static($result);
    }

    /**
     * Subtracts $that Vector/number from this Vector and returns the result as a new Vector.
     *
     * @param   Vector|number    $that      The Vector or (numeric) bias to subtract from this Vector.
     * @return  Vector                      The resulting difference as a new Vector instance.
     * @throws  \DomainException            When a Vector is given as input and it is not in the same space.
     * @throws  \InvalidArgumentException   When the value to add is neither a Vector nor numeric.
     */
    public function subtract($that) : Vector
    {
        $result = [];

        if ($that instanceof Vector) {
            if (!$this->isSameDimension($that)) {
                throw new \DomainException('The given input Vector is not in the same dimension as this Vector.');
            }

            foreach ($this->components as $i => $component) {
                $result[$i] = $component - $that->components[$i];
            }
        } elseif (is_numeric($that)) {
            // We're accepting all numeric values but will be casting to a float, so be aware of potential
            // precision loss.
            $that = (float) $that;

            foreach ($this->components as $i => $component) {
                $result[$i] = $component - $that;
            }
        } else {
            throw new \InvalidArgumentException('Unknown type to subtract given - can only subtract other Vectors or numbers from Vectors.');
        }

        return new static($result);
    }

    /**
     * Returns the cartesian distance of this Vector to the given Vector.
     *
     * @param   Vector  $that       The Vector to calculate the distance to.
     * @return  float
     * @throws  \DomainException    When the given Vector is not in the same space as this Vector.
     */
    public function cartesianDistanceTo(Vector $that) : float
    {
        if (!$this->isSameDimension($that)) {
            throw new \DomainException('The given input Vector is not in the same dimension as this Vector.');
        }

        $result = 0;

        foreach ($this->components as $i => $component) {
            $result += pow($component - $that->components[$i], 2);
        }

        return sqrt($result);
    }

    /**
     * Returns the taxicab (AKA Manhattan / city block / rectilinear) distance of this Vector to the given Vector.
     *
     * @param   Vector  $that       The Vector to calculate the distance to.
     * @return  float
     * @throws  \DomainException    When the given Vector is not in the same space as this Vector.
     */
    public function taxicabDistanceTo(Vector $that) : float
    {
        if (!$this->isSameDimension($that)) {
            throw new \DomainException('The given input Vector is not in the same dimension as this Vector.');
        }

        $result = [];

        foreach ($this->components as $i => $component) {
            $result[$i] = abs($component - $that->components[$i]);
        }

        return max($result);
    }

    /**
     * @see self::get()
     *
     * @throws  \LogicException     When the given $key does not exist.
     */
    public function offsetGet($key)
    {
        if (!isset($this->components[$key])) {
            throw new \LogicException('The requested key ['.$key.'] does not exist.');
        }

        return $this->components[$key];
    }

    /**
     * {@inheritDoc}
     *
     * @throws  \LogicException     Always, since Vectors are immutable.
     */
    public function offsetSet($key, $item)
    {
        throw new \LogicException('Cannot set ['.$key.'] - Vectors are immutable.');
    }

    /**
     * @see self::has()
     */
    public function offsetExists($key)
    {
        return isset($this->components[$key]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws  \LogicException     Always, since Vectors are immutable.
     */
    public function offsetUnset($key)
    {
        throw new \LogicException('Cannot unset ['.$key.'] - Vectors are immutable.');
    }
}
