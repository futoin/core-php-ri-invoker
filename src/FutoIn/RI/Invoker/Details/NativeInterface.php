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
    const MSG_MAXSIZE = 65536;

    private $ccmimpl;
    private $raw_info;
    private $iface_info = null;
    
    public function __construct( $ccmimpl, $info )
    {
        $this->ccmimpl = $ccmimpl;
        $this->raw_info = $info;
    }
    
    public function call( \FutoIn\AsyncSteps $as, $name, $params, $upload_data=null, $download_stream=null )
    {
        // Create message
        //---
        $as->add(function($as) use ( $name, $params ) {
            $this->ccmimpl->createMessage( $as, $this->raw_info, $name, $params );
        });
        
        // Perform request
        //---
        $as->add(function($as, $req) use ( $upload_data, $download_stream ) {
            $curl_opts = array(
                CURLOPT_FORBID_REUSE => FALSE,
                CURLOPT_FRESH_CONNECT => FALSE,
                CURLOPT_NETRC => FALSE,
                CURLOPT_SSLVERSION => 3,
                CURLOPT_SSL_VERIFYPEER => TRUE,
                CURLOPT_FOLLOWLOCATION => FALSE,

                CURLOPT_CONNECTTIMEOUT_MS => 3000,
                CURLOPT_TIMEOUT_MS => 30000,

                CURLOPT_BINARYTRANSFER => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
            ) + $this->ccmimpl->curl_opts;
            
            $curl = curl_init();
            
            $headers = array(
                "Content-Type: application/octet-stream",
                "Accept: application/futoin+json, */*"
            );
            
            $url = $this->raw_info->endpoint;
            $req = json_encode( $req, JSON_FORCE_OBJECT|JSON_UNESCAPED_UNICODE );

            
            if ( $upload_data )
            {
                $url .= '?ftnreq='.base64_encode( $req );
            
                if ( is_resource( $upload_data ) )
                {
                    // Old C-style trick
                    fseek( $upload_data, -1, SEEK_END );
                    $upload_size = ftell( $upload_data ) + 1;
                    fseek( $upload_data, 0, SEEK_SET );
                    
                    $curl_opts[ CURLOPT_PUT ] = true;
                    $curl_opts[ CURLOPT_CUSTOMREQUEST ] = "POST";
                    $curl_opts[ CURLOPT_INFILE ] = $upload_data;
                    $curl_opts[ CURLOPT_INFILESIZE ] = $upload_size;
                    $headers['Content-Length'] = $upload_size;
                    
                    # Disable cURL-specific 1 second delay (empty 'Expect' does not work)
                    $curl_opts[ CURLOPT_HTTP_VERSION ] = CURL_HTTP_VERSION_1_0;
                }
                else
                {
                    $headers['Content-Length'] = strlen( $upload_data );
                    $curl_opts[ CURLOPT_POST ] = true;
                    $curl_opts[ CURLOPT_POSTFIELDS ] = $upload_data;
                }
            }
            else
            {
                $curl_opts[ CURLOPT_POST ] = true;
                $curl_opts[ CURLOPT_POSTFIELDS ] = $req;
            }
            
            if ( $download_stream )
            {
                $curl_opts[ CURLOPT_FILE ] = $download_stream;
            }
            else
            {
                $curl_opts[ CURLOPT_RETURNTRANSFER ] = true;
                
                // Limit size for security reasons
                $curl_opts[ CURLOPT_PROGRESSFUNCTION ] =
                    function( $curl, $full_dlsize, $dlsize ){
                        return ( $dlsize > self::MSG_MAXSIZE ) ? -1 : 0;
                    };
            }
            

            $curl_opts[ CURLOPT_HTTPHEADER ] = $headers;
            $curl_opts[ CURLOPT_URL ] = $url;
            
            curl_setopt_array( $curl, $curl_opts );
            
            // cURL multi
            //---
            $as->setTimeout( $curl_opts[CURLOPT_TIMEOUT_MS] * 1e3 );

            $as->add(function($as) use ( $curl ){
                $this->ccmimpl->multiCurlAdd( $as, $curl );
            });

            $as->add(function($as, $curl, $info ) use ($download_stream) {
                $http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
                $content_type = curl_getinfo( $curl, CURLINFO_CONTENT_TYPE );
                $error = curl_error( $curl );
                curl_close( $curl );
                
                //---

                if ( $http_code !== 200 )
                {
                    $as->error_info = "HTTP:$http_code RSP:$rsp CURL:$error";
                    $as->error( \FutoIn\Error::CommError );
                }
                elseif ( $download_stream )
                {
                    if ( $info['result'] === CURLE_OK )
                    {
                        $as->success( true );
                    }
                    else
                    {
                        $as->error_info = "CURL:$error";
                        $as->error( \FutoIn\Error::CommError );
                    }
                }
                elseif ( $content_type === 'application/futoin+json' )
                {
                    $rsp = json_decode( $as->_futoin_response );
                    
                    if ( $rsp )
                    {
                        $this->ccmimpl->onMessageResponse( $as, $rsp );
                    }
                    else
                    {
                        $as->error_info = "JSON:".json_last_error_msg();
                        $as->error( \FutoIn\Error::CommError );
                    }
                }
                else
                {
                    $this->ccmimpl->onDataResponse( $as );
                }
            });
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
    
    public function burst()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
    }
    
    public function bindDerivedKey( \FutoIn\AsyncSteps $as )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
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