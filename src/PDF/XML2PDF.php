<?php

namespace PbClasses\PDF;

use PbClasses\Conf;

/**
 * Description of XML2PDF
 *
 * @author Peter Bieling
 */
class XML2PDF {

    protected $pdf;
    protected $config;

    public function __construct($config) {
        $this->pdf = new \PbClasses\PDF\PDF();
        $this->config = $config;
        $this->pdf->setConfig($config);


        if (isset($this->config['footer'])) {
            $this->makeUserText('footer');
        }
        if (isset($this->config['header'])) {
            $this->makeUserText('header');
        }


        $this->makePage();


        if (isset($this->config['user'])) {
            $this->makeUserText();
        }

        if (isset($this->config['classes'])) {
            $this->handleClasses($this->config['classes']);
        }



        //If no filename ist given show PDF, else save.
        if (empty($this->config['filename'])) {
            $this->pdf->Output();
            exit;
        }

        $this->pdf->Output($this->config['filename'], 'F');
    }

    /*
     * Eine oder mehrere Klassen aufrufen, sofern diese definiert sind
     */
    protected function handleClasses($classNames) {
        if (!is_array($classNames)) {
            $classNames = array($classNames);
        }
        foreach ($classNames as $sgClassName) {
            //new $sgClassName($this->pdf, $this->config);
            new $sgClassName($this->pdf, $this->config);
        }
    }

    protected function makePage() {
        if (!isset($this->config['page'])) {

            return $this->defaultPage();
        }

        if (!file_exists($this->config['page'])) {
            throw new \Exception('Failed to open File ' . $this->config['page']);
        }
        $xml = new \SimpleXMLElement(\file_get_contents($this->config['page']));

        $result = $xml->xpath('/page');
        $fieldArr = $this->getPageArr($result);
        //test($result, __LINE__, 1);
        //test((string) $result[0]->topmargin, __LINE__, 1);
        /*
         * <!-- 
          $this->pdf->SetTopMargin(50);
          $this->pdf->SetLeftMargin(10);
          $this->pdf->SetRightMargin(7);

          $this->pdf->getAliasNbPages();
          $this->pdf->SetAutoPageBreak(true, 15);
          -->
          <TopMargin>50</TopMargin>
          <LeftMargin>10</LeftMargin>
          <RightMargin>7</RightMargin>
          <AutoPageBreak switch="on">15</AutoPageBreak>
         */
        if (isset($fieldArr['TopMargin'])) {
            $this->pdf->SetTopMargin((float) $fieldArr['TopMargin']['val']);
        }
        if (isset($fieldArr['LeftMargin'])) {
            $this->pdf->SetLeftMargin((float) $fieldArr['LeftMargin']['val']);
        }
        if (isset($fieldArr['RightMargin'])) {
            $this->pdf->SetRightMargin((float) $fieldArr['RightMargin']['val']);
        }
        $this->pdf->getAliasNbPages();

        //AutoPageBreak
        if (isset($fieldArr['AutoPageBreak']) && $fieldArr['AutoPageBreak']['@']['switch'] === 'true') {
            $this->pdf->SetAutoPageBreak(true, $fieldArr['AutoPageBreak']['val']);
        }
        //test($fieldArr['RightMargin']['val'], __LINE__, 1);
        $this->pdf->AddPage();
    }

    protected function defaultPage() {
        $this->pdf->SetTopMargin(50);
        $this->pdf->SetLeftMargin(10);
        $this->pdf->SetRightMargin(70);

        $this->pdf->getAliasNbPages();
        $this->pdf->SetAutoPageBreak(true, 15);

        $this->pdf->AddPage();
    }

    public function makeUserText($dest = false) {
        $this->pdf->SetCellPadding(0);
        if (!$dest) {
            $dest = 'user';
        }
        //Über die Konfiguration kann ein Search-Replace-Array übergeben werden
        if (file_exists($this->config[$dest])) {

            if (!empty($this->config['sere'][$dest])) {
                //Index "main" ergänzen
                $t = new \PbClasses\PbTpl($this->config[$dest], true, 'main');
                $xmlStr = $t->fillTpl('main', $this->config['sere'][$dest]);




                /*
                  if ($dest === 'user') {
                  echo $xmlStr; exit;
                  }
                 * 
                 */
            } else {
                $xmlStr = \file_get_contents($this->config[$dest]);
            }
            $xml = new \SimpleXMLElement($xmlStr);

            $result = $xml->xpath('/' . $dest . '/field');
            $fieldArr = $this->getFieldArr($result);
            //$this->processFields($fieldArr);
            /*
              if ($dest === 'user') {
              test($fieldArr, __LINE__, 1);
              }
             * 
             */
        } else {
            die('Failed to open File ' . $this->config[$dest]);
        }

        if ($dest === 'header') {

            $this->pdf->set_Header_data($fieldArr);
        } elseif ($dest === 'footer') {
            $this->pdf->set_Footer_data($fieldArr);
        } elseif ($dest === 'user') {
            $this->processFields($fieldArr);    
        }
    }

    protected function processFields($fieldArr) {
        foreach ($fieldArr as $field) {
            if (!empty($field['@']['type'])) {

                if (preg_match('/image/i', $field['@']['type'])) {
                    $image = trim($field['val']);


                    if ($image && isset($this->config['PDF_IMGPATH'])) {
                        $image = $this->config['PDF_IMGPATH'] . $image;
                    }


                    if (!$image or !file_exists($image)) {
                        if ($this->config['PDF_DUMMY_IMG'] && file_exists($this->config['PDF_DUMMY_IMG'])) {
                            $this->pdf->make_image($this->config['PDF_DUMMY_IMG'], $field['@']);
                        }
                    } else {
                        $this->pdf->make_image($image, $field['@']);
                    }
                }
            } else {
                $this->pdf->make_text($field['val'], $field['@']);
            }
        }
    }

    protected function getPageArr($result) {
        $fieldArr = array();
        foreach ($result[0] as $nodeObj) {
            $fieldArr[$nodeObj->getName()] = array('val' => (string) $nodeObj,
                '@' => $this->attr2arr($nodeObj->attributes()));
        }
        //test($fieldArr, __LINE__, 1);
        return $fieldArr;
    }

    function getFieldArr($result) {
        $fieldArr = array();
        foreach ($result as $nodeObj) {
            $fieldArr[] = array('val' => (string) $nodeObj,
                '@' => $this->attr2arr($nodeObj->attributes()));
        }
        return $fieldArr;
    }

    function attr2arr($attrObj) {
        $attrArr = array();
        foreach ($attrObj as $k => $vObj) {
            $attrArr[$k] = (string) $vObj;
        }
        return $attrArr;
    }
    /*
    function sere_arr($field_arr, $sr) {
        //test($field_arr, __LINE__, 1);
        foreach ($field_arr as $k => $arr) {
            $field_arr[$k]['val'] = pb_tpl_sere($arr['val'], $sr);
        }
        return $field_arr;
    }
     * 
     */

}
