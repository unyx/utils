<?php namespace nyx\utils\math\vectors;

// Internal dependencies
use nyx\utils\math;

/**
 * 3-dimensional Vector
 *
 * @package     Nyx\Utils\Math
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/math.html
 */
class Vector3D extends math\Vector
{
    /**
     * Constructs a new Vector3D instance.
     *
     * @param   float   $x  The X-component of the Vector.
     * @param   float   $y  The Y-component of the Vector.
     * @param   float   $z  The Z-component of the Vector.
     */
    public function __construct(float $x, float $y, float $z)
    {
        parent::__construct(['x' => $x, 'y' => $y, 'z' => $z]);
    }

    /**
     * Computes the cross product of two 3-dimensional Vectors (A ^ B).
     *
     * @param   Vector3D    $that   The Vector to compute the cross product against.
     * @return  Vector3D            The cross product of the two Vectors as a new Vector3D instance.
     */
    public function crossProduct(Vector3D $that)
    {
        $thisC = $this->components;
        $thatC = $that->components;

        return new static (
            $thisC['y'] * $thatC['z'] - $thisC['z'] * $thatC['y'],
            $thisC['z'] * $thatC['x'] - $thisC['x'] * $thatC['z'],
            $thisC['x'] * $thatC['y'] - $thisC['y'] * $thatC['x']
        );
    }

    /**
     * {@inheritDoc}
     */
    public function dimension() : int
    {
        return 3;
    }

    /**
     * Computes the scalar triple product of this and two other Vectors.
     *
     * @param   Vector3D    $second     The second Vector of the triple product.
     * @param   Vector3D    $third      The third Vector of the triple product.
     * @return  float                   The scalar triple product of the three Vectors.
     */
    public function scalarTripleProduct(Vector3D $second, Vector3D $third) : float
    {
        return $this->dotProduct($second->crossProduct($third));
    }

    /**
     * Computes the vector triple product of this and two other Vectors.
     *
     * @param   Vector3D    $second The second Vector of the triple product.
     * @param   Vector3D    $third  The third Vector of the triple product.
     * @return  Vector3D            The vector triple product of the three Vectors as a new Vector3 instance.
     */
    public function vectorTripleProduct(Vector3D $second, Vector3D $third) : Vector3D
    {
        return $this->crossProduct($second->crossProduct($third));
    }

    /**
     * {@inheritDoc}
     */
    public function __get($name)
    {
        if ('x' === $name || 'y' === $name || 'z' === $name) {
            return $this->components[$name];
        }

        return parent::__get($name);
    }
}
