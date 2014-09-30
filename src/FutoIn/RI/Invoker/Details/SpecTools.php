<?php
/**
 * SpecTools
 *
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Invoker\Details;

/**
 * These are internal helper tools for Spec processing, not standardized!
 * @internal
 */
class SpecTools
{
    public static $standard_errors = array(
        \FutoIn\Error::UnknownInterface => 1,
        \FutoIn\Error::NotSupportedVersion => 1,
        \FutoIn\Error::NotImplemented => 1,
        \FutoIn\Error::Unauthorized => 1,
        \FutoIn\Error::InternalError => 1,
        \FutoIn\Error::InvalidRequest => 1,
        \FutoIn\Error::DefenseRejected => 1,
        \FutoIn\Error::PleaseReauth => 1,
        \FutoIn\Error::SecurityError => 1,
    );

    public static function loadSpec( \FutoIn\AsyncSteps $as, RegistrationInfo $info, array $specdirs )
    {
        $raw_spec = null;
        $fn = $info->iface . '-' . $info->version . '-iface.json';
        
        foreach ( $specdirs as $v )
        {
            $v = $v . DIRECTORY_SEPARATOR . $fn;

            if ( file_exists( $v ) )
            {
                $raw_spec = file_get_contents( $v );
                $raw_spec = json_decode( $raw_spec );
                break;
            }
        }
        
        if ( !$raw_spec ||
             ( $info->iface != (string)$raw_spec->iface ) ||
             ( $info->version != (string)$raw_spec->version ) ||
             !isset( $raw_spec->funcs ) ||
             !is_object( $raw_spec->funcs ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "Failed to load valid spec for ".$info->iface.":".$info->version );
        }
        
        $info->funcs = (array)$raw_spec->funcs;
        
        foreach( $info->funcs as $f )
        {
            if ( isset( $f->params ) )
            {
                $f->params = (array) $f->params;
                $f->min_args = 0;
                
                foreach ( $f->params as $p )
                {
                    if ( !isset( $p->type ) )
                    {
                        $as-error( \FutoIn\Error::InvokerError, "Missing type for params" );
                    }
                    
                    if ( !isset( $p->{"default"} ) )
                    {
                        $f->min_args += 1;
                    }
                }
            }
            else
            {
                $f->params = array();
            }
            
            if ( isset( $f->result ) )
            {
                $f->result = (array) $f->result;
                
                foreach ( $f->result as $p )
                {
                    if ( !isset( $p->type ) )
                    {
                        $as->error( \FutoIn\Error::InvokerError, "Missing type for result" );
                    }
                }
            }
            
            if ( !isset( $f->rawupload ) )
            {
                $f->rawupload = false;
            }
            
            if ( !isset( $f->rawresult ) )
            {
                $f->rawresult = false;
            }
            
            if ( isset( $f->throws ) )
            {
                $f->throws = array_flip( $f->throws );
            }
            else
            {
                $f->throws = [];
            }
        }
        
        if ( isset( $raw_spec->requires ) )
        {
            $info->constraints = array_flip( $raw_spec->requires );
        }
        else
        {
            $info->constraints = [];
        }
        
        if ( !isset( $raw_spec->inherit ) )
        {
            return;
        }
        
        $sup_info = new RegistrationInfo;
        $ifacever = explode( ':', $raw_spec->inherit );
        assert( count( $ifacever ) == 2 );
        
        $sup_info->iface = $ifacever[0];
        $sup_info->version = $ifacever[1];
        $this->loadSpec( $as, $sup_info, $specdirs );
        
        $info->constraints += $sup_info->constraints;
        $info->funcs += $sup_info->funcs;
    }
    
    public static function checkFutoInType( \FutoIn\AsyncSteps $as, $type, $var, $val )
    {
        $rtype = '';
            
        switch( $type )
        {
            case 'boolean':
            case 'integer':
            case 'string':
            case 'array':
                $rtype = $type;
                break;
                
            case 'map':
                $rtype = 'object';
                break;
                
            case 'number':
                $rtype = 'string';
                break;
        }
        
        if ( gettype($val) !== $rtype )
        {
            $as->error( \FutoIn\Error::InvokerError, "Type mismatch ($rtype) for $var" );
        }
    }

}