<?php

namespace PbClasses\Access\Ajax;

use PbClasses\Conf;

class UpdateGroupsForPage extends \PbClasses\Ajax\AbstractResponse {

    protected $db;
    protected $pid;
    protected $accTable;

    #[\Override]
    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $this->db = Conf::get('db');
        $this->accTable = Conf::get(['access_tables', 'PAGE2GROUPS_TABLE']);

        if (empty($this->P['pid'])) {
            $this->errorOutput('Fehlende Seiten-ID', __FILE__, __LINE__);
        }
        $this->pid = (int) $this->P['pid'];
        
        
        $this->addGroup();
        $this->removeGroup();
        $this->okOutput();
    }

    protected function addGroup() {
        if (empty($this->P['add']))  {
            return;
        }

        foreach ($this->P['add'] as $gid) {
            $inArr = [
                'PID' => $this->pid,
                'GID' => $gid,
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

    protected function removeGroup() {
        if (empty($this->P['remove'])) {
            return;
        }

        foreach ($this->P['remove'] as $gid) {
            //UID GID. CrBy CrDate
            $whereArr = [
                'PID' => $this->pid,
                'GID' => $gid,
            ];
            try {
                $this->db->delete($this->accTable, $whereArr); //ignore
            } catch (\Exception $exc) {
                $this->errorOutput($exc->getMessage(), __FILE__, __LINE__);
            }
        }
    }
}
