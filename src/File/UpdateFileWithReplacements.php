<?php
namespace PbClasses\File;

/**
 * Description of UpdateFileWithReplacements
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 11.10.2019, 12:31:32
 */
class UpdateFileWithReplacements {

    /**
     * 
     * @param mixed $fileArr   file or array of files
     * @param type $seRe       search-replace array
     */
    protected $seRe;
    protected $searchArr = array();  //Contains modified placeholders if $upperCaseCurlyBracket = true

    public function __construct($fileArr, $seRe, $upperCaseCurlyBracket = true) {

        $this->seRe = $seRe;
        if (\is_string($fileArr)) {
            $fileArr = array($fileArr);
        }

        if ($upperCaseCurlyBracket) {
            $this->modifySeRe();
        }
        foreach ($fileArr as $sgFile) {
            $this->handleFile($sgFile);
        }
        if ($upperCaseCurlyBracket) {
            $this->modifySeRe();
        } else {
            $this->searchArr = \array_keys($this->seRe);
        }
    }

    protected function handleFile($sgFile) {
        if (!\file_exists($sgFile)) {
            throw new \Exception($sgFile . ' does not exist.');
        }

        $fileContent = str_replace($this->searchArr, \array_values($this->seRe),\file_get_contents($sgFile));
                if (!  \file_put_contents($sgFile, $fileContent)) {
          throw new \Exception($sgFile . ' could not be upated.');
        }
    }

    protected function modifySeRe() {
       
        foreach (\array_keys($this->seRe) as $placeholder) {
            $this->searchArr[] = '{' . \strtoupper($placeholder) . '}';
        }
    }

}
