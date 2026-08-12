<?php
namespace PbClasses\Backend\Ajax;
use PbClasses\Conf;
class Action {

    public function __construct() {

        $sd = Conf::get('sub_dir');
        $iconSubdir = ($sd) ? '/' . $sd : '';
        $iconArr = Conf::get('icons');
        $iconPencil = (empty($iconArr)) ? 'glyphicon glyphicon-pencil red' : $iconArr['pencil'];
        
        
        $GET = Conf::get('get_param');
        if (isset($GET['operation'])) {
            if (isset($GET['id'])) {
                $get_id = (substr($GET['id'], 0, 6) === 'phtml_') ? (int) substr($GET['id'], 6) : $GET['id'];
                if ($get_id === '#') {
                    $get_id = 0;
                }
            }

            if (isset($GET['parent'])) {
                $parent = (substr($GET['id'], 0, 6) === 'phtml_') ? (int) substr($GET['parent'], 6) : $GET['parent'];
                if ($parent === '#') {
                    $parent = 0;
                }
            }
            //!
            $db_tree = new \PbClasses\Backend\DbTree(Conf::get('db_tables')['backnavi']);
          
            try {
                $rslt = null;
                switch ($GET['operation']) {
                    case 'get_node':
                        $node = isset($GET['id']) && $GET['id'] !== '#' ? (int) $GET['id'] : 0;

                        $rslt = array();
                        break;
                    case "get_content":
                        $node = $get_id;
                        
                        
                        /*
                        $ov_obj = new \PbClasses\PG\PageOverview();
                        $rslt = array('content' => $ov_obj->getOv($get_id),
                            'icn' => $ov_obj->getIcon(),
                            'modul' => $ov_obj->getModul());
                         * 
                         */
                        $rslt = array();
                       break;
                    case 'create_node':
                        $node = isset($get_id) ? $get_id : 0; //sollte in diesem Fall die Parent-ID sein
                        $crtext = isset($GET['text']) ? $GET['text'] : 'New node';
                        $ord = isset($GET['position']) ? (int) $GET['position'] : 0;
                        $crn_id = $db_tree->create($node, $ord, $crtext);
                        if ($crn_id) {
                            $rslt = array('id' => 'phtml_' . $crn_id, 
                                          'icn' => $iconPencil);
                                          //'icn' => $iconSubdir . '/icn/new_page.png');
                        }
                        break;
                    case 'rename_node':
                        $node = $get_id;
                        $rntext = isset($GET['text']) ? $GET['text'] : 'Renamed node';
                        $rslt = $db_tree->rename($node, $rntext);
                        //Todo: Antwort wird nicht ausgewertet. 
                        //rslt sollte noch eine message erhalten
                        break;
                    case 'delete_node':
                        $node = $get_id;
                        $rslt = $db_tree->delete($node, $parent);

                        break;
                    case 'move_node':
                        $node = isset($get_id) ? $get_id : 0; //sollte in diesem Fall die Parent-ID sein
                        $ord = isset($GET['position']) ? (int) $GET['position'] : 0;
                        $parn = isset($parent) ? $parent : 0;

                        $succ = $db_tree->move($node, $ord, $parn);

                        $rslt = true; //PB	
                        break;
                    case 'copy_node':
                        $node = isset($GET['id']) && $GET['id'] !== '#' ? (int) $GET['id'] : 0;
                        $parn = isset($GET['parent']) && $GET['parent'] !== '#' ? (int) $GET['parent'] : 0;
                        $rslt = $fs->cp($node, $parn, isset($GET['position']) ? (int) $GET['position'] : 0);
                        break;
                    default:
                        throw new \Exception('Unsupported operation: ' . $GET['operation']);
                        break;
                }
                \header('Content-Type: application/json; charset=utf-8');
                //new DebugLogging(print_r($rslt, true), __FILE__, __LINE__);
                echo \json_encode($rslt);
            } catch (\Exception $e) {
                
               new \PbClasses\Exception\Logging($e->getMessage(), __FILE__, __LINE__);
                
                
                \header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
                \header('Status: 500 Server Error');
                echo $e->getMessage();
            }
            //die();
        }
    }

}
