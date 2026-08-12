<?php

/**
 * Description of GetTools
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 02.01.2018, 11:41:25
 */

namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbClasses\Backend\Icon;
use PbModule_commondocu\MConf;

class GetTools extends \PbClasses\Ajax\AbstractResponse {
    
    protected $db;
    protected $conf;
    protected $c; //Template
    
    public function action() {
        
        
        \PbClasses\Ajax\SessionStart::start();
        
        $this->db = Conf::get('db');
        
        $this->conf = MConf::get('docu_conf');

        $this->c = new \PbClasses\PbTpl($this->conf['admin']['module_form_tpl']);        
        $indexRow = $this->getIndexRow($this->P['id']);
        //Wird im Baum eingefügt, falls es Änderungen gegeben hat
        $this->rslt['node_icon'] = \PbClasses\Backend\Icon::getIconType($indexRow);


        
        $this->rslt['content_meta'] = $this->c->fillTpl('overview_form', $indexRow);
        
        
        
        //Felder einzeln holen
         $fieldArr =  \array_keys($this->conf['doc_vars']['placeholder_filter']);
         //$fieldArr = array('teaser', 'maintext', 'keywords', 'author'); //Todo: auslagern
        
        $fieldBuffer = '';
        foreach($fieldArr as $placeHolder) {
            $inpSeRe = $this->getInputRow($placeHolder, $indexRow);
            $tplkey = 'input_' . $placeHolder;
            
            $fieldBuffer .= $this->c->fillTpl($tplkey, $inpSeRe);
            
        }
        
        $formSeRe = array('id' => $indexRow['ID'],
                      'modid' => $indexRow['ModID'],
                      'input_rows' =>$fieldBuffer);
        
        $this->rslt['maintext_form'] = $this->c->fillTpl('maintext_form', $formSeRe);

        $this->rslt['succ'] = 'OK';
        $this->output();
    }
    
    protected function getIndexRow($id) {
        $res = $this->db->selectAssoc($this->conf['doc_tables']['PAGE_INDEX'], '', array('ID' => $id), '', 'LIMIT 1');
        if (! $res) {
            $this->rslt['errortext'] = 'Kein Eintrag für Index ' . $id;
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
       
        $this->addUrlSuggestion($row);
        
        
        return $row;
    }
    
    protected function addUrlSuggestion(& $row) {
        if ($row['URL']) {
            $row['url_suggestion'] = '';
            return;
        }
        $parentUrlPart = $this->getParentUrlPart($row['ParentID']);
        
        $filePart = $this->getFilePart($row['Label']);
        
        $row['url_suggestion'] = $this->c->fillTpl('url_suggestion', 'btn_url_suggestion', $parentUrlPart . $filePart);
       
    }
    
    
    protected function getFilePart($label) {
        $labelLower = \strtolower($label);
        $seRe = array(' ' => '-',
                      'ß' => 'ss',
                      'ä' => 'ae',
                      'ü' => 'ue',
                      'ö' => 'oe');
        
        $labelSpecial = \str_replace(\array_keys($seRe), \array_values($seRe), $labelLower);
                
       // return \urlencode($labelSpecial) . '.php';
        return \urlencode($labelSpecial); //ohne .php!
        
        
    }
    
    protected function getParentUrlPart($parentId) {
        if (! $parentId) {
            return '';
        }
        
        $res = $this->db->selectAssoc($this->conf['doc_tables']['PAGE_INDEX'], 'URL', ['ID' => $parentId], '', 'LIMIT 1');
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
    
    
    
    
    
    protected function getInputRow($placeHolder, $indexRow) {
       //ID 	ParentID 	ModID 	GrID 	Label 	URL 	AltTpl 	HelpID 	Ord 	Hide 	NoPage
        $seRe = array('id' => $indexRow['ID'],
                      'modid' => $indexRow['ModID'],
                      'value' => '',
                      'entry' => '',
                      'updid' => '',
                      'placeholder' => $placeHolder);
        $table = $this->conf['doc_tables']['PAGE_CONTENT'];
        //ID 	ModID 	PageId 	Row 	Placeholder 	Entry 	Filter
        $where = array(
            'PageId' => $indexRow['ID'], 
            'ModID' => $indexRow['ModID'],
            '`Row`'   => 0,
            'Placeholder' => $placeHolder);
        $res = $this->db->selectAssoc($table, 'ID, entry', $where, '', 'LIMIT 1');
        if ($res) {
            $seRe['updid'] = $res[0]['ID'];
            $seRe['entry'] = $res[0]['entry'];
        }
        return $seRe;
        
    }

}
