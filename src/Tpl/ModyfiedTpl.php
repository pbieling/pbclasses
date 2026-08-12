<?php
namespace PbClasses\Tpl;
/**
 * Make replacements in the whole templatefile (not only in a single snippet) before the Template ist used.
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 08.10.2019, 10:50:45
 */
class ModyfiedTpl extends \PbClasses\PbTpl {

    /**
     * 
     * @param type $tplFile    filename e.g. '/srv/www/htdocs/admin/backtemplates/example.tpl'
     * @param type $replArr     e.g. $replArr = array('%%PG_MODUL%%' => 'pg-pbieling')
     */
    public function __construct($tplFile, $replArr) {
        $tplString = \str_replace(\array_keys($replArr), \array_values($replArr), \file_get_contents($tplFile));
        parent::__construct($tplString, false);
    }
}
