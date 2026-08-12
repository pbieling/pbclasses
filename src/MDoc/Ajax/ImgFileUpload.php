<?php
namespace PbClasses\MDoc\Ajax;
use PbModule_commondocu\MConf;
use PbClasses\Util\Slash;


/**
 * @author Peter Bieling
 */
class ImgFileUpload extends \PbClasses\Ajax\AbstractResponse {

    protected $t;
    protected $formFields = array();
    protected $conf;

    //const MAX_UPLOAD_NUMBER = 3;

    public function action() {
       \PbClasses\Ajax\SessionStart::start();
        
         
        $this->conf = MConf::get('docu_conf');
        $uplConfig = $this->conf['admin'];
        
        
        /*
         *  'image_destination_dir' => $modRoot . '/media/img/',
            'thumb_destination_dir' => $modRoot . 'upload/img/thumbs/registered/',
            'img_allowed_types' => ['jpg', 'gif', 'png']
         */
        $fileType = \strtolower(\pathinfo(\basename($_FILES["file"]["name"]), PATHINFO_EXTENSION));
        //$fileName = $this->P['tk'] . '_' . $nr . '.' . $fileType;
        $fileName =  \date('YmdHis') . '_' . \basename($_FILES["file"]["name"]);

        if (!\in_array($fileType, $uplConfig['img_allowed_types'])) {
            $this->rslt['errortext'] = 'Falscher Dateityp. Erlaubt sind ' . join(', ', $uplConfig['img_allowed_types']) . '.';
            $this->output();
        }
        $targetFilePath = Slash::add($uplConfig['img_src_dir']) . $fileName;
        

        if (!\move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
            $this->rslt['errortext'] = 'Technischer Fehler beim Upload. Bitte versuchen Sie es noch einmal. ';
            $this->output();
        }
        
        if (!\file_exists($targetFilePath)) {
             $this->rslt['errortext'] = 'Bild nicht gefunden.';
             
            $this->output();           
        }

        $thumbsTargetFilePath = Slash::add($uplConfig['thumb_src_dir']) . 'thumb_' .$fileName;
        
        $thumbObj = new \PbClasses\Image\Resize($targetFilePath, $thumbsTargetFilePath, null, $uplConfig['thumb_max_size']);
        $thumbObj->run();

        $this->rslt['succ'] = 'OK';
        $this->rslt['message'] = 'Das Bild wurde hochgeladen.';
        $this->output();
    }

}
