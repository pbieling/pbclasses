<?php
namespace PbClasses\Tpl\Form;

/**
 * Description of MakeSelect
 *
 * @author Bieling Peter
 */
class MakeSelect {
    /*
     * [option_standard]
       <option{SELECTION_ATTR} value="{OPTION_VALUE}">{OPTION_LABEL}</option>
     */
    
    /**
     * 
     * @param \PbClasses\PbTpl $tplObj
     * @param array $kvArr
     * @param mixed $selectedVal
     * @param string|null $optionTplName
     * 
     * @return string $buffer
     */
    public static function get(\PbClasses\PbTpl $tplObj, array $kvArr, $selectedVal = null, ?string $optionTplName = null) {
          $selectedAttr = ' selected';
          $buffer = '';
          if (! $optionTplName) {
              $optionTplName = 'option_standard';
          }
          
          foreach ($kvArr as $optionValue => $optionLabel) {
              $optVal = (is_int($optionValue)) ? $optionLabel : $optionValue;
              $seRe = [
                  'option_value' => $optVal,
                  'option_label' => $optionLabel,
                  'selection_attr' => ($selectedVal == $optVal) ? $selectedAttr : ''
                   
              ];
              
              $buffer .= $tplObj->fillTpl($optionTplName, $seRe);
          }
          
          return $buffer;
          
          
        
    }
}
