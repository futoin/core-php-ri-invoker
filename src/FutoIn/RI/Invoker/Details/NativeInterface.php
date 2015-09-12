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
    use \Sabre\Event\EventEmitterTrait;

    const MSG_MAXSIZE = 65536;

    private $ccmimpl;
    private $raw_info;
    private $iface_info = null;
    
    public function __construct( $ccmimpl, $info )
    {
        $this->ccmimpl = $ccmimpl;
        $this->raw_info = $info;
    }
    
    public function call( \FutoIn\AsyncSteps $as, $name, $params, $upload_data=null, $download_stream=null, $timeout=null )
    {
        $ctx = new \StdClass;
        $ctx->name = $name;
        $ctx->info = $this->raw_info;
        $ctx->upload_data = $upload_data;
        $ctx->download_stream = $download_stream;
        
        $params = (object)$params;
    
        // Create message
        //---
        $as->add(function($as) use ( $ctx, $params ) {
            $this->ccmimpl->createMessage( $as, $ctx, $params );
        });
        
        // Perform request
        //---
        $as->add(function($as, $req) use ( $ctx, $upload_data, $download_stream, $timeout ) {
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
                "Accept: application/futoin+json, */*"
            );
            
            $url = $this->raw_info->endpoint;
            
            if ( substr( $url, -1 ) !== '/' )
            {
                $url .= '/';
            }
            
            if ( $upload_data )
            {
                // Encode according to FTN5: HTTP integration
                //---

                // iface / ver / func
                $url .= str_replace( ':', '/', $req->f );
                
                // /sec
                if ( isset( $req->sec ) )
                {
                    $url .= '/' . $req->sec;
                }
                
                // Params as query
                if ( isset( $req->p ) )
                {
                    $url .= '?' . http_build_query( (array) $req->p );
                }
                //---
            
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
                    
                    # Disable cURL-specific 1 second delay (empty 'Expect' does not work)
                    $curl_opts[ CURLOPT_HTTP_VERSION ] = CURL_HTTP_VERSION_1_0;
                }
                else
                {
                    $upload_size = strlen( $upload_data );
                    $curl_opts[ CURLOPT_POST ] = true;
                    $curl_opts[ CURLOPT_POSTFIELDS ] = $upload_data;
                }
                
                $headers[] = "Content-Type: application/octet-stream";
                $headers[] = 'Content-Length: ' . $upload_size;
            }
            else
            {
                $headers[] = "Content-Type: application/futoin+json";
                $req = json_encode( $req, JSON_FORCE_OBJECT|JSON_UNESCAPED_UNICODE );
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
            if ( $timeout === null )
            {
                $timeout = $curl_opts[CURLOPT_TIMEOUT_MS];
            }
            
            if ( (int)$timeout > 0 )
            {
                $as->setTimeout( $timeout );
            }

            $as->add(function($as) use ( $curl ){
                $this->ccmimpl->multiCurlAdd( $as, $curl );
            });

            $as->add(function($as, $curl, $info ) use ($ctx, $download_stream) {
                $http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
                $content_type = curl_getinfo( $curl, CURLINFO_CONTENT_TYPE );
                $error = curl_error( $curl );
                curl_close( $curl );
                
                //---

                if ( $http_code !== 200 )
                {
                    $as->error( \FutoIn\Error::CommError, "HTTP:$http_code CURL:$error" );
                }
                elseif ( $download_stream )
                {
                    if ( $info['result'] === CURLE_OK )
                    {
                        $as->success( true );
                    }
                    else
                    {
                        $as->error( \FutoIn\Error::CommError, "CURL:$error" );
                    }
                }
                elseif ( $content_type === 'application/futoin+json' )
                {
                    $rsp = json_decode( $as->_futoin_response );
                    
                    if ( $rsp )
                    {
                        $this->ccmimpl->onMessageResponse( $as, $ctx, $rsp );
                    }
                    else
                    {
                        $as->error( \FutoIn\Error::CommError, "JSON:".json_last_error_msg() );
                    }
                }
                else
                {
                    $this->ccmimpl->onDataResponse( $as, $ctx );
                }
            });
        });
    }
    
    public function ifaceInfo()
    {
        if ( !$this->iface_info )
        {
            $this->iface_info = new InterfaceInfo( $this->raw_info );
        }
        
        return $this->iface_info;
    }
    
    public function bindDerivedKey( \FutoIn\AsyncSteps $as )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
    }
    
    public function __call( $name, $args )
    {
        if ( !$args )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        $as = array_shift( $args );
        $funcs = $this->raw_info->funcs;
        
        if ( !( $as instanceof \FutoIn\AsyncSteps ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
    
        if ( !is_array( $funcs ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "No function definition / Not AdvancedCCM" );
        }
    
        if ( !isset( $funcs[$name] ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "Unknown interface function" );
        }
        
        $func_info = $funcs[$name];
        
        if ( $func_info->rawupload )
        {
            $as->error( \FutoIn\Error::InvokerError, "Raw upload is required, use call() instead" );
        }
        
        $keys = array_keys( $func_info->params );
        
        if ( count( $args ) > count( $keys ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "Unknown parameters" );
        }
        elseif ( count( $args ) < $func_info->min_args )
        {
            $as->error( \FutoIn\Error::InvokerError, "Missing parameters" );
        }
        elseif ( count( $args ) < count( $keys ) )
        {
            array_splice( $keys, count( $args ) );
        }
        
        // Perform final call
        $params = array_combine( $keys, $args );
        $this->call( $as, $name, $params );
    }
}
