<?php
namespace PbClasses\Tasks;

use PbClasses\Conf;

/**
 * @author Peter Bieling 2018-02-06
 */
class GenerateTaskClass {
    public function __construct($table, $row, $id = null) {
        $path = Conf::get('APATH') . 'classes/Tasks/' . $row['TaskClass'] . '.php';
        if (file_exists($path)) {
            return;
        }
        
        
        $sr = ['classname'  =>  $row['TaskClass']];
        
        $t = new \PbClasses\PbTpl(Conf::get('BACKTPLPATH') . 'system/task_class.tpl');
        
        $content = $t->fillTpl('main', $sr);
        
        if (! file_put_contents($path, $content)) {
            throw new \Exception('Die Task-Klasse konnte nicht angelegt werden.');
        }
        
    }
    
    
    

}
