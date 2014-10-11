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
        
        // temporary workaround
        $phpcmd = defined('HHVM_VERSION')
            ? '/usr/bin/php5'
            : 'php';
        
        self::$phpserver = proc_open(
            "$phpcmd -d always_populate_raw_post_data=-1 -S localhost:12345 " . __DIR__.'/Server_SimpleCCMTest.php',
            array(
                0 => array("file", '/dev/null', "r"),
                1 => array("file", '/dev/null', "w"),
                2 => array("file", '/dev/null', "w"),
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
        gc_collect_cycles();
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testForbidCloneCMM()
    {
        clone $this->ccm;
        $this->assertTrue( true );
    }
    
    /**
     * @medium
     * Just give a little more time on initial load
     */
    public function testRegister()
    {
        $this->as->add(
            function($as){
                $this->ccm->register( $as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
                $this->ccm->register( $as, 'testB', 'test.b:2.3', 'http://localhost:12345/ftn/', 'userX:passX' );
            },
            function($as,$err){
                $this->assertFalse( true );
            }
        );
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
    
    public function testRegisterFail1()
    {
        $this->as->add(
            function($as){
                $this->ccm->register( $as, 'testA', 'test.a:1.', 'http://localhost:12345/ftn' );
            },
            function( $as, $err ){
                $as->executed = true;
                $this->assertEquals( "InvokerError", $err );
            }
        )->add(function($as){
            $as->executed = false;
        });
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }
    
    public function testRegisterFail2()
    {
        $this->as->add(
            function($as){
                $this->ccm->register( $as, 'testA', 'test.a:2', 'http://localhost:12345/ftn' );
            },
            function( $as, $err ){
                $as->executed = true;
                $this->assertEquals( "InvokerError", $err );
            }
        )->add(function($as){
            $as->executed = false;
        });
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }

    public function testRegisterFail3()
    {
        $this->as->add(
            function($as){
                $this->ccm->register( $as, 'testA', 'test.a', 'http://localhost:12345/ftn' );
            },
            function( $as, $err ){
                $as->executed = true;
                $this->assertEquals( "InvokerError", $err );
            }
        )->add(function($as){
            $as->executed = false;
        });
        $this->as->run();
        
        $this->assertTrue( $this->as->executed );
    }

    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testAssertIfaceFail1()
    {
        $this->as->add(function($as){
            $this->ccm->register( $as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        });
        $this->as->run();

        $this->ccm->assertIface( 'testA', 'test.a:1.2' );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testAssertIfaceFail2()
    {
        $this->as->add(function($as){
            $this->ccm->register( $as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        });
        $this->as->run();
            
        $this->ccm->assertIface( 'testA', 'test.b:1.1' );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testAssertIfaceFail3()
    {
        $this->as->add(function($as){
            $this->ccm->register( $as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        });
        $this->as->run();
            
        $this->ccm->assertIface( 'testA', 'test.a:2.2' );
    }
    
    public function testUnRegister()
    {
        $this->as->add(function($as){
            $this->ccm->register( $as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        });
        $this->as->run();
        
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
        $this->as->add(function($as){
            $this->ccm->register( $as, '#defense', 'test.a:1.1', 'http://localhost:12345/ftn' );
        });
        $this->as->run();

        $this->assertEquals( "test.a", $this->ccm->defense()->ifaceInfo()->name() );
    }

    public function testLog()
    {
        $this->as->add(function($as){
            $this->ccm->register( $as, '#log', 'test.a:1.1', 'http://localhost:12345/ftn' );
        });
        $this->as->run();
            
        $this->assertEquals( "test.a", $this->ccm->log()->ifaceInfo()->name() );
    }
    
    public function testAlias()
    {
        $this->as->add(function($as){
            $this->ccm->register( $as, 'testA', 'test.a:1.1', 'http://localhost:12345/ftn' );
        });
        $this->as->run();

            
        $this->ccm->alias( 'testA', 'testB' );
        $this->assertEquals( "test.a", $this->ccm->iface( 'testB' )->ifaceInfo()->name() );
    }
    
    //=================================
    // Test Calls
    //=================================
    public function testCall()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        })->add(
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
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
      
        # Output data
        $this->as->add(
            function($as) use ( $upload_data ) {
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'data', array( 'ping' => 'PINGPING' ), $upload_data );
            },
            function( $as, $err ){
                var_dump( $err );
                var_dump( $as->error_info );
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
                var_dump( $err );
                var_dump( $as->error_info );
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
                
                if ( $this->ccm instanceof \FutoIn\Invoker\AdvancedCCM )
                {
                    $upload_data = null;
                }

                $iface->call( $as, 'test', array( 'ping' => 'PINGPING' ), $upload_data );
            },
            function( $as, $err ){
                var_dump( $err );
                var_dump( $as->error_info );
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
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        # Output data
        $this->as->add(
            function($as) {
                $iface = $this->ccm->iface( 'srv' );
                $as->myfile = tmpfile();
                $iface->call( $as, 'data', array( 'ping' => 'PINGPING' ), 'MYDATA-HERE', $as->myfile );
            },
            function( $as, $err ){
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
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
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
    
    public function testCallAliasException()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
            $this->ccm->alias( 'srv', 'asrv' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'asrv' );
                $iface->call( $as, 'throw', array( 'errtype' => 'MyErrorType' ) );
            },
            function( $as, $err ){
                $this->assertEquals( 'MyErrorType', $err );
                $as->executed = true;
            }
        )->add(
            function( $as, $rsp ){
                $as->executed = false;
            }
        );
        
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }
    
    public function testCallNotImplemented()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'notimpl', array() );
            },
            function( $as, $err ){
                $this->assertEquals( 'NotImplemented', $err );
                $as->executed = true;
            }
        )->add(
            function( $as, $rsp ){
                $as->executed = false;
            }
        );
        
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }
    
    public function testCallUnknown()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.testa:1.1', 'http://localhost:12345/ftn' );
        });

        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'notimpl', array() );
            },
            function( $as, $err ){
                $this->assertEquals( 'UnknownInterface', $err );
                $as->executed = true;
            }
        )->add(
            function( $as, $rsp ){
                $as->executed = false;
            }
        );
        
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }
    
    public function testCallNotSupportedVersion()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'notversion', array() );
            },
            function( $as, $err ){
                $this->assertEquals( 'NotSupportedVersion', $err );
                $as->executed = true;
            }
        )->add(
            function( $as, $rsp ){
                $as->executed = false;
            }
        );
        
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }
    
    public function testCallParallel()
    {   
        $this->as->add(function($as){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        $this->as->run();
        
        $model_as = new \FutoIn\RI\AsyncSteps;
        
        $model_as->add(
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
    
        $asl = array();
        
        for ( $i = 0; $i < 10; ++$i )
        {
            $asl[] = clone $model_as;
        }
      
        foreach( $asl as $as )
        {
            $as->execute();
        }
        
        \FutoIn\RI\AsyncToolTest::run();
        
        foreach( $asl as $i => $as )
        {
            $this->assertTrue( $as->executed, "Failed on $i" );
        }
    }
    
    /**
     * @medium
     */
    public function testCallComplex()
    {
        # Make sure re-used cURL works OK
        for ( $i = 0; $i < 5; ++$i )
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
            $this->testCallAliasException();
            $this->ccm->unRegister( 'srv' );
            $this->testCallNotImplemented();
            $this->ccm->unRegister( 'srv' );
            $this->testCallUnknown();
            $this->ccm->unRegister( 'srv' );
            $this->testCallNotSupportedVersion();
            $this->ccm->unRegister( 'srv' );
            $this->testCallParallel();
            $this->ccm->unRegister( 'srv' );
            
            gc_collect_cycles();
        }
    }
}
