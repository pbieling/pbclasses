<?php

namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbModule_commondocu\MConf;

/**
 * Description of ThumbnaulBar
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 17.05.2018, 16:34:56
 */
class ThumbnailBar extends \PbClasses\Ajax\AbstractResponse {
    protected $conf;
    
    public function action() {
        \PbClasses\Ajax\SessionStart::start();

        $this->conf = MConf::get('docu_conf');
        $thumbsData = $this->getThumbsData();


        try {
            $c = new \PbClasses\PbTpl($this->conf['admin']['image_upload_tpl']);
        } catch (\Exception $exc) {
            $this->rslt['errortext'] = $exc->getMessage();
            $this->output(__FILE__, __LINE__);
        }
        $this->rslt['thumbs_bar'] = $c->fillRowTpl('thumb_bar_item', $thumbsData );
        //new \PbClasses\Debug\Logging($this->conf, __FILE__, __LINE__);
        //$this->rslt['result'] = print_r($this->P, true);
        $this->rslt['succ'] = 'OK';
        $this->output();
    }
    
    protected function getThumbsData() {
        $thumbsData = array();
        $source = $this->conf['admin']['thumb_src_dir'];
        
        $dir = dir("$source");
        /*
         * echo "Handle: " . $d->handle . "\n";
           echo "Path: " . $d->path . "\n";
         */
        while (false !== ($fileName = $dir->read())) {
            // Skip pointers
            
            $fullFile = $this->conf['admin']['thumb_src_dir'] . '/' . $fileName;
            if ($fileName === '.' || $fileName === '..' || \is_dir($fullFile)) {
                continue;
            }
            $httpFile = $this->conf['admin']['thumb_http_path'] . $fileName;
            
            
            $thumbsData[] = array( 'filename' => $fileName,
                                   'thumbnail_path' => $httpFile,
                                  'date' =>  \date ("d.m.Y H:i:s", \filemtime($fullFile)));
       }
        $dir->close();
        return $thumbsData;
    }
    
    
    
    

}
