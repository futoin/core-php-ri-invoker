<?php
/**
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Invoker\Details;

/**
 * @internal
 */
class InterfaceInfo
    implements \FutoIn\InterfaceInfo
{
    private $raw_info;
    
    public function __construct( $rawinfo )
    {
        $this->raw_info = $rawinfo;
    }
    
    public function name()
    {
        return $this->raw_info->iface;
    }
    
    public function version()
    {
        return $this->raw_info->version;
    }
    
    public function inherits()
    {
        return $this->raw_info->inherits;
    }
    
    public function funcs()
    {
        return $this->raw_info->funcs;
    }
    
    public function constraints()
    {
        return $this->raw_info->constraints;
    }
}