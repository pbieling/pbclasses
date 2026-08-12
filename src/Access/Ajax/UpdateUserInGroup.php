<?php

namespace PbClasses\Access\Ajax;

use PbClasses\Conf;

class UpdateUserInGroup extends \PbClasses\Ajax\AbstractResponse {

    protected $db;
    protected $gid;
    protected $accTable;

    #[\Override]
    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $this->db = Conf::get('db');
        $this->accTable = Conf::get(['access_tables', 'USER2GROUPS_TABLE']);

        if (empty($this->P['gid'])) {
            $this->errorOutput('Fehlende Gruppen-ID', __FILE__, __LINE__);
        }
        $this->gid = (int) $this->P['gid'];
       
        $this->addToGroup();
        $this->removeFromGroup();
        $this->okOutput();
    }

    protected function addToGroup() {
        if (empty($this->P['add']))  {
            return;
        }

        foreach ($this->P['add'] as $uid) {
            //UID GID. CrBy CrDate
            $inArr = [
                'UID' => $uid,
                'GID' => $this->gid,
                'CrBy' => $_SESSION['uid'] ?? 0,
                'CrDate' => date('Y-m-d H:i:s')
            ];

            try {
                $this->db->insert($this->accTable, $inArr, '', true); //ignore
            } catch (\Exception $exc) {
                $this->errorOutput($exc->getMessage(), __FILE__, __LINE__);
            }
        }
    }

    protected function removeFromGroup() {
        if (empty($this->P['remove'])) {
            return;
        }

        foreach ($this->P['remove'] as $uid) {
            $whereArr = [
                'UID' => $uid,
                'GID' => $this->gid
            ];
            try {
                $this->db->delete($this->accTable, $whereArr); //ignore
            } catch (\Exception $exc) {
                $this->errorOutput($exc->getMessage(), __FILE__, __LINE__);
            }
        }
    }
}
