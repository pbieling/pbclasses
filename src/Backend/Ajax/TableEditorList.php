<?php

namespace PbClasses\Backend\Ajax;

use PbClasses\Conf;
use PbClasses\File\FileList;
use PbClasses\Tpl\Form\MakeSelect;
use PbClasses\PbTpl;

/**
 * @author Peter Bieling - info@media-palette.de
 */
class TableEditorList extends \PbClasses\Ajax\AbstractResponse {

    //  protected $db;

    protected $tableConfDir;

    public function action() {
        \PbClasses\Ajax\SessionStart::start();

        $tableConfDir = Conf::get('APATH') . 'tableconf';
        $confFileList = FileList::get($tableConfDir, 'php');
        $tplFlle = Conf::get('BACKTPLPATH') . 'common/form-elements.tpl';

        if (false === $confFileList) {
            $this->errorOutput('Teschnisher Fehler: Verzeichnis nicht gefunden', __FILE__, __LINE__);
        }

       // new \PbClasses\Debug\Logging($confFileList, __FILE__, __LINE__);
        try {
            $t = new PbTpl($tplFlle);
            $options = MakeSelect::get($t, array_keys($confFileList));
            /*
             * <select class="form-control{SELECTION_CLASSES}" id="{SELECTION_ID}" name="{SELECTION_NAME}">
    {SELECT_OPTIONS}
</select> 
             */
            $seRe = ['selection_classes' => ' tableconfig-sel',
                     'selection_id' => 'tableconfig-selection',
                     'selection_name' => 'tableconfig-selection',
                     'select_options' => $options
                ];
            $this->rslt['html'] = $t->fillTpl('select_element', $seRe );
            
          //  new \PbClasses\Debug\Logging($options, __FILE__, __LINE__);
            
        } catch (\Exception $exc) {
            $this->errorOutput('Teschnisher Fehler: Verzeichnis nicht gefunden', __FILE__, __LINE__, $exc->getMessage());
        }



        $this->okOutput();
    }

}
