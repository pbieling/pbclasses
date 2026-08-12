<?php

namespace PbClasses\Ajax;

use PbClasses\Conf;
use PbClasses\User;
use \PbClasses\Session;

/**
 * Starts session and checks user
 * 
 * @author Peter Bieling - 2016-05-26
 */
class SessionStart {
    public static function start() {
      
        if (isset($_SESSION)) {
            return false;
        }
        
        
        new Session();
        //Wenn User nicht gecheckt werden soll (z.B, Tabelleneditor in Public)
        if (Conf::get('ajax_no_user_check') === true) {
            return true;
        }
                           
        
        new User('ajax');
        return true;
        

    }
}
