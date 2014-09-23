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
    protected static $phpserver = null;
    
    public static function setUpBeforeClass()
    {
        AsyncToolTest::init();
        
        self::$phpserver = proc_open(
            "php -S localhost:12345 " . __DIR__.'/Server_SimpleCCMTest.php',
            array(
                0 => array("pipe", "r"),
                1 => array("pipe", "w"),
                2 => array("pipe", "w"),
            ),
            $pipes
        );
    }
    
    public static function tearDownAfterClass()
    {
        $s = proc_get_status( self::$phpserver );
        posix_kill( $s['pid'], SIGKILL );
        proc_close( self::$phpserver  );
        self::$phpserver = null;
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
    
    //=================================
    // Test Calls
    //=================================
    public function testCall()
    {
        $this->ccm->register( $this->as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'test', array( 'ping' => 'PINGPING' ) );
            },
            function( $as, $err ){
                $as->executed = false;
            }
        )->add(
            function( $as, $rsp ){
                $this->assertEquals( 'PONGPONG', $rsp->pong );
                $this->assertEquals( 'PINGPING', $rsp->ping );
                $as->executed = true;
            }
        );
        
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }
    
    public function callDataCommon( $upload_data )
    {
        $this->ccm->register( $this->as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        
        # Output data
        $this->as->add(
            function($as) use ( $upload_data ) {
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'data', array( 'ping' => 'PINGPING' ), $upload_data );
            },
            function( $as, $err ){
                echo $as->error_info;
                $as->executed1 = false;
            }
        )->add(
            function( $as, $rsp ){
                $this->assertEquals( 'MYDATA-HERE', $rsp );
                $as->executed1 = true;
                $as->success();
            }
        # output data from ping parameter
        )->add(
            function($as) use ( $upload_data ) {
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'pingdata', array( 'ping' => 'PINGPING' ), $upload_data );
            },
            function( $as, $err ){
                $as->executed2 = false;
            }
        )->add(
            function( $as, $rsp ){
                $this->assertEquals( 'PINGPING', $rsp );
                $as->executed2 = true;
                $as->success();
            }
        # output json message with data
        )->add(
            function($as) use ( $upload_data ) {
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'test', array( 'ping' => 'PINGPING' ), $upload_data );
            },
            function( $as, $err ){
                $as->executed3 = false;
            }
        )->add(
            function( $as, $rsp ){
                $this->assertEquals( 'PONGPONG', $rsp->pong );
                $this->assertEquals( 'PINGPING', $rsp->ping );
                $as->executed3 = true;
            }
        );
        
        $this->as->run();
        $this->assertTrue( $this->as->executed1 );
        $this->assertTrue( $this->as->executed2 );
        $this->assertTrue( $this->as->executed3 );
    }
    
    public function testCallData()
    {
        $this->callDataCommon( 'MYDATA-HERE' );
    }
    
    public function testCallDataFile()
    {
        $f = tmpfile();
        fwrite( $f, 'MYDATA-HERE' );
        rewind( $f );
        $this->callDataCommon( $f );
        fclose( $f );
    }
    
    public function testCallDownloadStream()
    {
        $this->ccm->register( $this->as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        
        # Output data
        $this->as->add(
            function($as) {
                $iface = $this->ccm->iface( 'srv' );
                $as->myfile = tmpfile();
                $iface->call( $as, 'data', array( 'ping' => 'PINGPING' ), 'MYDATA-HERE', $as->myfile );
            },
            function( $as, $err ){
                echo $as->error_info;
                $as->executed1 = false;
            }
        )->add(
            function( $as, $rsp ){
                $this->assertTrue(  $rsp );
                
                rewind( $as->myfile );
                $rsp = fread( $as->myfile, 100 );
                fclose( $as->myfile );
                
                $this->assertEquals( 'MYDATA-HERE', $rsp );
                
                $as->executed1 = true;
                $as->success();
            }
        );
        
        $this->as->run();
        $this->assertTrue( $this->as->executed1 );
    }
    
    public function testCallOutData()
    {
        $this->ccm->register( $this->as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'pingdata', array( 'ping' => 'PINGPING' ) );
            },
            function( $as, $err ){
                $as->executed = false;
            }
        )->add(
            function( $as, $rsp ){
                $this->assertEquals( 'PINGPING', $rsp );
                $as->executed = true;
            }
        );
        
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }
    
    /**
     * @medium
     */
    public function testCallComplex()
    {
        # Make sure re-used cURL works OK
        for ( $i = 0; $i < 3; ++$i )
        {
            $this->testCall();
            $this->ccm->unRegister( 'srv' );
            $this->testCallData();
            $this->ccm->unRegister( 'srv' );
            $this->testCallDataFile();
            $this->ccm->unRegister( 'srv' );
            $this->testCallDownloadStream();
            $this->ccm->unRegister( 'srv' );
            $this->testCallOutData();
            $this->ccm->unRegister( 'srv' );
        }
    }
}
