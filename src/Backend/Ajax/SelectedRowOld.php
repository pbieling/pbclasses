<?php
namespace PbClasses\Backend\Ajax;
use PbClasses\Conf;
use PbClasses\Backend\Icon;
use PbClasses\Access\AccessGroupsForPage;

/**
 * @author Peter Bieling - 2017-12-06
 */
class SelectedRowOld  extends \PbClasses\Ajax\AbstractResponse {
    protected $db;
    protected $c;
    const DEFAULT_MAX_ACCESS_LEVEL = 3;
    
    
    public function action() {
        \PbClasses\Ajax\SessionStart::start();
         $this->c = new \PbClasses\PbTpl(Conf::get('BACKTPLPATH') . 'ajax/backend/selected-row.tpl');       
        //$testdata = print_r($_SESSION, true);
        $id = (int) $this->P['id'];
        if (! $id) {
            $this->rslt['errortext'] = 'Keine gültige ID übermittelt';
            $this->output(__FILE__, __LINE__);
        }
        $row = $this->getRow($id);
        
        if (! empty($this->P['get_icon'])) {
            $this->rslt['node_icon'] = Icon::getIconType($row);
        }
        $this->rslt['response'] = $this->c->fillTpl('content', $row);
        $this->rslt['succ'] = 'OK';
        $this->output();
    }
    
    
    protected function getRow($id) {
        $this->db = Conf::get('db');
        $res = $this->db->selectAssoc(Conf::get('db_tables')['backnavi'], '', ['ID' => $id], '', 'LIMIT 1');
        if (! $res) {
            $this->rslt['errortext'] = 'Kein Datensatz gefunden für ID ' . $id;
            $this->output(__FILE__, __LINE__);            
        }
        $row = & $res[0];
        
        //$row['hide_glyph'] = ($row['Hide']) ? 'glyphicon-eye-close' : 'glyphicon-eye-open';
        $row['hide_glyph'] = ($row['Hide']) ? Icon::getIcon('visual_no') : Icon::getIcon('visual_yes');
        
        $row['hide_class'] = ($row['Hide']) ? 'btn-danger' : 'btn-primary';
        $row['hide_value'] = $row['Hide'];
        
        
        //$row['nopage_glyph'] = ($row['NoPage']) ? 'glyphicon-folder-close' : 'glyphicon-file';
        $row['nopage_glyph'] = ($row['NoPage']) ? Icon::getIcon('folder_close') : Icon::getIcon('file');
        
        $row['nopage_class'] = ($row['NoPage']) ? 'btn-info' : 'btn-primary';  
        $row['nopage_value'] = $row['NoPage'];
        
        $accLevel = (Conf::get('max_accesslevel'))  ? Conf::get('max_accesslevel') : self::DEFAULT_MAX_ACCESS_LEVEL;
        for ($i = 0; $i <= $accLevel; $i++) {
            $row['SELECTED_' . $i]  = ($i == $row['AccLevel']) ? ' selected' : '';
        }
        $this->addUrlSuggestion($row);
        
        $this->addConfigBlock($row);
        
        
        $this->addAccessBlock($row);
        
        return $row;
    }
    
    /*
     * Pfad aus Parent holen, sofern vorhanden und Label in Dateinamen umwandeln (Leerzeichen,Umlaute ändern und URL encoden)
     */
    
    protected function addUrlSuggestion(& $row) {
        if ($row['URL']) {
            $row['url_suggestion'] = '';
            return;
        }
        $parentUrlPart = $this->getParentUrlPart($row['ParentID']);
        
        $filePart = $this->getFilePart($row['Label']);
        
        $row['url_suggestion'] = $this->c->fillTpl('url_suggestion', 'btn_url_suggestion', $parentUrlPart . $filePart);
       
    }
    
    protected function addConfigBlock(& $row) {
        if ($row['NoPage']) {
            $row['page_config'] = '';
            return;
        }
       $assumedPageConfig = Conf::get('APATH') . 'config/' . $row['URL'];
        if ($row['URL']) {
            
            if (file_exists($assumedPageConfig)) {
                //$row['page_config'] = $this->c->fillTpl('page_config_found', 'config_path', $assumedPageConfig); 
                $row['page_config'] = $this->c->fillTpl('page_config_found', 'config_path', $row['URL']); 
                return;
            }
        } 
       $row['page_config'] = $this->c->fillTpl('page_config_selection', 'config_path', $assumedPageConfig); 
    }    
    
    protected function getFilePart($label) {
        $labelLower = \strtolower($label);
        $seRe = array(' ' => '-',
                      'ß' => 'ss',
                      'ä' => 'ae',
                      'ü' => 'ue',
                      'ö' => 'oe');
        
        $labelSpecial = \str_replace(\array_keys($seRe), \array_values($seRe), $labelLower);
                
        return \urlencode($labelSpecial) . '.php';
        
        
    }
    
    protected function getParentUrlPart($parentId) {
        if (! $parentId) {
            return '';
        }
        
        $res = $this->db->selectAssoc(Conf::get('db_tables')['backnavi'], 'URL', ['ID' => $parentId], '', 'LIMIT 1');
        if (! $res) {
            return '';
        }
        
        $parentUrl = \rtrim(\strtolower($res[0]['URL']), '.php');
        $partArr = \explode('/', $parentUrl);
            
        if (\count($partArr) < 2) {
            return '';
        }
        
       // \array_pop($partArr);
        return \join('/', $partArr) . '/';
    }
    
    protected function addAccessBlock(& $row) {
        $accTableArr = Conf::get('access_tables');
        if (!$accTableArr) {
            $row['access_block'] = '';
            return;
        } 
        
        $accGroupObj = new AccessGroupsForPage();
        $accGroupArr = $accGroupObj->getGroupArr($row['ID']);
        
        $accSeRe  = [
            'acc_groups' => $this->c->fillRowTpl('access_group_items', $accGroupArr)
        ];

        $row['access_block'] = $this->c->fillTpl('access_block', $accSeRe);
    }
}
