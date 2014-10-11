<?php

namespace FutoIn\RI\Invoker\Details;

/**
 * @internal
 * @ignore
 */
class SimpleCCMImpl
{
    public $curl_opts;
    public $curl_mh;
    private $curl_cb = [];
    private $curl_event = null;
    
    public function __construct( VaultProvider $vault=null, $curl_opts = null )
    {
        $this->curl_opts = $curl_opts ? $curl_opts : [];
        
        // TODO: cURL "eventization" with libevent/libev/libuv and integration with AsyncSteps loop
        $this->curl_mh = curl_multi_init();
        
        # Missing on HHVM
        if ( function_exists( 'curl_multi_setopt' ) )
        {
            curl_multi_setopt( $this->curl_mh, CURLMOPT_PIPELINING, 1 );
            curl_multi_setopt( $this->curl_mh, CURLMOPT_MAXCONNECTS,
                isset( $curl_opts[CURLOPT_MAXCONNECTS] )
                ? $curl_opts[CURLOPT_MAXCONNECTS] : 8 );
        }
    }
    
    public function __destruct()
    {
        curl_multi_close( $this->curl_mh ); 
    }
    
    public function multiCurlAdd( \FutoIn\AsyncSteps $as, $curl )
    {
        curl_multi_add_handle( $this->curl_mh, $curl );
        $this->curl_cb[(int)$curl] = $as;
        
        $as->setCancel(function( $as ) use ( $curl ) {
            curl_multi_remove_handle( $this->curl_mh, $curl );
            unset( $this->curl_cb[(int)$curl] );
            curl_close( $curl );
        });
        
        $this->multiCurlPoll();
    }
    
    public function multiCurlPoll()
    {
        $curl_mh = $this->curl_mh;
        
        do
        {
            $mrc = curl_multi_exec( $curl_mh, $still_running );
            
            if ( $mrc == CURLM_OK )
            {
                $info = curl_multi_info_read( $curl_mh );
                
                if ( $info && isset($this->curl_cb[(int)$info['handle']]) )
                {
                    $as = $this->curl_cb[(int)$info['handle']];
                    $curl = $info['handle'];
                    unset( $this->curl_cb[(int)$curl] );
                    
                    $as->_futoin_response = curl_multi_getcontent( $curl );
                    $as->success( $curl, $info );
                    
                    curl_multi_remove_handle( $this->curl_mh, $curl );
                }
            }
        }
        while ( $mrc === CURLM_CALL_MULTI_PERFORM );
        
        if ( $this->curl_event !== null )
        {
            \FutoIn\RI\AsyncTool::cancelCall( $this->curl_event );
            $this->curl_event = null;
        }

        if ( $this->curl_cb )
        {
            $this->curl_event = \FutoIn\RI\AsyncTool::callLater( [ $this, "multiCurlSelect" ] );
        }
    }
    
    public function multiCurlSelect()
    {
        // Yes, very very dirty. cURL integration with event loop is required
        $timeout = 0.01;
        curl_multi_select( $this->curl_mh, $timeout );
        $this->multiCurlPoll();
    }

    
    public function onRegister( \FutoIn\AsyncSteps $as, $info )
    {
    }
    
    public function createMessage( \FutoIn\AsyncSteps $as, $ctx, $params )
    {
        $info = $ctx->info;
    
        $req = array(
            'f' => $info->iface . ':' . $info->version . ':' . $ctx->name,
            'p' => $params,
            'forcersp' => true,
        );
        
        if ( ( $info->creds !== null ) &&
             ( $info->creds !== 'master' ) )
        {
            $req['sec'] = $info->creds;
        }
        
        $as->success( (object)$req );
    }
    
    public function onMessageResponse( \FutoIn\AsyncSteps $as, $ctx, $rsp )
    {
        if ( isset( $rsp->e ) )
        {
            $as->error( $rsp->e );
        }
        else
        {
            $as->success( $rsp->r );
        }
    }
    
    public function onDataResponse( \FutoIn\AsyncSteps $as, $ctx )
    {
        $as->success( $as->_futoin_response );
    }
}