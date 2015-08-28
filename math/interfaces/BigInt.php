<?php namespace nyx\utils\math\interfaces;

// External dependencies
use nyx\core;

// Internal dependencies
use nyx\utils\math;

/**
 * BigInt
 *
 * BigInts are immutable - every arithmetic operation on the underlying value returns a new instance
 * of a BigInt implementation.
 *
 * @package     Nyx\Utils\Math
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/math.html
 */
interface BigInt extends \Serializable, \JsonSerializable, core\interfaces\Stringable, core\interfaces\Jsonable
{
    /**
     * Returns the absolute value of this BigInt.
     *
     * @return  BigInt
     */
    public function abs() : BigInt;

    /**
     * Adds the given number to this BigInt.
     *
     * @param   string|int|BigInt    $operand   The number to add.
     * @return  BigInt
     */
    public function add($operand) : BigInt;

    /**
     * Compares this BigInt to the given number.
     *
     * @param   string|int|BigInt    $operand   The number to compare this BigInt to.
     * @return  int                             -1 if this instance is less than $operand, 0 if they are equal
     *                                          or 1 if this instance is greater than $operand.
     */
    public function compareTo($operand) : int;

    /**
     * Divides this BigInt by the given number.
     *
     * Note: Returns only the quotient of the division as a BigInt instance.
     *
     * @param   string|int|BigInt    $operand   The number to divide by.
     * @return  BigInt
     * @throws  math\exceptions\DivisionByZero  When attempting to divide by zero.
     */
    public function div($operand) : BigInt;

    /**
     * Checks whether this BigInt is equal to the given number.
     *
     * @param   string|int|BigInt    $operand   The number to compare this BigInt to.
     * @return  bool                            True if the numbers are equal, false otherwise..
     */
    public function equals($operand) : bool;

    /**
     * Calculates this BigInt modulo $modulo.
     *
     * @param   string|int|BigInt   $modulo     The module to evaluate.
     * @return  BigInt
     */
    public function mod($modulo) : BigInt;

    /**
     * Multiplies this BigInt by the given number.
     *
     * @param   string|int|BigInt    $operand   The number to multiply by.
     * @return  BigInt
     */
    public function mul($operand) : BigInt;

    /**
     * Raises this BigInt into the given power.
     *
     * @param   string|int|BigInt   $exponent   The positive power by which to raise the base.
     * @return  BigInt
     */
    public function pow($exponent) : BigInt;

    /**
     * Raises this BigInt into the given power reduced by the given modulo.
     *
     * @param   string|int|BigInt   $exponent   The positive power by which to raise the base.
     * @param   string|int|BigInt   $modulo     The modulo to evaluate.
     * @return  BigInt
     */
    public function powmod($exponent, $modulo) : BigInt;

    /**
     * Returns the square root of this BigInt.
     *
     * @return  BigInt
     */
    public function sqrt() : BigInt;

    /**
     * Subtracts the given number from this BigInt.
     *
     * @param   string|int|BigInt    $operand   The number to subtract.
     * @return  BigInt
     */
    public function sub($operand) : BigInt;

    /**
     * Returns the binary string representation of the BigInt.
     *
     * @return  string
     */
    public function toBinaryString() : string;

    /**
     * Returns the decimal string representation of the BigInt.
     *
     * @return  string
     */
    public function toDecimalString() : string;

    /**
     * {@inheritDoc}
     *
     * Implementations *must* alias this method to self::toDecimalString().
     */
    public function toString() : string;
}
