<?php
/**
 * @package FutoIn\Core\PHP\RI\Invoker
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */
 
use \FutoIn\RI\AsyncToolTest;
use \FutoIn\RI\Invoker\AdvancedCCM;

/**
 * @ignore
 */
class AdvancedCCMTest extends SimpleCCMTest
{
    public function setUp()
    {
        $this->as = new \FutoIn\RI\ScopedSteps();
        $this->ccm = new AdvancedCCM(array(
            AdvancedCCM::OPT_SPEC_DIRS => __DIR__ . DIRECTORY_SEPARATOR . 'specs',
            AdvancedCCM::OPT_VAULT => null,
        ));
    }
    
    public function tearDown()
    {
        $this->as = null;
        $this->ccm = null;
    }

}