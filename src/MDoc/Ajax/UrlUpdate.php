<?php
namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbModule_commondocu\MConf;

/**
 * Description of UrlUpdate
 *
 * @author Peter Bieling - info@media-palette.de
 */
class UrlUpdate extends \PbClasses\Ajax\AbstractResponse {

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


        if ($this->P['url']) {
            $this->makeUpdate($id);
        }

        //var params = {id: row_id, url: posted_url, original_url : url, old_url: old_url,  conf_selection_val: conf_selection_val};
        $this->rslt['succ'] = 'OK';
        $this->output();
    }

    protected function makeUpdate($id) {
        $updArr = ['URL' => $this->P['url']];

        try {

            if ($this->db->update($this->conf['doc_tables']['PAGE_INDEX'], $updArr, '', ['ID' => $id])) {
                $this->rslt['succ'] = 'OK';
            } else {
                $this->rslt['errortext'] = 'Kein Update möglich';
                $this->output(__FILE__, __LINE__);
            }
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
            $this->rslt['errortext'] = 'Datenbankfehler beim Update';
            $this->output(__FILE__, __LINE__);
        }
    }

}
