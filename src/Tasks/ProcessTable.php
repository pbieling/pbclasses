<?php
namespace PbClasses\Tasks;

class ProcessTable {
    protected $db; 
    protected $tablename;
    protected $statusfield;
    
    protected $oldStatus = null;
    protected $lastError = '';
    
    
    protected $statusArr = array('Unprocessed' => 0,
                                 'Running' => 9,
                                 'Error' => 8,
                                 'Problem' => 3,
                                 'Wait' => 5,
                                 'OK' => 1);
    protected $whereArr = array();


    public function __construct($db, $tablename, $whereArr, $statusfield = 'Status') {
        $this->db = $db;
        $this->tablename = $tablename;
        $this->statusfield = $statusfield;
        $this->whereArr = $whereArr;
    }
    
    public function defineOwnStatus($key, $value) {
        $this->statusArr[$key] = $value;
    }
    
    public function defineOwnStatusArr(array $ownStatusArr) {
        $this->statusArr = array_merge($this->statusArr, $ownStatusArr);
    }
    
    /**
     * Reset
     */
    public function prepareRun() {
        $this->oldStatus =  $this->statusArr['OK']; 
        return $this->setUnprocessed();
    }
    
    public function set($key) {
        //Reset
        $this->lastError = '';

        //Altstatus setzen. An dieser Stelle, damit die Möglichkeit besteht, den Startstatus zu überschreiben
        if ($this->oldStatus === null) {
            $this->oldStatus = $this->statusArr['Unprocessed'];
        }
        
        if (! isset($this->statusArr[$key])) {
            throw new \Exception('Key of status array not found!');
        }
        
        $upd = array($this->statusfield => $this->statusArr[$key]);
        $where = $this->whereArr;
        //Schutz vor Fremdzugriff:
        $where[$this->statusfield] = $this->oldStatus; //Ergänzen zur Sicherheit
        
        if ($this->db->update($this->tablename, $upd, '', $where)) {
            $this->oldStatus = $this->statusArr[$key];
            return true;
        }
        $this->lastError = 'Update ' . $key . ' not possible';
        return false;
    }
    
    public function setUnprocessed() {
        return $this->set('Unprocessed');
    }
    
    public function setRunning() {
        return $this->set('Running');
    }
    public function setError() {
        return $this->set('Error');
    }
    
    public function setProblem() {
        return $this->set('Problem');
    }
    public function setWait() {
        return $this->set('Wait');
    }
    
    public function setOK() {
        return $this->set('OK');
    }
    
    
    /**
     * Updates in der Tabelle, ohne Veränderungen des Statusfelds.
     * 
     * @param array $updArr key-value-array (fieldnames => values)
     * @return boolean
     */
    public function updateValues($updArr) {
        $where = $this->whereArr;
        //Schutz vor Fremdzugriff:
        $where[$this->statusfield] = $this->oldStatus; //Ergänzen zur Sicherheit
        
        if ($this->db->update($this->tablename, $updArr, '', $where)) {
            return true;
        } 
        return false;
    }
    
    public function getError() {
        return $this->lastError;
    }
    
    
}

