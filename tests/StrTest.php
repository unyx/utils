<?php namespace nyx\utils\tests;

// Internal dependencies
use nyx\utils\Str;

/**
 * Str Tests
 *
 * @package     Nyx\Utils\Tests
 * @version     0.0.1
 * @author      Michal Chojnacki <m.chojnacki@muyo.io>
 * @copyright   2012-2016 Nyx Dev Team
 * @link        http://docs.muyo.io/nyx/utils/index.html
 */
class StrTest extends \PHPUnit_Framework_TestCase
{
    // Str::occurrences()
    public function testStrOccurrences()
    {
        $source = 'foobarfoobarfoobar';

        // Basic case-sensitive test
        $this->assertEquals([0, 6, 12], Str::occurrences($source, 'foobar', 0, true));
        $this->assertEquals([], Str::occurrences($source, 'FOOBAR', 0, true));

        // Case-insensitive
        $this->assertEquals([0, 6, 12], Str::occurrences($source, 'foobar', 0, false));
        $this->assertEquals([0, 6, 12], Str::occurrences($source, 'FOOBAR', 0, false));

        // Start at a positive offset within the $source
        $this->assertEquals([6, 12], Str::occurrences($source, 'foobar', 3));
        $this->assertEquals([6, 12], Str::occurrences($source, 'foobar', 6));
        $this->assertEquals([12], Str::occurrences($source, 'foobar', 9));

        // Start at a negative offset within the $source
        $this->assertEquals([], Str::occurrences($source, 'foobar', -3));
        $this->assertEquals([12], Str::occurrences($source, 'foobar', -6));
        $this->assertEquals([12], Str::occurrences($source, 'foobar', -9));
        $this->assertEquals([6, 12], Str::occurrences($source, 'foobar', -12));

        // Start at an offset not within the $source
        $this->setExpectedException('OutOfBoundsException', 'The given $offset [100]');
        Str::occurrences($source, 'foobar', 100);
    }
}
