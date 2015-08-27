<?php namespace nyx\utils;

/**
 * Math
 *
 * Utilities related to mathematical functions and working with numbers.
 *
 * @package     Nyx\Utils\Math
 * @version     0.0.1
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/math.html
 */
class Math
{
    /**
     * The traits of the Math class.
     */
    use traits\StaticallyExtendable;

    /**
     * Mathematical constants map.
     */
    const CONSTANTS = [
        'pi'               => M_PI,
        'e'                => M_E,
        'euler-mascheroni' => M_EULER,
        'conway'           => 1.303577269
    ];

    /**
     * Rounding constants.
     */
    const ROUND_ZERO     = 0;
    const ROUND_PLUSINF  = 1;
    const ROUND_MINUSINF = 2;

    /**
     * Returns the number of decimal places contained in the given number.
     *
     * @param   float       $value      The number to count the decimal places of.
     * @return  int|bool                The number of decimal places or false if the given $value was not numeric.
     */
    public static function countDecimals(float $value)
    {
        // When the value when cast to an integer is about the same, return 0 decimal places. Otherwise count them.
        return (int) $value == $value ? 0 : (strlen($value) - strrpos($value, '.') - 1);
    }

    /**
     * Checks whether the given value is a mathematical constant (one of self::$constants) and returns its name
     * when it is.
     *
     * @param   float       $value      The number to check.
     * @param   int         $precision  The decimal precision of the check, 4 at minimum.
     * @return  string                  The name of the constant (one of the keys of self::$constants) or null if
     *                                  the given value is not a constant.
     */
    public static function detectConstant(float $value, int $precision = 6)
    {
        foreach (static::CONSTANTS as $name => $constant) {
            if (0 === bccomp($value, $constant, max($precision - 1, 4))) {
                return $name;
            }
        }

        return null;
    }
}
