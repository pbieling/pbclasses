<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace PbClasses\Image;

/**
 * Description of Resize
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 16.05.2018, 14:45:02
 */
class Resize {

    protected $srcFile;
    protected $targetFile;
    protected $srcWidth;
    protected $srcHeight;
    protected $targetWidth;
    protected $targetHeight;
    protected $imgType;

    public function __construct($srcFile, $targetFile, $targetWidth, $targetHeight = null, $autoRun = false) {
        $this->srcFile = $srcFile;
        $imgProperties = \getimagesize($this->srcFile);
        $this->srcWidth = $imgProperties[0];
        $this->srcHeight = $imgProperties[1];
        $this->imgType = $imgProperties[2];
        $this->targetFile = $targetFile;
        //Wenn Zielhöhe oder -weite weggelassen wird, wird dieser Wert nach der Ratio des Quellbildes ermittelt
        $this->targetWidth = ($targetWidth === null) ? (int) ($targetHeight * $this->srcWidth / $this->srcHeight) : $targetWidth;
        $this->targetHeight = ($targetHeight === null) ? (int) ($this->srcHeight * $targetWidth / $this->srcWidth) : $targetHeight;
        if ($autoRun) {
            $this->run();
        }
    }

    public function run() {

        if ($this->imgType == IMAGETYPE_JPEG) {
            $resourceId = \imagecreatefromjpeg($this->srcFile);
            $targetLayer = $this->resize($resourceId);
            \imagejpeg($targetLayer, $this->targetFile);
        } elseif ($this->imgType == IMAGETYPE_GIF) {
            $resourceId = \imagecreatefromgif($this->srcFile);
            $targetLayer = $this->resize($resourceId);
            \imagegif($targetLayer, $this->targetFile);
        } elseif ($this->imgType == IMAGETYPE_PNG) {
            $resourceId = \imagecreatefrompng($this->srcFile);
            $targetLayer = $this->resize($resourceId);
            \imagepng($targetLayer, $this->targetFile);
        }
    }

    protected function resize($resourceId) {
        $targetLayer = \imagecreatetruecolor($this->targetWidth, $this->targetHeight);
        //imagecopyresampled ( resource $dst_image , resource $src_image , int $dst_x , int $dst_y , int $src_x , int $src_y , int $dst_w , int $dst_h , int $src_w , int $src_h )
        \imagecopyresampled($targetLayer, $resourceId, 0, 0, 0, 0, $this->targetWidth, $this->targetHeight, $this->srcWidth, $this->srcHeight);
        return $targetLayer;
    }

}
