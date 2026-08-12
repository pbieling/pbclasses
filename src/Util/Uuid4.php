<?php
namespace PbClasses\Util;

use PbClasses\Conf;
use Ramsey\Uuid\Uuid;


/**
 * Wrapper für die leichtere Nutzung der Bibliothek
 *
 * @author Peter Bieling 
 */
class Uuid4 {

    public function __construct() {

        if (!class_exists('Ramsey\\Uuid\\Uuid')) {
            $pbclassesPath = dirname(Conf::getPbClassesPath());
            if ($pbclassesPath) {
                $autoload = $pbclassesPath . '/ramsey/autoload.php';
                if (\file_exists($autoload)) {
                    require_once $autoload;
                }
            }
        }
    }

    public function get() {
        return (string) Uuid::uuid4();
    }

}
