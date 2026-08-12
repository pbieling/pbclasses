<?php

/**
 * @author Peter Bieling 2016-02-03
 */

namespace PbClasses\File;

class Upload {

    protected $errors = array();
    protected $fileExtensions = array();
    protected $uploadedFiles = 0;
    protected $checkImage = false;
    protected $maxFileSize = 4000000;

    public function __construct($imageCheck = false) {
        if ($imageCheck) {
            $this->checkImage = true;
        }
        
        if (null !== \PbClasses\Conf::get('allowed_file_extensions')) {
            $this->fileExtensions = \PbClasses\Conf::get('allowed_file_extensions');
        }
        if (!is_array($this->fileExtensions)) {
            throw new \Exception('Fehlerhaftes File-Extensions-Array.');
        }
        if (!count($this->fileExtensions)) {
            throw new \Exception('Keine File-Extensions angegeben!');
        }
    }

    public function setExtensionArr($extArr) {
        if (!is_array($extArr)) {
            throw new \Exception('Fehlerhaftes File-Extensions-Array.');
        }
        $this->fileExtensions = $extArr;
    }

    public function store($file_arr_name, $uploaddestination, $determinedFileName = false) {
        $uploaddestination .= DIRECTORY_SEPARATOR;
        if (!count($_FILES)) {
            $this->errors[] = "Keine Uploaddatei(en) erhalten";
            return false;
        } 
        
        if (count($_FILES) > 1 && $determinedFileName) {
            throw new \Exception('Dritter Parameter nur im Single-File-Modus erlaubt.');
        }

        for ($i = 0; $i < count($_FILES[$file_arr_name]['tmp_name']); $i++) {
            if (!$_FILES[$file_arr_name]['tmp_name'][$i]) {
                continue;
            }
            if ($_FILES[$file_arr_name]['error'][$i]) {
                // test($_FILES[$file_arr_name]['error'][$i], __LINE__);
                $this->errors[] = "Beim Upload von ist ein technischer Fehler aufgetreten.";
                continue;
            }
            $filename = ($determinedFileName) ? $determinedFileName : $_FILES[$file_arr_name]['name'][$i];
            if (false === $determinedFileName) {
                $filename = strtolower($filename);
                //Alle sonderbaren Zeichen ausixxen
                $filename = preg_replace('/[^a-z0-9_.-]/', 'x', $filename);
                if (true !== $this->checkExtension($filename)) {
                    continue;
                }
            }

            if ($this->checkImage && ! $this->checkImageFile($_FILES[$file_arr_name]['tmp_name'][$i] , $filename)) {
               // $this->errors[] = 'Fehlerhafte Bilddatei ' . $filename;
                continue;
            }    
            
            if ($this->maxFileSize && ! $this->checkFileSize($_FILES[$file_arr_name]["size"][$i] , $filename)) {
                //$this->errors[] = 'Fehlerhafte Bilddatei ' . $filename;
                continue;               
            }
 
            if (!move_uploaded_file($_FILES[$file_arr_name]['tmp_name'][$i], $uploaddestination . $filename)) {
                $this->errors[] = "_ERR_01: " . $filename . " konnte nicht verschoben werden.";
                continue;
            } else {
                $this->uploadedFiles++;
            }

            if (!chmod($uploaddestination . $filename, 0755)) {
                $this->errors[] = "_ERR_02: " . $filename . " - Zugriffsrechte konnten nicht geändert werden.";
            }
        }

        if (count($this->errors)) {
            return false;
        }
        return true;
    }
    
    protected function checkFileSize($size , $filename) {
        if ($size > $this->maxFileSize) {
            
            $this->errors[] = "Die Datei ist zu groß! Das Limit ist " . $this->maxFileSize . ' Byte.';
            return false;          
        } 
        return true;
    }

    protected function checkExtension($filename) {
        $file_arr = explode('.', $filename);
        if (count($file_arr) < 2) {
            $this->errors[] = "Die Dateiendung fehlt!";
            return false;
        }
        $ext = strtolower($file_arr[count($file_arr) - 1]);
        if (!in_array($ext, $this->fileExtensions)) {
            $this->errors[] = "Die Dateiendung $ext ist nicht freigegeben.";
            return false;
        }
        return true;
    }
    
    
    
    public function checkImageFile($file , $customername) {
        
// Check if image file is a actual image or fake image

        if (! getimagesize($file)) {
            $err = 'Die hochgeladene Datei ist kein Bild! ';
            $this->errors[] = $err;
            new \PbClasses\Exception\Logging($err . $customername, __FILE__, __LINE__);
            return false;
        }
        return true;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getUploadedNum() {
        return $this->uploadedFiles;
    }

}
