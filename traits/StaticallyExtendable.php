<?php namespace nyx\utils\traits;

/**
 * StaticallyExtendable
 *
 * A StaticallyExtendable class is one that can be dynamically extended with additional static methods at
 * runtime.
 *
 * Do, however, note that this relies on the magic __callStatic() method which introduces considerable overhead
 * for each call, especially for very simple code. If possible, extend the exhibitors of this trait casually
 * to avoid the performance hit.
 *
 * @package     Nyx\Utils
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/index.html
 */
trait StaticallyExtendable
{
    /**
     * @var array   The registered extension methods.
     */
    private static $extensions = [];

    /**
     * Registers an additional static method.
     *
     * @param   string|array    $name       The name the callable should be made available as or an array of
     *                                      name => callable pairs.
     * @param   callable        $callable   The actual code that should be called.
     * @throws  \InvalidArgumentException   When the first parameter is not an array and the second is not given or
     *                                      when an array is given and its values are not callables.
     */
    public static function extend($name, callable $callable = null)
    {
        if (!is_array($name)) {
            if (null === $callable) {
                throw new \InvalidArgumentException("A callable must be given as second parameter if the first is not an array.");
            }

            self::$extensions[$name] = $callable;
            return;
        }

        foreach ($name as $method => $callable) {
            if (!is_callable($callable)) {
                throw new \InvalidArgumentException("The values of an array passed to the extend() method must be callables.");
            }

            self::$extensions[$method] = $callable;
        }
    }

    /**
     * Dynamically handles calls to the extended methods.
     *
     * @param   string  $method             The name of the method being called.
     * @param   array   $parameters         The parameters to call the method with.
     * @return  mixed
     * @throws  \BadMethodCallException     When no extension method with the given name exists.
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (isset(self::$extensions[$method])) {
            return call_user_func_array(self::$extensions[$method], $parameters);
        }

        throw new \BadMethodCallException("The method [$method] does not exist.");
    }
}
