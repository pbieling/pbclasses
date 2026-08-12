<?php

namespace PbClasses\Tpl;

use PbClasses\Conf;

/**
 * Die Klasse nutzt den in der Konfiguration abgelegten Baum, um den jeweilingen Teilbaum zu finden
 *
 * @author Peter Bieling - 2017-02-15
 */
class SubNavi {

    public function __construct(& $mainConfigSeRe) {
        //array('sidenavi', 'standard', 'sidenavi.tpl'),


        $naviTree = Conf::get('site_navi_tree');
        $pageId = Conf::get('PAGE_ID');

        list($placeholder, $naviType, $tplFile ) = Conf::get('subnavi');

        if (empty($naviTree[$pageId]['ParentID'])) {
            $mainConfigSeRe[$placeholder] = '';
            return;
        }
        $fullPathTplFile = Conf::get('BACKTPLPATH') . $tplFile;

        // \PbClasses\Develop::test($naviTree, __LINE__, 1);

        $id = $naviTree[$pageId]['ParentID'];
        //Lösung über Fabrik, wie bei der normalen Navigation (ShowPage):

        $tnav = \PbClasses\Tpl\CreateMakeNavi::getObj($fullPathTplFile, $naviType);
        $mainConfigSeRe[$placeholder] = $tnav->get($naviTree[$id]['nextlevel']);
    }

}
