<?php

namespace PbClasses\Module;

/**
 * Description of AbstractInit
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 05.04.2020, 19:12:30
 */
class AbstractInit {

    const DEFAULT_PLACEHOLDER = 'module_output';

    protected $P;
    protected $paramArr;
    protected $mconfName; //wird von der Kindklasse gesetzt
    protected $t; //Template-Objekt

    public function __construct($paramArr, $confNamespace) {
        $this->mconfName = '\\' . $confNamespace . '\\MConf';
        //Parameter aus Pageconfig müssen verfügbar sein 
        $this->paramArr = $paramArr;
        // MConf::set('param_arr', $paramArr);
        \call_user_func_array([$this->mconfName, 'set'], ['param_arr', $paramArr]);

        //TEST - Bei AjaxDateien hier aussteigen
        if ($this->paramArr === 'ajax') {
            return;
        }



        $this->P = \filter_input_array(INPUT_POST);
        if (isset($this->P['mod_action']) || !empty($this->P['mod_newaction'])) {
            $actionName = (!empty($this->P['mod_newaction'])) ? $this->P['mod_newaction'] : $this->P['mod_action'];
            $indexArr = array('mod_actions', $actionName); //Konfigurationspfad            

            $action = \call_user_func_array([$this->mconfName, 'get'], [$indexArr]);
            if (null !== $action) {
                $this->handleType($action);
            }
            // } elseif (null !== ($action = MConf::get('mod_start'))) {
        } elseif (null !== ($action = \call_user_func_array([$this->mconfName, 'get'], ['mod_start']))) {
            $this->handleType($action);
        }




        $this->output();
    }

    protected function output() {
        //Verlegt vor die Klassen (s.o.)
        // $outputTplFile = \call_user_func_array([$this->mconfName, 'get'], ['output_tpl_file']);
        // $t = new \PbClasses\PbTpl($outputTplFile);
        $outputTplName = \call_user_func_array([$this->mconfName, 'get'], ['output_tpl_name']);
        $outputSere = \call_user_func_array([$this->mconfName, 'get'], ['output_sere']);

        $output = $this->t->fillTpl($outputTplName, $outputSere);
        $placeholder = (isset($this->paramArr['placeholder'])) ? $this->paramArr['placeholder'] : self::DEFAULT_PLACEHOLDER;
        \PbClasses\Conf::addKV('cSeRe', $placeholder, $output);
    }

    protected function handleType($action) {
        if (isset($action['output_tpl_file'])) {
            \call_user_func_array([$this->mconfName, 'set'], ['output_tpl_file', $action['output_tpl_file']]);
        }
        if (isset($action['output_tpl_name'])) {
            \call_user_func_array([$this->mconfName, 'set'], ['output_tpl_name', $action['output_tpl_name']]);
        }
        if (isset($action['output_sere'])) {
            \call_user_func_array([$this->mconfName, 'set'], ['output_sere', $action['output_sere']]);
        }

        if (isset($action['css_files'])) {
            $this->setCssJsFiles('pbmodule_css_files', $action['css_files']);
        }
        if (isset($action['js_files'])) {
            $this->setCssJsFiles('pbmodule_js_files', $action['js_files']);
        }

        //Template-Objekt erzeugen
        $outputTplFile = \call_user_func_array([$this->mconfName, 'get'], ['output_tpl_file']);
        //Falls nicht null
        if ($outputTplFile) {
            $this->t = new \PbClasses\PbTpl($outputTplFile);
            //Template-Objekt in den Klassen verfügbar machen:
            \call_user_func_array([$this->mconfName, 'set'], ['t', $this->t]);
        }



        //Darf hier erst stehen, um das SeRe-Array erweitern zu können, wird sonst überschrieben
        if (isset($action['classes'])) {
            $this->handleClasses($action['classes']);
        }
    }

    protected function setCssJsFiles($key, $fileArr) {
        $existingfileArr = \PbClasses\Conf::get($key);
        //Vorhandenes Array ergänzen. Dubletten entfernen
        if ($existingfileArr) {
            \PbClasses\Conf::set($key, \array_unique(\array_merge($existingfileArr, $fileArr)));
            return;
        }
        //Sonst neues Array direkt setzen
        \PbClasses\Conf::set($key, $fileArr);
    }

    protected function handleClasses($classes) {
        if (!is_array($classes)) {
            $classes = array($classes);
        }

        foreach ($classes as $k => $v) {
            if (\is_int($k)) {
                new $v(); //Klasse aufrufen
            } else {
                new $k($v); //Parameter vorhanden
            }
        }
    }

}
