<?php

namespace PbClasses\Backend\Ajax;

use PbClasses\Conf;

/**
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 03.03.2018, 13:50
 */
class UpdateSgValue extends \PbClasses\Ajax\AbstractResponse {

    protected $db;

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $id = (int) $this->P['id'];
        if (!$id) {
            $this->rslt['errortext'] = 'Keine gültige ID übermittelt';
            $this->output(__FILE__, __LINE__);
        }
        $this->db = Conf::get('db');
        if (isset($this->P['value']) && isset($this->P['type']) && $this->P['type'] === 'accesslevel') {
            $this->makeUpdate($id);
        } else {
             $this->rslt['errortext'] = 'Technischer Fehler - Falscher Typ';
             $this->output(__FILE__, __LINE__);
        }
        $this->rslt['succ'] = 'OK';
        $this->output();
    }

    protected function makeUpdate($id) {
        $updArr = ['AccLevel' => $this->P['value']];
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
