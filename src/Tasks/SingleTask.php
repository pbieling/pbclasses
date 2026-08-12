<?php

namespace PbClasses\Tasks;

use \PbClasses\Exception\Logging as ExcLogging;

/**
 * Description of SingleTask
 * 
 *
 * @author Peter Bieling - 2017-06-07
 */
class SingleTask {

    protected $taskTable;
    protected $taskId;
    protected $fingerprint;
    protected $db;
    protected $taskRow;

    /**
     * 
     * @param type $taskTable
     * @param type $taskId
     * @param type $fingerprint must be the same as in the task table Fingerprint.
     * @return type
     */
    public function __construct($taskTable, $taskId, $fingerprint) {



        $this->taskTable = $taskTable;
        $this->taskId = $taskId;
        $this->fingerprint = $fingerprint;
        $this->db = \PbClasses\Conf::get('db');
        if (!$this->checkTask()) {
            return; //Kann nicht abgebrochen werden, das möglicherweise ein anderer Prozess beteiligt ist
        }
        $taskClass = \PbClasses\Conf::get('ownclasses_namespace') . 'Tasks\\' . $this->taskRow['TaskClass'];
        try {
            if (! \class_exists($taskClass)) {
                new ExcLogging($taskClass . ' nicht gefunden', __FILE__, __LINE__);

                $this->setStatus('ERROR');
            }
            
            $taskObj = new $taskClass();
            $succ = $taskObj->run($this);
        } catch (\Exception $exc) {
            new ExcLogging($taskClass . ' ' . $exc->getMessage(), __FILE__, __LINE__);
            $this->setStatus('ERROR');
            //echo $exc->getMessage();
        }
        if ($succ) {
            $this->setStatus('ON');
        } else {
            $this->setStatus('ERROR');
        }
        
        // new \PbClasses\Debug\Logging($this->taskId, __FILE__, __LINE__);
        
    }

    protected function checkTask() {
        $what = "Description, TaskClass, Minutes, Nextrun, LastStart, Status, Fingerprint";
        $where = array('ID' => $this->taskId);
        $res = $this->db->selectAssoc($this->taskTable, $what, $where);

        if (!$res) {
            new ExcLogging('Task ' . $this->taskId . ' in table ' . $this->taskTable . ' not found.');
            return false;
        }

        //Make sure that the parameter fingerprint matches the database entry Fingerprint. Else on misconfiguration
        //a task could be started accidentally
        if ($this->fingerprint !== $res[0]['Fingerprint']) {
            new ExcLogging('Task ' . $this->taskId . ' in table ' . $this->taskTable . ' wrong fingerprint: ' . $this->fingerprint . ' : db: ' . $res[0]['Fingerprint']);
            return false;
        }

        if ('RUNNING' !== $res[0]['Status']) {
            new ExcLogging('Task ' . $this->taskId . ' in table ' . $this->taskTable . ' wrong Status: ' . $res[0]['Status']);
            return false;
        }
        $this->taskRow = $res[0];
        return true;
    }

    protected function setStatus($status) {
        $what = array('Status' => $status,
            'Finished' => date('Y-m-d H:i:s'),
            'Fingerprint' => '');
        $where = array('ID' => $this->taskId,
            'Status' => 'RUNNING',
            'Fingerprint' => $this->fingerprint);
        if (! $this->db->update($this->taskTable, $what, '', $where)) {
            $err = 'Status kann nicht auf ' . $status . ' gesetzt werden. ';
            $err .= ' Table ' . $this->taskTable . "\r\n" . print_r($where, true);
            new ExcLogging($err, __FILE__, __LINE__);
            return false;
        }
        //Todo: Debug-Logging
        return true;
        
    }
    
    
    public function getTaskTable() {
        return $this->taskTable;
    }
    public function getTaskId() {
        return $this->taskId;
    }
    /*
     * 
      function set_on() {
      global $log;
      $what = array('Status' => 'ON',
      'Finished' => date('Y-m-d H:i:s'));
      $where = array('ID' => TASK_ID,
      'Status' => 'RUNNING');
      $succ = pb_update (TASKS, $what, '', $where);
      if (!$succ) {
      $log->log_entry("\r\n Programm kann nicht zurückgesetzt werden. Status ist nicht RUNNING ===== " . date('d.m.Y H:i:s'));
      } else {
      $log->log_entry("\r\n OK - Status auf ON gesetzt");
      }
      $log->close_log();
      exit;
      }

      function stop_program() {
      global $log;
      $log->log_entry("\r\n Abbruch. ===== " . date('d.m.Y H:i:s'));
      $what = array('Status' => 'ERROR',
      'Finished' => date('Y-m-d H:i:s'));
      $where = array('ID' => TASK_ID);
      pb_update (TASKS, $what, '', $where);
      $log->close_log();
      exit;
      }


      function set_running() {
      global $log;
      $what = array('Status' => 'RUNNING',
      'Finished' => '0000-00-00 00:00:00');
      $where = array('ID' => TASK_ID,
      'Status' => 'ON');
      $succ = pb_update (TASKS, $what, '', $where);
      if (!$succ) {
      $log->log_entry("\r\n Programm kann nicht gestartet werden. Status ist nicht ON ===== " . date('d.m.Y H:i:s'));
      $log->close_log();
      exit;
      }
      return true;
      }

      function log_exception($message, $file = '', $line = '') {
      require_once (CLASSPATH . 'ExceptionLogging.class.php');
      new ExceptionLogging($message, $file, $line);
      }

      function set_heartbeat($message = false) {
      global $log;
      $what = array('Heartbeat' => date('Y-m-d H:i:s'));
      $where = array('ID' => TASK_ID);
      pb_update (TASKS, $what, '', $where);
      if ($message) {
      $log->log_entry("\r\n == HEARTBEAT === " . date('d.m.Y H:i:s') . "\r\n" . $message);
      }

      }

     */
}
