<?php

namespace PbClasses;

/**
 * Developer debug utility class.
 *
 * Provides static methods for inspecting variables during development.
 * Output is rendered as a styled, draggable popover overlay in the browser,
 * keeping the page layout intact while surfacing debug information clearly.
 *
 * @package PbClasses
 * @author  Original author Peter Bieling / extended by assistant claude.ai
 * @version 2.0.0
 */
class Develop
{
    /**
     * Counter to generate unique IDs for each popover instance.
     */
    private static int $instanceCount = 0;

    /**
     * Whether the shared CSS/JS has already been injected into the page.
     */
    private static bool $assetsInjected = false;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Dump a value in a draggable debug popover.
     *
     * Renders any scalar, array, or object as a formatted, readable block
     * inside a floating, draggable overlay so it does not disturb the page
     * layout. Multiple calls each produce their own independently movable panel.
     *
     * @param  mixed       $value     The value to inspect (scalar, array, or object).
     * @param  string|int  $label     Optional label shown in the popover header
     *                                (e.g. a line number or descriptive name).
     * @param  bool        $exit      If true, script execution stops after output.
     *
     * @return void
     */
    public static function inspect(mixed $value, string|int $label = '', bool $exit = false): void
    {
        self::injectAssetsOnce();

        self::$instanceCount++;
        $id      = 'pb-dev-' . self::$instanceCount;
        $header  = self::buildHeader($label);
        $content = self::formatValue($value);
        $type    = self::resolveType($value);
        $offset  = (self::$instanceCount - 1) * 30; // cascade new popovers

        echo <<<HTML
        <div id="{$id}" class="pb-dev-popover" style="top:calc(60px + {$offset}px);left:calc(60px + {$offset}px);" role="dialog" aria-label="Debug panel">
          <div class="pb-dev-header" onmousedown="pbDevDrag(event, '{$id}')">
            <span class="pb-dev-icon">⬡</span>
            <span class="pb-dev-title">{$header}</span>
            <span class="pb-dev-badge">{$type}</span>
            <button class="pb-dev-close" onclick="document.getElementById('{$id}').remove()" title="Close">✕</button>
          </div>
          <div class="pb-dev-body">
            <pre class="pb-dev-pre">{$content}</pre>
          </div>
        </div>
        HTML;

        if ($exit) {
            exit;
        }
    }

    /**
     * Shorthand alias for {@see self::inspect()}.
     *
     * Mirrors the original method signature so existing call-sites keep working
     * after upgrading to this version.
     *
     * @param  mixed       $value  The value to inspect.
     * @param  string|int  $label  Optional label / line number.
     * @param  bool        $exit   Stop execution after output when true.
     *
     * @return void
     */
    public static function test(mixed $value, string|int $label = '', bool $exit = false): void
    {
        self::inspect($value, $label, $exit);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a human-readable header string for the popover title bar.
     *
     * @param  string|int  $label  A line number or descriptive label, or empty.
     *
     * @return string
     */
    private static function buildHeader(string|int $label): string
    {
        if ($label === '' || $label === 0) {
            return 'Debug output';
        }

        return is_numeric($label)
            ? 'Line&nbsp;' . htmlspecialchars((string) $label, ENT_QUOTES)
            : htmlspecialchars((string) $label, ENT_QUOTES);
    }

    /**
     * Convert the given value to a safe, HTML-escaped string for display.
     *
     * Arrays and objects are formatted with {@see print_r()}; scalars and null
     * are cast to string first.
     *
     * @param  mixed  $value  The value to format.
     *
     * @return string HTML-safe representation.
     */
    private static function formatValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $raw = print_r($value, true);
        } elseif (is_bool($value)) {
            $raw = $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            $raw = 'null';
        } else {
            $raw = (string) $value;
        }

        return htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Return a short type label for the badge in the popover header.
     *
     * @param  mixed  $value  The value whose type is described.
     *
     * @return string  E.g. "array", "object", "string", "int", …
     */
    private static function resolveType(mixed $value): string
    {
        if (is_object($value)) {
            return 'object(' . $value::class . ')';
        }

        return gettype($value);
    }

    /**
     * Inject the shared CSS and JavaScript exactly once per page response.
     *
     * Subsequent calls are no-ops, so multiple {@see self::inspect()} calls
     * on the same page do not duplicate the stylesheet or script block.
     *
     * @return void
     */
    private static function injectAssetsOnce(): void
    {
        if (self::$assetsInjected) {
            return;
        }

        self::$assetsInjected = true;

        echo <<<'HTML'
        <style>
          /* ── Develop popover ───────────────────────────────────────────── */
          .pb-dev-popover {
            --pd-bg:       #0f1117;
            --pd-surface:  #1a1d27;
            --pd-border:   #2e3347;
            --pd-accent:   #5af78e;
            --pd-accent2:  #57c7ff;
            --pd-text:     #cdd6f4;
            --pd-muted:    #6c7086;
            --pd-radius:   10px;
            --pd-shadow:   0 8px 40px rgba(0,0,0,.55), 0 2px 8px rgba(0,0,0,.4);
            --pd-font:     'JetBrains Mono', 'Fira Mono', 'Cascadia Code', monospace;

            position:   fixed;
            z-index:    99999;
            min-width:  340px;
            max-width:  min(720px, 90vw);
            max-height: 80vh;
            background: var(--pd-bg);
            border:     1px solid var(--pd-border);
            border-radius: var(--pd-radius);
            box-shadow: var(--pd-shadow);
            display:    flex;
            flex-direction: column;
            font-family: var(--pd-font);
            font-size:  13px;
            color:      var(--pd-text);
            user-select: none;
            backdrop-filter: blur(6px);
            animation: pb-dev-in 180ms cubic-bezier(.16,1,.3,1) both;
          }

          @keyframes pb-dev-in {
            from { opacity:0; transform:scale(.92) translateY(6px); }
            to   { opacity:1; transform:scale(1)   translateY(0);   }
          }

          /* Header / drag handle */
          .pb-dev-header {
            display:     flex;
            align-items: center;
            gap:         8px;
            padding:     8px 12px;
            background:  var(--pd-surface);
            border-bottom: 1px solid var(--pd-border);
            border-radius: var(--pd-radius) var(--pd-radius) 0 0;
            cursor:      grab;
          }
          .pb-dev-header:active { cursor: grabbing; }

          .pb-dev-icon {
            color: var(--pd-accent);
            font-size: 15px;
            line-height: 1;
            flex-shrink: 0;
          }

          .pb-dev-title {
            flex: 1;
            font-weight: 700;
            letter-spacing: .02em;
            color: var(--pd-accent2);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
          }

          .pb-dev-badge {
            font-size:     10px;
            font-weight:   600;
            letter-spacing:.06em;
            text-transform: uppercase;
            padding:       2px 7px;
            border-radius: 20px;
            background:    rgba(90,247,142,.12);
            color:         var(--pd-accent);
            border:        1px solid rgba(90,247,142,.25);
            white-space:   nowrap;
          }

          .pb-dev-close {
            background:  none;
            border:      none;
            color:       var(--pd-muted);
            cursor:      pointer;
            font-size:   15px;
            line-height: 1;
            padding:     2px 4px;
            border-radius: 4px;
            transition:  color .15s, background .15s;
            flex-shrink: 0;
          }
          .pb-dev-close:hover {
            color:      #f38ba8;
            background: rgba(243,139,168,.12);
          }

          /* Body / scrollable content */
          .pb-dev-body {
            overflow:  auto;
            padding:   14px 16px;
            flex:      1;
          }
          .pb-dev-body::-webkit-scrollbar      { width: 6px; height: 6px; }
          .pb-dev-body::-webkit-scrollbar-track { background: transparent; }
          .pb-dev-body::-webkit-scrollbar-thumb { background: var(--pd-border); border-radius: 3px; }

          /* Pre / code block */
          .pb-dev-pre {
            margin:      0;
            white-space: pre-wrap;
            word-break:  break-all;
            color:       var(--pd-text);
            line-height: 1.65;
            user-select: text;
          }
        </style>

        <script>
        /**
         * Make a debug popover draggable.
         * Called on mousedown of the header element.
         *
         * @param {MouseEvent} event - The initiating mouse event.
         * @param {string}     id    - The id attribute of the popover element.
         */
        function pbDevDrag(event, id) {
          const panel = document.getElementById(id);
          if (!panel) return;

          const startX    = event.clientX - panel.offsetLeft;
          const startY    = event.clientY - panel.offsetTop;

          /** @param {MouseEvent} e */
          function onMove(e) {
            panel.style.left = (e.clientX - startX) + 'px';
            panel.style.top  = (e.clientY - startY) + 'px';
          }

          function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup',   onUp);
          }

          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup',   onUp);

          // Bring clicked panel to front
          document.querySelectorAll('.pb-dev-popover').forEach(p => {
            p.style.zIndex = '99999';
          });
          panel.style.zIndex = '100000';
        }
        </script>
        HTML;
    }
}