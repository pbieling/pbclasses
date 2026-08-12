<?php

declare(strict_types=1);

/**
 * TableHtml – renders a database-backed data table as HTML.
 *
 * This class extends the base Table class and is responsible for turning
 * query result sets into fully templated HTML tables, including:
 *  - Header (<th>) and body (<td>) rendering
 *  - Per-cell CSS class assignment (nullable, editable, value-dependent)
 *  - Row-level CSS class injection via switch-labels and rowmarker strategies
 *  - Row action buttons (insert, change, delete)
 *  - Markdown and custom output-filter pipelines
 *  - Dynamic field injection via provider classes
 *
 * @author Peter Bieling (original), refactored to modern PHP 8.x
 */

namespace PbClasses\Table;

use PbClasses\Conf;
use PbClasses\Exception\Logging;
use PbClasses\Table\ColumnNamesOfEmptyTable;

class TableHtml extends Table
{
    /**
     * CSS class added to every <td> whose database value is NULL.
     * Can be changed at runtime via JavaScript on the first occurrence.
     */
    public const TD_NULL_CLASS = 'pbval-isnull';

    /** Template engine instance used to render table fragments. */
    protected mixed $t = null;

    /**
     * Per-column HTML attribute strings (e.g. colspan).
     * Keyed by column/field name.
     *
     * @var array<string, string>
     */
    protected array $tdAttrArr = [];

    /**
     * Per-column CSS class strings to be applied to every <td> in that column.
     * Keyed by column/field name.
     *
     * @var array<string, string>
     */
    protected array $tdClassArr = [];

    /**
     * Accumulates CSS classes for the <tr> currently being rendered.
     * Reset after each row.
     *
     * @var list<string>
     */
    protected array $rowClassArr = [];

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * @param string|array $table    Table name or table definition.
     * @param array        $conf     Configuration array for this table widget.
     * @param mixed|null   $dbHandle Database handle passed to the parent class.
     */
    public function __construct(mixed $table, array $conf, mixed $dbHandle = null)
    {
        parent::__construct($table, $conf, $dbHandle);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Renders the complete HTML table (or only a specific part of it).
     *
     * Behaviour when the result set is empty depends on the configuration key
     * `display_if_empty_on_start`:
     *  - `show_table`  → render the table shell with headers but no body rows.
     *  - `use_first_row` → derive headers from the first physical row of the
     *                      underlying table (ignoring filters), but render no body.
     *  - (absent)       → return an empty string.
     *
     * @param string $part 'all' (default) renders the full table;
     *                     'body' renders only the <tbody> rows.
     *
     * @return string Rendered HTML fragment.
     */
    public function getTableHtml(string $part = 'all'): string
    {
        // Fetch data; true = also fetch the total (unfiltered) row count.
        $resultSet = $this->getTableData(true);

        $emptyTableMessage = '';

        if (!$resultSet) {
            $emptyTableMessage = $this->conf['empty_table_message'] ?? '';

            // Ensure the key exists so that at least the table shell is shown.
            if (!isset($this->conf['display_if_empty_on_start'])) {
                $this->conf['display_if_empty_on_start'] = ['show_table' => true];
            }

            $emptyConf = $this->conf['display_if_empty_on_start'];

            if (!empty($emptyConf['show_table'])) {
                // Derive column names directly from the table schema.
                $headArr = ColumnNamesOfEmptyTable::get($this->db, $this->table, $this->fieldnameWhat);
                if (empty($headArr)) {
                    return '';
                }
                $resultSet = []; // No body rows.

            } elseif (!empty($emptyConf['use_first_row'])) {
                // Derive column names from the first physical row.
                $headArr = ColumnNamesOfEmptyTable::getFromFirst($this->db, $this->table, $this->fieldnameWhat);
                if (empty($headArr)) {
                    return '';
                }
                $resultSet = []; // No body rows.

            } else {
                return '';
            }
        } else {
            // Use the keys of the first result row as column headers.
            $headArr = array_keys($resultSet[0]);
        }

        $this->fillAttrClassArr($headArr);
        $this->initTemplate();

        // Store how many rows were actually delivered (may be less than total).
        $this->numberOfDeliveredRows = count($resultSet);

        $bodyRows = $this->getBodyRows($resultSet);

        if ($part === 'body') {
            return $bodyRows;
        }

        $thElements = $this->getThElements($headArr);

        $sr = [
            'tablename'         => $this->table,
            'display_tablename' => $this->conf['display_tablename'] ?? $this->table,
            'tabid'             => $this->conf['tabid'] ?? '',
            'th_elements'       => $thElements,
            'header_button_area' => $this->t->hasTpl('header_button_area')
                ? $this->t->fillTpl('header_button_area')
                : '',
            'body_rows'         => $bodyRows,
            'totalnumber'       => $this->numberOfUnfilteredRows ?: $this->numberOfRows,
            'insert_button'     => $this->getInsertButton(),
            'empty_table_message' => $emptyTableMessage,
        ];

        // Allow registered project-level classes to mutate the search-replace array.
        $this->runProjectClasses($sr);

        // Merge any extra template placeholders defined in configuration.
        if (!empty($this->conf['table_template_sere']) && is_array($this->conf['table_template_sere'])) {
            $sr = array_merge($sr, $this->conf['table_template_sere']);
        }

        $this->addIcons($sr);

        return $this->t->fillTpl('table', $sr);
    }

    /**
     * Returns the total number of rows matched by the current query
     * (before any LIMIT/pagination is applied).
     */
    public function getNum(): int
    {
        return $this->numberOfRows;
    }

    /**
     * Returns the zero-based offset of the first row in the current page.
     */
    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    /**
     * Returns the number of rows that were actually rendered in the last call
     * to {@see getTableHtml()} or {@see getBodyRows()}.
     */
    public function getBodyNum(): int
    {
        return $this->numberOfDeliveredRows;
    }

    /**
     * Returns the total number of rows without any active filters applied.
     * Falls back to the filtered count when no unfiltered count is available.
     */
    public function getUnfilteredNum(): int
    {
        return $this->numberOfUnfilteredRows ?: $this->numberOfRows;
    }

    /**
     * Returns the current pagination/limit selection value.
     */
    public function getLimitSelection(): mixed
    {
        return $this->limitSelection;
    }

    // -------------------------------------------------------------------------
    // Template initialisation
    // -------------------------------------------------------------------------

    /**
     * Instantiates the appropriate template engine based on the configuration.
     *
     * If `table_template_file_replacements` is set, a ModifiedTpl instance is
     * created (which supports string replacements in template files). Otherwise
     * the plain PbTpl engine is used.
     */
    protected function initTemplate(): void
    {
        $template = $this->conf['table_templatefile'];

        if (
            isset($this->conf['table_template_file_replacements']) &&
            is_array($this->conf['table_template_file_replacements'])
        ) {
            $this->t = new \PbClasses\Tpl\ModyfiedTpl(
                $template,
                $this->conf['table_template_file_replacements']
            );
        } else {
            $this->t = new \PbClasses\PbTpl($template);
        }
    }

    // -------------------------------------------------------------------------
    // Column-level attribute / class setup
    // -------------------------------------------------------------------------

    /**
     * Populates {@see $tdAttrArr} and {@see $tdClassArr} for each column.
     *
     * If `table_def.rights.change` is:
     *  - `true`  → every column receives the CSS class `chg`.
     *  - `array` → only the listed column names receive `chg`.
     *  - absent  → no class is added.
     *
     * @param array<string> $headArr Column names from the result set.
     */
    protected function fillAttrClassArr(array &$headArr): void
    {
        // Initialise empty entries for every column.
        foreach ($headArr as $head) {
            $this->tdAttrArr[$head] = '';
            $this->tdClassArr[$head] = '';
        }

        $changeRights = $this->conf['table_def']['rights']['change'] ?? null;

        if (empty($changeRights)) {
            return;
        }

        if ($changeRights === true) {
            // All columns are editable.
            foreach ($this->tdClassArr as $k => $v) {
                $this->tdClassArr[$k] .= ' chg';
            }
            return;
        }

        if (is_array($changeRights)) {
            // Only explicitly listed columns are editable.
            foreach ($changeRights as $fieldName) {
                if (!isset($this->tdClassArr[$fieldName])) {
                    new Logging($fieldName . ' is not a known field', __FILE__, __LINE__);
                    continue;
                }
                $this->tdClassArr[$fieldName] .= ' chg';
            }
            return;
        }

        new Logging(
            'Editable fields are not correctly defined: ' . print_r($changeRights, true),
            __FILE__,
            __LINE__
        );
    }

    // -------------------------------------------------------------------------
    // Header row (<th>) rendering
    // -------------------------------------------------------------------------

    /**
     * Renders all <th> elements for the table header row.
     *
     * Hidden columns (configured via `table_def.hidden`) are skipped.
     * Columns with a colspan of 0 are also skipped; any other colspan value is
     * emitted as a `colspan="…"` attribute.
     *
     * If a template fragment named `th_{fieldname}` exists it will be used
     * instead of the generic `th` template.
     *
     * @param  array<string> $headArr Column names.
     * @return string                 Concatenated <th> HTML fragments.
     */
    protected function getThElements(array $headArr): string
    {
        $buffer = '';

        foreach ($headArr as $head) {
            // Skip hidden columns.
            if ($this->isHiddenField($head)) {
                continue;
            }

            $colspan = '';
            $colspanDef = $this->conf['table_def']['colspan'][$head] ?? null;

            if ($colspanDef !== null) {
                if ($colspanDef === 0) {
                    continue; // Collapsed: skip entirely.
                }
                $colspan = ' colspan="' . $colspanDef . '"';
            }

            $sr = [
                'attributes' => $colspan,
                'fieldname'  => $head,
                'entry'      => $this->getModifiedTh($head),
            ];

            $template = $this->t->hasTpl('th_' . $head) ? 'th_' . $head : 'th';
            $buffer  .= $this->t->fillTpl($template, $sr);
        }

        return $buffer;
    }

    /**
     * Returns the display label for a column header.
     *
     * If `table_def.display_name` maps the raw field name to a custom label,
     * that label is returned. Otherwise the raw field name is returned unchanged.
     *
     * Configuration example:
     * ```php
     * 'table_def' => [
     *     'display_name' => [
     *         'Beschr' => 'DESCRIPTION',
     *         'ID'     => 'Serial No.',
     *     ],
     * ]
     * ```
     *
     * @param  string $head Raw column name.
     * @return string       Display label.
     */
    protected function getModifiedTh(string $head): string
    {
        return $this->conf['table_def']['display_name'][$head] ?? $head;
    }

    // -------------------------------------------------------------------------
    // Body row (<tr>/<td>) rendering
    // -------------------------------------------------------------------------

    /**
     * Renders all data rows into a concatenated HTML string.
     *
     * For each row the following pipeline is executed:
     *  1. Special-character escaping (HTML entities).
     *  2. Optional Markdown transformation.
     *  3. Optional custom output-filter class.
     *  4. Optional additional virtual fields.
     *  5. Switch-label and row-mode expansion.
     *  6. <td> or full-row template rendering.
     *  7. Dynamic data provider injection.
     *  8. Icon placeholder injection.
     *
     * @param  array<array<string, mixed>> $resultSet Rows from the database query.
     * @return string                                 Concatenated <tr> HTML fragments.
     */
    protected function getBodyRows(array $resultSet): string
    {
        $buffer = '';

        foreach ($resultSet as $tdArr) {
            $this->setSpecialChars($tdArr);

            if (!empty($this->conf['outputfilter']['md'])) {
                $this->applyMd($tdArr);
            }
            if (!empty($this->conf['outputfilter']['ownclass'])) {
                $this->applyOwnClass($tdArr);
            }
            if (!empty($this->conf['add_fields'])) {
                $this->applyAddFields($tdArr);
            }

            if (empty($this->conf['full_row_template'])) {
                // Standard rendering: individual <td> elements.
                $sr = [
                    'td_elements'     => $this->getTdElements($tdArr),
                    'button_area'     => $this->addRowButtons($tdArr),
                    'row_classes'     => $this->getRowClasses($tdArr),
                    'primary_key_val' => $this->getPrimaryKeyVal($tdArr),
                ];
            } else {
                // Full-row template: the raw row array IS the search-replace map.
                $this->addRowmodeSwitchLabels($tdArr);
                $sr                = &$tdArr;
                $sr['button_area'] = $this->addRowButtons($tdArr);
                $sr['row_classes'] = $this->getRowClasses($tdArr);
            }

            $this->provideFields($tdArr, $sr);
            $this->provideDynData($tdArr, $sr);
            $this->addIcons($sr);

            $buffer .= $this->t->fillTpl('body_rows', $sr);
        }

        return $buffer;
    }

    // -------------------------------------------------------------------------
    // Icon injection
    // -------------------------------------------------------------------------

    /**
     * Merges globally configured icon placeholders into the search-replace array.
     *
     * Icons are read from the application-wide configuration under the key
     * `pbtable_icons` and are made available in every template that is filled
     * during the current render pass.
     *
     * @param array<string, mixed> $sr Search-replace array (passed by reference).
     */
    private function addIcons(array &$sr): void
    {
        $iconArr = Conf::get('pbtable_icons');
        if (is_array($iconArr)) {
            $sr = array_merge($sr, $iconArr);
        }
    }

    // -------------------------------------------------------------------------
    // Output filters
    // -------------------------------------------------------------------------

    /**
     * HTML-encodes cell values using PHP's FILTER_SANITIZE_SPECIAL_CHARS filter.
     *
     * Cells are skipped when:
     *  - The value is NULL.
     *  - The field has an `html`, `md`, or `ownclass` output-filter entry
     *    (those filters handle their own escaping).
     *  - The field appears in `add_fields` (virtual/computed fields).
     *
     * @param array<string, mixed> $tdArr Row data (passed by reference).
     */
    protected function setSpecialChars(array &$tdArr): void
    {
        foreach ($tdArr as $key => $val) {
            if ($val === null) {
                continue;
            }
            if (!empty($this->conf['outputfilter']['html'][$key])) {
                continue;
            }
            if (!empty($this->conf['outputfilter']['md'][$key])) {
                continue;
            }
            if (!empty($this->conf['outputfilter']['ownclass'][$key])) {
                continue;
            }
            if (!empty($this->conf['add_fields'][$key])) {
                continue;
            }

            $tdArr[$key] = filter_var($val, FILTER_SANITIZE_SPECIAL_CHARS);
        }
    }

    /**
     * Transforms Markdown field values to HTML using Michelf\MarkdownExtra.
     *
     * Only fields listed under `outputfilter.md` in the configuration are
     * processed; all other fields remain untouched.
     *
     * @param array<string, mixed> $tdArr Row data (passed by reference).
     */
    protected function applyMd(array &$tdArr): void
    {
        foreach ($tdArr as $key => $val) {
            if (!empty($this->conf['outputfilter']['md'][$key])) {
                $tdArr[$key] = \Michelf\MarkdownExtra::defaultTransform((string) $val);
            }
        }
    }

    /**
     * Invokes a custom output-filter class for each field that has one configured.
     *
     * The class receives the entire row array by reference so it can modify
     * multiple fields at once if needed. The class name is read from
     * `outputfilter.ownclass.{fieldName}`.
     *
     * @param array<string, mixed> $tdArr Row data (passed by reference).
     */
    protected function applyOwnClass(array &$tdArr): void
    {
        foreach ($tdArr as $key => $val) {
            if (!empty($this->conf['outputfilter']['ownclass'][$key])) {
                $className = $this->conf['outputfilter']['ownclass'][$key];
                new $className($tdArr); // Row is passed by reference to the constructor.
            }
        }
    }

    /**
     * Appends virtual (computed) fields to the row array via add-field classes.
     *
     * Each entry in `conf['add_fields']` maps a target field name to a class name.
     * The class constructor receives the target key, the full row array, and the
     * configuration and is expected to set `$tdArr[$key]` by reference.
     *
     * @param array<string, mixed> $tdArr Row data (passed by reference).
     */
    protected function applyAddFields(array &$tdArr): void
    {
        foreach ($this->conf['add_fields'] as $key => $className) {
            if (!empty($className)) {
                new $className($key, $tdArr, $this->conf);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Field / data providers
    // -------------------------------------------------------------------------

    /**
     * Copies selected row fields into the search-replace array under
     * configurable placeholder names.
     *
     * This is useful when a template needs a raw database value (e.g. a numeric
     * ID for building a link) that is not rendered as a standalone cell.
     *
     * Configuration example:
     * ```php
     * 'provide_fields' => [
     *     'user_id' => 'USER_ID_PLACEHOLDER',
     * ]
     * ```
     *
     * @param array<string, mixed> $tdArr Source row data.
     * @param array<string, mixed> $sr    Search-replace array (passed by reference).
     */
    protected function provideFields(array &$tdArr, array &$sr): void
    {
        if (empty($this->conf['provide_fields'])) {
            return;
        }

        foreach ($this->conf['provide_fields'] as $fieldName => $placeholder) {
            $sr[$placeholder] = $tdArr[$fieldName];
        }
    }

    /**
     * Populates dynamic data placeholders in the search-replace array via
     * registered provider classes.
     *
     * Each entry in `conf['provide_dyn_data']` maps a placeholder name to either
     * a class-name string or an array where the first element is the class name
     * and remaining elements are additional constructor arguments.
     *
     * @param array<string, mixed> $tdArr Row data (passed by reference).
     * @param array<string, mixed> $sr    Search-replace array (passed by reference).
     */
    protected function provideDynData(array &$tdArr, array &$sr): void
    {
        if (empty($this->conf['provide_dyn_data'])) {
            return;
        }

        foreach ($this->conf['provide_dyn_data'] as $placeholder => $classDef) {
            if (!$classDef) {
                continue;
            }

            if (is_string($classDef) && class_exists($classDef)) {
                new $classDef($placeholder, $sr, $tdArr, $this->conf);
                continue;
            }

            if (is_array($classDef)) {
                $className    = array_shift($classDef); // First element = class name.
                $extraArgs    = $classDef;              // Remaining elements = extra args.
                new $className($placeholder, $sr, $tdArr, $this->conf, $extraArgs);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Row CSS class helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the final CSS class string for the current <tr> element.
     *
     * Combines:
     *  1. Classes accumulated in {@see $rowClassArr} during cell rendering.
     *  2. Classes returned by an optional rowmarker class
     *     (`table_def.rowmarker_class`).
     *
     * The internal buffer is reset after each call.
     *
     * @param  array<string, mixed> $tdArr Row data (passed by reference).
     * @return string                      Space-separated CSS class string.
     */
    protected function getRowClasses(array &$tdArr): string
    {
        $bufferedClasses    = trim(implode(' ', $this->rowClassArr));
        $this->rowClassArr  = []; // Reset buffer for the next row.

        $markerClass = $this->conf['table_def']['rowmarker_class'] ?? null;
        if (empty($markerClass)) {
            return $bufferedClasses;
        }

        try {
            $obj = new $markerClass();
            return trim($bufferedClasses . ' ' . $obj->getClassString($tdArr));
        } catch (\Exception $exc) {
            new Logging($exc->getMessage(), __FILE__, __LINE__);
            return $bufferedClasses;
        }
    }

    // -------------------------------------------------------------------------
    // Row buttons
    // -------------------------------------------------------------------------

    /**
     * Renders the action buttons (delete, change, etc.) for a data row.
     *
     * Button visibility is controlled by `table_def.row_buttons`, which is a map
     * of button identifiers to boolean flags:
     * ```php
     * 'row_buttons' => ['deletable' => true, 'changeable' => true]
     * ```
     *
     * @param  array<string, mixed> $tdArr Row data (passed by reference).
     * @return string                      Concatenated button HTML.
     */
    protected function addRowButtons(array &$tdArr): string
    {
        $buttonDefArr = $this->conf['table_def']['row_buttons'] ?? null;

        if (!is_array($buttonDefArr)) {
            return '';
        }

        $buttonBuffer = '';
        $sr           = ['PRIMARY_KEY_VAL' => $this->getPrimaryKeyVal($tdArr)];

        foreach ($buttonDefArr as $buttonId => $active) {
            if (!$active) {
                continue;
            }

            $buttonBuffer .= match ($buttonId) {
                'deletable'  => $this->t->fillTpl('delete_button', $sr),
                'changeable' => $this->t->fillTpl('change_button', $sr),
                default      => '',
            };
        }

        return $buttonBuffer;
    }

    // -------------------------------------------------------------------------
    // Primary key resolution
    // -------------------------------------------------------------------------

    /**
     * Extracts and returns the primary key value for a row.
     *
     * Supports both single-column and composite primary keys:
     *  - Single key (string): the raw value from `$tdArr[$pk]` is returned.
     *  - Composite key (array of strings): values are serialised and base64-encoded
     *    so that they can be passed as a single URL parameter.
     *
     * Falls back to `'ID'` when no primary key is configured.
     *
     * @param  array<string, mixed> $tdArr Row data (passed by reference).
     * @return string                      Primary key value (or base64-encoded composite).
     */
    protected function getPrimaryKeyVal(array &$tdArr): string
    {
        $pk = $this->conf['table_def']['primary_key'] ?? 'ID';

        if (is_string($pk)) {
            if (!isset($tdArr[$pk])) {
                new Logging($pk . ' not found.', __FILE__, __LINE__);
                return '';
            }
            return (string) $tdArr[$pk];
        }

        if (is_array($pk)) {
            $values = [];
            foreach ($pk as $singleKey) {
                if (!isset($tdArr[$singleKey])) {
                    new Logging($singleKey . ' not found.', __FILE__, __LINE__);
                    return '';
                }
                $values[] = $tdArr[$singleKey];
            }
            return base64_encode(serialize($values));
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // Cell (<td>) rendering
    // -------------------------------------------------------------------------

    /**
     * Renders all visible <td> elements for a single data row.
     *
     * Hidden fields are skipped (see `table_def.hidden`).
     * Each cell receives:
     *  - Its column CSS classes plus an optional null-value class.
     *  - A switch-label for toggle columns.
     *  - A value-dependent CSS class (if configured).
     *
     * A field-specific template (`td_{fieldName}`) is used when available,
     * otherwise the generic `td` template is used.
     *
     * @param  array<string, mixed> $tdArr Row data.
     * @return string                      Concatenated <td> HTML fragments.
     */
    protected function getTdElements(array $tdArr): string
    {
        $buffer = '';

        foreach ($tdArr as $key => $td) {
            if ($this->isHiddenField($key)) {
                continue;
            }

            $nullClass = ($td === null) ? ' ' . self::TD_NULL_CLASS : '';
            $classes   = ($this->tdClassArr[$key] ?? '') . $nullClass;

            $sr = [
                'fieldname'  => $key,
                'attributes' => '',
                'classes'    => ltrim($classes),
                'entry'      => ($td === null) ? '' : $td,
                'id'         => $tdArr['ID'] ?? '',
            ];

            $this->addSwitchLabel($sr);
            $this->addValDependentClass($sr);

            $template = $this->t->hasTpl('td_' . $key) ? 'td_' . $key : 'td';
            $buffer  .= $this->t->fillTpl($template, $sr);
        }

        return $buffer;
    }

    // -------------------------------------------------------------------------
    // Switch-label and row-mode helpers
    // -------------------------------------------------------------------------

    /**
     * Injects a human-readable switch label into the cell's search-replace array
     * and queues a corresponding row CSS modifier class.
     *
     * Switch columns map raw database values (e.g. `0`, `1`) to display labels
     * (e.g. `'Off'`, `'On'`). Configuration example:
     * ```php
     * 'rights' => [
     *     'switch' => ['on_off' => [0 => 'Off', 1 => 'On']]
     * ]
     * ```
     *
     * The `css_rowmod` configuration key controls which value drives the generated
     * CSS class:
     *  - absent / other → use the label value (default).
     *  - `'TABLE_VALUE'` → use the raw database value.
     *  - `'NONE'`        → do not add a row CSS class.
     *
     * @param array<string, mixed> $sr Cell search-replace array (passed by reference).
     */
    protected function addSwitchLabel(array &$sr): void
    {
        $fieldName  = $sr['fieldname'];
        $switchConf = $this->conf['table_def']['rights']['switch'][$fieldName] ?? null;

        if ($switchConf === null) {
            return;
        }

        $sr['switch_label'] = $switchConf[$sr['entry']];

        $cssModStrategy = isset($this->conf['css_rowmod'][$fieldName])
            ? strtoupper($this->conf['css_rowmod'][$fieldName])
            : null;

        if ($cssModStrategy === 'NONE') {
            return;
        }

        $cssModifier = match ($cssModStrategy) {
            'TABLE_VALUE' => $sr['entry'],
            default       => $sr['switch_label'],
        };

        $this->rowClassArr[] = 'rowmod-' . $fieldName . '-' . $cssModifier;
    }

    /**
     * Injects switch labels for all switch columns into the full-row
     * search-replace array (used in `full_row_template` mode).
     *
     * Also queues row CSS modifier classes using the same strategy as
     * {@see addSwitchLabel()}.
     *
     * @param array<string, mixed> $sr Full-row search-replace array (passed by reference).
     */
    protected function addRowmodeSwitchLabels(array &$sr): void
    {
        $switchConf = $this->conf['table_def']['rights']['switch'] ?? null;
        if (empty($switchConf)) {
            return;
        }

        foreach ($switchConf as $fieldName => $valueMap) {
            $sr[$fieldName . '_switch_label'] = $valueMap[$sr[$fieldName]];

            $cssModStrategy = isset($this->conf['css_rowmod'][$fieldName])
                ? strtoupper($this->conf['css_rowmod'][$fieldName])
                : null;

            if ($cssModStrategy === 'NONE') {
                return;
            }

            $cssModifier = match ($cssModStrategy) {
                'TABLE_VALUE' => $sr[$fieldName],
                default       => $sr[$fieldName . '_switch_label'],
            };

            $this->rowClassArr[] = 'rowmod-' . $fieldName . '-' . $cssModifier;
        }
    }

    /**
     * Appends a value-dependent CSS class to the row class buffer.
     *
     * Configuration example:
     * ```php
     * 'value_dependent_row_classes' => [
     *     'status' => ['approved' => 'row-approved', 'pending' => 'row-pending'],
     * ]
     * ```
     *
     * @param array<string, mixed> $sr Cell search-replace array (passed by reference).
     */
    protected function addValDependentClass(array &$sr): void
    {
        $cssClass = $this->conf['table_def']['value_dependent_row_classes'][$sr['fieldname']][$sr['entry']] ?? null;
        if ($cssClass !== null) {
            $this->rowClassArr[] = $cssClass;
        }
    }

    // -------------------------------------------------------------------------
    // Insert button
    // -------------------------------------------------------------------------

    /**
     * Renders the "insert new record" button if both the insert right and the
     * corresponding template fragment are present.
     *
     * @return string HTML for the insert button, or an empty string.
     */
    protected function getInsertButton(): string
    {
        if (
            !empty($this->conf['table_def']['rights']['insert']) &&
            $this->t->hasTpl('insert_button')
        ) {
            return $this->t->fillTpl('insert_button');
        }
        return '';
    }

    // -------------------------------------------------------------------------
    // Project class runner
    // -------------------------------------------------------------------------

    /**
     * Instantiates each class listed in `conf['run_classes']`, passing the
     * search-replace array, the template engine, and the configuration.
     *
     * This hook allows project-specific classes to add or modify template
     * placeholders without subclassing TableHtml.
     *
     * @param array<string, mixed> $sr Search-replace array (passed by reference).
     */
    protected function runProjectClasses(array &$sr): void
    {
        if (empty($this->conf['run_classes'])) {
            return;
        }

        foreach ($this->conf['run_classes'] as $projectClass) {
            if (class_exists($projectClass)) {
                new $projectClass($sr, $this->t, $this->conf);
            } else {
                new Logging($projectClass . ' not found', __FILE__, __LINE__);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Utility helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true when the given field name is in the configured hidden-fields list.
     *
     * @param string $fieldName Column name to check.
     */
    protected function isHiddenField(string $fieldName): bool
    {
        $hidden = $this->conf['table_def']['hidden'] ?? null;
        return is_array($hidden) && in_array($fieldName, $hidden, strict: true);
    }
}