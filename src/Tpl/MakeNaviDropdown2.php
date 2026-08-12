<?php

namespace PbClasses\Tpl;

use PbClasses\PbTpl;
use PbClasses\Develop;

class MakeNaviDropdown2 extends PbTpl {

    protected $indexfound = false;
    protected $stoplevel = 0;
    protected $pageId;
    protected $naviType = 'dropdown2';

    public function __construct($template, $file = true, $pageId = false) {

        // Develop::test($template, __FILE__);
        if (false === $pageId) {
            $this->pageId = \PbClasses\Conf::get('PAGE_ID');
        } else {
            $this->pageId = $pageId;
        }
        parent::__construct($template, $file);
    }

    public function setNaviType($type) {
        $this->naviType = 'dropdown2';
    }

    public function get($tree, $level = 1) {
        $tempflag = false; //wird auf true gesetzt, wenn auf dem aktuellen Level
        //die aktuelle Seite gefunden wurde.
        $lastpage = false;
        $html_buffer = '';
        if ($this->stoplevel == 5) {
            return '';
        }
        if ($this->indexfound) {
            $this->stoplevel++;
        }

        foreach ($tree as $k => $v_arr) {
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
            //$v_arr['path'] = \PbClasses\Conf::get('HTTP_ROOT');
            $v_arr['path'] = '/'; //Pagegenerator


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
            } elseif (!empty($v_arr['NoPage'])) { //Keine Seite sondern Gruppierungslink
                $tplkeyLevel = 'dropdown_level' . $level;
                $tplkeyLevelAll = 'dropdown_level_all';
                $tplKey = ($this->hasTpl($tplkeyLevel)) ? $tplkeyLevel : $tplkeyLevelAll;
                $html_buffer .= $this->fillTpl($tplKey, $v_arr);
                /*
                 *        [ID] => 1
                  [ParentID] => 0
                  [Label] => Übersicht
                  [URL] => index.php
                  [AltTpl] =>
                  [HelpID] =>
                  [Ord] => 1
                  [Hide] => 0
                  [NoPage] => 0
                  [nextlevel] =>
                 */
            } else {


                /*
                  if (($this->indexfound === false) || ($tempflag === true)) {
                  //TEST
                  if ($this->naviType !== 'dropdown') {
                  $v_arr['nextlevel'] = '';
                  }
                  }
                 * 
                 * 
                 */
                $linktemplate = ($level == 1 && $v_arr['nextlevel']) ? 'links_dropdown' : 'links';

                if ($level == 1) {
                    $linktemplate = ($v_arr['nextlevel']) ? 'links_dropdown' : 'links';
                } else {
                  //  new \PbClasses\Debug\Logging($v_arr['Label'], __FILE__, __LINE__);
                    $linktemplate = ($v_arr['nextlevel']) ? 'links_dropdown' : 'links_dropdown_final';
                }

                // $html_buffer .= $this->fillTpl('links', $v_arr);
                $html_buffer .= $this->fillTpl($linktemplate, $v_arr);
            }

            if ($level == 1) {
                $this->indexfound = false; //Reset;
            }
        }

        // \PbClasses\Develop::test($this->fillTpl('navi', 'links', $html_buffer), __LINE__, 0);
        //$template = ($level == 2 && $this->naviType === 'dropdown' && !$tempflag) ? 'navi_dropdown' : 'navi';
        //$template = ($level == 2 && !$tempflag) ? 'navi_dropdown' : 'navi';

        $ulContainerTpl = 'navi';
        if ($level == 2) {
            $ulContainerTpl = 'navi_dropdown';
        } elseif ($level > 2) {
            $ulContainerTpl = 'navi_dropdown';
        }

        //new \PbClasses\Debug\Logging($this->fillTpl($ulContainerTpl, 'links', $html_buffer), __FILE__, __LINE__);
        return $this->fillTpl($ulContainerTpl, 'links', $html_buffer);
    }

}
