<?php
namespace PbClasses\Table\Ajax;

use PbClasses\Conf;

class TableDef  extends \PbClasses\Ajax\AbstractResponse {
    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        //new \PbClasses\Debug\Logging(Conf::getAll(), __FILE__, __LINE__);
        
        
        
    }
}
