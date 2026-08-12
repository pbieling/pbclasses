<?php

namespace PbClasses\Backend;

use PbClasses\Conf;
use PbClasses\PbTpl;

/**
 * Description of GenerateConfig:
 * 
 * Die Klasse
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 15.12.2017, 14:52:08
 */
class GenerateConfig {

    protected $db;

    public function __construct($id, $P) {
        //$url ist leer, wenn nichts geändert wurde (alt = neu)
        $type = $P['conf_selection_val'];
        $url = $P['url'];
        $oldUrl = $P['old_url'];

        $this->db = Conf::get('db');
        $baseDir = Conf::get('APATH') . 'config/';
        $assumedPageConfig = ($url) ? $baseDir . $url : Conf::get('APATH') . 'config/' . $oldUrl;
        //new \PbClasses\Debug\Logging('TEST-oben', __FILE__, __LINE__);

        //Es gibt bereits eine Konfiguration na der richtigen Stelle. Sollte nicht vorkommen
        if (\file_exists($assumedPageConfig)) {
            new \PbClasses\Exception\Logging($assumedPageConfig . ' existiert bereits.', __FILE__, __LINE__);
            return;
        }
        
        $urlPath = ($url) ? $url : $oldUrl;
        //Eventuell fehlende Verzeichnisse anlegen:
        \PbClasses\File\DirUtil::makeMissingDirs($baseDir, $urlPath);
        
        

        //Es gibt einen neuen Pfad und eine alte Konfiguration, die umbennat wird
        if ($url && $oldUrl && $oldUrl !== $url) {
            $oldAssumedPageConfig = $baseDir . $oldUrl;
            if (\file_exists($oldAssumedPageConfig)) {
                if (!\rename($oldAssumedPageConfig, $assumedPageConfig)) {
                    throw new \Exception('Die alte Konfigurationsdatei konnte nicht umbenannt werden.');
                }
                return;
            }
        }

        //Eine Konfigurationsdatei wird generiert
        //new \PbClasses\Debug\Logging('TEST', __FILE__, __LINE__);
        //Datei schreiben:
        $sr = ['id' => $id];
        /*
         *     [conf_selection_val] => docu-view
    [documoduleselection] => abcdef
         */
        
        if ($type === 'docu-view' || $type === 'docu-admin') {
            $sr['docu_name'] = $P['documoduleselection'];
        }
        
        

        $t = new \PbClasses\PbTpl(Conf::get('BACKTPLPATH') . 'system/config_' . $type . '.tpl');
        $content = $t->fillTpl('main', $sr);
        
        if (!file_put_contents($assumedPageConfig, $content)) {
            throw new \Exception('Die Konfigurationsdatei konnte nicht angelegt werden.');
        }
        
        //Bei Tabellen-Editioren wird noch eine individuelle Seiten-Template-Datei erstellt.
        if (isset($P['tableconfigselection'])) {
            $this->makeContentTpl($P['tableconfigselection'], $id);
        }
        
        
        //D:\Projekte\pbclasses2\pbc2\htdocs\admin\backtemplates\system\content-tableeditor.tpl
    }
    
    
    /*
     * Legt das passende Content-Tpl mit autoload-div an
     */
    protected function makeContentTpl($configFile,$id) {
        $outfile = Conf::get('BACKTPLPATH') . 'content-tableeditor-' . $id . '.tpl';
        $cTplFile = Conf::get('BACKTPLPATH') . 'system/content-tableeditor.tpl';
        $sr = ['tableconfig' => substr($configFile, 0, -4)]; //.php abschneiden
        
        $c = new PbTpl($cTplFile);
        file_put_contents($outfile, $c->fillTpl('main', $sr));
    }

}
