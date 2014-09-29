<?php
/**
 * Simple FutoIn Connection and Credentials Manager Reference Implementation
 *
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Invoker;

/**
 * Simple CCM - Reference Implementation
 *
 * @warning BLOCKING in current implementation - SHOULD NOT be used in processes handling multiple clients
 *
 * @see http://specs.futoin.org/final/preview/ftn7_iface_invoker_concept-1.html
 * @api
 */
class SimpleCCM
    implements \FutoIn\Invoker\SimpleCCM
{
    protected $iface_info = [];
    protected $iface_impl = [];
    protected $impl = null;
    
    /**
     * Construct
     *
     * @param array $curl_opts - Name-Value pairs for curl_setopt()
     */
    public function __construct( VaultProvider $vault=null, $curl_opts = null )
    {
        $this->impl = new Details\SimpleCCMImpl( $vault, $curl_opts );
    }

    /** @see \FutoIn\SimpleCCM */
    public function register( \FutoIn\AsyncSteps $as, $name, $ifacever, $endpoint, $credentials=null )
    {
        if ( ! preg_match('/^([a-z][a-z0-9]*)(\\.[a-z][a-z0-9]*)+:[0-9]+\\.[0-9]+$/i', $ifacever ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
    
        $ifacever = explode( ':', $ifacever );
        $mjrmnr = explode( '.', $ifacever[1] );
        
        // Unregister First
        if ( array_key_exists( $name, $this->iface_info ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        // Silently map WebSockets to HTTP/HTTPS as per FTN7 spec
        $endpoint = preg_replace( '/^ws(s?):\/\//', 'http${1}://', $endpoint );
        
        $info = new Details\RegistrationInfo;
        $info->iface = $ifacever[0];
        $info->version = $ifacever[1];
        $info->mjrver = $mjrmnr[0];
        $info->mnrver = $mjrmnr[1];
        $info->endpoint = $endpoint;
        $info->creds = $credentials;
        
        $url = parse_url( $endpoint );
        
        if ( $url['scheme'] === 'self' )
        {
            $info->impl = str_replace( '.', '\\', $url['host'] );
        }
        else
        {
            $info->impl = "\FutoIn\RI\Invoker\Details\NativeInterface";
        }
        
        $this->iface_info[$name] = $info;
        
        $this->impl->onRegister( $as, $info );
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function iface( $name )
    {
        if ( !array_key_exists( $name, $this->iface_info ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        if ( !isset( $this->iface_impl[$name] ) )
        {
            $info = $this->iface_info[$name];
            $this->iface_impl[$name] = new $info->impl( $this->impl, $info );
        }
        
        return $this->iface_impl[$name];
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function unRegister( $name )
    {
        if ( array_key_exists( $name, $this->iface_info ) )
        {
            $info = &$this->iface_info[$name];
            unset( $this->iface_info[$name] );
            unset( $this->iface_impl[$name] );
            
            if ( $info->aliases ) foreach ( $info->aliases as $v )
            {
                unset( $this->iface_info[$v] );
            }
        }
        else
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function defense()
    {
        return $this->iface( self::SVC_DEFENSE );
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function log()
    {
        return $this->iface( self::SVC_LOG );
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function burst()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function cacheL1()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
    }

    /** @see \FutoIn\SimpleCCM */
    public function cacheL2()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
    }

    /** @see \FutoIn\SimpleCCM */
    public function cacheL3()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function assertIface( $name, $ifacever )
    {
        $ifacever = explode( ':', $ifacever );
        assert( count($ifacever) == 2 );
        $mjrmnr = explode( '.', $ifacever[1] );
        assert( count($mjrmnr) == 2 );

        if ( !array_key_exists( $name, $this->iface_info ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        $info = $this->iface_info[$name];
        
        if ( ( $ifacever[0] !== $info->iface ) ||
             ( $mjrmnr[0] !== $info->mjrver ) || 
             ( $mjrmnr[1] > $info->mnrver ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function alias( $name, $alias )
    {
        if ( !array_key_exists( $name, $this->iface_info ) ||
             array_key_exists( $alias, $this->iface_info ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        $this->iface_info[$alias] = &$this->iface_info[$name];
        
        if ( is_array( $this->iface_info[$name]->aliases ) )
        {
            $this->iface_info[$name]->aliases[] = $alias;
        }
        else
        {
            $this->iface_info[$name]->aliases = [$alias];
        }
    }
}
