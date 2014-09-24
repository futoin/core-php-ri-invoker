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
 * Not standard, but essential interface for secure key storage and related operations
 * @api
 */
interface VaultProvider
{
    public function getKeyInfo( \FutoIn\AsyncSteps $as, $scope, $key_id=null, $sequence_id=null );
    public function setKey( \FutoIn\AsyncSteps $as, $scope, $enc_key_id, $key_id, $enc_key_data );
    public function genRegistrationKey( \FutoIn\AsyncSteps $as, $scope );
}
