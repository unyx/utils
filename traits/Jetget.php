<?php namespace nyx\utils\traits;

/**
 * Jetget
 *
 * @package     Nyx\Utils
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/index.html
 */
trait Jetget
{
    /**
     * Magic getter. This will take care of retrieving all properties in the private scope from the outside
     * by either calling a specific getter if it exists (for the property "name", the getter would be called
     * "getName" and so on) or directly getting the value.
     *
     * @param   string  $name
     * @return  mixed
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            // First let's see if we've got a specific method for this.
            if (method_exists($this, $method = 'get'.ucfirst($name))) {
                return $this->$method();
            }

            // Otherwise let's just run some generic automagic.
            return $this->$name;
        }

        return null;
    }
}
