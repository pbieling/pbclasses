<?php

namespace PbClasses\Tasks;
use \PbClasses\Exception\Logging as ExcLogging;

/**
 * Description of Runner
 *
 * @author Peter Bieling - info@media-palette.de - 2017-06-06
 */
class Runner {

    protected $db;
    protected $metaTable;

    public function __construct($metaTable, $ids = null) {
        $this->db = \PbClasses\Conf::get('db');
        $this->metaTable = $metaTable;
        if (!$this->db) {
            throw new \Exception('Missing database connection.');
        }



        $where = "RunningMode != 'off' AND RunningStatus = '0' ";


        //if ($ids === null) { //select all

        if (is_integer($ids)) {
            $where .= "AND ID = $ids";
        } elseif (is_array($ids)) {
            foreach ($ids as $id) {
                if (!is_int($id)) {
                    throw new \Exception('Wrong Type of ID');
                }
                $joinedIds = join(', ', $ids);
                $where .= "AND ID IN($joinedIds)";
            }
        }
        //Reset Status 1 (OK to 0 (prepared for new run)
        $this->prepareRun(); //not earlier because an exception is possible before.

        $this->selectTables($where);
    }

    protected function prepareRun() {
        $where = "RunningMode != 'off' AND RunningStatus = '1' ";
        $upd = "RunningStatus = 0";

        $this->db->update($this->metaTable, $upd, '', $where);
    }

    protected function selectTables($where) {#
        $what = "ID, TaskTable, RunningMode";
        $res = $this->db->selectAssoc($this->metaTable, $what, $where);
        if (!$res) {
            return;
        }

        foreach ($res as $row) {
            $this->handleSgTaskTable($row);
        }
    }

    protected function handleSgTaskTable($row) {
        $whereArr = array('ID' => $row['ID']);
        $statusObj = new \PbClasses\Tasks\ProcessTable($this->db, $this->metaTable, $whereArr, 'RunningStatus');
        if (!$statusObj->setRunning()) {
            $err = 'Status update set running not possible.';
            new ExcLogging($err, __FILE__, __LINE__);
            return false;
        }

        //new \PbClasses\Debug\Logging($row, __FILE__, __LINE__);

        try {
            $tasksObj = new \PbClasses\Tasks\Tasks($row['TaskTable'], $this->db);
            if (true !== $tasksObj->run()) {


                $statusObj->setError() || new ExcLogging($statusObj->getError(), __FILE__, __LINE__);
                return false;
            }

        } catch (\Exception $ex) {
           
            new ExcLogging( $ex->getMessage(), __FILE__, __LINE__);
            $statusObj->setError() || new ExcLogging($statusObj->getError(), __FILE__, __LINE__);
            return false;
        }



        if (!$statusObj->setOK()) {
            
            new ExcLogging($statusObj->getError(), __FILE__, __LINE__);
            return false;
        }
    }

}
