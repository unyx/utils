<?php namespace nyx\utils;

// External dependencies
use nyx\core;

/**
 * Unit
 *
 * Utilities related to unit conversions within and between types.
 *
 * @package     Nyx\Utils\Units
 * @version     0.0.1
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/units.html
 */
class Unit
{
    /**
     * The traits of the Unit class.
     */
    use traits\StaticallyExtendable;

    /**
     * Converts a string that may contain size units (e.g. "64m", "128k") into an integer of bytes.
     *
     * @param   string  $string     The string to convert to an integer of bytes.
     * @return  int                 The number of bytes.
     */
    public static function sizeStringToBytes($string)
    {
        // '-1' happens more often than not to denote an "unlimited" or "not defined" size, so let's reduce some
        // overhead of doing the conversion if possible.
        if ('-1' === $string) {
            return -1;
        }

        $max = strtolower(ltrim($string, '+'));

        if (0 === strpos($max, '0x')) {
            $max = intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = intval($max);
        }

        switch (substr($string, -1)) {
            case 't': $max *= 1024;
            case 'g': $max *= 1024;
            case 'm': $max *= 1024;
            case 'k': $max *= 1024;
        }

        return $max;
    }
}
