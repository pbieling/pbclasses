<?php

declare(strict_types=1);

namespace PbClasses\Backend;

use PbClasses\Conf;
use PbClasses\PbTpl;

/**
 * Renders the backend navigation tree by recursively walking each level of the
 * site hierarchy and building nested HTML via a template engine.
 *
 * The tree is traversed level by level. Each node fetches its children from the
 * database and passes the sub-array recursively into {@see self::buildSitemap()}
 * until no further children exist. The deepest level is rendered first, and the
 * finished HTML bubbles back up to the caller, resulting in a fully nested
 * structure stored in the global configuration under the key {@see Conf::addKV}.
 */
class BackendTree
{
    /** Template engine instance bound to the site-tree template. */
    protected PbTpl $tpl;

    /**
     * Initialises the template, builds the complete sitemap HTML, and stores
     * the result in the global key–value store as {@code backend_tree}.
     */
    public function __construct()
    {
        $subDir = Conf::get('sub_dir');
        $subDirPrefix = $subDir ? '/' . $subDir : '';

        $this->tpl = new PbTpl(Conf::get('BACKTPLPATH') . 'backend/site-tree.tpl');

        $replacements = [
            'tree' => $this->buildSitemap($this->getNaviRows()),
        ];

        Conf::addKV(
            'cSeRe',
            'backend_tree',
            $this->tpl->fillTpl('content1', $replacements)
        );
    }

    /**
     * Fetches all navigation rows whose parent matches {@code $parentId} and
     * recursively attaches each node's children under the {@code nextlevel} key.
     *
     * @param  int                    $parentId  ID of the parent node (0 = root level).
     * @return array<int, array{
     *     Label:     string,
     *     ID:        int,
     *     Status:    int,
     *     type:      string,
     *     nextlevel: array<int, mixed>|string
     * }>|false  Associative node array keyed by node ID, or {@code false} when
     *            no rows are found for the given parent.
     */
    protected function getNaviRows(int $parentId = 0): array|false
    {
        $db     = Conf::get('db');
        $table  = Conf::get('db_tables')['backnavi'];
        $where  = ['ParentId' => $parentId];

        $rows = $db->selectAssoc($table, '', $where, '', 'ORDER BY Ord, ID');

        if (!$rows) {
            return false;
        }

        $tree = [];

        foreach ($rows as $row) {
            $id = (int) $row['ID'];

            $tree[$id] = [
                'Label'     => $row['Label'],
                'ID'        => $id,
                'Status'    => $row['URL'] ? 1 : 0,
                'type'      => Icon::getIconType($row),
                'nextlevel' => $this->getNaviRows($id) ?: '',
            ];
        }

        return $tree;
    }

    /**
     * Recursively converts the raw navigation tree into nested HTML fragments
     * using the configured template engine.
     *
     * Each node is rendered via the {@code links} template slot; the collected
     * fragment is then wrapped by the {@code tree} slot before being returned
     * to the calling frame.
     *
     * @param  array<int, array<string, mixed>>|false $tree   Current level's node array,
     *                                                         or {@code false} for an empty branch.
     * @param  int                                    $level  Current nesting depth (1 = root).
     * @return string  Rendered HTML for this level and all its descendants.
     */
    protected function buildSitemap(array|false $tree, int $level = 1): string
    {
        if (!$tree) {
            return '';
        }

        $htmlBuffer = '';

        foreach ($tree as $nodeData) {
            // Recursively render children before the parent so inner HTML is
            // ready when the parent template slot is filled.
            $nodeData['nextlevel'] = is_array($nodeData['nextlevel'])
                ? $this->buildSitemap($nodeData['nextlevel'], $level + 1)
                : '';

            $nodeData['level'] = $level;

            $htmlBuffer .= $this->tpl->fillTpl('links', $nodeData);
        }

        return $this->tpl->fillTpl('tree', 'links', $htmlBuffer);
    }
}