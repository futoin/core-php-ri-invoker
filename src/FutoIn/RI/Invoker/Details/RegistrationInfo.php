<?php
/**
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Invoker\Details;

/**
 * @ignore
 * @internal
 */
class RegistrationInfo
{
    public $iface = null;
    public $version = null;
    public $mjrver = null;
    public $mnrver = null;
    public $endpoint = null;
    public $creds = null;
    public $def = null;
    public $impl = null;
    public $aliases = null;
    public $secure_channel = false;
    public $regname = null;
    
    public $inherits = null;
    public $funcs = null;
    public $constraints = null;
    public $options = null;
}
