<?php namespace nyx\utils;

/**
 * Arr
 *
 * The Arr class provides a few helper methods to make dealing with arrays easier.
 *
 * All methods which work with string delimited keys accept a string delimiter. If none is given (ie. null is passed),
 * the default delimiter (dot) set statically in this class will be used.
 *
 * Some code in this class can be simplified and some duplication could be avoided but it is laid out so that the
 * most common use cases are checked for first with performance being the priority.
 *
 * Some methods have aliases. To avoid the overhead please use the base methods, not the aliases. The methods which
 * have aliases are documented as such and each alias directly points to the base method.
 *
 * Note: This class is based on Laravel, FuelPHP, Lo-dash and a few others, but certain methods which appear in
 * those are not included since they would add overhead we consider 'not worth it' and don't want to encourage
 * the use thereof:
 *
 *   - Arr::each()                      -> use array_map() instead.
 *   - Arr::filter(), Arr::reject()     -> use array_filter() instead.
 *   - Arr::range()                     -> use range() instead.
 *   - Arr::repeat()                    -> use array_fill() instead.
 *   - Arr::search()                    -> use array_search() instead.
 *   - Arr::shuffle()                   -> use shuffle() instead.
 *   - Arr::size()                      -> use count() instead.
 *
 * @package     Nyx\Utils
 * @version     0.1.0
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/index.html
 * @todo        Arr::sort() and Arr:sortBy() (add sortBy() to core\traits\Collection as well).
 * @todo        Add ArrayObject support? How? Just strip the array type hints so as to not add overhead with checks?
 */
class Arr
{
    /**
     * The traits of the Arr class.
     */
    use traits\StaticallyExtendable;

    /**
     * @var string  The delimiter to use to separate array dimensions.
     */
    public static $delimiter = '.';

    /**
     * Adds an element to an array but only if it does not yet exist.
     *
     * Note: Null as value of an item is considered a non-existing item for the purposes
     *       of this method.
     *
     * @param   array   $array      The array to which the element should be added.
     * @param   string  $key        The key at which the value should be added.
     * @param   mixed   $value      The value of the element.
     * @param   string  $delimiter  The delimiter to use when exploding the key into parts.
     */
    public static function add(array& $array, string $key, $value, string $delimiter = null)
    {
        if (null === static::get($array, $key, null, $delimiter)) {
            static::set($array, $key, $value, $delimiter);
        }
    }

    /**
     * Checks if all elements in the given array pass the given truth test.
     *
     * Aliases:
     *  - @see Arr::every()
     *
     * @param   array       $array      The array to traverse.
     * @param   callable    $callback   The truth test the elements should pass.
     * @param   bool        $strict     Whether strict equality matches should be performed on the results.
     * @return  bool                    True when the elements passed the truth test, false otherwise.
     */
    public static function all(array $array, callable $callback, bool $strict = true) : bool
    {
        // Map the array and then search for a 'false' boolean. If none is found, we assume all elements passed
        // the test.
        return false === array_search(false, array_map($callback, $array), $strict);
    }

    /**
     * Checks if any of the elements in the given array passes the given truth test.
     *
     * Aliases:
     *  - @see Arr::some()
     *
     * @param   array       $array      The array to traverse.
     * @param   callable    $callback   The truth test the elements should pass.
     * @param   bool        $strict     Whether strict equality matches should be performed on the results.
     * @return  bool                    True when at least on the the elements passed the truth test, false
     *                                  otherwise.
     */
    public static function any(array $array, callable $callback, bool $strict = true) : bool
    {
        // Map the array and then search for a 'true' boolean. If at least one is found, we assume at least one
        // element passed the test.
        return false !== array_search(true, array_map($callback, $array), $strict);
    }

    /**
     * Returns the average value of the given array.
     *
     * @param   array   $array      The array to traverse.
     * @param   int     $decimals   The number of decimals to return.
     * @return  float               The average value.
     */
    public static function average(array $array, int $decimals = 0) : float
    {
        return round((array_sum($array) / count($array)), $decimals);
    }

    /**
     * Removes all elements containing falsy values from the given array. The keys are preserved.
     *
     * See {@link http://php.net/manual/en/language.types.boolean.php} for information on which values evaluate
     * to false.
     *
     * @param   array   $array  The array to traverse.
     * @return  array           The resulting array.
     */
    public static function clean(array $array) : array
    {
        return array_filter($array, function ($value) {
            return (bool) $value;
        });
    }

    /**
     * Collapses an array of arrays into a single array.
     *
     * @param   array   $array  The array to collapse.
     * @return  array           The resulting array.
     */
    public static function collapse(array $array) : array
    {
        $results = [];

        foreach ($array as $item) {
            $results = array_merge($results, $item);
        }

        return $results;
    }

    /**
     * Checks if the given value is contained within the given array. Equivalent of a recursive in_array. When you
     * are sure you are dealing with a 1-dimensional array, use in_array instead to avoid the overhead.
     *
     * @param   array   $haystack   The array to search in.
     * @param   mixed   $needle     The value to search for.
     * @param   bool    $strict     Whether strict equality matches should be performed on the values.
     * @return  bool                True when the value was found in the array, false otherwise.
     */
    public static function contains(array $haystack, $needle, bool $strict = true)
    {
        foreach ($haystack as $value) {
            if ((!$strict and $needle == $value) or $needle === $value) {
                return true;
            }

            if (is_array($value) and static::contains($needle, $value, $strict)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flattens a multi-dimensional array using the given delimiter.
     *
     * @param   array   $array      The initial array.
     * @param   string  $prepend    A string that should be prepended to the keys.
     * @param   string  $delimiter  The delimiter to use when exploding the key into parts.
     * @return  array               The resulting array.
     */
    public static function delimit(array $array, string $prepend = '', string $delimiter = null)
    {
        // Results holder.
        $results = [];

        // Which string delimiter should we use?
        if (null === $delimiter) {
            $delimiter = static::$delimiter;
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, static::delimit($value, $prepend.$key.$delimiter));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Alias for @see Arr::find()
     */
    public static function detect(array $array, callable $callback, $default = null)
    {
        return static::find($array, $callback, $default);
    }

    /**
     * Divides an array into two arrays - the first containing the keys, the second containing the values.
     *
     * @param   array   $array  The initial array.
     * @return  array           The resulting array.
     */
    public static function divide(array $array)
    {
        return [array_keys($array), array_values($array)];
    }

    /**
     * Alias for @see Arr::all()
     */
    public static function every(array $array, callable $callback, bool $strict = true)
    {
        return static::all($array, $callback, $strict);
    }

    /**
     * Returns a subset of the given array, containing all keys except for the ones specified.
     *
     * @param   array   $array  The initial array.
     * @param   array   $keys   An array of keys (the keys are expected to be values of this array).
     * @return  array
     */

    public static function except(array $array, array $keys)
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Fetches a flattened array of an element nested in the initial array.
     *
     * @param   array   $array      The initial array.
     * @param   string  $key        The string delimited key.
     * @param   string  $delimiter  The delimiter to use when exploding the key into parts.
     * @return  array               The resulting array.
     */
    public static function fetch(array $array, string $key, string $delimiter = null) : array
    {
        // Results holder.
        $results = [];

        // Which string delimiter should we use?
        if (null === $delimiter) {
            $delimiter = static::$delimiter;
        }

        foreach (explode($delimiter, $key) as $segment) {
            $results = [];

            foreach ($array as $value) {
                $value = (array) $value;

                $results[] = $value[$segment];
            }

            $array = array_values($results);
        }

        return array_values($results);
    }

    /**
     * Returns the first value which passes the given truth test.
     *
     * Aliases:
     *  - @see Arr::detect()
     *
     * @param   array       $array      The array to traverse.
     * @param   callable    $callback   The truth test the value should pass.
     * @param   mixed       $default    The default value to be returned if none of the elements passes the test.
     * @return  mixed
     */
    public static function find(array $array, callable $callback, $default = null)
    {
        foreach ($array as $key => $value) {
            if (call_user_func($callback, $key, $value)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Returns the first element of the array, the first $callback elements of the array when $callback is a number,
     * or the first element which passes the given truth test when the $callback is a callable.
     *
     * Aliases:
     *  - @see Arr::head()
     *  - @see Arr::take()
     *
     * @param   array               $array      The array to traverse.
     * @param   callable|int|bool   $callback   The truth test the value should pass or an integer denoting how many
     *                                          of the initial elements of the array should be returned.
     *                                          When a falsy value is given, the method will return the first
     *                                          element of the array.
     * @param   mixed               $default    The default value to be returned if none of the elements passes
     *                                          the test or the array is empty.
     * @return  mixed
     */
    public static function first(array $array, $callback = false, $default = null)
    {
        // Avoid some overhead at this point already if possible.
        if (empty($array)) {
            return $default;
        }

        // Most common use case - simply return the first value of the array.
        if (!$callback) {
            return reset($array);
        }

        // With a callable given, return the first value which passes the given truth test.
        if (is_callable($callback)) {
            return static::find($array, $callback, $default);
        }

        // Return only the first element when the callback equals 1, otherwise return the initial $callback elements.
        return (1 === $callback = abs((int) $callback)) ? reset($array) : array_slice($array, 0, $callback);
    }

    /**
     * Flattens a multi-dimensional array.
     *
     * @param   array   $array  The initial array.
     * @return  array           The flattened array.
     */
    public static function flatten(array $array)
    {
        $results = [];

        array_walk_recursive($array, function ($x) use (&$results) {
            $results[] = $x;
        });

        return $results;
    }

    /**
     * Returns a string delimited key from an array, with a default value if the given key does not exist. If null
     * is given instead of a key, the whole initial array will be returned.
     *
     * @param   array   $array      The array to search in.
     * @param   string  $key        The string delimited key.
     * @param   mixed   $default    The default value.
     * @param   string  $delimiter  The delimiter to use when exploding the key into parts.
     * @return  mixed
     */
    public static function get(array $array, string $key = null, $default = null, string $delimiter = null)
    {
        // Make loops easier for the end-user - return the initial array if the key is null instead of forcing
        // a valid value.
        if (null === $key) {
            return $array;
        }

        // More often than not we will simply be looking for a non-nested item it a one-dimensional array
        // so let's avoid some overhead if possible.
        // Note: This also makes it possible to have keys including the specified delimiter ("some.array.key")
        // in the *first* dimension of the given array. However, nested items are expected to follow the convention
        // of the delimiter specifying the dimension.
        if (isset($array[$key])) {
            return $array[$key];
        }

        // Which string delimiter should we use?
        if (null === $delimiter) {
            $delimiter = static::$delimiter;
        }

        // One dimension at a time.
        foreach (explode($delimiter, $key) as $segment) {
            if (!array_key_exists($segment, $array)) {
                // @todo Invoke closures right away or return them like currently? Ie. Laravel's value() helper function.
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Check if the given string delimited key exists in the array.
     *
     * @param   array   $array      The array to search in.
     * @param   string  $key        The string delimited key.
     * @param   string  $delimiter  The delimiter to use when exploding the key into parts.
     * @return  bool                True when the given key exists, false otherwise.
     */
    public static function has(array $array, string $key, string $delimiter = null)
    {
        // Which string delimiter should we use?
        if (null === $delimiter) {
            $delimiter = static::$delimiter;
        }

        foreach (explode($delimiter, $key) as $segment) {
            if (!is_array($array) or !array_key_exists($segment, $array)) {
                return false;
            }

            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Alias for {@see static::first()}
     */
    public static function head(array $array, $callback = false, $default = null)
    {
        return static::first($array, $callback, $default);
    }

    /**
     * Returns all but the last value of the given array.
     *
     * If a callable is passed, elements at the end of the array are excluded from the result as long as the
     * callback returns a truthy value. If a number is passed, the last n values are excluded from the result.
     *
     * @param   array           $array      The array to traverse.
     * @param   callable|int    $callback   The truth test the value should pass or an integer denoting how many
     *                                      of the final elements of the array should be excluded. The count is
     *                                      1-indexed, ie. if you want to exclude the last 2 elements, pass 2.
     * @param   mixed           $default    The default value to be returned if none of the elements passes the test.
     *                                      Only useful when $callback is a callable.
     * @return  mixed
     */
    public static function initial(array $array, $callback, $default = null)
    {
        // When given a callable, keep counting as long as the callable returns a truthy value.
        if (is_callable($callback)) {
            $i = 0;

            foreach (array_reverse($array) as $key => $value) {
                if (!call_user_func($callback, $key, $value)) {
                    break;
                }

                $i++;
            }

            // If we didn't get at least a single truthy value, return the default.
            if ($i === 0) {
                return $default;
            }

            // Otherwise we're just gonna overwrite the $callback and proceed as if it were an integer in the
            // first place.
            $callback = $i;
        }

        // At this point we need a positive integer, 1 at minimum.
        $callback = (int) $callback;
        $callback = !$callback ? 1 : abs($callback);

        return array_slice($array, 0, count($array) - $callback);
    }

    /**
     * Checks whether the given array is an associative array.
     *
     * @param   array   $array  The array to check.
     * @return  bool            True when the array is associative, false otherwise.
     */
    public static function isAssociative(array $array) : bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Checks whether the given array is a multidimensional array.
     *
     * @param   array   $array  The array to check.
     * @return  bool            True when the array has multiple dimensions, false otherwise.
     */
    public static function isMultidimensional(array $array) : bool
    {
        return count($array) !== count($array, COUNT_RECURSIVE);
    }

    /**
     * Returns the last element of the array, the final $callback elements of the array when $callback is a number,
     * or the last element which passes the given truth test when the $callback is a callable.
     *
     * @param   array               $array      The array to traverse.
     * @param   callable|int|bool   $callback   The truth test the value should pass or an integer denoting how many
     *                                          of the final elements of the array should be returned.
     *                                          When a falsy value is given, the method will return the last
     *                                          element of the array.
     * @param   mixed               $default    The default value to be returned if none of the elements passes
     *                                          the test or the array is empty.
     * @return  mixed
     */
    public static function last(array $array, $callback = false, $default = null)
    {
        // Avoid some overhead at this point already if possible.
        if (empty($array)) {
            return $default;
        }

        // Most common use case - simply return the last value of the array.
        if (!$callback) {
            return end($array);
        }

        // With a callable given, return the last value which passes the given truth test.
        if (is_callable($callback)) {
            foreach (array_reverse($array) as $key => $value) {
                if (call_user_func($callback, $key, $value)) {
                    return $value;
                }
            }

            return $default;
        }

        // Return only the last element when the callback equals 1, otherwise return the final $callback elements.
        return (1 === $callback = -1 * abs((int) $callback)) ? end($array) : array_slice($array, $callback);
    }

    /**
     * Returns the biggest value from the given array.
     *
     * @param   array   $array  The array to traverse.
     * @return  mixed           The resulting value.
     */
    public static function max(array $array)
    {
        // Avoid some overhead at this point already if possible.
        if (empty($array)) {
            return null;
        }

        // Sort in a descending order.
        arsort($array);

        // Return the first element of the sorted array.
        return reset($array);
    }

    /**
     * Merges 2 or more arrays recursively. Differs in two important aspects from array_merge_recursive():
     *   - In case of 2 different values, when they are not arrays, the latter one overwrites the earlier instead
     *     of merging them into an array;
     *   - Non-conflicting numeric keys are left unchanged. In case of a conflict, the new value will be pushed
     *     to the resulting array.
     *
     * @param   array   $array      The initial array.
     * @param   array   ...$with    One or more (ie. separate arguments) arrays to merge in.
     * @return  array               The resulting merged array.
     */
    public static function merge(array $array, array ...$with) : array
    {
        foreach ($with as $arr) {
            foreach ($arr as $key => $value) {
                // Append numeric keys.
                if (is_int($key)) {
                    array_key_exists($key, $array) ? array_push($array, $value) : $array[$key] = $value;
                }
                // Merge multi-dimensional arrays recursively..
                elseif (is_array($value) and array_key_exists($key, $array) and is_array($array[$key])) {
                    $array[$key] = static::merge($array[$key], $value);
                } else {
                    $array[$key] = $value;
                }
            }
        }

        return $array;
    }

    /**
     * Returns the smallest value from the given array.
     *
     * @param   array   $array  The array to traverse.
     * @return  mixed           The resulting value.
     */
    public static function min(array $array)
    {
        // Avoid some overhead at this point already if possible.
        if (empty($array)) {
            return null;
        }

        // Sort in an ascending order.
        asort($array);

        // Return the first element of the sorted array.
        return reset($array);
    }

    /**
     * Returns a subset of the given array, containing only the specified keys.
     *
     * @param   array   $array  The initial array.
     * @param   array   $keys   An array of keys (the keys are expected to be values of this array).
     * @return  array
     */
    public static function only(array $array, array $keys) : array
    {
        return array_intersect_key($array, array_values($keys));
    }

    /**
     * Given an array containing other arrays or objects, this method will look for the value with the given
     * key/property of $value within them and return a new array containing all values of said key from the
     * initial array. Essentially like fetching a column from a classic database table.
     *
     * When the optional $key parameter is given, the resulting array will be indexed by the values corresponding
     * to the given $key.
     *
     * @param   array   $array  The array to search in.
     * @param   string  $value  The key of the value to look for.
     * @param   string  $key    The key of the value to index the resulting array by.
     * @return  array
     */
    public static function pluck(array $array, $value, string $key = null) : array
    {
        $results = [];

        foreach ($array as $item) {
            $curValue = is_object($item) ? $item->{$value} : $item[$value];

            // If the key given is null, the resulting array will contain numerically indexed keys.
            if (null === $key) {
                $results[] = $curValue;
            }
            // Otherwise we are going use the value of the given key and use it in the resulting array as key
            // for the value determined earlier.
            else {
                $results[is_object($item) ? $item->{$key} : $item[$key]] = $curValue;
            }
        }

        return $results;
    }

    /**
     * Returns the value for a string delimited key from an array and then removes it.
     *
     * @param   array   $array      The array to search in.
     * @param   string  $key        The string delimited key.
     * @param   string  $delimiter  The delimiter to use when exploding the key into parts.
     * @return  mixed
     */
    public static function pull(&$array, string $key, string $delimiter = null)
    {
        $value = static::get($array, $key, null, $delimiter);

        static::remove($array, $key, $delimiter);

        return $value;
    }

    /**
     * Returns a random value from the given array. If a number is given as the second argument, $number random
     * values will be returned.
     *
     * @param   array           $array  The array to search in.
     * @param   callable|int    $number The number of random values to return or a callable to return the first
     *                                  randomly shuffled element that passes the given truth test. When $number
     *                                  is falsy, only a single element will be returned.
     * @return  mixed
     */
    public static function random(array $array, $number = null)
    {
        // Avoid some errors if possible.
        if (empty($array)) {
            return null;
        }

        // Falsy values result in just a single random element being returned.
        if (!$number) {
            return $array[array_rand($array)];
        }

        shuffle($array);

        // Return the first $number elements of the randomly shuffled array.
        return static::first($array, $number);
    }

    /**
     * Removes a string delimited key from the given array.
     *
     * @param   array   $array      The array to search in.
     * @param   string  $key        The string delimited key.
     * @param   string  $delimiter  The delimiter to use when exploding the key into parts.
     */
    public static function remove(array& $array, string $key, string $delimiter = null)
    {
        // Which string delimiter should we use?
        if (null === $delimiter) {
            $delimiter = static::$delimiter;
        }

        // Explode the key according to that delimiter.
        $keys = explode($delimiter, $key);

        while ($key = array_shift($keys)) {
            if (!isset($array[$key]) or !is_array($array[$key])) {
                return;
            }

            $array =& $array[$key];
        }

        unset($array[array_shift($keys)]);
    }

    /**
     * Returns all but the first value of the given array, all but the first elements for which the $callback
     * returns true if $callback is a callable, or all but the first $callback elements if $callback is a number.
     *
     * Aliases:
     *  - @see Arr::tail()
     *
     * @param   array               $array      The array to traverse.
     * @param   callable|int|bool   $callback   The truth test the value should pass or an integer denoting how many
     *                                          of the initial elements of the array should be excluded. The count
     *                                          is 1-indexed, ie. if you want to exclude the first 2 elements, pass 2.
     *                                          When a falsy value is given, the method will return all but the first
     *                                          element of the array.
     * @param   mixed               $default    The default value to be returned if none of the elements passes the
     *                                          test or the array contains no more than one item.
     * @return  mixed
     */
    public static function rest(array $array, $callback = false, $default = null)
    {
        // Avoid some overhead at this point already if possible. We need at least 2 elements in the array for
        // this method to make any usage sense.
        if (2 > count($array)) {
            return $default;
        }

        // For a falsy callback, return all but the first element of the array.
        if (!$callback) {
            return array_slice($array, 1);
        }

        // With a callable given, keep counting as long as the callable returns a truthy value.
        if (is_callable($callback)) {
            $i = 0;

            foreach ($array as $key => $value) {
                if (!call_user_func($callback, $key, $value)) {
                    break;
                }

                $i++;
            }

            // If we didn't get at least a single truthy value, return the default.
            if ($i === 0) {
                return $default;
            }

            // Otherwise we're just gonna overwrite the $callback and proceed as if it were an integer in the
            // first place.
            $callback = $i;
        }

        // Return the final $callback elements.
        return array_slice($array, abs((int) $callback));
    }

    /**
     * Sets the given value for a string delimited key within the given array. If null is given instead of a key,
     * the whole initial array will be overwritten with the given value.
     *
     * @param   array   $array      The array to set the value in.
     * @param   string  $key        The string delimited key.
     * @param   mixed   $value      The value to set.
     * @param   string  $delimiter  The delimiter to use when exploding the key into parts.
     * @return  mixed
     */
    public static function set(array& $array, $key, $value, string $delimiter = null)
    {
        // Make loops easier for the end-user - overwrite the whole array if the key is null.
        if (null === $key) {
            return $array = $value;
        }

        // Which string delimiter should we use?
        if (null === $delimiter) {
            $delimiter = static::$delimiter;
        }

        // Explode the key according to that delimiter.
        $keys = explode($delimiter, $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array =& $array[$key];
        }

        return $array[array_shift($keys)] = $value;
    }

    /**
     * Alias for {@see static::any()}
     */
    public static function some(array $array, callable $callback, bool $strict = true) : bool
    {
        return static::any($array, $callback, $strict);
    }

    /**
     * Alias for {@see static::rest()}
     */
    public static function tail(array $array, $callback = false, $default = null)
    {
        return static::rest($array, $callback, $default);
    }

    /**
     * Alias for {@see static::first()}
     */
    public static function take(array $array, $callback = false, $default = null)
    {
        return static::first($array, $callback, $default);
    }

    /**
     * Returns an array based on the initial array with all occurrences of the passed values removed. Uses strict
     * equality comparisons.
     *
     * @param   array   $array      The array to traverse.
     * @param   mixed   ...$values  The values which should get removed.
     * @return  array
     */
    public static function without(array $array, ...$values) : array
    {
        return array_filter($array, function ($value) use ($values) {
            return !in_array($value, $values, true);
        });
    }
}
