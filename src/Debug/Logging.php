<?php

namespace PbClasses\Debug;

use \PbClasses\Exception\Logging as ExcLogging;

class Logging {

    const DEFAULT_OUTFILE = 'system/log/debug.log';

    /**
     * Uses \PbClasses\Exception\Logging
     * 
     * @param mixed $data      text to save
     * @param string $srcFile    __FILE__
     * @param string $srcLine         __LINE__
     * @param string $outFile      (own filename, full path)
     */
    public function __construct($data, $srcFile = '', $srcLine = '', $outFile = null) {
        $apath = \PbClasses\Conf::get('APATH');
        $logfile = $outFile ?? $apath . self::DEFAULT_OUTFILE;
        new ExcLogging($data, $srcFile, $srcLine, $logfile);
        
    }
}
