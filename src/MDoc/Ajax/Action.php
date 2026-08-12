<?php

namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbModule_commondocu\MConf;

class Action
{

    public function __construct()
    {
        \PbClasses\Ajax\SessionStart::start();

        $getId = null;
        //$idPrefix = '';
        $GET = Conf::get('get_param');
        //new \PbClasses\Debug\Logging($GET, __FILE__, __LINE__); exit;
        if (isset($GET['operation'])) {

            $conf = MConf::get('docu_conf');

            if (isset($GET['id'])) {
                $getIdArr = explode('_', $GET['id']);
                if (count($getIdArr) === 2) {
                    $getId = $getIdArr[1];
                    //$idPrefix = $getIdArr[0] . '_';
                } elseif ($GET['id'] === '#') {
                    $getId = 0;
                }
            }

            if (isset($GET['parent'])) {
                if ($GET['parent'] === '#') {
                    $parent = 0;
                } else {
                    $parentArr = \explode('_', $GET['parent']);
                    if (!isset($parentArr[1])) {
                        throw new \Exception('Wrong id for parent.');
                    }
                    $parent = $parentArr[1];
                }
            }

            // $db_tree = new \PbClasses\Backend\DbTree(Conf::get('db_tables')['backnavi']);
            $db_tree = new \PbClasses\Backend\DbTree($conf['doc_tables']['PAGE_INDEX']);

            try {
                $rslt = null;
                switch ($GET['operation']) {
                    case 'get_node':
                        $node = isset($GET['id']) && $GET['id'] !== '#' ? (int) $GET['id'] : 0;

                        $rslt = array();
                        break;
                    case "get_content":
                        $node = $getId;

                        $rslt = array();
                        break;
                    case 'create_node':
                        $node = isset($getId) ? $getId : 0; //sollte in diesem Fall die Parent-ID sein
                        $crtext = isset($GET['text']) ? $GET['text'] : 'New node';
                        $ord = isset($GET['position']) ? (int) $GET['position'] : 0;
                        $crn_id = $db_tree->create($node, $ord, $crtext);
                        if ($crn_id) {
                            $rslt = array(
                                'id' => $crn_id, //Prefix lieber per Javascript einfügen     
                            );
                        }
                        break;
                    case 'rename_node':
                        $node = $getId;
                        $rntext = isset($GET['text']) ? $GET['text'] : 'Renamed node';
                        $rslt = $db_tree->rename($node, $rntext);
                        //Todo: Antwort wird nicht ausgewertet. 
                        //rslt sollte noch eine message erhalten
                        break;
                    case 'delete_node':
                        $node = $getId;
                        $rslt = $db_tree->delete($node, $parent);

                        break;
                    case 'move_node':
                        $node = isset($getId) ? $getId : 0; //sollte in diesem Fall die Parent-ID sein
                        $ord = isset($GET['position']) ? (int) $GET['position'] : 0;
                        $parn = isset($parent) ? $parent : 0;

                        $succ = $db_tree->move($node, $ord, $parn);

                        $rslt = true; //PB	
                        break;

                    default:
                        throw new \Exception('Unsupported operation: ' . $GET['operation']);
                }
                \header('Content-Type: application/json; charset=utf-8');

                echo \json_encode($rslt);
            } catch (\Exception $e) {

                new \PbClasses\Exception\Logging($e->getMessage(), __FILE__, __LINE__);

                \header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
                \header('Status: 500 Server Error');
                echo $e->getMessage();
            }
        }
    }
}
