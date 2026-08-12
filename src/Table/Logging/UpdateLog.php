<?php
namespace PbClasses\Table\Logging;

use PbClasses\Conf;

/**
 * Description of UpdateLog
 *
 * @author Bieling Peter
 */
class UpdateLog {
    //UpdateLog::log($destTable, $uid, $logArr);
    public static function log($destTable, $uid, $logArr, $dbh = null) {
        $updStr = is_array($logArr['upd']) ? json_encode($logArr['upd']) :  $logArr['upd'];
        $whereStr = is_array($logArr['where']) ? json_encode($logArr['where']) : $logArr['where'];
        
        $inArr['CrTime'] = date('Y-m-d H:i:s');
        $inArr['UID'] = $uid;
        $inArr['UpdType'] = $logArr['type'];
        $inArr['UpdTable'] = $logArr['table'];
        $inArr['UpdWhat'] = $updStr;
        $inArr['UpdWhere'] = $whereStr;
        
        $db = ($dbh === null) ? Conf::get('db') : $dbh;
        
        try {
            $db->insert($destTable, $inArr);
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
        }
    }
}
