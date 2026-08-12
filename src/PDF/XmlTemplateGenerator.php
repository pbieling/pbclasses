<?php
namespace PbClasses\PDF;
use PbClasses\Develop as Dev;
use PbClasses\Debug\Logging as Log;

/**
 * Description of XmlTemplateGenerator
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 2020-03-30
 */
class XmlTemplateGenerator {

    protected $structArr = array();
    protected $level = 0;
    protected $openParent = null;
    protected $templateBuffer = '';
    protected $t; //Template-Objekt
    
    protected $firstPage = true; //Flag für die erste Seite. Wird für active-Markierung des ersten Tabs gebraucht

    public function __construct($structFile, $tplSnippetFile, $outputFile) {
        $this->t = new \PbClasses\PbTpl($tplSnippetFile);
        $lineArr = \file($structFile);

        foreach ($lineArr as $line) {
            $this->parse(\trim($line));
        }



        if ($this->templateBuffer) {
            $fullTpl = $this->t->fillTpl('content', 'generated_form', $this->templateBuffer);

            file_put_contents($outputFile, $fullTpl);
        }


        //  new Log($this->structArr, __FILE__, __LINE__);
    }

    protected function parse($line) {
        if ($line === '') {
            return;
        }
        if (substr($line,0,1) === '#') {
            return;
        }
        
        if (substr($line, 0, 8) === '--page--') {
            $this->level = 0;
            $this->setPageStart(substr($line, 8));
        } elseif ($line === '--page_end--') {
            $this->level = 0;
            $this->templateBuffer .= $this->t->fillTpl('end_page'); //leeres Template
        } elseif ($line === ':row_start') {
            $this->level = 1;
            $this->templateBuffer .= $this->t->fillTpl('start_row'); //leeres Template
        } elseif ($line === ':row_end') {
            $this->level = 1;
            $this->templateBuffer .= $this->t->fillTpl('end_row'); //leeres Template
        } elseif ($line === '::col_start') {
            $this->templateBuffer .= $this->t->fillTpl('start_col'); //leeres Template
            $this->level = 2;
        } elseif ($line === '::col_end') {
            $this->templateBuffer .= $this->t->fillTpl('end_col'); //leeres Template
            $this->level = 2;
        } elseif ($line === '::col_start_auto') {
            $this->templateBuffer .= $this->t->fillTpl('start_col_auto'); //leeres Template
            $this->level = 2;
        } elseif ($line === '::col_end_auto') {
            $this->templateBuffer .= $this->t->fillTpl('end_col_auto'); //leeres Template
            $this->level = 2;
        } elseif (substr($line, 0, 3) === ':::') {
            $this->level = 3;
            $this->addGroupLabel(substr($line, 3));
        } else {
            if ($this->level >= 2) {
                $this->addTemplate($line);
            }
        }

    }

    /*
     * [start_page]
      <h4 id="{ANCHOR}">{HEAD_ENTRY}</h4>

      [start_row]
      <div class="row">
      !
      [end_row]
      </div>
      !
      [start_col]
      <div class="col">
      !
      [end_col]
      </div>
      !

      [group_label]
      <h3>{GROUP}</h3>
     */

    protected function addGroupLabel($group) {
        $this->templateBuffer .= $this->t->fillTpl('group_label', 'group', $group);
    }

    protected function addTemplate($line) {
        
        
        $arr = explode(';', $line);
        for ($i = 0; $i < count($arr); $i++) {
            $arr[$i] = trim($arr[$i]);
        }
        //Wenn als letztes Element eine geschweifte klammer steht, wurde hier
        //ein JSON-String angehöngt.
        if (\substr($line, -1) === '}') {
            $jsonSeRe = \json_decode(\array_pop($arr), true);
        }

        /*
         *     <label for="vorname" class="col-sm-2 col-form-label">{LABEL}:</label> 
          <div class="col-sm-10">
          <input id="field_{FIELD_NAME}" name="field_{FIELD_NAME}" type="text" class="form-control"{ATTRIBUTES} value="{{PLACEHOLDER}}">
          </div>
         */

        //Standardtemplate verarbeiten
        if (!isset($arr[2]) || $arr[2] === 's') {
            $tplName = 'standard';
        } elseif ($arr[2] === 'sll') {
            $tplName = 'standard_long_label';
        } 
        //....
        //Hier können weitere Kurzformen eingebaut werden
        //....
        else {
            $tplName = $arr[2];
        }
        
        //
        if (! $this->t->hasTpl($tplName)) {
            new Log('Template ' . $tplName . ' wurde nicht gefunden', __FILE__, __LINE__);
            return false;
        }

        $seRe = array('field_name' => $arr[0],
            'label' => $arr[1],
            'placeholder' => strtoupper($arr[0]),
            'attributes' => '');
        
        if (isset($jsonSeRe)) {
            $seRe = \array_merge($seRe, $jsonSeRe);
        }

        if (isset($arr[3])) {
            $seRe['attributes'] = ($arr[3] === 'r') ? ' required' : ' ' . $arr[3];
        }

        $this->templateBuffer .= $this->t->fillTpl($tplName, $seRe);
        return;



    }


    protected function setPageStart($valString) {
//{TAB_PANE_ACTIVE} Platzhalter muss für den ersten Tab mit ' active' gefüllt sein, sonst leer
        if ($this->firstPage) {
            $activeMark = ' active';
            $this->firstPage = false; //Flag ändern
        } else {
            $activeMark = '';
        }
        
        $kvArr = explode(';', $valString);
        $key = trim($kvArr[0]);
        $value = (isset($kvArr[1])) ? trim($kvArr[1]) : $key;
        $seRe = array('head_entry' => $value,
            'anchor' => $key,
            'tab_pane_active' => $activeMark);

        $this->templateBuffer .= $this->t->fillTpl('start_page', $seRe);
    }

}
