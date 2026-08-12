<?php

namespace PbClasses\MDoc;

use PbClasses\Conf;

/**
 * @author Peter Bieling - info@media-palette.de
 * Created on : 04.06.2018, 17:04:18
 */
class DocIndex {

    const TEASER_IMAGE_PATTERN = 'teaser-%d.jpg';

    protected $conf;
    protected $db;
    protected $docTables;
    protected $docVars;

    const QUANTITY_ARTICLE_PREVIEW = 5;
    const QUANTITY_ARCHIVE_ITEMS = 20;

    public function __construct($conf) {
        $this->conf = $conf;
        $this->db = Conf::get('db');
        $this->docTables = $conf['doc_tables'];
        $this->docVars = $conf['doc_vars'];
    }

    public function getIndex() {
        $numberOfPreviewArticles = (empty($this->conf['public']['number_of_preview_articles'])) ? self::QUANTITY_ARTICLE_PREVIEW : (int) $this->conf['public']['number_of_preview_articles'];
       // \PbClasses\Develop::test($numberOfPreviewArticles, __LINE__, 1);
        
        $table = $this->docTables['PAGE_INDEX'];
        //return print_r($this->conf, true);
        //ID 	ParentID 	ModID 	GrID 	Label 	URL 	HelpID 	Ord 	Hide 	NoPage 	LastUpdate
        $what = array('ID', 'ModID', 'Label', 'URL');
        $where = array('ParentID' => 0, 'NoPage' => 0, 'Hide' => 0);
        $condition = 'ORDER BY Ord LIMIT ' . $numberOfPreviewArticles;


        $res = $this->db->selectAssoc($table, $what, $where, '', $condition);

        $tplFile = $this->conf['public']['blog_index_tpl'];
        $c = new \PbClasses\PbTpl($tplFile);

        if (!$res) {
            return $c->fillTpl('content', 'teaser_rows', $c->fillTpl('no_rows'));
        }

        $teaserRows = '';
        for ($i = 0; $i < count($res); $i++) {
            $headline = $this->getContentValue('headline', $res[$i]);
            $seRe = array(
                'headline' => ($headline) ? $headline : $res[$i]['Label'],
                'teaser' => $this->getContentValue('teaser', $res[$i]),
                'doc_link' => $res[$i]['URL'],
                'preview_image_block' => $this->getPreviewImageBlock($res[$i], $c));
            $teaserRows .= $c->fillTpl('teaser_row', $seRe);
        }

        return $c->fillTpl('content', 'teaser_rows', $teaserRows);
    }

    public function getArchive() {
        $numberOfArchiveArticles = (empty($this->conf['public']['number_of_archive_articles'])) ? self::QUANTITY_ARCHIVE_ITEMS : (int) $this->conf['public']['number_of_archive_articles'];

        
        $table = $this->docTables['PAGE_INDEX'];
        //return print_r($this->conf, true);
        //ID 	ParentID 	ModID 	GrID 	Label 	URL 	HelpID 	Ord 	Hide 	NoPage 	LastUpdate
        $what = array('ID', 'ModID', 'Label', 'URL');
        $where = "ParentID > 0 AND NoPage = 0 AND Hide = 0";
        $condition = 'ORDER BY Ord LIMIT ' . $numberOfArchiveArticles;


        $res = $this->db->selectAssoc($table, $what, $where, '', $condition);
        if (!$res) {
            return ''; //Leerstring = Keine Archivanzeige
        }

        $tplFile = $this->conf['public']['blog_archive_tpl'];
        $c = new \PbClasses\PbTpl($tplFile);

        /*
          if (!$res) {
          return $c->fillTpl('content', 'archive_rows', $c->fillTpl('no_rows'));
          }
         * 
         */

        $archiveRows = '';


        for ($i = 0; $i < count($res); $i++) {
            $headline = $this->getContentValue('headline', $res[$i]);
            $seRe = array(
                'headline' => ($headline) ? $headline : $res[$i]['Label'],
                'doc_link' => $res[$i]['URL']);

            $archiveRows .= $c->fillTpl('archive_row', $seRe);
        }

        return $c->fillTpl('content', 'archive_rows', $archiveRows);
    }

    protected function getPreviewImageBlock($indexRow, $c) {
        $imgDir = $this->conf['admin']['image_destination_dir'];
        $image = sprintf(self::TEASER_IMAGE_PATTERN, $indexRow['ID']);
        if (!\file_exists($imgDir . $image)) {
            return '';
        }


        /*
         * [preview_image_block]
          <div class="preview_image_block">
          <img src="{PREVIEW_IMAGE_SRC}" alt="{PREVIEW_IMAGE_ALT}">
          <p class="preview_image_author">{PREVIEW_IMAGE_AUTHOR}</p>
          </div>
         */

        $seRe = array('PREVIEW_IMAGE_SRC' => $this->conf['doc_vars']['img_http_dir'] . $image,
            'PREVIEW_IMAGE_AUTHOR' => '',
            'PREVIEW_IMAGE_ALT' => '');

        return $c->fillTpl('preview_image_block', $seRe);
    }

    protected function getContentValue($key, & $indexRow) {
        $table = $this->docTables['PAGE_CONTENT'];
        $what = 'Entry, Filter';
        //ID 	ModID 	PageId 	Row 	Placeholder 	Entry 	Filter 	
        $where = array(
            'PageId' => $indexRow['ID'],
            'Placeholder' => $key,
            'ModID' => $indexRow['ModID']
        );
        $condition = 'LIMIT 1';
        $res = $this->db->selectAssoc($table, $what, $where, '', $condition);
        if (!$res) {
            return '';
        }
        return $res[0]['Entry'];

        //Todo: md5
    }

}
