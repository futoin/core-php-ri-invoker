<?php

namespace FutoIn\RI\Invoker\Details;

use \FutoIn\RI\Invoker\AdvancedCCM;

/**
 * @internal
 * @ignore
 */
class AdvancedCCMImpl 
    extends SimpleCCMImpl
{
    private $development_checks = true;
    private $specdirs = array();
    
    public function __construct( $options )
    {
        parent::__construct(
            isset( $options[AdvancedCCM::OPT_VAULT] )
                    ? $options[AdvancedCCM::OPT_VAULT] : null,
            isset( $options[AdvancedCCM::OPT_CURL] )
                    ? $options[AdvancedCCM::OPT_CURL] : null
        );
        
        if ( isset( $options[AdvancedCCM::OPT_SPEC_DIRS] ) )
        {
            $this->specdirs = (array)$options[AdvancedCCM::OPT_SPEC_DIRS];
        }
        
        if ( isset( $options[AdvancedCCM::OPT_PROD_MODE] ) )
        {
            $this->development_checks = !$options[AdvancedCCM::OPT_PROD_MODE];
        }
    }

    
    public function onRegister( \FutoIn\AsyncSteps $as, $info )
    {
        $this->loadSpec( $as, $info );
    }
    
    private function loadSpec( $as, $info )
    {
        $raw_spec = null;
        $fn = $info->iface . '-' . $info->version . '-iface.json';
        
        foreach ( $this->specdirs as $v )
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
            $as->error_info = "Failed to load valid spec for ".$info->iface.":".$info->version;
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
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
                        $as->error_info = "Missing type for params";
                        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
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
                        $as->error_info = "Missing type for result";
                        throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
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
        $this->loadSpec( $sup_info );
        
        $info->constraints += $sup_info->constraints;
        $info->funcs += $sup_info->funcs;
    }
    
    public function checkType( $as, $type, $var, $val )
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
            $as->error_info = "Type mismatch ($rtype) for $var";
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
    }
    
    public function checkParams( \FutoIn\AsyncSteps $as, $ctx, &$params )
    {
        $info = $ctx->info;
        $name = $ctx->name;
        
        if ( !isset( $info->funcs[$name] ) )
        {
            $as->error_info = "Unknown interface function";
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        $f = $info->funcs[$name];
        
        if ( $ctx->upload_data &&
             !$f->rawupload )
        {
            $as->error_info = "Raw upload is not allowed";
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        if ( empty( $f->params ) && count( $params ) )
        {
            $as->error_info = "No params are defined";
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        // Check params
        foreach ( $params as $k => $v )
        {
            if ( !isset( $f->params[$k] ) )
            {
                $as->error_info = "Unknown parameter $k";
                throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
            }
            
            $this->checkType( $as, $f->params[$k]->type, $k, $v );
        }
        
        // Check missing params
        foreach ( $f->params as $k => $v )
        {
             if ( !isset( $params[$k]) &&
                  !isset( $v->{"default"} ) )
             {
                $as->error_info = "Missing parameter $k";
                throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
             }
        }
    }
        
    
    public function createMessage( \FutoIn\AsyncSteps $as, $ctx, &$params )
    {
        if ( $this->development_checks )
        {
            $this->checkParams( $as, $ctx, $params );
        }
        
        $info = $ctx->info;

        $req = array(
            'f' => $info->iface . ':' . $info->version . ':' . $ctx->name,
            'p' => $params
        );
        
        if ( $info->creds !== null )
        {
            if ( $info->creds === 'master' )
            {
                // TODO: add signature
            }
            else
            {
                $req['sec'] = $info->creds;
            }
        }
        
        $as->success( $req );
    }
    
    static private $standard_errors = array(
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
    
    public function onMessageResponse( \FutoIn\AsyncSteps $as, $ctx, $rsp )
    {
        $info = $ctx->info;
        $func_info = $info->funcs[$ctx->name];
        
        // Check raw result
        if ( $func_info->rawresult )
        {
            $as->error_info = "Raw result is expected";
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    
        // Check signature
        if ( $info->creds === 'master' )
        {
            // TODO: check signature
        }
        
        // Check for exeception
        if ( isset( $rsp->e ) )
        {
            $error = $rsp->e;
            
            if ( isset( $func_info->throws[$error] ) ||
                 isset( self::$standard_errors[$error] ) )
            {
                $as->error_info = "Executor-generated error";
                $as->error( $error );
                return;
            }
            else
            {
                $as->error_info = "Not expected exception from Executor";
                throw new \FutoIn\Error( \FutoIn\Error::InternalError );
            }
        }
        
        // check result variables
        if ( isset( $func_info->result ) )
        {
            $resvars = $func_info->result;
            
            foreach ( $rsp->r as $k => $v )
            {
                if ( !isset( $resvars[$k] ) )
                {
                    $as->error_info = "Unknown result variable $k";
                    throw new \FutoIn\Error( \FutoIn\Error::InternalError );
                }
                
                $this->checkType( $as, $resvars[$k]->type, $k, $v );
                unset( $resvars[$k] );
            }
            
            if ( count( $resvars ) )
            {
                $as->error_info = "Missing result variables";
                throw new \FutoIn\Error( \FutoIn\Error::InternalError );
            }
        
            // Success
            $as->success( $rsp->r );
        }
        else
        {
            $as->success();
        }
    }
    
    public function onDataResponse( \FutoIn\AsyncSteps $as, $ctx )
    {
        if ( $ctx->info->funcs[$ctx->name]->rawresult )
        {
            $as->success( $as->_futoin_response );
        }
        else
        {
            $as->error_info = "Raw result is not expected";
            $as->error( \FutoIn\Error::InternalError );
        }
    }
}
