<?php
namespace PbClasses\PDF;

/**
 * @author Peter Bieling - 2019-03-04
 */
class AbstrPDF {

    protected $pdf;
    protected $config;

    protected function processFields($fieldArr) {
        //\PbClasses\Develop::test($fieldArr, __LINE__, 1);
        foreach ($fieldArr as $field) {
            if (empty($field['@']['type'])) {
                $this->pdf->make_text($field['val'], $field['@']);
                continue;
            }

            if (!\preg_match('/image/i', $field['@']['type'])) {
                continue;
            }

            $image = trim($field['val']);

            if ($image && isset($this->config['PDF_IMGPATH'])) {
                $image = $this->config['PDF_IMGPATH'] . $image;
            }

            if ($image && \file_exists($image)) {
                $this->pdf->make_image($image, $field['@']);
                continue;
            }


            if ($this->config['PDF_DUMMY_IMG'] && \file_exists($this->config['PDF_DUMMY_IMG'])) {
                $this->pdf->make_image($this->config['PDF_DUMMY_IMG'], $field['@']);
            }
        }
        //\PbClasses\Develop::test($field, __LINE__, 1);
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

    /*
      protected function getFieldArrFromXml($xmlFile, $rootName = 'user') {
      $xml = new \SimpleXMLElement($xmlFile, 0, true);

      $result = $xml->xpath('/' . $rootName . '/field');
      return $this->getFieldArr($result);
      }
     * 
     */

    protected function processXml($xmlString, $rootName = 'user') {
        $xml = new \SimpleXMLElement($xmlString);

        $result = $xml->xpath('/' . $rootName . '/field');
        $fieldArr = $this->getFieldArr($result);

        $this->processFields($fieldArr);
    }

    protected function getFieldArr($result) {
        $fieldArr = array();
        foreach ($result as $nodeObj) {
            $fieldArr[] = array('val' => (string) $nodeObj,
                '@' => $this->attr2arr($nodeObj->attributes()));
        }
        return $fieldArr;
    }

    protected function attr2arr($attrObj) {
        $attrArr = array();
        foreach ($attrObj as $k => $vObj) {
            $attrArr[$k] = (string) $vObj;
        }
        return $attrArr;
    }

}
