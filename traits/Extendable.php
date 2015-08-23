<?php namespace nyx\utils\traits;

/**
 * Extendable
 *
 * An Extendable object is one that can be dynamically extended with additional methods during runtime.
 *
 * If you extend the object with Closures instead of other callables, remember that you can also bind them
 * to the scope ($this) of the object you're extending, giving you easier access to it. While it is possible
 * to automatically invoke extending Closures in this scope, the decision about the scope is by design left
 * to the extension, also to provide consistency with other types of callables for which this is not possible.
 *
 * Do, however, note that this relies on the magic __call() method which introduces considerable overhead
 * for each call, especially for very simple code. If possible, extend the exhibitors of this trait casually
 * to avoid the performance hit.
 *
 * @package     Nyx\Utils
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/index.html
 */
trait Extendable
{
    /**
     * @var array   The registered methods.
     */
    private $extensions = [];

    /**
     * Registers an additional method.
     *
     * @param   string|array    $name       The name the name should be made available as or an array of
     *                                      name => callable pairs.
     * @param   callable        $callable   The actual code that should be called.
     * @throws  \InvalidArgumentException   When the first parameter is not an array and the second is not given or
     *                                      when an array is given and its values are not callables.
     */
    public function extend($name, callable $callable = null)
    {
        if (!is_array($name)) {
            if (null === $callable) {
                throw new \InvalidArgumentException("A callable must be given as second parameter if the first is not an array.");
            }

            $this->extensions[$name] = $callable;
            return;
        }

        foreach ($name as $method => $callable) {
            if (!is_callable($callable)) {
                throw new \InvalidArgumentException("The values of an array passed to the extend() method must be callables.");
            }

            $this->extensions[$method] = $callable;
        }
    }

    /**
     * Dynamically handles calls to the to the extended methods.
     *
     * @param   string  $method             The name of the method being called.
     * @param   array   $parameters         The parameters to call the method with.
     * @return  mixed
     * @throws  \BadMethodCallException     When no extension method with the given name exists.
     */
    public function __call($method, array $parameters)
    {
        if (isset($this->extensions[$method])) {
            return call_user_func($this->extensions[$method], ...$parameters);
        }

        throw new \BadMethodCallException("The method [$method] does not exist.");
    }
}
