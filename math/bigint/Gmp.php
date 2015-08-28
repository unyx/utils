<?php namespace nyx\utils\math\bigint;

// Internal dependencies
use nyx\utils\math;
use nyx\utils;

/**
 * Gmp
 *
 * BigInt implementation as an abstraction over the GMP extension.
 *
 * Requires:
 *   - ext-gmp
 *
 * @package     Nyx\Utils\Math
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/math.html
 * @todo        Remember to overload the assignment and comparison operators for BigInt in ext-nyx.
 */
class Gmp implements math\interfaces\BigInt
{
    /**
     * @var \GMP    The underlying GMP object holding the actual number.
     */
    protected $value;

    /**
     * Converts the binary string represented number into a BigInt instance representing the number
     * in decimal format.
     *
     * @param   string      $bytes      The binary string representing the number.
     * @param   bool        $twosc      Whether the number is represented in two's complement form.
     * @return  math\interfaces\BigInt
     */
    public static function fromBinaryString(string $bytes, bool $twosc = false) : math\interfaces\BigInt
    {
        $sign      = '';
        $bNegative = $twosc && (ord($bytes[0]) & 0x80);

        if ($bNegative) {
            $bytes = ~$bytes;
            $sign  = '-';
        }

        // We're going to use hexadecimal as base to use PHP's built-ins.
        // Not instantiating here just yet since we might need to subtract if we got the number in
        // two's complement (and the instances are immutable, ie. we're saving a wee bit overhead).
        $result = gmp_init($sign.bin2hex($bytes), 16);

        if ($bNegative) {
            $result = gmp_sub($result, '1');
        }

        return new static($result);
    }

    /**
     * Converts the arbitrary string represented number into a BigInt instance representing the number
     * in decimal format.
     *
     * The number can be in scientific notation on top of other formats supported natively by GMP (decimal,
     * hexadecimal or octal).
     *
     * @param   string      $number
     * @param   int|null    $base
     */
    public static function fromString(string $number, $base = null)
    {
        // Temporarily split the sign from the operand for easier matching. We will reattach it later.
        $sign   = 0 === strpos($number, '-') ? '-' : '';
        $number = ltrim($number, '-+');

        if (null === $base) {
            if (preg_match('#^(?:([1-9])\.)?([0-9]+)[eE]\+?([0-9]+)$#', $number, $matches)) {
                if (!empty($matches[1])) {
                    if ($matches[3] < strlen($matches[2])) {
                        throw new \InvalidArgumentException('Failed to create BigInt - malformed scientific notation of the number.');
                    }
                } else {
                    $matches[1] = '';
                }
                $number = str_pad(($matches[1] . $matches[2]), ($matches[3] + 1), '0', STR_PAD_RIGHT);
            } else {
                $base = 0;
            }
        }

        return new static(gmp_init($sign.$number, $base));
    }

    /**
     * Creates a BigInt instance from the given int.
     *
     * @param   string|int  $number
     * @param   int         $base
     */
    public static function fromInt(int $number, $base = null)
    {
        return new static(gmp_init($number, $base));
    }

    /**
     * Constructs a new GMP BigInt instance.
     *
     * @param   \GMP    $value  The GMP object holding the actual value.
     */
    public function __construct(\GMP $value)
    {
        $this->value = $value;
    }

    /**
     * Returns the underlying GMP object holding the actual value.
     *
     * This can be useful if you want to operate directly on the object to utilize functions not implemented by
     * this abstraction, for performance reasons or simply to make use of the overloaded operators \GMP has.
     *
     * @return  \GMP
     */
    public function expose() : \GMP
    {
        return $this->value;
    }

    /**
     * {@inheritDoc}
     */
    public function abs() : math\interfaces\BigInt
    {
        return new static(gmp_abs($this->value));
    }

    /**
     * {@inheritDoc}
     */
    public function add($operand) : math\interfaces\BigInt
    {
        return new static(gmp_add($this->value, $this->assertValidOperand($operand)));
    }

    /**
     * {@inheritDoc}
     */
    public function compareTo($operand) : int
    {
        return gmp_cmp($this->value, $this->assertValidOperand($operand));
    }

    /**
     * {@inheritDoc}
     */
    public function div($operand) : math\interfaces\BigInt
    {
        $operand = $this->assertValidOperand($operand);

        // Loose comparison since we're allowing multiple types as operands.
        if ($operand == 0) {
            throw new math\exceptions\DivisionByZero("Attempted division by zero. Prevented the apocalypse for the divisor ['.$this->value.']");
        }

        return new static(gmp_div_q($this->value, $operand));
    }

    /**
     * {@inheritDoc}
     */
    public function equals($operand) : bool
    {
        return 0 === gmp_cmp($this->value, $this->assertValidOperand($operand));
    }

    /**
     * {@inheritDoc}
     */
    public function mod($modulo) : math\interfaces\BigInt
    {
        return new static(gmp_mod($this->value, $this->assertValidOperand($modulo)));
    }

    /**
     * {@inheritDoc}
     */
    public function mul($operand) : math\interfaces\BigInt
    {
        return new static(gmp_mul($this->value, $this->assertValidOperand($operand)));
    }

    /**
     * {@inheritDoc}
     */
    public function pow($exponent) : math\interfaces\BigInt
    {
        return new static(gmp_pow($this->value, $this->assertValidOperand($exponent)));
    }

    /**
     * {@inheritDoc}
     */
    public function powmod($exponent, $modulo) : math\interfaces\BigInt
    {
        return new static(gmp_powm($this->value, $this->assertValidOperand($exponent), $this->assertValidOperand($modulo)));
    }

    /**
     * {@inheritDoc}
     */
    public function sqrt() : math\interfaces\BigInt
    {
        return new static(gmp_sqrt($this->value));
    }

    /**
     * {@inheritDoc}
     */
    public function sub($operand) : math\interfaces\BigInt
    {
        return new static(gmp_mul($this->value, $operand));
    }

    /**
     * {@inheritDoc}
     */
    public function serialize() : string
    {
        return serialize($this->value);
    }

    /**
     * {@inheritDoc}
     */
    public function unserialize($data)
    {
        $this->value = unserialize($data);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return $this->toDecimalString();
    }

    /**
     * {@inheritDoc}
     */
    public function toJson(int $options = 0) : string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * {@inheritDoc}
     */
    public function toBinaryString() : string
    {
        $decimal  = $this->toDecimalString();
        $nullbyte = chr(0);

        // Drop the signs.
        $decimal = ltrim($decimal, '+-0');

        if (empty($decimal)) {
            return $nullbyte;
        }

        // We're going to convert to a hexadecimal string first. Can't simply use decbin()
        // since it expects an integer while we're working with strings internally.
        $hex = gmp_strval($decimal, 16);

        if (strlen($hex) & 1) {
            $hex = '0' . $hex;
        }

        // Pack the hexadecimals into a string and remove the nullbyte.
        return ltrim(pack('H*', $hex), $nullbyte);
    }

    /**
     * {@inheritDoc}
     */
    public function toDecimalString() : string
    {
        return gmp_strval($this->value, 10);
    }

    /**
     * {@inheritDoc}
     */
    public function toString() : string
    {
        return $this->toDecimalString();
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return $this->toDecimalString();
    }

    /**
     * Asserts the given $operand is of a type that can be directly passed to any of the gmp_* functions.
     * If it's not, tries to convert known types into usable types and returns the usable operand on success.
     *
     * Does *not* actually check if a numeric value can be transformed to a number as understood by GMP.
     * GMP will gladly provide a cold error shower if it is not.
     *
     * @param   mixed   $operand
     * @return  mixed
     * @throws  \InvalidArgumentException   When the $operand is not usable.
     */
    protected function assertValidOperand($operand)
    {
        if ($operand instanceof static) {
            return $operand->expose();
        }

        if ($operand instanceof math\interfaces\BigInt) {
            return $operand->toString();
        }

        if (is_numeric($operand)) {
            return $operand;
        }

        throw new \InvalidArgumentException('Invalid operand given. Expected an instance of \nyx\utils\math\interfaces\BigInt or a numeric string, got ['.gettype($operand).'] instead.');
    }
}
