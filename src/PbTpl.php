<?php

declare(strict_types=1);
/**
 * PbTpl - Simple but effective template class
 * (Optimized with claude.ai)
 *
 * @version   4.1
 * @link      https://www.media-palette.de/demo/tpldemo/examples/index.php
 * @copyright Copyright (c) 2004 - 2026 Peter Bieling - pb@p-bieling.de
 * @license   MIT
 */

namespace PbClasses;

class PbTpl
{
    /**
     * Usually empty lines in the template are ignored. To save a line set ! at the beginning.
     */
    private const EMPTY_ROW = '!';

    /**
     * Holds all templates in an associative array. The key is the name of the template. The value the template string.
     *
     * @var array<string,string>
     */
    private array $tplArr = [];

    /**
     * @var bool
     */
    private bool $convert = true;

    /**
     * @param string $template  The path to a template file or a string (that could be the content of a template file)
     * @param bool $file  if you pass a string set to false
     * @param string $addIndex  If you pass a string or if the file only contains
     *                        one single template (or for any reason you don't want
     *                        to declare the first index) you can add an index
     *                        on the fly with the third parameter $addIndex (for instance "main").
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __construct(string $template, bool $file = true, string $addIndex = "")
    {
        if ($file && !file_exists($template)) {
            throw new \InvalidArgumentException("Missing template file: {$template}");
        }

        if (!$file && $template === '') {
            throw new \InvalidArgumentException('Missing template code');
        }

        $lineArr = $file ? file($template) : preg_split('/(?<=\n)/', $template);

        if ($lineArr === false) {
            throw new \RuntimeException('Problem reading template file');
        }

        if ($addIndex !== '') {
            $this->tplArr[$addIndex] = "";
        }

        $currIndex = $addIndex;
        foreach ($lineArr as $line) {
            $this->buildTplArr($line, $currIndex);
        }
    }

    /**
     * Whether or not to convert the placeholder from
     * placeholdername to {PLACEHOLDERNAME}. (Uppercase in curly braces)
     *
     * @param bool $convert
     * @return void
     */
    public function setConvert(bool $convert = true): void
    {
        $this->convert = $convert;
    }

    /**
     * @param string $placeholder
     * @return string
     */
    private function convertPlaceholder(string $placeholder): string
    {
        return '{' . strtoupper($placeholder) . '}';
    }

    /**
     * The method is looking for template names in square brackets, e.g.
     * [main] and sets it as key. The following rows are taken as value,
     * except comment lines and empty lines that are not marked with !.
     *
     * @param string $line The current line of the template file.
     * @param string &$currIndex
     * @return void
     * @throws \RuntimeException
     */
    private function buildTplArr(string $line, string &$currIndex): void
    {
        $templine = trim($line);

        if ($templine === '') {
            return;
        }

        $first = $templine[0];

        // Handle escaped lines
        if ($first === '\\' && $currIndex !== '') {
            $pos = strpos($line, '\\');
            if ($pos === false) {
                throw new \RuntimeException('strpos error - this should never happen');
            }
            $this->tplArr[$currIndex] .= substr_replace($line, '', $pos, 1);
            return;
        }

        // Handle template section names
        if ($first === '[' && str_ends_with($templine, ']')) {
            $currIndex = substr($templine, 1, -1);
            $this->tplArr[$currIndex] = "";
            return;
        }

        // Handle comments
        if ($first === '#' || $first === ';') {
            return;
        }

        // Add content to current template
        if ($currIndex !== '') {
            if ($templine === self::EMPTY_ROW) {
                $line = str_replace(self::EMPTY_ROW, '', $line);
            }
            $this->tplArr[$currIndex] .= $line;
        }
    }

    /**
     * @param string $name  of the template
     * @param string|array<string,scalar> $search  array or string placeholder (Empty $search returns template without replacements.)
     * @param string|array<scalar> $replace  array or string for replacement(s)
     * @return string
     * @throws \InvalidArgumentException
     */
    public function fillTpl(string $name, string|array $search = [], string|array $replace = ''): string
    {
        if (!isset($this->tplArr[$name])) {
            throw new \InvalidArgumentException("Template '{$name}' does not exist");
        }

        $template = $this->tplArr[$name];

        // Handle single string replacement
        if (is_string($search) && is_string($replace)) {
            if ($search === '') {
                return $template;
            }
            $search = $this->convert ? $this->convertPlaceholder($search) : $search;
            return str_replace($search, $replace, $template);
        }

        // Handle array search with empty replace (key-value pairs)
        if (is_array($search) && $replace === '') {
            return $this->replaceArrayPlaceholders($template, $search);
        }

        // Handle parallel arrays
        if (is_array($search) && is_array($replace)) {
            $searchArr = $this->convert
                ? array_map([$this, 'convertPlaceholder'], $search)
                : $search;
            return str_replace($searchArr, $replace, $template);
        }

        return $template;
    }

    /**
     * Replace placeholders using associative array
     *
     * @param string $template
     * @param array<string,scalar> $placeholders
     * @return string
     * @throws \InvalidArgumentException
     */
    private function replaceArrayPlaceholders(string $template, array $placeholders): string
    {
        $searchArr = [];
        $replaceArr = [];

        foreach ($placeholders as $key => $value) {
            if (is_int($key)) {
                throw new \InvalidArgumentException('Associative array expected, numeric key found');
            }

            $searchArr[] = $this->convert ? $this->convertPlaceholder($key) : $key;
            $replaceArr[] = (string) $value;
        }

        return str_replace($searchArr, $replaceArr, $template);
    }

    /**
     * Fill a template repeatedly, e.g. for table rows
     *
     * @param string $name
     * @param string|array<array<string,scalar>> $search   If $replace is null, $search must be a numeric array of associative arrays.
     *                        If $replace is a numeric array of numeric arrays, $search must be a numeric array
     *                        of the placeholder names.
     *                        $search may be a string if there is only a single placeholder and $replace is a list of values.
     * @param array<array<scalar>>|null $replace  array or array of associative arrays
     * @return string         The filled and concatenated row templates.
     */
    public function fillRowTpl(string $name, string|array $search, ?array $replace = null): string
    {
        $buffer = "";

        if ($replace === null) {
            foreach ($search as $lineArr) {
                $buffer .= $this->fillTpl($name, $lineArr, '');
            }
            return $buffer;
        }

        foreach ($replace as $lineArr) {
            $buffer .= $this->fillTpl($name, $search, $lineArr);
        }

        return $buffer;
    }

    /**
     * Checks whether or not the template exists.
     *
     * @param string $key
     * @return bool
     */
    public function hasTpl(string $key): bool
    {
        return isset($this->tplArr[$key]);
    }

    /**
     * Get the whole TplArr, e.g. for merging with another template object.
     *
     * @return array<string,string>
     */
    public function getTplArr(): array
    {
        return $this->tplArr;
    }

    /**
     * Merge with other TemplateArray
     *
     * @param array<string,string> $arr
     * @return void
     */
    public function mergeTplArr(array $arr): void
    {
        $this->tplArr = array_merge($this->tplArr, $arr);
    }

    /**
     * Insert new template snippet with key and value or overwrite existing.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function setTpl(string $key, string $value): void
    {
        $this->tplArr[$key] = $value;
    }

    /**
     * Get template string by key.
     *
     * @param string $key
     * @return string|null
     */
    public function getTpl(string $key): ?string
    {
        return $this->tplArr[$key] ?? null;
    }

    /**
     * Remove a template by key
     *
     * @param string $key
     * @return bool
     */
    public function removeTpl(string $key): bool
    {
        if (!isset($this->tplArr[$key])) {
            return false;
        }
        unset($this->tplArr[$key]);
        return true;
    }
}
