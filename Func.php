<?php namespace nyx\utils;

/**
 * Func
 *
 * Utilities related to functions/methods/callables/closures..
 *
 * @package     Nyx\Utils\Func
 * @version     0.0.4
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/func.html
 * @todo        Return a custom value instead of null when a Closure prevents the invocation of a callable?
 */
class Func
{
    /**
     * The traits of the Func class.
     */
    use traits\StaticallyExtendable;

    /**
     * Constant used to pass through the arguments from a main wrapped callable to a related callable. Concrete
     * behaviour depends on the method being used, therefore check the method docs for actual usage info.
     */
    const PASSTHROUGH = "__passthrough__";

    /**
     * @var array   The in-memory results cache for self::memoize().
     */
    private static $memory = [];

    /**
     * Creates a Closure that, when called, ensures the wrapped callable only gets invoked after being called
     * at least $times times.
     *
     * @param   int         $times      The number of times the function should get called before being executed.
     * @param   callable    $callback   The callable to wrap.
     * @return  \Closure                The wrapper.
     */
    public static function after($times = 1, callable $callback)
    {
        return function (...$args) use ($callback, $times) {
            static $count = 0;

            if (++$count >= $times) {
                return call_user_func($callback, ...$args);
            }
        };
    }

    /**
     * Returns a Closure which will return the subsequent given value (argument to this method) on each call.
     * While this is primarily a utility for strings, it can be used with any type of values.
     *
     * When the Closure gets called with false as its argument, it will return the current internal value without
     * alternating the next time (ie. the same value will be returned with the next call).
     *
     * @param   mixed       ...$between    Two or more values to alternate between, given as separate arguments.
     * @return  \Closure
     */
    public static function alternator(...$between) : \Closure
    {
        return function ($next = true) use ($between) {
            static $i = 0;
            return $between[($next ? $i++ : $i) % count($between)];
        };
    }

    /**
     * Generates a hash (signature) for a given callable with the given arguments.
     *
     * @param   callable    $callable   The callable to hash.
     * @param   array       $args       The arguments to hash.
     * @return  string                  The hash.
     */
    public static function hash(callable $callable, array $args)
    {
        if ($callable instanceof \Closure) {
            $callable = var_export($callable, true);
        } elseif (is_array($callable)) {
            $callable = is_string($callable[0]) ? implode('::', $callable) : $callable[1];
        }

        return md5(join('_', [$callable, var_export($args, true)]));
    }

    /**
     * Creates a Closure that memoizes the result of the wrapped callable.
     *
     * @param   callable            $callback   The callable to wrap.
     * @param   callable|string     $key        When a callable is given, it will be invoked with 2 arguments: the
     *                                          wrapped callable and the arguments for the call. Its return value
     *                                          will be cast to a string and used as the cache key for the result.
     *                                          When any other value except for null is given, it will be cast to
     *                                          a string and used as the cache key. When not given, self::hash()
     *                                          will be used to create a hash of the call signature to use as key.
     * @return  \Closure                        The wrapper.
     * @todo                                    Expose the cached result inside the callable (limited to the same
     *                                          callable?)
     * @todo                                    Use the first argument for the callable as cache key if not given?
     * @todo                                    Optionally hook into storage\cache for self::memoize() instead
     *                                          of using an internal array?
     */
    public static function memoize(callable $callback, $key = null)
    {
        return function (...$args) use ($callback, $key) {

            // Determine which cache key to use.
            $key = null === $key
                ? static::hash($callback, $args)
                : (string) (is_callable($key) ? $key($callback, $args) : $key);

            // If we don't already have a hit for the cache key, populate it with the result of invocation.
            if (!array_key_exists($key, static::$memory)) {
                static::$memory[$key] = call_user_func($callback, ...$args);
            }

            return static::$memory[$key];
        };
    }

    /**
     * Creates a Closure that, when called, ensures the wrapped callable only gets invoked once.
     *
     * @param   callable    $callback   The callable to wrap.
     * @return  \Closure                The wrapper.
     */
    public static function once(callable $callback)
    {
        return static::only(1, $callback);
    }

    /**
     * Creates a Closure that, when called, ensures the wrapped callable may only get invoked $times times at most.
     *
     * @param   int         $times      The number of times the function may get invoked at most.
     * @param   callable    $callback   The callable to wrap.
     * @return  \Closure                The wrapper.
     */
    public static function only($times = 1, callable $callback)
    {
        return function (...$args) use ($callback, $times) {
            // Keep track of how many times the Closure was already called.
            static $called = 0;

            // Invoke the callback when we didn't hit our limit yet.
            if ($times >= ++$called) {
                return call_user_func($callback, ...$args);
            }
        };
    }

    /**
     * Creates a Closure that, when called, invokes the wrapped callable with any additional partial arguments
     * prepended to those provided to the new Closure.
     *
     * @param   callable    $callback           The callable to wrap.
     * @param   mixed       ...$prependedArgs   The arguments to prepend to the callback.
     * @return  \Closure                        The wrapper.
     */
    public static function partial(callable $callback, ...$prependedArgs)
    {
        return function (...$args) use ($callback, $prependedArgs) {
            return call_user_func($callback, ...$prependedArgs, ...$args);
        };
    }

    /**
     * Creates a Closure that, when called, invokes the wrapped callable with any additional partial arguments
     * appended to those provided to the new Closure.
     *
     * @param   callable    $callback           The callable to wrap.
     * @param   mixed       ...$appendedArgs    The arguments to append to the callback.
     * @return  \Closure                        The wrapper.
     */
    public static function partialRight(callable $callback, ...$appendedArgs)
    {
        return function (...$args) use ($callback, $appendedArgs) {
            return call_user_func($callback, ...$args, ...$appendedArgs);
        };
    }

    /**
     * Wraps a callable in a Closure to ensure the callable can only be executed once every $wait milliseconds.
     * Subsequent calls to the wrapped callable before the wait time allows another execution run will return the
     * result of the previous execution.
     *
     * @param   callable    $callback   The callable to wrap.
     * @param   int         $wait       The time in milliseconds that must pass before the callable can be executed
     *                                  again.
     * @return  \Closure                The wrapper.
     */
    public static function throttle(callable $callback, $wait = null)
    {
        return function (...$args) use ($callback, $wait) {
            static $timer  = 0;
            static $result = null;

            if ($timer <= $microtime = microtime(true)) {
                // Update the internal timer.
                $timer = $microtime + $wait / 1000;

                // And call the actual callable.
                $result = call_user_func($callback, ...$args);
            }

            return $result;
        };
    }

    /**
     * Wraps a callable in a Closure to only allow it to be invoked when the $test callable returns a falsy value.
     *
     * @param   callable    $test       The truth test.
     * @param   callable    $callback   The callable to wrap.
     * @param   mixed       $testArgs   The arguments to pass to the truth test. Will be cast to an array. When
     *                                  self::PASSTHROUGH gets passed, the same arguments passed to the callable
     *                                  will also be passed to the truth test.
     * @return  \Closure                The wrapper.
     */
    public static function unless(callable $test, callable $callback, $testArgs = null)
    {
        return static::whenInternal($test, $callback, $testArgs, false);
    }

    /**
     * Wraps a callable in a Closure to only allow it to be invoked when the $test callable returns a truthy value.
     *
     * @param   callable    $test       The truth test.
     * @param   callable    $callback   The callable to wrap.
     * @param   mixed       $testArgs   The arguments to pass to the truth test. Will be cast to an array. When
     *                                  self::PASSTHROUGH gets passed, the same arguments passed to the callable
     *                                  will also be passed to the truth test.
     * @return  \Closure                The wrapper.
     */
    public static function when(callable $test, callable $callback, $testArgs = null)
    {
        return static::whenInternal($test, $callback, $testArgs, true);
    }

    /**
     * Creates a Closure that provides the given value to the wrapper as its first argument. Additional arguments
     * provided to the created Closure will be appended to the value given here.
     *
     * @param   mixed       $value      The value to wrap and pass to the wrapper on each invocation.
     * @param   callable    $wrapper    The wrapper.
     * @return  \Closure                The created Closure.
     */
    public static function wrap($value, callable $wrapper)
    {
        return function (...$args) use ($value, $wrapper) {
            return call_user_func($wrapper, $value, ...$args);
        };
    }

    /**
     * Wraps a callable in a Closure to only allow it to be invoked when the $test callable returns a boolean
     * true or false. Used internally by self::unless() and self::when() to reduce some code duplication.
     *
     * @param   callable    $test       The truth test.
     * @param   callable    $callback   The callable to wrap.
     * @param   mixed       $testArgs   The arguments to pass to the truth test. Will be cast to an array. When
     *                                  self::PASSTHROUGH gets passed, the same arguments passed to the callable
     *                                  will also be passed to the truth test.
     * @param   bool        $expect     The boolean to expect to allow the callable to be invoked.
     * @return  \Closure                The wrapper.
     */
    protected static function whenInternal(callable $test, callable $callback, $testArgs = null, $expect = true)
    {
        return function (...$callbackArgs) use ($callback, $test, $testArgs, $expect) {
            $testArgs = $testArgs === self::PASSTHROUGH ? $callbackArgs : (array) $testArgs;

            // Loose comparison on purpose to make this more elastic.
            if ($expect == call_user_func($test, ...$testArgs)) {
                return call_user_func($callback, ...$callbackArgs);
            }
        };
    }
}
