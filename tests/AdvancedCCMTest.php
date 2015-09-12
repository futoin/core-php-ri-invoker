<?php
/**
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */
 
use \FutoIn\RI\AsyncToolTest;
use \FutoIn\RI\Invoker\AdvancedCCM;

/**
 * @ignore
 */
class AdvancedCCMTest extends SimpleCCMTest
{
    public function setUp()
    {
        $this->as = new \FutoIn\RI\ScopedSteps();
        $this->ccm = new AdvancedCCM(array(
            AdvancedCCM::OPT_SPEC_DIRS => __DIR__ . DIRECTORY_SEPARATOR . 'specs',
            AdvancedCCM::OPT_VAULT => null,
        ));
    }
    
    public function tearDown()
    {
        $this->as = null;
        $this->ccm = null;
        gc_collect_cycles();
    }
    
    public function testRegisterNoSpec()
    {
        $this->as->add(
            function($as){
                $this->ccm->register( $as, 'testA', 'test.ab:1.1', 'http://localhost:12345/ftn' );
            },
            function( $as, $err ){
                $as->executed = true;
                $this->assertEquals( "InternalError", $err );
                $this->assertTrue( strpos($as->error_info, "Failed to load valid spec for" ) === 0 );
            }
        )->add(function($as){
            $as->executed = false;
        });
        $this->as->run();
        $this->assertTrue( $this->as->executed );
    }
    
    public function testCallWrongParam()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'throw', array( 'errtypea' => 'MyErrorType' ) );
            },
            function( $as, $err ){
                $this->assertEquals( 'InvokerError', $err );
                $this->assertTrue( strpos($as->error_info, "Unknown parameter" ) === 0 );
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
    
    public function testCallWrongParamType()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'throw', array( 'errtype' => true ) );
            },
            function( $as, $err ){
                $this->assertEquals( 'InvalidRequest', $err );
                $this->assertTrue( strpos($as->error_info, "Type mismatch" ) === 0 );
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

    public function testCallNotExpectedError()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'throw', array( 'errtype' => 'NotMyErrorType' ) );
            },
            function( $as, $err ){
                $this->assertEquals( 'InternalError', $err );
                $this->assertTrue( strpos($as->error_info, "Not expected exception from Executor" ) === 0 );
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
    
    public function testCallUploadNotExpectedError()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'test', array( 'ping' => 'PINGPING' ), 'MYDATA-HERE' );
            },
            function( $as, $err ){
                $this->assertEquals( 'InvokerError', $err );
                $this->assertTrue( strpos($as->error_info, "Raw upload is not allowed" ) === 0 );
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
    
    public function testCallWrongRawData()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'wrongdata', array() );
            },
            function( $as, $err ){
                $this->assertEquals( 'InternalError', $err );
                $this->assertTrue( strpos($as->error_info, "Raw result is not expected" ) === 0 );
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
    
    public function testCallWrongResponse()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'wrongrsp', array() );
            },
            function( $as, $err ){
                $this->assertEquals( 'InternalError', $err );
                $this->assertTrue( strpos($as->error_info, "Raw result is expected" ) === 0 );
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
    
    public function testAdvancedCall()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'srv.test:1.1', 'http://localhost:12345/ftn' );
        });
        
        $this->as->executed = 0;
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->advancedcalla( $as );
            },
            function( $as, $err ){
                $this->assertEquals( 'InvokerError', $err );
                $this->assertTrue( strpos($as->error_info, "Unknown interface function" ) === 0 );
                $as->executed += 1;
                $as->success();
            }
        )->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->advancedcall( $as );
            },
            function( $as, $err ){
                $this->assertEquals( 'InvokerError', $err );
                $this->assertTrue( strpos($as->error_info, "Missing parameters" ) === 0 );
                $as->executed += 1;
                $as->success();
            }
        )->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->advancedcall( $as, 'a', 'b' );
            },
            function( $as, $err ){
                $this->assertEquals( 'InvalidRequest', $err );
                $this->assertTrue( strpos($as->error_info, "Type mismatch" ) === 0 );
                $as->executed += 1;
                $as->success();
            }
        )->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->advancedcall( $as, 1 );
            },
            function( $as, $err ){
                $this->assertEquals( 'InvokerError', $err );
                $this->assertTrue( strpos($as->error_info, "Missing parameters" ) === 0 );
                $as->executed += 1;
                $as->success();
            }
        )->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->advancedcall( $as, 1, 2, 3, 4 );
            },
            function( $as, $err ){
                $this->assertEquals( 'InvokerError', $err );
                $this->assertTrue( strpos($as->error_info, "Unknown parameter" ) === 0 );
                $as->executed += 1;
                $as->success();
            }
        )->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->advancedcall( $as, 1, 2, 3 );
            },
            function( $as, $err ){
                $this->assertFalse( true );
            }
        )->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->advancedcall( $as, 1, 2 );
            },
            function( $as, $err ){
                $this->assertFalse( true );
            }
        )->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $as->executed += 1;
                $as->success();
            }
        );
        
        $this->as->run();
        $this->assertEquals( 6, $this->as->executed );

    }
    
    public function testSecureChannel()
    {
        $this->as->add(function( $as ){
            $this->ccm->register( $as, 'srv', 'test.b:2.3', 'http://localhost:12345/ftn' );
        });
        
        $this->as->add(
            function($as){
                $iface = $this->ccm->iface( 'srv' );
                $iface->call( $as, 'somefunc', array() );
            },
            function( $as, $err ){
                $this->assertEquals( 'SecurityError', $err );
                $this->assertTrue( strpos($as->error_info, "Requires secure channel" ) === 0 );
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
    
    /**
     * @medium
     */
    public function testAdvancedCallComplex()
    {
        # Make sure re-used cURL works OK
        for ( $i = 0; $i < 5; ++$i )
        {
            $this->testRegisterNoSpec();
            $this->testCallWrongParam();
            $this->ccm->unRegister( 'srv' );
            $this->testCallWrongParamType();
            $this->ccm->unRegister( 'srv' );
            $this->testCallNotExpectedError();
            $this->ccm->unRegister( 'srv' );
            $this->testCallUploadNotExpectedError();
            $this->ccm->unRegister( 'srv' );
            $this->testCallWrongRawData();
            $this->ccm->unRegister( 'srv' );
            $this->testCallWrongResponse();
            $this->ccm->unRegister( 'srv' );
            $this->testAdvancedCall();
            $this->ccm->unRegister( 'srv' );
            $this->testSecureChannel();
            $this->ccm->unRegister( 'srv' );
            
            gc_collect_cycles();
        }
    }
}