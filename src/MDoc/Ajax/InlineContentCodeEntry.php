<?php

namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbModule_commondocu\MConf;

/**
 * @author Peter Bieling - info@media-palette.de
 */
class InlineContentCodeEntry extends \PbClasses\Ajax\AbstractResponse {

    protected $db;
    protected $conf;

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $id = (int) $this->P['id'];


        if (!$id) {
            $this->rslt['errortext'] = 'Keine gültige ID übermittelt';
            $this->output(__FILE__, __LINE__);
        }

        $this->conf = MConf::get('docu_conf');
        $this->db = Conf::get('db');
        $elementId = $this->makeIndexEntry();
        $this->makeCodeEntry($elementId);

        /*
         *         "PAGE_INDEX_INLINE" => \PbClasses\Conf::get('table_prefix') . 'doc_index_inline',
          "PAGE_CONTENT_INLINE" => \PbClasses\Conf::get('table_prefix') . 'doc_content_inline',
         * 
         * INDEX: ( ElementId ) TemplateId 	ElementType 	ReplaceList
         * 
         * CONTENT: (ID) ElementId 	Placeholder 	Entry 	Filter
         */
        $this->rslt['succ'] = 'OK';
        $this->rslt['inline_id'] = '%%' . $elementId . '%%';
        $this->output();
    }

    protected function makeIndexEntry() {
        $codeType = $this->P['code_type'];
        $codeTypeArr = & $this->conf['doc_vars']['code_types'];
        if (!isset($codeTypeArr[$codeType])) {
            $this->rslt['errortext'] = 'Falsche Code-Type.';
            $this->output(__FILE__, __LINE__);
        }

        $inArr = ['TemplateId' => $codeTypeArr[$codeType], 'ElementType' => $codeType, 'ReplaceList' => 'code'];
        try {
            $this->db->insert($this->conf['doc_tables']["PAGE_INDEX_INLINE"], $inArr);
        } catch (\Exception $exc) {
            $this->rslt['errortext'] = 'Es ist ein Datenbankfehler aufgetreten.';
            $this->output(__FILE__, __LINE__);
        }

        return $this->db->insertId();
    }

    protected function makeCodeEntry($entryId) {
        //PAGE_CONTENT_INLINE
        //CONTENT: (ID) ElementId 	Placeholder 	Entry 	Filter
        $inArr = ['ElementId' => $entryId, 'Placeholder' => 'code', 'Entry' => $this->P['src_code'], 'Filter' => 'specialchars'];

        try {
            $this->db->insert($this->conf['doc_tables']["PAGE_CONTENT_INLINE"], $inArr);
        } catch (\Exception $exc) {
            $this->rslt['errortext'] = 'Es ist ein Datenbankfehler aufgetreten.';
            $this->output(__FILE__, __LINE__);
        }
    }

}
