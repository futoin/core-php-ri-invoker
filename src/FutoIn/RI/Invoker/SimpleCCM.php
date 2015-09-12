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
    use \Sabre\Event\EventEmitterTrait;
    
    protected $iface_info = [];
    protected $iface_impl = [];
    protected $impl = null;
    
    /**
     * Construct
     *
     * @param array $curl_opts - Name-Value pairs for curl_setopt()
     */
    public function __construct( array $options = [] )
    {
        $this->impl = new Details\SimpleCCMImpl( $options );
    }

    /** @see \FutoIn\SimpleCCM */
    public function register( \FutoIn\AsyncSteps $as, $name, $ifacever, $endpoint, $credentials=null, $options=null )
    {
        // Unregister First
        if ( array_key_exists( $name, $this->iface_info ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "Already registered" );
        }

        // Check ifacever
        if ( !preg_match('/^(([a-z][a-z0-9]*)(\\.[a-z][a-z0-9]*)+):(([0-9]+)\\.([0-9]+))$/', $ifacever, $m ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "Invalid ifacever" );
        }

        $iface = $m[1];
        $mjrmnr = $m[4];
        $mjr = $m[5];
        $mnr = $m[6];

        if ( !is_string( $endpoint ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "Invalid endpoint" );
        }

        $endpoint = preg_replace( '/^secure\\+/', '', $endpoint, 1, $repcnt );
        $secure_channel = ( $repcnt > 0 );
        
        // Silently map WebSockets to HTTP/HTTPS as per FTN7 spec, if not supported
        $endpoint = preg_replace( '/^ws(s?):\\/\\//', 'http${1}://', $endpoint );
        
        if ( !$secure_channel &&
            preg_match( '/^https:/', $endpoint ) )
        {
            $secure_channel = true;
        }
        
        $url = parse_url( $endpoint );
    
        if ( $url['scheme'] === 'self' )
        {
            $impl = str_replace( '.', '\\', $url['host'] );
        }
        else
        {
            $impl = "\FutoIn\RI\Invoker\Details\NativeInterface";
        }
        
        $options = array_merge( (array)$options, $this->impl->options );

        $info = new Details\RegistrationInfo;
        $info->iface = $iface;
        $info->version = $mjrmnr;
        $info->mjrver = $mjr;
        $info->mnrver = $mnr;
        $info->endpoint = $endpoint;
        $info->creds = $credentials;
        $info->secure_channel = $secure_channel;
        $info->impl = $impl;
        $info->regname = $name;
        $info->options = &$options;
        
        $this->iface_info[$name] = $info;
        
        $this->impl->onRegister( $as, $info );

        $as->add( function( $as ) use ( $name, $ifacever, $info ) {
            $this->emit( 'register', [ $name, $ifacever, $info ] );
        } );
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function iface( $name )
    {
        if ( !array_key_exists( $name, $this->iface_info ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        $regname = $this->iface_info[ $name ]->regname;
        
        if ( !isset( $this->iface_impl[$regname] ) )
        {
            $info = $this->iface_info[$regname];
            $this->iface_impl[$regname] = new $info->impl( $this->impl, $info );
        }
        
        return $this->iface_impl[$regname];
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function unRegister( $name )
    {
        if ( array_key_exists( $name, $this->iface_info ) )
        {
            $info = $this->iface_info[$name];
            $regname = $info->regname;
            
            if ( $regname === $name )
            {
                unset( $this->iface_info[$regname] );
                unset( $this->iface_impl[$regname] );
                
                if ( $info->aliases ) foreach ( $info->aliases as $v )
                {
                    unset( $this->iface_info[$v] );
                }
            }
            else
            {
                unset( $this->iface_info[$name] );
                array_splice( $info->aliases, array_search( $name, $info->aliases, true ) );
            }
            
            $this->emit( 'unregister', [ $name, $info ] );
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
    public function cache( $bucket = 'default' )
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

        $info = $this->iface_info[$name];
        $this->iface_info[$alias] = $info;

        if ( is_array( $info->aliases ) )
        {
            $info->aliases[] = $alias;
        }
        else
        {
            $info->aliases = [$alias];
        }
        
        $this->emit( 'register', [ $alias, $info->iface . ':' . $info->version, $info ] );
    }
    
    /** @see \FutoIn\SimpleCCM */
    public function close()
    {
        foreach ( $this->iface_impl as $impl )
        {
            $impl->emit( 'close' );
        }

        $this->emit( 'close' );
    }
    
    /** @internal */
    public function __clone()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
}

/**
 * CCM regiser event. Fired on new interface registration.
 * ( name, ifacever, info )
 * @event SimpleCCM#register
 */

/**
 * CCM regiser event. Fired on interface unregistration.
 * ( name, info )
 * @event SimpleCCM#unregister
 */

/**
 * CCM close event. Fired on CCM shutdown.
 * @event SimpleCCM#close
 */