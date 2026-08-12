<?php
namespace PbClasses\MDoc;

/**
 *  @author Peter Bieling - 2016-09-20 
 *  Diese Klasse kann vereinfacht genutzt werden, wenn ein einzelner Platzhalter mit dem Ergebnis eines
 *  Klassenaufrufs gefüllt werden soll: Eintrag eines Strings durch die Kindgklasse Method run() in $this->result.
 * 
 *  Sollen mehrere Platzhalter gefüllt werden, muss ein assoziatives Array in this->result geschrieben werden.
 * (Beispiel: Bildergalerie mit AreaMaps)
 * 
 */


abstract class ClassResult {
    protected $paramArr;
    
    protected $result;
   
    public function __construct($paramArr) {
        $this->paramArr = $paramArr;
        $this->run();
    }
    
    abstract protected function run();

    public function getSeRe($fields) {
        //Es kommen mehrere Platzhalter mit Inhalt als key => value zurück
        if (\is_array($this->result)) {
            return $this->result;
        }
        
        //Es kommt ein String zuück. Vereinfachung:
        return array($fields[0] => $this->result);
    }
}
