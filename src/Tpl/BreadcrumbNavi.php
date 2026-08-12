<?php

namespace PbClasses\Tpl;

use PbClasses\Conf;
use PbClasses\PbTpl;

/**
 * Generates breadcrumb navigation for the current page.
 *
 * Resolves the current page's position in the navigation tree and builds
 * an HTML breadcrumb trail by walking up through parent nodes. The result
 * is injected directly into the provided search-replace array.
 *
 * Usage (in PbClasses\View\ShowPage):
 *   if (Conf::get('breadcrumbnavi')) {
 *       new BreadcrumbNavi($this->mainConfig['se_re']);
 *   }
 *
 * Conf keys consumed:
 *   - 'breadcrumbnavi'    array  [placeholder, naviType, tplFile]
 *   - 'PAGE_ID'           int    The current page's ID
 *   - 'site_navi_tree'    array  The full navigation tree
 *   - 'BACKTPLPATH'       string Filesystem path to the template directory
 *   - 'navi_autoprepend'  string Optional URL subdirectory prefix
 *
 * @author Peter Bieling
 */
class BreadcrumbNavi
{

    /**
     * Accumulates rendered HTML snippets for each breadcrumb level.
     * Items are prepended so the array ends up in root-to-current order.
     *
     * @var string[]
     */
    private array $htmlBuffer = [];

    /**
     * The full site navigation tree, keyed by page ID.
     * Each node is expected to contain at least:
     *   - 'Label'    string       Display label for the page
     *   - 'URL'      string       Relative URL of the page
     *   - 'ParentID' int|null     ID of the parent node, or falsy for root
     *   - 'NoPage'   bool         Whether the node is a non-linkable group
     *   - 'nextlevel' array       Child nodes (used when NoPage is true)
     *
     * @var array<int, array<string, mixed>>
     */
    private array $naviTree;

    /**
     * The compiled template used to render individual breadcrumb snippets.
     *
     * @var PbTpl
     */
    private PbTpl $tpl;

    /**
     * Optional URL subdirectory prefix prepended to all node URLs.
     * Resolved once from Conf and cached to avoid repeated lookups.
     *
     * @var string
     */
    private string $urlPrefix;

    /**
     * Builds the breadcrumb navigation and injects it into the search-replace array.
     *
     * Reads the current page ID and navigation tree from Conf, walks up the tree
     * to collect ancestor nodes, renders each as an HTML snippet via the template,
     * and stores the combined result under the configured placeholder key.
     *
     * No output is produced if the current page is at the top level (no parent).
     *
     * @param array<string, string> $mainConfigSeRe Search-replace array passed by
     *                                              reference; the rendered breadcrumb
     *                                              HTML is written to the configured
     *                                              placeholder key.
     */
    public function __construct(array &$mainConfigSeRe)
    {
        [$placeholder,, $tplFile] = Conf::get('breadcrumbnavi');

        $pageId = Conf::get('PAGE_ID');
        $naviTree = Conf::get('site_navi_tree');

        // Breadcrumb is meaningless at the top level
        if (empty($naviTree[$pageId]['ParentID'])) {
            $mainConfigSeRe[$placeholder] = '';
            return;
        }

        $this->naviTree = $naviTree;
        $this->urlPrefix = (string) (Conf::get('navi_autoprepend') ?? '');
        $this->tpl = new PbTpl(Conf::get('BACKTPLPATH') . $tplFile);

        $this->buildBreadcrumb($pageId);

        $mainConfigSeRe[$placeholder] = $this->tpl->fillTpl(
                'navi',
                'links',
                implode('', $this->htmlBuffer)
        );
    }

    /**
     * Recursively builds the breadcrumb trail by walking up the navigation tree.
     *
     * Renders the node for the given page ID as an HTML snippet and prepends it
     * to {@see $htmlBuffer}, then recurses into the parent node until the root
     * is reached.
     *
     * Three template slots are used depending on node type:
     *   - 'link_active'  The current (deepest) page; no hyperlink, just a label.
     *   - 'link'         A normal navigable ancestor page.
     *   - 'group_link'   A non-page group node whose link targets its first child.
     *
     * @param int  $pageId       ID of the navigation node to render.
     * @param bool $isActivePage True for the first (current) page call;
     *                           false for all ancestor calls.
     */
    private function buildBreadcrumb(int $pageId, bool $isActivePage = true): void
    {
        $node = $this->naviTree[$pageId];

        if ($isActivePage) {
            $snippet = $this->tpl->fillTpl('link_active', 'label', $node['Label']);
        } else {
            $seRe = ['label' => $node['Label']];

            if (!$node['NoPage']) {
                $seRe['url'] = '/' . $this->urlPrefix . $node['URL'];
                $snippet = $this->tpl->fillTpl('link', $seRe);
            } else {
                $firstChild = reset($node['nextlevel']);
                $seRe['url'] = '/' . $this->urlPrefix . $firstChild['URL'];
                $snippet = $this->tpl->fillTpl('group_link', $seRe);
            }
        }

        array_unshift($this->htmlBuffer, $snippet);

        if ($node['ParentID']) {
            $this->buildBreadcrumb($node['ParentID'], false);
        }
    }
}
