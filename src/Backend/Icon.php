<?php
namespace PbClasses\Backend;
use PbClasses\Conf;

/** Peter Bieling - 2020-08-14
 * Die Klasse sorgt im Zusammnspiel mit JavaScript für JsTree, für den Austausch von Icons,
 * z.B. nach Sperren eines Ordners. In der Hauptkonfiguration können andere Icon-Klassen definiert
 * werden als die hier verwendeten Glyhpicons, z.B. Font-Awesom
 *
 * @author Peter Bieling - info@media-palette.de
 */
class Icon {
    private static $defaultIconArr =  array('visual_yes' => 'glyphicon-eye-open',
                         'visual_no'  => 'glyphicon-eye-close',
                         'file' => 'glyphicon-file',
                         'folder_close' => 'glyphicon-folder-close',
                         'folder_open' => 'glyphicon-folder-open');
    
    public static function getIconType($row) {
        if (! $row['URL']) {
            return 'default';
        }
        
        $append = ($row['Hide']) ? '-locked' : '-unlocked';
        if (Conf::get('pbc_version')) {
            //Verbesserte Version beim Page-Generator, Dokusystem und Websitegenerator.
            //Geschlossener Ordner wird anfangs korrekt angezeigt.
            $first = (! empty($row['NoPage'])) ? 'dir-closed' : 'file';
        } else {
            //alte Version:
            $first = (! empty($row['NoPage'])) ? 'dir' : 'file';
        }
        
        return $first . $append;
    }
    
    
    public static function getIcon($key) {
        $iconArr = Conf::get('icons');
        if (empty($iconArr)) {
            return (isset(self::$defaultIconArr[$key])) ? self::$defaultIconArr[$key] : '';
           
        }
        return (isset($iconArr[$key])) ? $iconArr[$key] : '';
    }
}
