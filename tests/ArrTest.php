<?php namespace nyx\utils\tests;

// Internal dependencies
use nyx\utils\Arr;

/**
 * Arr Tests
 *
 * @package     Nyx\Utils\Tests
 * @version     0.0.1
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/index.html
 */
class ArrTest extends \PHPUnit_Framework_TestCase
{
    // Arr::add()
    public function testArrayAdd()
    {
        $source = ['alpha' => 'foo'];
        $target = ['alpha' => 'foo', 'beta' => 'bar'];

        // Add once. Should set the 'beta' key to the 'bar' string.
        Arr::add($source, 'beta', 'bar');
        $this->assertEquals($target, $source);

        // Add again. Should not change the source array anymore.
        Arr::add($source, 'beta', 'bar');
        $this->assertEquals($target, $source);
    }

    // Arr::last()
    public function testArrayLast()
    {
        $source = [1, 2, 3, 4, 5];

        // Early return on empty array - should return null (default value)
        $this->assertNull(Arr::last([]));

        // Early return on empty array - should return the given default value.
        $this->assertEquals('default', Arr::last([], false, 'default'));

        // Return the last element (default behaviour) - should return '5'.
        $this->assertEquals(5, Arr::last($source));

        // Return the last element (with falsy values as second parameter)
        $this->assertEquals(5, Arr::last($source, false));
        $this->assertEquals(5, Arr::last($source, 0));
        $this->assertEquals(5, Arr::last($source, []));
        $this->assertEquals(5, Arr::last($source, null));

        // Return the last element (with truthy, values as second parameter)
        $this->assertEquals(5, Arr::last($source, true));
        $this->assertEquals(5, Arr::last($source, ['test']));
        $this->assertEquals(5, Arr::last($source, 1));

        // Return the last n elements
        $this->assertEquals([3, 4, 5], Arr::last($source, 3));
        $this->assertEquals([3, 4, 5], Arr::last($source, '3'));
        $this->assertEquals([3, 4, 5], Arr::last($source, '3.14'));
        $this->assertEquals([2, 3, 4, 5], Arr::last($source, 4));

        // Return more elements than there actually are in $source - should return full $source
        $this->assertEquals($source, Arr::last($source, 10));

        // -- Slicing with a truth test - this one will always fail, should return default value instead.
        $truthTest = function($key, $value) {
            return $value === 'does_not_exist_in_$source';
        };

        $this->assertNull(Arr::last($source, $truthTest));
        $this->assertEquals('default', Arr::last($source, $truthTest, 'default'));

        // -- Slicing with a truth test - this one should return the first value smaller than 4 and nothing else.
        $truthTest = function($key, $value) {
            return $value < 4;
        };

        $this->assertEquals(3, Arr::last($source, $truthTest));
    }

    // Arr::set()
    public function testArraySet()
    {
        // Set once. Should set the 'beta' key to the 'bar' string.
        $source = ['alpha' => 'foo'];
        $target = ['alpha' => 'foo', 'beta' => 'bar'];

        Arr::set($source, 'beta', 'bar');
        $this->assertEquals($target, $source);

        // Set again. Should change the source array for the 'beta' key.
        $target = ['alpha' => 'foo', 'beta' => 'baz'];

        Arr::set($source, 'beta', 'baz');
        $this->assertEquals($target, $source);

        // Set with a delimited key. Should overwrite the 'beta' key to make
        // it an array holding the 'nested' array with a 'key' key => 'baz'
        $target = ['alpha' => 'foo', 'beta' => [
            'nested' => [
                'key' => 'baz'
            ]
        ]];

        Arr::set($source, 'beta.nested.key', 'baz');
        $this->assertEquals($target, $source);

        // Set with a delimited key with a custom delimiter. Should overwrite
        // the beta.nested.key from the previous test from baz to ugachaka.
        $target = ['alpha' => 'foo', 'beta' => [
            'nested' => [
                'key' => 'ugachaka'
            ]
        ]];

        Arr::set($source, 'beta::nested::key', 'ugachaka', '::');
        $this->assertEquals($target, $source);
    }
}
