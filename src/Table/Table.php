<?php

/**
 * @author Peter Bieling 
 */

namespace PbClasses\Table;

use PbClasses\Conf;

class Table
{

    const LIMIT_SKIP = 0;
    const LIMIT_ROWS = 30;

    protected $table;
    protected $db;
    protected $conf;
    protected $query = false;
    protected $numberOfRows = null;
    protected $startIndex = 0;
    protected $numberOfDeliveredRows = 0;
    protected $numberOfUnfilteredRows = null;
    protected $limitSelection;
    protected $fieldnameWhat;

    public function __construct($table, $conf, $dbHandle = null)
    {
        $this->table = $table;
        $this->db = ($dbHandle) ? $dbHandle : Conf::get('db');
        $this->conf = $conf;
    }

    public function getTableData($numOfRows = false)
    {
        $what = (isset($this->conf['query']) && isset($this->conf['query']['table_what'])) ? $this->conf['query']['table_what'] : '';

        $where = (isset($this->conf['query']) && isset($this->conf['query']['table_where'])) ? $this->conf['query']['table_where'] : '';
        
        $this-> fillPlaceholderInWhereAndWhat($where, $what);


        $this->fieldnameWhat = & $what;
        $andor = (isset($this->conf['query']) && isset($this->conf['query']['table_andor'])) ? $this->conf['query']['table_andor'] : '';

        $condition = (isset($this->conf['query']) && isset($this->conf['query']['table_condition'])) ? $this->conf['query']['table_condition'] : '';

        $unfilteredWhere = $where;
        $this->handleFilteredWhere($where);

        $this->handleNumOfRows($numOfRows, $unfilteredWhere, $where, $andor, $condition);

        $limitSkip = (isset($this->conf['query']) && isset($this->conf['query']['table_limit_skip'])) ? (int) $this->conf['query']['table_limit_skip'] : self::LIMIT_SKIP;
        $limitRows = (isset($this->conf['query']) && isset($this->conf['query']['table_limit_rows'])) ? (int) $this->conf['query']['table_limit_rows'] : self::LIMIT_ROWS;
        $this->limitSelection = $limitRows;
        $this->modifyLimitSkip($limitSkip);

        $this->startIndex = $limitSkip;

        $this->modifyCondition($condition);


        if (!preg_match('/limit/i', $condition)) {
            $condition .= " LIMIT $limitSkip, $limitRows";
        }
        return $this->db->selectAssoc($this->table, $what, $where, $andor, $condition);
    }
    
    
                /*
         * Austausch der Platzhalter aus autoload-table.
         * Wird für Subqueries auch bei What gebraucht!
         */
    private function fillPlaceholderInWhereAndWhat(& $where, & $what)  {
         if (\is_string($where) && isset($_SESSION[$this->conf['unique_identifier']]['where_id'])) {
            $whereId = $this->db->escape($_SESSION[$this->conf['unique_identifier']]['where_id']);
            $where = \str_replace('{WHERE_ID}', $whereId, $where);
            $what = \str_replace('{WHERE_ID}', $whereId, $what);
        }
        if (\is_string($where) && isset($_SESSION[$this->conf['unique_identifier']]['scope'])) {
            $scope = $this->db->escape($_SESSION[$this->conf['unique_identifier']]['scope']);
            $where = \str_replace('{SCOPE}', $scope, $where);
            $what = \str_replace('{SCOPE}', $scope, $what);
        }
    }
      

    private function modifyCondition(& $condition)
    {

        if (!isset($this->conf['query']) || !isset($this->conf['query']['table_order']) || !is_array($this->conf['query']['table_order'])) {
            return;
        }
        // Fixe Sortierreihenfolge schon bei Query

        if (strlen($condition) > 0) {
            $condition .= ' ';
        }
        $condition .= 'ORDER BY ';
        $items = array();
        foreach ($this->conf['query']['table_order'] as $field => $direction) {
            $items[] = $field . ' ' . $direction;
        }
        $condition .= implode(', ', $items);
    }

    /**
     * 
     * @param type $limitSkip
     */
    private function modifyLimitSkip(& $limitSkip)
    {
        //Beim Umschalten auf ein größeres Limit, kann es passieren, dass die Navigation wegfällt, wenn das Limit größer ist als
        //die Anzahl. Es muss dann die ganze
        //Menge angezeigt werden,
        //Variante 1: Möglichst viel anzeigen (bis Ende)
        if ($limitSkip + $this->limitSelection > $this->numberOfRows) {
            $limitSkip = $this->numberOfRows - $this->limitSelection;
        }
        if ($limitSkip < 0) {
            $limitSkip = 0;
        }

        //Variante 2: Skip auf Null setzen wenn Limit > als Gesamtmenge ist.
        //  if ($limitRows >= $this->numberOfRows) {
        //     $limitSkip = 0;
        // }   
    }

    private function handleFilteredWhere(array|string & $where): void
    {  //reference !
        if (!isset($this->conf['query']['filter_where'])) {
            return;
        }
        if (!$where) {
            $where = $this->conf['query']['filter_where'];
            return;
        }

        if (is_string($where)) {
            $where = "($where) AND " . $this->conf['query']['filter_where'];
            return;
        }

        throw new \Exception('In combination with filters $where must be a string');
    }

    private function handleNumOfRows($numOfRows, $unfilteredWhere, $where, $andor, $condition)
    {
        if ($numOfRows === false) {
            return;
        }

        //if the total number is needed and the result is empty return.


        $this->setNumberOfRows($where, $andor, $condition);
        if ($where && $unfilteredWhere !== $where) {
            $this->setNumberOfUnfilteredRows($unfilteredWhere, $andor);
        }
        
        if (!$this->numberOfRows) {
            return 0;
        }
    }

    public function setNumberOfRows($where, $andor, $condition)
    {
        $what = "count('*')";
        $res = $this->db->select($this->table, $what, $where, $andor, $condition);
        if ($res) {
            $this->numberOfRows = (int) $res[0][0];
        }
    }

    public function setNumberOfUnfilteredRows($where, $andor)
    {
        $what = "count('*')";
        $res = $this->db->select($this->table, $what, $where, $andor);
        if ($res) {
            $this->numberOfUnfilteredRows = (int) $res[0][0];
        }
    }
}
