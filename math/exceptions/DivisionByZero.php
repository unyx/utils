<?php namespace nyx\utils\math\exceptions;

/**
 * Division by Zero Exception
 *
 * @package     Nyx\Utils\Math
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/math.html
 */
class DivisionByZero extends Arithmetic
{
    /**
     * {@inheritDoc}
     */
    public function __construct(string $message = null, int $code = 0, \Exception $previous = null)
    {
        parent::__construct($message ?: "Attempted division by zero.", $code, $previous);
    }
}