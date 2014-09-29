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
    }
    
    public function testRegisterNoSpec()
    {
        $this->as->add(
            function($as){
                $this->ccm->register( $as, 'testA', 'test.ab:1.1', 'http://localhost:12345/ftn' );
            },
            function( $as, $err ){
                $as->executed = true;
                $this->assertEquals( "InvokerError", $err );
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
                $this->assertEquals( 'InvokerError', $err );
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
}