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
        SpecTools::loadSpec( $as, $info, $this->specdirs );
    }
    
    public function checkParams( \FutoIn\AsyncSteps $as, $ctx, $params )
    {
        $info = $ctx->info;
        $name = $ctx->name;
        
        if ( !isset( $info->funcs[$name] ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "Unknown interface function" );
        }
        
        $f = $info->funcs[$name];
        
        if ( $ctx->upload_data &&
             !$f->rawupload )
        {
            $as->error( \FutoIn\Error::InvokerError, "Raw upload is not allowed" );
        }
        
        if ( empty( $f->params ) && count( get_object_vars( $params ) ) )
        {
            $as->error( \FutoIn\Error::InvokerError, "No params are defined" );
        }
        
        // Check params
        foreach ( $params as $k => $v )
        {
            if ( !isset( $f->params[$k] ) )
            {
                $as->error( \FutoIn\Error::InvokerError, "Unknown parameter $k" );
            }
            
            SpecTools::checkFutoInType( $as, $f->params[$k]->type, $k, $v );
        }
        
        // Check missing params
        foreach ( $f->params as $k => $v )
        {
             if ( !isset( $params->$k) &&
                  !isset( $v->{"default"} ) )
             {
                $as->error( \FutoIn\Error::InvokerError, "Missing parameter $k" );
             }
        }
    }
        
    
    public function createMessage( \FutoIn\AsyncSteps $as, $ctx, $params )
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
                 isset( SpecTools::$standard_errors[$error] ) )
            {
                $as->error( $error, "Executor-generated error" );
            }
            else
            {
                $as->error( \FutoIn\Error::InternalError, "Not expected exception from Executor" );
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
                    $as->error( \FutoIn\Error::InternalError, "Unknown result variable $k" );
                }
                
                SpecTools::checkFutoInType( $as, $resvars[$k]->type, $k, $v );
                unset( $resvars[$k] );
            }
            
            if ( count( $resvars ) )
            {
                $as->error( \FutoIn\Error::InternalError, "Missing result variables" );
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
            $as->error( \FutoIn\Error::InternalError, "Raw result is not expected" );
        }
    }
}
