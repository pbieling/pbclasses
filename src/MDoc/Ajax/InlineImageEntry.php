<?php

namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbModule_commondocu\MConf;

/**
 * Description of InlineImageEntry
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 28.05.2018, 15:38:46
 */
class InlineImageEntry extends \PbClasses\Ajax\AbstractResponse {

    protected $db;
    protected $conf;
    protected $img_id;

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $this->img_id = (int) $this->P['img_id'];
       // new \PbClasses\Debug\Logging($this->P, __FILE__, __LINE__);

        if (!$this->img_id) {
            $this->rslt['errortext'] = 'Keine gültige ID übermittelt';
            $this->output(__FILE__, __LINE__);
        }

        $this->conf = MConf::get('docu_conf');
        $this->db = Conf::get('db');

        $elementId = $this->makeIndexEntry();
        $this->makeImageEntry($elementId);





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
        $imageType = $this->P['image_type'];
        $imageTypeArr = & $this->conf['doc_vars']['image_types'];
        if (!isset($imageTypeArr[$imageType])) {
            $this->rslt['errortext'] = 'Falscher Image-Type.';
            $this->output(__FILE__, __LINE__);
        }

        $inArr = ['TemplateId' => $imageTypeArr[$imageType], 'ElementType' => $imageType, 'ReplaceList' => 'image_id'];
        try {
            $this->db->insert($this->conf['doc_tables']["PAGE_INDEX_INLINE"], $inArr);
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
            $this->rslt['errortext'] = 'Es ist ein Datenbankfehler aufgetreten.';
            $this->output(__FILE__, __LINE__);
        }

        return $this->db->insertId();
    }

    
    /*
     * Eingetragen wird nur Die Bild-ID. Der Rest wird bei der Generierung aus der Bildtabelle geholt.
     * In einer späteren Fassung könnte man die Bildblöcke noch weiter ausbauen und bei der Auswahl noch 
     * Einfügemöglichkeiten anbieten. Das sollte hier zunächst auf die Description beschränkt bleiben.
     */
    protected function makeImageEntry($entryId) {
        $imgArr = $this->getImageArr();

        //PAGE_CONTENT_INLINE
        //CONTENT: (ID) ElementId 	Placeholder 	Entry 	Filter
        //$entry = $this->conf['doc_vars']['img_http_dir'] . $imgArr['ImgFile'];
        //$inArr = ['ElementId' => $entryId, 'Placeholder' => 'http_path', 'Entry' => $entry, 'Filter' => ''];

        $inArr = ['ElementId' => $entryId, 'Placeholder' => 'image_id', 'Entry' => $this->img_id, 'Filter' => 'add_imgdata'];
        try {
            $this->db->insert($this->conf['doc_tables']["PAGE_CONTENT_INLINE"], $inArr);
        } catch (\Exception $exc) {
            $this->rslt['errortext'] = 'Es ist ein Datenbankfehler aufgetreten.';
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
            $this->output(__FILE__, __LINE__);
        }
    }

    protected function getImageArr() {
        $table = $this->conf['doc_tables']['PAGE_IMAGES_INDEX'];
        $where = array('ID' => $this->img_id);
        try {
            $res = $this->db->selectAssoc($table, '', $where, '', 'LIMIT 1');
        } catch (\Exception $exc) {
            $this->rslt['errortext'] = 'Es ist ein Datenbankfehler aufgetreten.';
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
            $this->output(__FILE__, __LINE__);
        }
        if (!$res) {
            $this->rslt['errortext'] = 'Es ist ein Datenbankfehler aufgetreten.';
            $this->output(__FILE__, __LINE__);
        }
        return $res[0];
    }

}
