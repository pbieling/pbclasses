<?php

namespace PbClasses\Backend\Ajax;

use PbClasses\Conf;

/**
 * Description of UrlUpdate
 *
 * @author Peter Bieling - info@media-palette.de
 */
class UrlUpdate extends \PbClasses\Ajax\AbstractResponse {

    protected $db;

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $id = (int) $this->P['id'];
        if (!$id) {
            $this->rslt['errortext'] = 'Keine gültige ID übermittelt';
            $this->output(__FILE__, __LINE__);
        }
        $this->db = Conf::get('db');


        if ($this->P['url']) {
            $this->makeUpdate($id);
        }

        if ($this->P['conf_selection_val']) {
            try {
                new \PbClasses\Backend\GenerateConfig($id, $this->P);
            } catch (\Exception $exc) {
                $err =  $exc->getMessage();
                new \PbClasses\Exception\Logging($err, __FILE__, __LINE__);
                
            }
        }

        //var params = {id: row_id, url: posted_url, original_url : url, old_url: old_url,  conf_selection_val: conf_selection_val};
        $this->rslt['succ'] = 'OK';
        $this->output();
    }

    protected function makeUpdate($id) {
        $updArr = ['URL' => $this->P['url']];

        try {

            if ($this->db->update(Conf::get('db_tables')['backnavi'], $updArr, '', ['ID' => $id])) {
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
