<?php

namespace PbClasses\Tpl;

/**
 * Description of CreateMakeNaviObj
 *
 * @author Peter Bieling
 */
class CreateMakeNavi {
    
    
    /**
     * 
     * @param type $tplFile
     * @param type $naviType
     * @return \PbClasses\Tpl\MakeNavi
     */
    
    public static function getObj($tplFile, $naviType = 'navi-left', $pageId = false) {
       // \PbClasses\Develop::test($naviType, __LINE__, 0);
        switch ($naviType) {
            case 'navi-left' :
            case 'standard' :
                return  new MakeNavi_OldVersion($tplFile, true, $pageId); 
               
                
            
            case 'dropdown' :
                //return new MakeNaviDropdown($tplFile, true, $pageId);
                return  new MakeNavi_OldVersion($tplFile, true, $pageId); 
            case 'dropdown2' :
                return new MakeNaviDropdown2($tplFile, true, $pageId);
                        
            default:
                return  new MakeNavi($tplFile, true, $pageId);    
                
        }
        
        
    }

}
