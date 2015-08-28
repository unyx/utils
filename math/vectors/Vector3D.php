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
     * {@inheritDoc}
     */
    public function dimension() : int
    {
        return 3;
    }

    /**
     * Computes the cross product of two 3-dimensional Vectors (A ^ B).
     *
     * @param   Vector3D    $that   The vector to compute the cross product against.
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
}
