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
    }

    
    public function onRegister( \FutoIn\AsyncSteps $as, $info )
    {
        $this->loadSpec( $info );
        $as->success();
    }
    
    private function loadSpec( $info )
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
            throw new \FutoIn\Error( \FutoIn\Error::InvokerError );
        }
        
        $info->funcs = $raw_spec->funcs;
        
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
    
    public function addSignature( \FutoIn\AsyncSteps $as, $info, $name, &$req )
    {
        // TODO: add  signature
    
        $as->success( $req );
    }
    
    public function onMessageResponse( \FutoIn\AsyncSteps $as, $info, $rsp )
    {
        // TODO: check signature
    
        if ( isset( $rsp->e ) )
        {
            $as->error( $rsp->e );
        }
        else
        {
            $as->success( $rsp->r );
        }
    }
}
