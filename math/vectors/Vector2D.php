<?php namespace nyx\utils\math\vectors;

// Internal dependencies
use nyx\utils\math;

/**
 * 2-dimensional Vector
 *
 * @package     Nyx\Utils\Math
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/math.html
 */
class Vector2D extends math\Vector
{
    /**
     * {@inheritDoc}
     *
     * Overridden to directly construct a zero 2-dimensional Vector.
     */
    public static function zero() : Vector2D
    {
        return new static(0.0, 0.0);
    }

    /**
     * Constructs a new Vector2D instance.
     *
     * @param   float   $x  The X-component of the Vector.
     * @param   float   $y  The Y-component of the Vector.
     */
    public function __construct(float $x, float $y)
    {
        parent::__construct(['x' => $x, 'y' => $y]);
    }
}
