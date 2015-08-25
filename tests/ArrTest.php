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
