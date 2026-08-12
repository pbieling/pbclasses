<?php

/**
 * @author Peter Bieling www.media-palette.de
 * Erzeugt eine Ausklappnavigation aus Sicht der jeweiligen Seite.
 * Die PAGE_ID wird am Seitenanfang als Konstante definiert.
 * Jede Seite hat ihre eigene ID.
 *
 * Erg�nzung 24.02.2010 Nutzung der Klasse CheckAccess von Florian B�tow
 * �ber das Objekt $GLOBALS['acc_obj'].
 * Seiten f�r die der User ($_SESSION['uid'] keine Berechtigung hat, werden
 * in der Navigation �bergangen)
 *
 */
//class pb_tpl_displaynavi extends pb_tpl_auto

namespace PbClasses\Tpl;

use PbClasses\PbTpl;

class MakeNavi_OldVersion extends PbTpl {

    protected $indexfound = false;
    protected $stoplevel = 0;
    protected $pageId;
    
    protected $naviType = 'standard';
    
    public function __construct($template, $file = true, $pageId = false) {
        if (false === $pageId) {
            $this->pageId = \PbClasses\Conf::get('PAGE_ID'); 
        } else {
            $this->pageId = $pageId;
        }
        parent::__construct($template, $file);
    }
    
    public function setNaviType($type) {
        $this->naviType = $type;
    }

    public function get($tree, $level = 1) {
        $tempflag = false; //wird auf true gesetzt, wenn auf dem aktuellen Level
        //die aktuelle Seite gefunden wurde.
        $lastpage = false;
        $html_buffer = '';
        if ($this->stoplevel == 1) {
            return '';
        }
        if ($this->indexfound) {
            $this->stoplevel++;
        }
       // $check_access = false;

        /** @todo
         * 
          if (isset($GLOBALS['acc_obj'])) {
          $check_access = true;
          if ($GLOBALS['acc_obj']->isAdmin()) {
          // User ist admin => Alle Seiten in die Navigation einfuegen
          $check_access = false;
          }
          }
         */
        foreach ($tree as $k => $v_arr) {
           // trigger_error(print_r($v_arr, true));exit;
            
            /*
            if ($check_access) {
                if (!$GLOBALS['acc_obj']->checkPageAccess($k, $_SESSION['uid'])) {
                    continue;
                }
            }
             * 
             */
            
            if($v_arr['URL'] === 'index.php' || $v_arr['URL'] === 'index.html') {
                $v_arr['URL'] = '';
            }

            if ($k == $this->pageId) {
                $this->indexfound = true;
                
                if ($level > 1) {
                    //return '';
                    $this->pageId = 1000; //TEST
                }
                
            }
            $tmp = '';

            if (is_array($v_arr['nextlevel'])) {
                $tmp = $this->get($v_arr['nextlevel'], $level + 1);
                
                
            } else {
                $lastpage = true;
            }


            $v_arr['nextlevel'] = $tmp;
            $v_arr['level'] = $level;
            $v_arr['path'] = \PbClasses\Conf::get('HTTP_ROOT');
            


            if ($k == $this->pageId) {
                /*
                //Nur zwei Level. Der zweite Level nur als Dropdown!
                if ($this->naviType === 'dropdown') {
                    $v_arr['nextlevel'] = '';
                }   
                 * 
                 */        
                /*
                if ($level === 2 && $this->naviType === 'dropdown') {
                    return '';    
                }      
                 * 
                 */        
                $html_buffer .= $this->fillTpl('links_akt', $v_arr);
                $tempflag = true;
                

              
            } else {
                if (($this->indexfound === false) || ($tempflag === true)) {
                    //TEST
                    if ($this->naviType !== 'dropdown') {
                        $v_arr['nextlevel'] = '';
                    }
                }
                $linktemplate = ($level == 1 && $this->naviType === 'dropdown' && $v_arr['nextlevel']) ? 'links_dropdown' : 'links';
                
                $html_buffer .= $this->fillTpl($linktemplate, $v_arr);
            }

            if ($level == 1) {
                $this->indexfound = false; //Reset;
            }
        }
        
       // \PbClasses\Develop::test($this->fillTpl('navi', 'links', $html_buffer), __LINE__, 0);
        $template =  ($level == 2 && $this->naviType === 'dropdown' && ! $tempflag) ? 'navi_dropdown' : 'navi';
            
        
        return ($this->fillTpl($template, 'links', $html_buffer));
    }

}
