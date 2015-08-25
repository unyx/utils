<?php namespace nyx\utils\tests;

// Internal dependencies
use nyx\utils\Func;

/**
 * Func Tests
 *
 * @package     Nyx\Utils\Tests
 * @version     0.0.1
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/index.html
 */
class FuncTest extends \PHPUnit_Framework_TestCase
{
    // Used by the when/unless tests.
    private $whenExists;

    // Func::after()
    public function testAfter()
    {
        $times = 5;
        $runs  = 11;

        // Let's create a simple helper.
        $test = function (callable $wrapper, callable $function, $value) use ($times, $runs) {

            // Invoke the function once to determine the expected value.
            $expected = $function($value);

            for ($i = 1; $i <= $runs; $i++) {
                if ($i < $times) {
                    $this->assertNull($wrapper($value));
                } else {
                    $this->assertEquals($expected, $wrapper($value));
                }
            }
        };

        // Prepare our functions.
        $func1 = function ($value) {return 'foo'.$value;};
        $func2 = function ($value) {return $value.'bar';};

        // Run a few loops.
        $wrapper = Func::after($times, $func1);
        $test($wrapper, $func1, 'bar');

        $wrapper = Func::after($times, $func2);
        $test($wrapper, $func2, 'foo');
    }

    // Func::hash()
    public function testHash()
    {
        // Prepare our functions.
        $func1 = function ($arg1, $arg2) {return true;};

        // Run some tests.
        $this->assertEquals('8bdb4ed5e8d60590121851789f7c5366', Func::hash($func1, ['arg1', 'arg2']));
        $this->assertEquals('d18a838748b014d758771d5dc497f78f', Func::hash($func1, ['arg1', ['arg2', 'arg3']]));

        // Same callable, different arguments.
        $this->assertEquals('0322135bc1a0c8e574fc5c3d380eb8d7', Func::hash([$this, 'compute'], ['arg1', ['arg2', 'arg3']]));
        $this->assertEquals('bd1d96a1996666394149e71b1f18352c', Func::hash([$this, 'compute'], ['arg1']));

        // Both should boil down to the same hash - same arguments, just a different callable notation.
        $this->assertEquals('2edbc7fd908ff5a29f88330640479c08', Func::hash(['\nyx\utils\tests\FuncTest', 'staticCompute'], ['arg1', ['arg2', 'arg3']]));
        $this->assertEquals('2edbc7fd908ff5a29f88330640479c08', Func::hash('\nyx\utils\tests\FuncTest::staticCompute', ['arg1', ['arg2', 'arg3']]));

        // Same callable notation, different arguments though.
        $this->assertEquals('2e287dc68fecd9998c0fcc8e15dbe3aa', Func::hash(['\nyx\utils\tests\FuncTest', 'staticCompute'], ['arg1']));
        $this->assertEquals('95d485a14f7431598b81c70e92999745', Func::hash('\nyx\utils\tests\FuncTest::staticCompute', ['arg2']));
    }

    // Func::memoize()
    public function testMemoize()
    {
        // Prepare our functions.
        $func = function ($arg1, $arg2) {return $arg1.$arg2;};

        // Without resolver (automatic cache key).
        $memoized = Func::memoize($func);

        $this->assertEquals('foobar', $memoized('foo', 'bar'));
        $this->assertNotEquals('barfoo', $memoized('foo', 'bar'));
        $this->assertEquals('barfoo', $memoized('bar', 'foo'));
        $this->assertNotEquals('foobar', $memoized('bar', 'foo'));

        // With resolver (key depends on args).
        $resolver = function (callable $func, $args) {
            return 'key_'.$args[0].$args[1];
        };
        $memoized = Func::memoize($func, $resolver);

        $this->assertEquals('foobar', $memoized('foo', 'bar'));
        $this->assertNotEquals('barfoo', $memoized('foo', 'bar'));
        $this->assertEquals('barfoo', $memoized('bar', 'foo'));
        $this->assertNotEquals('foobar', $memoized('bar', 'foo'));

        // With resolver (fixed key) - all calls should return the first result, regardless of the arguments
        // passed, since the result will be fetched from the fixed key.
        $resolver = function (callable $func, $args) {return 'key';};
        $memoized = Func::memoize($func, $resolver);

        $this->assertEquals('foobar', $memoized('foo', 'bar'));
        $this->assertEquals('foobar', $memoized('bar', 'foo'));
        $this->assertEquals('foobar', $memoized('test', 'omnomnom'));

        // Same as above but external resolver callable.
        $memoized = Func::memoize($func, [$this, 'compute']);

        $this->assertEquals('foobar', $memoized('foo', 'bar'));
        $this->assertEquals('foobar', $memoized('bar', 'foo'));
        $this->assertEquals('foobar', $memoized('test', 'omnomnom'));
    }

    // Func::once()
    public function testOnce()
    {
        // Prepare our functions.
        $func1 = function ($value) {return 'foo'.$value;};
        $func2 = function ($value) {return $value.'bar';};

        // Run some tests.
        $wrapper = Func::once($func1);
        $this->assertEquals('foobar', $wrapper('bar'));
        $this->assertNull($wrapper('bar'));
        $this->assertNull($wrapper('baz'));

        $wrapper = Func::once($func2);
        $this->assertEquals('foobar', $wrapper('foo'));
        $this->assertNull($wrapper('bar'));
        $this->assertNull($wrapper('baz'));
    }

    // Func::once()
    public function testOnly()
    {
        // Prepare our functions.
        $func1 = function ($value) {return 'foo'.$value;};
        $func2 = function ($value) {return $value.'bar';};

        // Run some tests.
        $wrapper = Func::only(3, $func1);
        $this->assertEquals('foobar', $wrapper('bar'));
        $this->assertEquals('foobaz', $wrapper('baz'));
        $this->assertEquals('fooxyz', $wrapper('xyz'));
        $this->assertNull($wrapper('bar'));
        $this->assertNull($wrapper('baz'));

        $wrapper = Func::only(2, $func2);
        $this->assertEquals('foobar', $wrapper('foo'));
        $this->assertEquals('bazbar', $wrapper('baz'));
        $this->assertNull($wrapper('bar'));
        $this->assertNull($wrapper('baz'));
    }

    // Func::partial()
    public function testPartial()
    {
        // Prepare our function.
        $func = function ($val1, $val2, $val3) {return $val1.$val2.$val3;};

        // Run some tests.
        $wrapper = Func::partial($func, 'foo', 'bar');
        $this->assertEquals('foobarbaz', $wrapper('baz'));
        $this->assertEquals('foobarzeta', $wrapper('zeta'));

        $wrapper = Func::partial($func, 'hello', ' world');
        $this->assertEquals('hello world of doom.', $wrapper(' of doom.'));
        $this->assertEquals('hello world of mine.', $wrapper(' of mine.'));
    }

    // Func::partial()
    public function testPartialRight()
    {
        // Prepare our function.
        $func = function ($val1, $val2, $val3) {return $val1.$val2.$val3;};

        // Run some tests.
        $wrapper = Func::partialRight($func, 'foo', 'bar');
        $this->assertEquals('bazfoobar', $wrapper('baz'));
        $this->assertEquals('zetafoobar', $wrapper('zeta'));

        $wrapper = Func::partialRight($func, 'hello', ' world');
        $this->assertEquals(' of doom.hello world', $wrapper(' of doom.'));
        $this->assertEquals(' of mine.hello world', $wrapper(' of mine.'));
    }

    // Func::throttle()
    public function testThrottle()
    {
        $counter  = 0;
        $function = function () use (&$counter) { $counter++; };
        $wrapper  = Func::throttle($function, 100);

        // Time it to invoke the function 5 times at most, even though we call the wrapper 7 times.
        $wrapper();
        $wrapper();
        $wrapper();
        usleep(120 * 1000);
        $wrapper();
        usleep(140 * 1000);
        $wrapper();
        usleep(220 * 1000);
        $wrapper();
        usleep(240 * 1000);
        $wrapper();
        $this->assertEquals(5, $counter, 'function was throttled');

        usleep(500 * 1000);

        // Single call.
        $counter  = 0;
        $function = function () use (&$counter) { $counter++; };
        $wrapper  = Func::throttle($function, 100);

        $wrapper();
        usleep(220 * 1000);
        $this->assertEquals(1, $counter, 'function called once');

        usleep(500 * 1000);

        // Double call.
        $counter  = 0;
        $function = function () use (&$counter) { $counter++; };
        $wrapper  = Func::throttle($function, 100);

        $wrapper();
        $wrapper();
        usleep(220 * 1000);
        $this->assertEquals(1, $counter, 'function called twice');
    }

    // Func::when()
    public function testUnless()
    {
        // -- Basic test. No params for the test nor the callable.
        $test = function () {
            return true === $this->getWhenExists();
        };

        $callable = function () {return 1;};

        $unless = Func::unless($test, $callable);
        $this->setWhenExists(true);
        $this->assertNull($unless());
        $this->setWhenExists(false);
        $this->assertEquals(1, $unless());

        // -- Params for the callable.
        $callable = function ($val) {return $val;};
        $unless = Func::unless($test, $callable);
        $this->setWhenExists(true);
        $this->assertNull($unless('foo'));
        $this->setWhenExists(false);
        $this->assertEquals('foo', $unless('foo'));

        // -- Same params for the callable and the test.
        $test = function ($bool) {
            return $bool === $this->getWhenExists();
        };

        $unless = Func::unless($test, $callable, Func::PASSTHROUGH);
        $this->setWhenExists(true);
        $this->assertNull($unless(true));
        $this->assertFalse($unless(false));
        $this->assertEquals('foo', $unless('foo'));
        $this->setWhenExists(false);
        $this->assertTrue($unless(true));

        // -- Different params for the callable and the test.
        $test = function ($bool) {
            return $bool === $this->getWhenExists();
        };

        // Pass 'true' to the test.
        $unless = Func::unless($test, $callable, true);
        $this->setWhenExists(true);
        $this->assertNull($unless('foo'));
        $this->assertNull($unless(false));
        $this->setWhenExists(false);
        $this->assertEquals('foo', $unless('foo'));
        $this->assertTrue($unless(true));
    }

    // Func::when()
    public function testWhen()
    {
        // -- Basic test. No params for the test nor the callable.
        $test = function () {
            return true === $this->getWhenExists();
        };

        $callable = function () {return 1;};
        $when = Func::when($test, $callable);
        $this->setWhenExists(true);
        $this->assertEquals(1, $when());
        $this->setWhenExists(false);
        $this->assertNull($when());

        // -- Params for the callable.
        $callable = function ($val) {return $val;};
        $when = Func::when($test, $callable);
        $this->setWhenExists(true);
        $this->assertEquals('foo', $when('foo'));
        $this->setWhenExists(false);
        $this->assertNull($when('foo'));

        // -- Same params for the callable and the test.
        $test = function ($bool) {
            return $bool === $this->getWhenExists();
        };

        $when = Func::when($test, $callable, Func::PASSTHROUGH);
        $this->setWhenExists(true);
        $this->assertTrue($when(true));
        $this->assertNull($when(false));
        $this->assertNull($when('foo'));
        $this->setWhenExists(false);
        $this->assertFalse($when(false));

        // -- Different params for the callable and the test.
        $test = function ($bool) {
            return $bool === $this->getWhenExists();
        };

        // Pass 'true' to the test.
        $when = Func::when($test, $callable, true);
        $this->setWhenExists(true);
        $this->assertEquals('foo', $when('foo'));
        $this->assertFalse($when(false));
        $this->setWhenExists(false);
        $this->assertNull($when('foo'));
        $this->assertNull($when(false));
    }

    // Used by the when/unless tests.
    protected function getWhenExists()
    {
        return $this->whenExists;
    }

    // Used by the when/unless tests.
    protected function setWhenExists($exists)
    {
        $this->whenExists = (bool) $exists;
    }

    // Used by the memoize and hash tests.
    public function compute()
    {
        return 'foobar';
    }

    // Used by the hash tests.
    public static function staticCompute()
    {
        return 'foobar';
    }
}
