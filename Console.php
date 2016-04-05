<?php namespace nyx\utils;

/**
 * Console
 *
 * @package     Nyx\Utils\Console
 * @version     0.0.1
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/console.html
 */
class Console
{
    /**
     * Escapes a (potentially unsafe) string to be used as a shell argument.
     *
     * @param   string  $argument   The argument to escape.
     * @return  string              The sanitized string.
     */
    public static function escape(string $argument) : string
    {
        if (!Platform::isWindows()) {
            return preg_match('{^[\w-]+$}', $argument) ? $argument : escapeshellarg($argument);
        }

        // Windows
        // Fix for PHP bug #49446 {@see https://bugs.php.net/bug.php?id=49446}
        // Fix for PHP bug #43784 {@see https://bugs.php.net/bug.php?id=43784}
        $result = '';

        foreach (preg_split('/([%"])/i', $argument, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if ('"' == $part) {
                $result .= '\\"';
            } elseif ('%' == $part) {
                $result .= '^%';
            } else {
                $result .= escapeshellarg($part);
            }
        }

        return $result;
    }
}
