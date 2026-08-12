<?php

/**
 * @author Peter Bieling www.media-palette.de
 * Update - 09.02.2019 - icon_page hinzugefügt - Bessere Darstellung in der Navigation
 */

namespace PbClasses\Tpl;

use PbClasses\PbTpl;
use PbClasses\Develop as Dev;

class MakeNavi {

    protected $indexfound = false;
    protected $stoplevel = 0;
    protected $pageId;
    protected $naviType = 'standard';
    protected $t;
    protected $confTree;
    protected $parentsArr;

    public function __construct($template, $file = true, $pageId = false) {
        $this->pageId = (false === $pageId) ? (int) \PbClasses\Conf::get('PAGE_ID') : (int) $this->pageId = $pageId;
        $this->t = new PbTpl($template);
        $this->confTree = \PbClasses\Conf::get('site_navi_tree');
        $this->parentsArr = $this->getPathArr();
    }
    
    /* Wird hier och nicht gebraucht. Eventuell vereinheitlichen mit Dropdown */
    public function setNaviType($type) {
        $this->naviType = $type;
    }

    public function get($tree, $level = 0, $isGroupParent = false, $parentId = 0) {
        $htmlBuffer = '';
        $pageIcon = ($this->t->hasTpl('icon_page')) ? $this->t->fillTpl('icon_page') : '';
        foreach ($tree as $k => $kvArr) {
            $urlSubdirPrefix = \PbClasses\Conf::get('navi_autoprepend');
            if ($urlSubdirPrefix) {
                $kvArr['URL'] = $urlSubdirPrefix . $kvArr['URL'];
            }
            
            $kvArr['listarrow'] = (empty($kvArr['nextlevel']))  ? $pageIcon : $this->t->fillTpl('icon_collapsed');
            //zum Auf- und Zuklappen ist JavaScript erforderlich! admin/main.js
                     
            //Wenn es sich um einen Gruppenlink handelt ($kvArr['NoPage'] = 1) werden ebenfalls die 
            //Kindelelmente abgefragt. Diese werden im HTML aber normalerweise mit CSS versteckt
            //und bei Bedarf ausgeklappt.
            
            $isGroup = (! empty($kvArr['NoPage'])) ? true : false;
            
            if ($level <= (\count($this->parentsArr) + 1) 
                    && ($this->pageId == $k || (isset($this->parentsArr[$level]) && $this->parentsArr[$level] == $k)) 
                    && \is_array($kvArr['nextlevel'])) {
                 $kvArr['listarrow'] = $this->t->fillTpl('icon_expanded');
                $kvArr['nextlevel'] = $this->get($kvArr['nextlevel'], $level + 1, $isGroup, $kvArr['ID']);
            } elseif ($isGroup && \is_array($kvArr['nextlevel'])) {
                $kvArr['nextlevel'] = $this->get($kvArr['nextlevel'], $level + 1, $isGroup,  $kvArr['ID']);    
            }
            

            else {
                $kvArr['nextlevel'] = '';
            }

            $kvArr['path'] = \PbClasses\Conf::get('HTTP_ROOT');

            //Elternelemente markieren
            $kvArr['classes'] = (isset($this->parentsArr[$level]) && $this->parentsArr[$level] == $k) ? ' activeparent' : '';

            if ($k == $this->pageId) {
                $htmlBuffer .= $this->t->fillTpl('links_akt', $kvArr);
            }   //Eine geöffnete Elterngruppe soll aufgeklappt bleiben:
                elseif ($isGroup && \in_array($kvArr['ID'], $this->parentsArr)) {
                $htmlBuffer .= $this->t->fillTpl('parent_group', $kvArr);
            } elseif ($isGroup) {
                $htmlBuffer .= $this->t->fillTpl('group', $kvArr);
            }
            
            else {
                $htmlBuffer .= $this->t->fillTpl('links', $kvArr);
            }
        }

        $naviTpl = ($level === 0 && $this->t->hasTpl('outer_navi')) ? 'outer_navi' : 'navi';
        return $this->t->fillTpl($naviTpl, array('links' => $htmlBuffer, 
                                                 'level' => $level,
                                                 'classes' => ($isGroupParent && ! \in_array($parentId, $this->parentsArr)) ? ' collapse' : '',
                                                 'parent' => ($parentId ) ? 'g' . $parentId : '' ));
    }

    protected function getPathArr() {
        if (! isset($this->confTree[$this->pageId]) || ! isset($this->confTree[$this->pageId]['parents']) ) {
            return array();
        }
        $path = $this->confTree[$this->pageId]['parents'];
        return (!$path) ? array() : explode(',', $path);
    }

}
