<?php
/**
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Invoker\Details;

/**
 * @note libev/libuv + cURL or custom HTTP client PHP extension is required
 * @warning BLOCKING in current implementation - SHOULD NOT be used in processes handling multiple clients
 * @internal
 * @ignore
 */
class NativeInterface
    implements \FutoIn\Invoker\NativeInterface
{
    const MSG_MAXSIZE = 16384;

    private $curl;
    private $ccmimpl;
    private $raw_info;
    private $iface_info = null;
    
    public function __construct( $ccmimpl, $info )
    {
        $this->curl = $ccmimpl->curl;
        $this->ccmimpl = $ccmimpl;
        $this->raw_info = $info;
    }
    
    public function call( \FutoIn\AsyncSteps $as, $name, $params )
    {
        $info = $this->raw_info;
        
        $req = array(
            'f' => $info->iface . ': ' . $info->version . ':' . $name,
            'p' => $params,
        );
        
        // Sign 
        //---
        $this->ccmimpl->signMessage( $as, $req );
        
        // Perform request
        //---
        $as->add(function($as, $req){
            $curl =  $this->curl;
            
            curl_setopt( $curl, CURLOPT_URL, $this->raw_info->endpoint );
            
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    "Content-Type: application/futoin+json; charset=utf-8",
                    "Accept: application/futoin+json"
                )
            );
            
            curl_setopt( $curl, CURLOPT_POST, true );
            curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( $req ) );
            
            // Limit size for security reasons
            curl_setopt(
                $curl,
                CURLOPT_PROGRESSFUNCTION,
                function( $curl, $full_dlsize, $dlsize ){
                    return ( $dlsize > self::MSG_MAXSIZE ) ? -1 : 0;
                }
            );
            
            $rsp = curl_exec( $curl );
            
            $http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
            $content_type = curl_getinfo( $curl, CURLINFO_CONTENT_TYPE );
            
            if ( ( $http_code !== 200 ) ||
                 ( $content_type !== 'application/futoin+json' ) )
            {
                $as->error( \FutoIn\Error::CommError );
            }
            else
            {
                $rsp = json_parse( $rsp );
                $this->ccmimpl->checkMessageSignature( $as, $rsp );
            }
        });
        
        // Process response
        //---
        $as->add(function( $as, $rsp ){
            $as->success( $rsp->r );
        });
    }
    
    public function ifaceInfo()
    {
        if ( !$this->iface_info )
        {
            $this->iface_info = new NativeInterface_InterfaceInfo( $this->raw_info );
        }
        
        return $this->iface_info;
    }
    
    public function callData( \FutoIn\AsyncSteps $as, $name, $params, array $upload_data )
    {
    }
    
    public function burst()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
    }
    
    public function __call( $name, $args )
    {
    }
}

/**
 * @internal
 */
class NativeInterface_InterfaceInfo
    implements \FutoIn\InterfaceInfo
{
    private $raw_info;
    
    public function __construct( $rawinfo )
    {
        $this->raw_info = $rawinfo;
    }
    
    public function name()
    {
        return $this->raw_info->iface;
    }
    
    public function version()
    {
        return $this->raw_info->version;
    }
    
    public function inherits()
    {
        return $this->raw_info->inherits;
    }
    
    public function funcs()
    {
        return $this->raw_info->funcs;
    }
    
    public function constraints()
    {
        return $this->raw_info->constraints;
    }
}