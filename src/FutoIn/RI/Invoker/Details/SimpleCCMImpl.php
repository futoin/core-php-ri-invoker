<?php

namespace FutoIn\RI\Invoker\Details;

/**
 * @internal
 * @ignore
 */
class SimpleCCMImpl
{
    public $curl;
    
    public function __construct( $curl_opts = null )
    {
        $curl = curl_init();
        curl_setopt( $curl, CURLOPT_FORBID_REUSE, FALSE );
        curl_setopt( $curl, CURLOPT_FRESH_CONNECT, FALSE );
        curl_setopt( $curl, CURLOPT_NETRC, FALSE );
        curl_setopt( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
        curl_setopt( $curl, CURLOPT_SSLVERSION, 3 );
        curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, TRUE );
        curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, FALSE );

        curl_setopt( $curl, CURLOPT_MAXCONNECTS, 8 );
        
        curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT_MS, 3000 );
        curl_setopt( $curl, CURLOPT_TIMEOUT_MS, 30000 );
        
        curl_setopt( $curl, CURLOPT_BINARYTRANSFER, true );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
       
        if ( $curl_opts )
        {
            curl_setopt_array( $curl, $curl_opts );
        }
        
        // TODO: cURL "eventization" with libevent/libev/libuv and integration with AsyncSteps loop
        
        $this->curl = $curl;
    }
    
    public function __destruct()
    {
        curl_close( $this->curl ); 
    }

    
    public function onRegister( $name, $info ){}
    
    public function signMessage( \FutoIn\AsyncSteps $as, &$req )
    {
        $req['forcersp'] = true;
        $as->success( $req );
    }
    
    public function checkMessageSignature( \FutoIn\AsyncSteps $as, &$rsp )
    {
        $as->success( $rsp );
    }
}