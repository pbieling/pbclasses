<?php

namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbModule_commondocu\MConf;

class ImgRegistration extends \PbClasses\Ajax\AbstractResponse {

    protected $conf;
    protected $imgArr;
    protected $imageName;
    protected $db;

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        if (!$this->P['field_arr']) {
            $this->rslt['errortext'] = 'Keine Bilddaten übermittelt';
            $this->output(__FILE__, __LINE__);
        }
        $this->db = Conf::get('db');
        \parse_str($this->P['field_arr'], $this->imgArr);

        $this->imageName = $this->imgArr['imgdata_oldimage'];
        $this->conf = MConf::get('docu_conf');

        $this->makeEntry();
        


        $this->rslt['succ'] = 'OK';
        $this->output();
    }

    protected function makeEntry() {
        $file = $this->conf['admin']['img_src_dir'] . '/' . $this->imageName;
        if (!\file_exists($file)) {
            $this->rslt['errortext'] = 'Bild nicht gefunden: ! ' . $file;
            $this->output();
        }
        if (!$imgArr = \getimagesize($file)) {
            $this->rslt['errortext'] = 'Die Datei ' . $this->imageName . ' ist kein Bild! ';
            $this->output();
        }
        //ID 	ParentID 	ImgFile 	ImgPath 	ImgW 	ImgH 	ImgDescr 	CrDate 	Location 	Updated 	Author 	UID 	Comment 	UploadFileName 
        //ImgFile 	ImgPath 	ImgW 	ImgH 	ImgDescr 	CrDate 	Location Author 	UID 	Comment UploadFileName 


        $crDate = $this->imgArr['imgdata_crdate'];

        $inArr = array('ImgFile' => $this->imgArr['imgdata_imgname'],
            'ImgPath' => '/', //Todo
            'ImgW' => $imgArr[0],
            'ImgH' => $imgArr[1],
            'ImgDescr' => $this->imgArr['imgdata_description'],
            'CrDate' => $crDate,
            'Location' => $this->imgArr['imgdata_location'],
            'Author' => $this->imgArr['imgdata_author'],
            //'UID' => $_SESSION['uid'],
            'Comment' => $this->imgArr['imgdata_comment'],
            'UploadFileName' => $this->imgArr['imgdata_oldimage']
        );

        $table = $this->conf['doc_tables']['PAGE_IMAGES_INDEX'];
        try {
            $this->db->insert($table, $inArr);
        } catch (Exception $exc) {
            $this->rslt['errortext'] = 'Bilddaten konnten nicht eingetragen werden.';
            new \PbClasses\Exception\Logging($exc->getMessage());
            $this->output();
        }
        
        $this->moveFiles($file, $inArr);
    }

    /*
     * Verschiebt die Bilddateien in die Zielordner
     */
    protected function moveFiles($srcFile, & $inArr) {
        $imgDestination = $this->conf['admin']['image_destination_dir'] . $inArr['ImgFile'];
        $srcThumb = $this->conf['admin']['thumb_src_dir'] . '/thumb_' . $inArr['UploadFileName'];
        $destinationThumb = $this->conf['admin']['thumb_destination_dir'] . '/thumb_' . $inArr['UploadFileName'];
        if (!rename($srcFile, $imgDestination)) {
            $this->rslt['errortext'] = 'Bild-Datei konnte nicht verschoben werden!';
            $this->output();
        }
        
        if (!rename($srcThumb, $destinationThumb)) {
            $this->rslt['errortext'] = 'Thumb-Datei konnte nicht verschoben werden!';
            $this->output();
        }
    }

}
