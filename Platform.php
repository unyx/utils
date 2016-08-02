<?php namespace nyx\utils;

/**
 * Platform
 *
 * Utilities related to the operating system PHP is running on, retrieving information about and executing
 * system processes etc.
 *
 * Requires:
 * - Function: shell_exec() (getting the shells available on this system)
 * - Function: exec() (checking whether TTY is available on this system)
 *
 * @package     Nyx\Utils\Platform
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/platform.html
 */
class Platform
{
    /**
     * The traits of the Platform class.
     */
    use traits\StaticallyExtendable;

    /**
     * Platform type constants.
     */
    const TYPE_UNIX    = 1;
    const TYPE_WINDOWS = 2;
    const TYPE_BSD     = 3;
    const TYPE_CYGWIN  = 4;
    const TYPE_DARWIN  = 5;

    /**
     * @var int     The platform PHP is running on.
     */
    private static $type;

    /**
     * @var array   An array of shell names and the paths to their binaries once populated or false when PHP is
     *              running on a system that does not support them (Windows).
     */
    private static $shells;

    /**
     * @var bool    Whether this platform has the 'stty' binary (always false on Windows).
     */
    private static $hasStty;

    /**
     * Guesses and returns the platform PHP is running on. If it can't be determined, the default
     * of self::TYPE_UNIX will be returned.
     *
     * @return  int     One of the platform TYPE_ constants defined in this class.
     */
    public static function getType() : int
    {
        // Return the cached result if it's already available.
        if (null !== static::$type) {
            return static::$type;
        }

        $os = strtolower(php_uname("s"));

        // Check in order of likeliness.
        if (false !== strpos($os, 'unix')) {
            return static::$type = self::TYPE_UNIX;
        }

        if (0 === strpos($os, 'win')) {
            return static::$type = self::TYPE_WINDOWS;
        }

        if (false !== strpos($os, 'bsd')) {
            return static::$type = self::TYPE_BSD;
        }

        if (false !== strpos($os, 'darwin')) {
            return static::$type = self::TYPE_DARWIN;
        }

        if (false !== strpos($os, 'cygwin')) {
            return static::$type = self::TYPE_CYGWIN;
        }

        // Use the default otherwise.
        return static::$type = self::TYPE_UNIX;
    }

    /**
     * Checks whether PHP is running on a Unix platform
     *
     * @return  bool    True when PHP is running on a Unix platform, false otherwise.
     */
    public static function isUnix() : bool
    {
        // Return the cached result if it's already available.
        if (null !== static::$type) {
            return static::$type === self::TYPE_UNIX;
        }

        return static::getType() === self::TYPE_UNIX;
    }

    /**
     * Checks whether PHP is running on a Windows platform
     *
     * @return  bool    True when PHP is running on a Windows platform, false otherwise.
     */
    public static function isWindows() : bool
    {
        // Return the cached result if it's already available.
        if (null !== static::$type) {
            return static::$type === self::TYPE_WINDOWS;
        }

        return static::getType() === self::TYPE_WINDOWS;
    }

    /**
     * Checks whether PHP is running on a BSD platform
     *
     * @return  bool    True when PHP is running on a BSD platform, false otherwise.
     */
    public static function isBsd() : bool
    {
        // Return the cached result if it's already available.
        if (null !== static::$type) {
            return static::$type === self::TYPE_BSD;
        }

        return static::getType() === self::TYPE_BSD;
    }

    /**
     * Checks whether PHP is running on a Darwin platform
     *
     * @return  bool    True when PHP is running on a Darwin platform, false otherwise.
     */
    public static function isDarwin() : bool
    {
        // Return the cached result if it's already available.
        if (null !== static::$type) {
            return static::$type === self::TYPE_DARWIN;
        }

        return static::getType() === self::TYPE_DARWIN;
    }

    /**
     * Checks whether PHP is running on a Cygwin platform
     *
     * @return  bool    True when PHP is running on a Cygwin platform, false otherwise.
     */
    public static function isCygwin() : bool
    {
        // Return the cached result if it's already available.
        if (null !== static::$type) {
            return static::$type === self::TYPE_CYGWIN;
        }

        return static::getType() === self::TYPE_CYGWIN;
    }

    /**
     * Returns the path to the given shell's binary or null when it is not available.
     *
     * @param   string          $name
     * @return  string|null
     */
    public static function getShell(string $name)
    {
        return static::getShells()[$name] ?? null;
    }

    /**
     * Checks whether a shell of the given name is available in the system.
     *
     * @param   string  $name
     * @return  bool
     */
    public static function hasShell(string $name) : bool
    {
        return isset(static::getShells()[$name]);
    }

    /**
     * Returns an array of shell names and the paths to their binaries once populated. Will return an empty
     * array on unsupported platforms (Windows).
     *
     * @return  array
     */
    public static function getShells() : array
    {
        // Return the cached result if it's already available.
        if (static::$shells !== null) {
            return static::$shells;
        }

        // Ensure this method will be ran once at most.
        static::$shells = [];

        // Can't easily check on Windows even if Cygwin or the likes are available.
        if (static::isWindows()) {
            return static::$shells;
        }

        if (file_exists($file = '/etc/shells')) {
            $cat = trim(shell_exec('cat '.$file.' 2> /dev/null'));

            foreach (explode(PHP_EOL, $cat) as $path) {
                // Ignore this line if it doesn't begin with a filepath.
                if ($path[0] != '/') {
                    continue;
                }

                $name = substr($path, strrpos($path, '/') + 1);

                static::$shells[$name] = $path;
            }
        }

        return static::$shells;
    }

    /**
     * Checks whether this platform has the 'stty' binary.
     *
     * @return  bool    True when 'stty' is available on this platform, false otherwise (always false on Windows).
     */
    public static function hasStty() : bool
    {
        // Return the cached result if it's already available.
        if (static::$hasStty !== null) {
            return static::$hasStty;
        }

        // Definitely no Stty on Windows.
        if (static::isWindows()) {
            return static::$hasStty = false;
        }

        // Run a simple exec() call and check whether it returned with an error code.
        exec('/usr/bin/env stty', $output, $exitCode);

        return static::$hasStty = $exitCode === 0;
    }
}
