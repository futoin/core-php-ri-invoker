<?php
/**
 * Advanced FutoIn Connection and Credentials Manager Reference Implementation
 *
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Invoker;

/**
 * Advanced CCM - Reference Implementation
 *
 * @warning BLOCKING in current implementation - SHOULD NOT be used in processes handling multiple clients
 *
 * @see http://specs.futoin.org/final/preview/ftn7_iface_invoker_concept-1.html
 * @api
 */
class AdvancedCCM
    extends SimpleCCM
    implements \FutoIn\Invoker\AdvancedCCM
{
    /** Secure Vault instance, if Master Service/Client model is used */
    const OPT_VAULT = 'vault';
    
    /** cURL options to override some of the default values, like timeouts */
    const OPT_CURL = 'curl';
    
    /** array of directories where to search for iface specs named by FTN3 standard
        
        Note: It can be URL, if supported by file_get_contents(), but discouraged
    */
    const OPT_SPEC_DIRS = 'specdirs';
    
    /** Disable extra sanity checks for production mode performance */
    const OPT_PROD_MODE = 'prodmode';

    /**
     * Construct
     *
     * @param array $curl_opts - Name-Value pairs for curl_setopt()
     */
    public function __construct( array $options )
    {
        $this->impl = new Details\AdvancedCCMImpl( $options );
    }
    
    /** @see \FutoIn\AdvancedCCM */
    public function initFromCache( \FutoIn\AsyncSteps $as, $cache_l1_endpoint )
    {
        /** TODO */
        $as->error( \FutoIn\Error::NotImplemented );
    }
    
    /** @see \FutoIn\AdvancedCCM */
    public function cacheInit( \FutoIn\AsyncSteps $as )
    {
        // TODO:
        $as->error( \FutoIn\Error::NotImplemented );
    }
}