<?php

namespace PbClasses\Backend\Ajax;

use PbClasses\Conf;
use PbClasses\File\DirList;
use PbClasses\Tpl\Form\MakeSelect;
use PbClasses\PbTpl;

/**
 * @author Peter Bieling - info@media-palette.de
 */
class DokuList extends \PbClasses\Ajax\AbstractResponse {
    protected $tableConfDir;

    public function action() {
        \PbClasses\Ajax\SessionStart::start();

        $baseDocDir = Conf::get('MODULE_PATH') . 'dokus';
        $docuDirList = DirList::get($baseDocDir, 'php');

        if (false === $docuDirList) {
            $this->errorOutput('Teschnisher Fehler: Verzeichnis nicht gefunden', __FILE__, __LINE__);
        }
        
        if (isset($docuDirList['common'])) {
            unset($docuDirList['common']);
        }

        $tplFlle = Conf::get('BACKTPLPATH') . 'common/form-elements.tpl';
        try {
            $t = new PbTpl($tplFlle);
        } catch (\Exception $exc) {
            $this->errorOutput('Teschnisher Fehler: Verzeichnis nicht gefunden', __FILE__, __LINE__, $exc->getMessage());
        }
        
        $options = MakeSelect::get($t, array_keys($docuDirList));
        /*
         * <select class="form-control{SELECTION_CLASSES}" id="{SELECTION_ID}" name="{SELECTION_NAME}">
          {SELECT_OPTIONS}
          </select>
         */
        $seRe = ['selection_classes' => ' tableconfig-sel',
            'selection_id' => 'docu-module-selection',
            'selection_name' => 'docu-module-selection',
            'select_options' => $options
        ];
        $this->rslt['html'] = $t->fillTpl('select_element', $seRe);

        $this->okOutput();
    }

}
