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
    /**
     * Construct
     *
     * @param array $curl_opts - Name-Value pairs for curl_setopt()
     */
    public function __construct( array $options = [] )
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