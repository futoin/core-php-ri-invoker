<?php
/**
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */
 
use \FutoIn\RI\AsyncToolTest;

/**
 * @ignore
 */
class SimpleCCMTest extends PHPUnit_Framework_TestCase
{
    protected $as = null;
    protected $ccm = null;
    
    public static function setUpBeforeClass()
    {
        AsyncToolTest::init();
    }
    
    public function setUp()
    {
        $this->as = new \FutoIn\RI\ScopedSteps();
        $this->ccm = new \FutoIn\RI\Invoker\SimpleCCM();
    }
    
    public function tearDown()
    {
        $this->as = null;
        $this->ccm = null;
    }
    
    public function testRegister()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        $this->ccm->register( $this->as, 'testB', 'test.b:2.3', 'http://localhost:12345/ftn/', array( 'user'=>'userX', 'password'=>'passX' ) );
        $this->as->run();
        
        $ifa = $this->ccm->iface( 'testA' );
        $ifb = $this->ccm->iface( 'testB' );
        
        $this->assertTrue( is_object( $ifa ) );
        $this->assertTrue( is_object( $ifb ) );
        
        $this->assertEquals( 'test.a', $ifa->ifaceInfo()->name() );
        $this->assertEquals( '1.1', $ifa->ifaceInfo()->version() );
        
        $this->assertEquals( 'test.b', $ifb->ifaceInfo()->name() );
        $this->assertEquals( '2.3', $ifb->ifaceInfo()->version() );
        
        $this->ccm->assertIface( 'testA', 'test.a:1.1' );
        $this->ccm->assertIface( 'testB', 'test.b:2.2' );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testRegisterFail1()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a:1.', 'http://localhost:12345/ftn' );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testRegisterFail2()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a:2', 'http://localhost:12345/ftn' );
    }

    /**
     * @expectedException \FutoIn\Error
     */
    public function testRegisterFail3()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a', 'http://localhost:12345/ftn' );
    }

    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testAssertIfaceFail1()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        $this->as->run();
        $this->ccm->assertIface( 'testA', 'test.a:1.2' );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testAssertIfaceFail2()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        $this->ccm->assertIface( 'testA', 'test.b:1.1' );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testAssertIfaceFail3()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        $this->ccm->assertIface( 'testA', 'test.a:2.2' );
    }
    
    public function testUnRegister()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        $this->ccm->iface( 'testA' );
        $this->ccm->unRegister( 'testA' );
        
        try
        {
            $this->ccm->iface( 'testA' );
            $this->assertFalse( true );
        }
        catch ( \FutoIn\Error $e )
        {
            $this->assertTrue( true );
        }
    }
    
    public function testDefense()
    {
        $this->ccm->register( $this->as, '#defense', 'test.a:1.1', 'http://localhost:12345/ftn' );
        $this->assertEquals( "test.a", $this->ccm->defense()->ifaceInfo()->name() );
    }

    public function testLog()
    {
        $this->ccm->register( $this->as, '#log', 'test.a:1.1', 'http://localhost:12345/ftn' );
        $this->assertEquals( "test.a", $this->ccm->log()->ifaceInfo()->name() );
    }
    
    public function testAlias()
    {
        $this->ccm->register( $this->as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        $this->ccm->alias( 'testA', 'testB' );
        $this->assertEquals( "test.a", $this->ccm->iface( 'testB' )->ifaceInfo()->name() );
    }
}
