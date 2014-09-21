<?php
/**
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

/**
 * @ignore
 */
class BootstrapTest extends PHPUnit_Framework_TestCase
{
    public function testSimpleCCM()
    {
        \FutoIn\RI\AsyncToolTest::init();
        new \FutoIn\RI\Invoker\SimpleCCM();
        $this->assertTrue( true );
    }
}
