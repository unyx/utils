<?php namespace nyx\utils\math\vectors;

// Internal dependencies
use nyx\utils\math;

/**
 * 3-dimensional space Vector
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
     * @param   float[]     The components of the Vector, ordered: X, Y, Z.
     */
    public function __construct(array $components)
    {
        if (3 !== count($components)) {
            throw new \InvalidArgumentException('Vector3D instances must have exactly 3 components corresponding to X, Y, Z.');
        }

        $this->components = $components;
    }
}
