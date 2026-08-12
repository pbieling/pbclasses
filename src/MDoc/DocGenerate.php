<?php

namespace PbClasses\MDoc;

use PbClasses\Conf;
use \PbClasses\MDoc\ProjectMethods;
use PbClasses\PbTpl;
use PbClasses\Exception\Logging;

class DocGenerate
{

    const INLINE_INCLUDE_NOT_FOUND = '- Element nicht gefunden -';

    protected $write_all_new = true;
    protected $all_navis = array(); //Hält die Navigationsstrukturen
    protected $navi_info = array(); //Templatedatei, Name etc.
    protected $row_counter = array();
    protected $errors = array();
    protected $messages = array();
    protected $token_arr = array();
    protected $docTables = array();
    protected $docVars = array();
    // protected $pageId;
    protected $preview = false;
    protected $techTools = '';
    protected $conf = null;
    protected $systemReplacements = array();
    protected $searchReplace;

    const STANDARD_DE_IMGDIR = '/img/';

    protected $db;

    public function __construct($conf)
    {
        $this->conf = $conf;
        //require_once (DISPLAYNAVITPLCLASS);
        //require_once(DISPLAYSTATICNAVITPLCLASS);
        $this->db = Conf::get('db');
        $this->docTables = $conf['doc_tables'];
        $this->docVars = $conf['doc_vars'];

        $this->token_arr = $this->getTokenArray();
    }

    public function getPage($pageId)
    {
        $rowIndex = $this->getPageIndexData($pageId);
        $modId = $rowIndex['ModID'];
        $grId = $rowIndex['GrID'];

        $switchModArray = array();
        
        //key value array of table rows
        $structureResult = $this->getStructure($modId);

        //Bei einem leeren Modul wird nur das Template eingebunden (Änderung 22.10.2016)

        $tplArray = $this->getTplArray($modId);
        if ($tplArray === false) {
            return false;
        }
                
        list ($tplFile, $tplName) = $tplArray;
        $tplObj = new PbTpl($tplFile);
        

        $html_buffer = '';
        if (!isset($this->row_counter[$modId])) {
            $this->row_counter[$modId] = 0;
        }

        $nextrow = false;
        do {
            $se_re = array();

            foreach ($structureResult as $row) {
                $nextrow = false;
                //$fields = $tmp_index = trim($row['Fields']);
                $fields = $tmp_index = preg_replace("/\s/", '', $row['Fields']);
                if (!$succ = $this->check_switch($pageId, $row['Pages'], $grId, $row['Groups'], $fields, $switchModArray)) {
                    continue;
                }

                if ($succ === 'G') {
                    $switchModArray[$fields] = 'G';
                } else if ($succ === 'P') {
                    $switchModArray[$fields] = 'P';
                }


                switch ($row['DataSource']) {
                    case 'entries':
                        $back_arr = $this->getEntries($row, $pageId, $this->row_counter[$modId]);
                        if ($back_arr === false) {
                            $this->errors[] = "Fehler beim Einlesen der Einträge aus DB.";
                            return false;
                        }
                        if (\count($back_arr)) {
                            $se_re = \array_merge($se_re, $back_arr);
                            if ($row['TplType'] == 'row') {
                                $nextrow = true;
                            }
                        } elseif ($row['TplType'] === 'row') {
                            if ($html_buffer) {
                                return $html_buffer; //Abbruch
                            }
                        }
                        break;
                    case 'navi':
                        $navi_page_id = $pageId;
                        //Falls die Navigation nur bis zu einer bestimmten Tiefe gehen soll
                        if ($row['Param'] == 'parent') {
                            $navi_page_id = $this->getParentId($pageId);
                        }

                        $back_arr = $this->get_navis($row, $navi_page_id);
                        //    test($back_arr,__LINE__,1);

                        if ($back_arr === false) {
                            $this->errors[] = "Fehler beim Einlesen der Einträge aus DB.";
                            return false;
                        }

                        $se_re = array_merge($se_re, $back_arr);
                        break;

                    case 'include':
                        $tmp_index = trim($row['Fields']);
                        $srcname = trim($row['SourceName']);
                        $tmp_src = $this->docVars['PAGE_CONTENT_PATH'] . $srcname;
                        if (strtolower($srcname) === 'empty') {
                            $se_re[$tmp_index] = '';
                        } elseif (!$tmp_src or !file_exists($tmp_src)) {
                            $this->errors[] = $tmp_src . ' wurde nicht gefunden.';
                            $se_re[$tmp_index] = '';
                        } else {
                            $se_re[$tmp_index] = file_get_contents($tmp_src);
                        }
                        break;

                    case 'mod':
                        $tmp_index = trim($row['Fields']);
                        $new_mod_id = trim($row['SourceName']);

                        $param = $row['Param'];
                        if ($param) {
                            $new_mod_id = $this->get_other_mod_id($modId, $param);
                        }
                        if ($new_mod_id == $modId) {
                            $this->errors[] = "Falscher Wert für Modul. Modul ruft sich selbst auf. Abbruch wegen Endlosschleife.";
                            return false;
                        }
                        //$se_re[$tmp_index] = getPage ($new_mod_id, $pageId, $grId);
                        if (!isset($mod_buffer[$tmp_index])) {
                            $mod_buffer[$tmp_index] = '';
                        }
                        if ($row['TplType'] == 'add') {
                            $mod_buffer[$tmp_index] .= $this->getPage($new_mod_id, $pageId, $grId);
                            $se_re[$tmp_index] = $mod_buffer[$tmp_index];
                        } else {
                            $se_re[$tmp_index] = $this->getPage($new_mod_id, $pageId, $grId);
                        }

                        break;

                    case 'img':
                        //Todo: Bei mehreren Bildnummern müssen die Platzhalter mit Nummer
                        //zurückgegeben werden: IMGH1, IMGH2 usw.
                        //Todo2: In row-Templates Reihe abfragen, wie bei Entries
                        $back_arr = $this->get_img_plh($fields, $modId, $pageId);
                        $se_re = \array_merge($se_re, $back_arr);
                        break;

                    case 'project_class':
                        //$funcname = strtolower($row['SourceName']);
                        $classname = $row['SourceName'];

                        //@todo:
                        //require_once(PROJECTFUNC_CLASS);
                        $projectMethodsClass = $this->docVars['PROJECT_METHODS_CLASS'];
                        //Konfiguration und Templatepfad mit übergeben!
                        $row['doc_conf'] = $this->conf;
                        //Hier wird das ganze Templateobjekt übergeben:
                        $row['tpl_obj'] = $tplObj;
                        $row['row_index'] = $rowIndex;

                        $prj = new $projectMethodsClass($pageId, $modId, $grId, $row, $this->token_arr);

                        try {
                            $fieldArr = explode(',', $fields);
                            $func_sere = $prj->getClassResult($classname, $fieldArr);
                            $se_re = \array_merge($se_re, $func_sere);
                        } catch (\Exception $exc) {

                            $this->errors[] = "Fehler: " . $exc->getMessage();
                        }

                        //\PbClasses\Develop::test($func_sere, __LINE__, 1);

                        break;

                    case 'project_func':
                        $funcname = $row['SourceName'];
                        $prj = new ProjectMethods($pageId, $modId, $grId, $row, $this->token_arr);

                        if (\method_exists($prj, $funcname)) {
                            $func_sere = $prj->$funcname();
                            $se_re = \array_merge($se_re, $func_sere);
                        } else {
                            $this->errors[] = "Methode $funcname nicht definiert!";
                        }
                    default:
                        break;  //Fall behandeln!!!
                }
            }
            $se_re['docindex_label'] = $rowIndex['Label'];
            $se_re['docindex_id'] = $rowIndex['ID'];

            if (isset($this->docVars['docu_tpl_sere'])) {
                //ermöglicht das Füllen von Platzhaltern aus der Konfiguration
                $se_re = array_merge($se_re, $this->docVars['docu_tpl_sere']);
            }

            $this->searchReplace = & $se_re;
            $html_buffer .= $tplObj->fillTpl($tplName, $se_re);

            $this->row_counter[$modId]++;
        } while ($nextrow == true);

        return $html_buffer;
    }
    
    private function getTplArray($modId) {
        
        
        //Template ID aus Modul-Tabelle holen
        $tplId = $this->getTplId($modId);
        if (false === $tplId) {
            $this->errors[] = "Kein Template für Modul-ID $modId gefunden.";
            return false; //neu!           
        }
        //Templateinformationen holen:       
        $tpl_arr = $this->getTplFile($tplId);

        if ($tpl_arr === false) {
            return false;
        }
        return $tpl_arr;
        
    }

    private function getPageIndexData($pageId)
    {
        $what = "ID, ParentID, Ord, ModID, Label, URL, GrID";

        $where = array('ID' => $pageId, 'Hide' => 0);
        $res = $this->db->selectAssoc($this->docTables['PAGE_INDEX'], $what, $where, '', 'LIMIT 1');
        if (!$res) {
            throw new \Exception("Pace $pageId not found.");
        }
        return $res[0];
    }   

    private function getStructure($modId)
    {
        $what = '';
        $where = [
            'ModID' => $modId,
            'Hide' => 0
        ];
        //Erst vorgegebene Reihenfolge, Felder um gleiche Felder zu gruppieren,
        //Seiten zuerst (vor Leerstring), Gruppen dann
        $condition = "order by Ord, Fields, Pages desc, `Groups` desc"; //sinnvoll?
        return $this->db->selectAssoc($this->docTables['PAGE_STRUCTURE'], $what, $where, '', $condition);
    }

    /*
     * Macht die Einzelelemente verfügbar, die in anderem Zusammenhang genutzt werden können
     * Rückgabe entweder ganzes Array oder Wert
     */

    public function getSearchReplace($index = null)
    {
        if (!$index) {
            return $this->searchReplace;
        }
        return $this->searchReplace[$index];
    }

    protected function getTplId($modId)
    {
        $res = $this->db->select($this->docTables['PAGE_MODULES'], 'TplID', array('ModID' => $modId, 'Hide' => 0), '', 'LIMIT 1');
        if (!$res) {
            return false;
        }
        return $res[0][0];
    }

    protected function getParentId($id)
    {
        $what = array('ParentID');
        $where = array('ID' => $id);
        $res = $this->db->select($this->docTables['PAGE_INDEX'], $what, $where);
        if (!$url = $res[0][0]) {
            $this->errors[] = "Keine ParentID für PageID $id gefunden.";
            return 0;
        }
        return ($res[0][0]);
    }

    protected function getTplFile($tpl_id)
    {
        // Template-Objekt:
        $what = '';
        //Eventuell noch verbessern, wegen redundanten TplID
        $where = array('ID' => $tpl_id);
        $condition = "Limit 1"; //sinnvoll?
        $res = $this->db->selectAssoc($this->docTables['PAGE_TEMPLATES'], $what, $where, '', $condition);
        if (!$res) {
            $this->errors[] = "Kein Template mit dieser ID gefunden.";
            return false;
        }

        //========
        $tplFile = $this->conf['doc_vars']['FRONTTPLPATH'] . $res[0]['FileName'];
        if (!file_exists($tplFile)) {
            $this->errors[] = "Template-Datei nicht gefunden: " . $res[0]['FileName'];
            return false;
        }

        return (array($tplFile, $res[0]['TplName']));
    }

    protected function get_img_plh($fields, $modId, $pageId)
    {
        $what = 'ImageID';
        $where = array('ModID' => $modId,
            'PageID' => $pageId,
            'ImageNr' => 1);
        $condition = 'limit 1';

        $res = $this->db->select($this->docTables['PAGE_IMAGES_DEST'], $what, $where, '', $condition);
        if (!$res) {
            $this->errors[] = "Kein Bild gefunden für PageID $pageId, ModID $modId";
            return array();
        }
        $img_id = $res[0][0];

        $what = 'ImgFile, ImgPath, ImgH, ImgW, ImgDescr, ImgAlt, Author';
        $where = array('ID' => $img_id);
        $condition = 'limit 1';
        $res = $this->db->selectAssoc($this->docTables['PAGE_IMAGES_INDEX'], $what, $where, '', $condition);
        if (!$res) {
            $this->errors[] = "Kein Bild gefunden für PageID $pageId, Bild-ID $img_id";
            return array();
        }

        $back_arr = $res[0];
        if (!$back_arr['ImgPath']) {
            //@todo
            $back_arr['ImgPath'] = self::STANDARD_DE_IMGDIR;
        }
        return $back_arr;
    }

    /*
     * prüft, ob der Platzhalter zur Gruppe passt und merkt sich den Platzhalter
     */

    protected function check_switch($pageId, $page_str, $grId, $gr_str, $fields, $switchModArray)
    {
        $page_str = trim($page_str);
        $gr_str = trim($gr_str);
        //Keine Einträge, also Default

        if ($gr_str === '' && $page_str === '') {
            //Feld wurde bereits registriert
            if (isset($switchModArray[$fields])) {
                return false;
            }
            return true;
        }
        //Pages:
        if ($page_str !== '') {
            $page_arr = explode(',', $page_str);
            foreach ($page_arr as $v) {
                $v = trim($v);
                if ($pageId == $v) {
                    //$switchModArray[$fields] = 'P';
                    return 'P';
                }
            }
        }

        if ($gr_str !== '') {
            $gr_arr = explode(',', $gr_str);
            foreach ($gr_arr as $v) {
                $v = trim($v);
                if ($grId == $v) {
                    if (isset($switchModArray[$fields])) {
                        //Bereits passender Seitenindex registriert
                        if ($switchModArray[$fields] == 'P') {
                            return false;
                        }
                    }
                    //neu registrieren oder überschreiben, falls doppelte Eintr�ge vorhanden sind
                    //$switchModArray[$fields] = 'G';
                    return 'G';
                }
            }
        }
        return false;
    }

    protected function getEntries($pstruct, $pageId, $row)
    {
        $back = array();
        $id = $pageId;

        /* Entries werden von der ParentID übernommen, wenn der Paramert gesetzt wird:
         *
         */
        if (trim($pstruct['Param']) == 'parent') {
            $id = $this->getParentId($id);
            $pstruct['ModID'] = trim($pstruct['SourceName']);
        }




        $fields = $pstruct['Fields'];
        if (!$fields = trim($fields)) {
            $this->errors[] = "Leerer Eintrag in Fields für PageID $pageId";
            return false;
        }
        $field_arr = explode(',', $fields);
        //counter für Reihen. Bei nicht definierten Feldern wird der
        //counter heruntergezählt. Erst, wenn kein kompletter Platzhalter-Satz definiert ist,
        //wird davon ausgegangen, dass keine neue Reihe kommt.
        $num_fields = count($field_arr);

        $langSuffix = (isset($this->langConf['lang'])) ? '_' . $this->langConf['lang'] : '';
        foreach ($field_arr as $sg) {
            $sg = trim($sg);
            $what = "Entry$langSuffix as Entry, Filter, ID";

            $where = "Placeholder = '$sg' AND " .
                    "(PageID = '$id' OR PageID = 0) AND " .
                    "ModID = '" . $pstruct['ModID'] . "' AND " .
                    "`Row` = '$row'";
            $condition = 'ORDER BY `PageID` DESC LIMIT 1';
            $res = $this->db->select($this->docTables['PAGE_CONTENT'], $what, $where, '', $condition);

            if ($row > 0 && !$res) {
                --$num_fields;
                if ($num_fields == 0) {
                    return array(); //leeres Array bedeutet, keine Reihe mehr.
                } else {
                    $back[$sg] = '';
                }
            } else if ($row == 0 && !$res) {
                $back[$sg] = '';
            } else {
                $text = $res[0][0];

                $filters = trim($res[0][1]);
                if ($filters) {
                    $filter_arr = explode(',', $filters);
                    foreach ($filter_arr as $sg_filter) {
                        $sg_filter = trim($sg_filter);
                        switch ($sg_filter) {
                            case 'md':
                                $text = $this->makeInlineReplacements($text);
                                $text = $this->markdownFilter($text);
                                break;
                            case 'p':
                                $text = $this->pFilter($text);
                                $text = $this->makeInlineReplacements($text);
                                break;
                            case 'nl2br':
                                $text = nl2br($text);
                                break;
                            default:
                                break;
                        }
                    }
                }
                $text = $this->getIncludes($text);

                $back[$sg] = $text;
            }
        }

        return $back;
    }

    protected function getIncludes($text)
    {
        //includes einetzen:
        //todo: ersetzt nur einen Treffer, erweitern auf preg_match_all

        $matches = null;
        if (!\preg_match('~%%([a-z]+)%(.*)%%~', $text, $matches)) {
            return $text;
        }
        $mod = $matches[1];
        $file = $matches[2];
        //@todo
        $fullPath = $this->docVars['PAGE_CONTENT_PATH'] . $file;
        if (!\file_exists($fullPath)) {
            $err = $fullPath . ' nicht gefunden.';
            new Logging($err, __FILE__, __LINE__);
        }

        $content = \file_get_contents($fullPath);
        $text = \trim(str_replace('%%' . $mod . '%' . $file . '%%', $content, $text));
        return $text;
    }

    /**
     * Markdown in HTML übersetzen
     * @param string $text
     * @return string
     */
    protected function markdownFilter($text)
    {
        $text = $this->prepareCodeForPrism($text);

        return \Michelf\MarkdownExtra::defaultTransform($text);
    }

    protected function prepareCodeForPrism($text)
    {
        return preg_replace_callback(
                '/```([a-zA-Z0-9_-]+)\s*\n([\s\S]*?)\n```/',
                function ($matches) {
                    $language = $matches[1]; // z.B. "php"
                    $code = $matches[2]; // der Code selbst
                    // Beispiel: HTML-escapen für Prism
                    $code = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $randNumber = uniqid();
                    return "<pre class='line-numbers language-{$language}'><code class=\"language-{$language}\" id='codeid-{$randNumber}'>{$code}</code></pre><script>Prism.highlightElement($('#codeid-{$randNumber}')[0]);</script>";
                },
                $text
        );
    }

    protected function pFilter($text)
    {
        $text_arr = preg_split("/[\r]?\n[\r]?\n/", $text);
        $back = '';
        $arr_len = sizeof($text_arr);
        $last = $arr_len - 1;
        for ($i = 0; $i < $arr_len; $i++) {
            $text_arr[$i] = trim($text_arr[$i]);

            if (
                    substr(trim($text_arr[$i]), 0, 1) == '<' or
                    substr(trim($text_arr[$i]), 0, 2) == '%%') {
                $back .= $text_arr[$i] . "\n"; //nichts machen wenn Element eingefügt ist
            } elseif (!trim($text_arr[$i])) {
                $back .= $text_arr[$i] . "\n";
                //nichts machen
            } elseif ($i < $last) {
                $back .= '<p>' . $text_arr[$i] . '</p>' . "\n";
            } else {
                //letztes Element markieren:
                $back .= '<p class="lastp">' . $text_arr[$i] . '</p>' . "\n";
            }
        }

        return $back;
    }

    /*
      function year_filter($text) {
      return preg_replace('/(\(\d{4}\))/', '<b>$1</b>', $text);

      }
     */

    /**
     * Replaces inline placeholders in the text with dynamically generated content from the database.
     *
     * This method scans the input text for placeholders matching the pattern "%%<ID>%%",
     * retrieves the corresponding template and content from the database, applies any specified filters,
     * and replaces the placeholder with the rendered template.
     *
     * @param string $text The input text containing placeholders to be replaced.
     * @return string The text with all placeholders replaced by their corresponding content.
     *
     * @throws \RuntimeException If a required template, file, or element is not found.
     */
    protected function makeInlineReplacements(string $text): string
    {
        return preg_replace_callback(
                "/%%(\d+)%%/",
                function (array $matches): string {
                    $elementId = (int) $matches[1];

                    // Fetch TemplateId for the element
                    $templateId = $this->fetchTemplateId($elementId);
                    if ($templateId === null) {
                        new Logging("Element {$elementId} nicht gefunden!", __FILE__, __LINE__);
                        return '-- FEHLENDES ELEMENT --';
                    }

                    // Fetch template file details
                    $templateDetails = $this->fetchTemplateDetails($templateId);
                    if ($templateDetails === null) {
                        new Logging("Template {$templateId} wurde nicht gefunden!", __FILE__, __LINE__);
                        return '-- FEHLENDES ELEMENT --';
                    }

                    $tplFile = $this->conf['doc_vars']['FRONTTPLPATH'] . $templateDetails['FileName'];
                    if (!file_exists($tplFile)) {
                        new Logging("Template-Datei {$tplFile} wurde nicht gefunden!", __FILE__, __LINE__);
                        return '-- FEHLENDES ELEMENT --';
                    }

                    $template = new PbTpl($tplFile);
                    $placeholders = [];
                    $replacements = [];

                    // Fetch content for the element
                    $contentRows = $this->fetchContentRows($elementId);
                    if ($contentRows !== []) {
                        $this->processContentRows($contentRows, $template, $placeholders, $replacements);
                        $placeholders[] = 'element_id';
                        $replacements[] = $elementId;
                    }

                    $this->addConfSeRe($placeholders, $replacements);

                    return $template->fillTpl($templateDetails['TplName'], $placeholders, $replacements);
                },
                $text
        );
    }

    /**
     * Fetches the TemplateId for a given ElementId from the database.
     *
     * @param int $elementId The ID of the element.
     * @return int|null The TemplateId, or null if not found.
     */
    private function fetchTemplateId(int $elementId): ?int
    {
        $result = $this->db->select(
                $this->docTables['PAGE_INDEX_INLINE'],
                'TemplateId',
                ['ElementId' => $elementId],
                '',
                'LIMIT 1'
        );

        return $result ? (int) $result[0][0] : null;
    }

    /**
     * Fetches the FileName and TplName for a given TemplateId from the database.
     *
     * @param int $templateId The ID of the template.
     * @return array{FileName: string, TplName: string}|null The template details, or null if not found.
     */
    private function fetchTemplateDetails(int $templateId): ?array
    {
        $result = $this->db->selectAssoc(
                $this->docTables['PAGE_TEMPLATES'],
                'FileName, TplName',
                ['ID' => $templateId],
                '',
                'LIMIT 1'
        );

        return $result ? $result[0] : null;
    }

    /**
     * Fetches all content rows for a given ElementId from the database.
     *
     * @param int $elementId The ID of the element.
     * @return array<array{Placeholder: string, Entry: string, Filter: string, ElementId: string}> The content rows.
     */
    private function fetchContentRows(int $elementId): array
    {
        return $this->db->selectAssoc(
                        $this->docTables['PAGE_CONTENT_INLINE'],
                        'Placeholder, Entry, Filter, ElementId',
                        ['ElementId' => $elementId]
                );
    }

    /**
     * Processes content rows, applies filters, and populates placeholders and replacements.
     *
     * @param array<array{Placeholder: string, Entry: string, Filter: string, ElementId: string}> $contentRows The content rows to process.
     * @param PbTpl $template The template engine instance.
     * @param array<string> &$placeholders The array of placeholders to be populated.
     * @param array<string> &$replacements The array of replacements to be populated.
     */
    private function processContentRows(
            array $contentRows,
            PbTpl $template,
            array &$placeholders,
            array &$replacements
    ): void
    {
        foreach ($contentRows as $row) {
            $placeholders[] = $row['Placeholder'];

            switch ($row['Filter']) {
                case 'getImageData':
                    $imageData = $this->getImageData($row['Entry']);
                    $replacements[] = $template->fillTpl('image_element', $imageData);
                    break;

                case 'add_imgdata':
                    $replacements[] = $row['Entry'];
                    $imageData = $this->getImageData($row['Entry']);
                    $placeholders = array_merge($placeholders, array_keys($imageData));
                    $replacements = array_merge($replacements, array_values($imageData));
                    break;

                case 'make_list':
                    $listItems = str_replace("\r\n", "\n", trim($row['Entry']));
                    $listItems = explode("\n", $listItems);
                    $replacements[] = $template->fillRowTpl('list_element', 'single_element', $listItems);
                    break;

                case 'nl2br':
                    $replacements[] = nl2br($row['Entry']);
                    break;

                case 'specialchars':
                    $replacements[] = htmlspecialchars($row['Entry']);
                    break;

                default:
                    $replacements[] = $row['Entry'];
            }
        }
    }

    protected function addConfSeRe(& $se, & $re)
    {
        //'docu_tpl_sere'
        //ermöglicht, das Einfügen von Werten in das Template, um Common-Templates zu modifizieren.
        //(z.B. Einfügen des media-Pfads
        if (isset($this->docVars['docu_tpl_sere'])) {
            foreach ($this->docVars['docu_tpl_sere'] as $k => $v) {
                $se[] = $k;
                $re[] = $v;
            }
        }
    }

    /*
     * z.B. für Inline-Blocks
     */

    protected function getImageData($imageId)
    {


        $what = 'ImgFile, ImgPath, ImgH, ImgW, ImgDescr, ImgAlt, Author';
        $where = array('ID' => $imageId);
        $condition = 'limit 1';
        $res = $this->db->selectAssoc($this->docTables['PAGE_IMAGES_INDEX'], $what, $where, '', $condition);
        if (!$res) {

            $this->errors[] = "Kein Bild gefunden für Bild-ID $imageId";
            $back_arr = [
                'ImgAlt' => self::INLINE_INCLUDE_NOT_FOUND,
                'ImgPath' => '',
                'ImgH' => '',
                'ImgW' => '',
                'ImgDescr' => 'Nicht gefunden',
                'Author' => ''
            ];
            $back_arr['http_path'] = '';
            $back_arr['ImgPath'] = '';
        } else {

            $back_arr = $res[0];
            $back_arr['http_path'] = $this->conf['doc_vars']['img_http_dir'] . $back_arr['ImgFile'];
            $back_arr['ImgPath'] = $this->conf['doc_vars']['img_http_dir'];
        }

        return $back_arr;
    }

    /**
     * Aufruf erst direkt vor der Ausgabe, damit Abkürzungen auf der ganzen Seite ersetzt werden können,
     * unabhängig davon, wo sie stehen.
     * @param type $str
     */
    protected function replaceTokens(& $str)
    {
        $str = str_replace(\array_keys($this->token_arr), \array_values($this->token_arr), $str);
    }

    protected function replaceSystemKeyVal(& $str)
    {
        $str = str_replace(\array_keys($this->systemReplacements), \array_values($this->systemReplacements), $str);
    }

    protected function getTokenArray()
    {
        $lang = (!isset($this->langConf)) ? 'DE' : strtoupper($this->langConf['lang']);
        $what = array('Token', $lang);
        $res = $this->db->selectAssoc($this->docTables['PAGE_TOKENS'], $what);
        $back = array();
        if (!$res) {
            return $back;
        }
        foreach ($res as $row) {
            $back[$row['Token']] = $row[$lang];
        }
        return $back;
    }

    public function getErrors()
    {
        return join("\n", $this->errors);
    }

    public function getMessages()
    {
        return $this->messages;
    }
}
