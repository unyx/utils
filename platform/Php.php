<?php namespace nyx\utils\platform;

// Internal dependencies
use nyx\utils;

/**
 * PHP
 *
 * Utils for introspecting information about PHP itself.
 *
 * @package     Nyx\Utils\Platform
 * @version     0.0.1
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/platform.html
 * @todo        Bitwise checks for combined phpinfo() sections when parsing information.
 */
class Php
{
    /**
     * The traits of the Php class.
     */
    use utils\traits\StaticallyExtendable;

    /**
     * @var bool    Whether PHP has been compiled with the "--enable-sigchild" flag.
     */
    private static $flags = [];

    /**
     * @var array   An array containing the output of phpinfo() calls, grouped together by the INFO_ constants
     *              used to retrieve them.
     */
    private static $info = [];

    /**
     * Checks whether PHP has been compiled with the given flag.
     *
     * @param   string  $flag   The name of the flag to check. The two initial hyphens can be omitted.
     * @return  bool            True when PHP has been compiled with the given flag, false otherwise.
     */
    public static function hasFlag(string $flag) : bool
    {
        // Standardize the flag name - remove starting hyphens.
        $flag = ltrim($flag, '-');

        // Return the check right away if it's already cached.
        if (isset(static::$flags[$flag])) {
            return static::$flags[$flag];
        }

        // Grab the output of phpinfo(). If INFO_ALL is already available, we will just parse it instead of
        // fetching INFO_GENERAL specifically for our case. Then we are simply going to check if the --string
        // appears in the info.
        $result = strpos(static::$info[INFO_ALL] ?? static::getInfo(INFO_GENERAL), '--'.$flag);

        // Cache the result and return it.
        return static::$flags[$flag] = false !== $result;
    }

    /**
     * Fetches, caches and returns the output of phpinfo().
     *
     * @param   int     $for    One of the INFO_* constants {@see http://php.net/manual/en/function.phpinfo.php}.
     * @return  string          The raw output of phpinfo.
     */
    public static function getInfo(int $for = INFO_ALL) : string
    {
        // Return the data right away if we've already cached the given section.
        if (isset(static::$info[$for])) {
            return static::$info[$for];
        }

        ob_start();
        phpinfo($for);

        return static::$info[$for] = ob_get_clean();
    }
}
