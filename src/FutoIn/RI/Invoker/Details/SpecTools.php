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
            if ( is_string( $v ) )
            {
                $v = $v . DIRECTORY_SEPARATOR . $fn;

                if ( file_exists( $v ) )
                {
                    $v = file_get_contents( $v );
                    $v = json_decode( $v );
                }
            }
            
            if ( is_object( $v ) &&
                 ( $info->iface === (string)$v->iface ) &&
                 ( $info->version === (string)$v->version ) &&
                 isset( $v->funcs ) &&
                 is_object( $v->funcs ) )
            {
                $raw_spec = $v;
                break;
            }
        }
        
        if ( !$raw_spec )
        {
            $as->error( \FutoIn\Error::InternalError, "Failed to load valid spec for ".$info->iface.":".$info->version );
        }
        
        $info->funcs = (array)$raw_spec->funcs;
        
        foreach( $info->funcs as $f )
        {
            $f->min_args = 0;

            if ( isset( $f->params ) )
            {
                $f->params = (array) $f->params;

                foreach ( $f->params as $p )
                {
                    if ( !isset( $p->type ) )
                    {
                        $as-error( \FutoIn\Error::InternalError, "Missing type for params" );
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
                        $as->error( \FutoIn\Error::InternalError, "Missing type for result" );
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
        
        $info->inherits = [];
        
        if ( !isset( $raw_spec->inherit ) )
        {
            return;
        }
        
        $sup_info = new RegistrationInfo;
        $ifacever = explode( ':', $raw_spec->inherit );
        assert( count( $ifacever ) == 2 );
        
        $sup_info->iface = $ifacever[0];
        $sup_info->version = $ifacever[1];
        self::loadSpec( $as, $sup_info, $specdirs );
        
        foreach ( $sup_info->funcs as $fn => $fdef )
        {
            if ( !isset( $info->funcs[$fn] ) )
            {
                $info->funcs[$fn] = $fdef;
                continue;
            }
            
            $sup_params = $fdef->params;
            $params = $info->funcs[$fn]->params;
            
            $sup_params_keys = array_keys( $sup_params );
            $params_keys = array_keys( $params );
            
            if ( count( $params ) < count( $sup_params_keys ) )
            {
                $as->error( \FutoIn\Error::InternalError, "Invalid param count for '$fn'" );
            }
            
            // Verify parameters are correctly duplicated
            for ( $i = 0; $i < count( $sup_params_keys ); ++$i )
            {
                $pn = $sup_params_keys[ $i ];

                if ( $pn !== $params_keys[ $i ] )
                {
                    $as->error( \FutoIn\Error::InternalError, "Invalid param order for '$fn/$pn'" );
                }
                
                if ( $sup_params[$pn]->type !== $params[$pn]->type )
                {
                    $as->error( \FutoIn\Error::InternalError, "Param type mismatch '$fn/$pn'" );
                }
            }
            
            // Verify that all added params have default value
            for ( ; $i < count( $params_keys ); ++$i )
            {
                $pn = $params_keys[ $i ];

                // NULL is allowed as well
                if ( !property_exists( $params[$pn], 'default' ) )
                {
                    $as->error( \FutoIn\Error::InternalError, "Missing default for '$fn/$pn'" );
                }
            }
            
            if ( $fdef->rawresult !== $info->funcs[$fn]->rawresult )
            {
                $as->error( \FutoIn\Error::InternalError, "'rawresult' flag mismatch for '$fn'" );
            }
            
            if ( $fdef->rawupload &&
                 !$info->funcs[$fn]->rawupload )
            {
                $as->error( \FutoIn\Error::InternalError, "'rawupload' flag is missing for '$fn'" );
            }
        }
        
        $info->inherits[] = $raw_spec->inherit;
        
        $info->inherits += $sup_info->inherits;
        
        if ( count( array_diff(
                array_keys( $sup_info->constraints ),
                $raw_spec->requires ) ) )
        {
            $as->error( \FutoIn\Error::InternalError, "Missing constraints from inherited" );
        }
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
            $as->error( \FutoIn\Error::InvalidRequest, "Type mismatch for parameter" );
        }
    }
    
    public static function checkConsistency( $as, $info )
    {
    }
}