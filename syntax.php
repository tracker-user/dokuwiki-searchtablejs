<?php
/**
 * Filter Tables plugin for DokuWiki — local fork.
 *
 * Wraps a table in `<searchtable>...</searchtable>` to add a JavaScript filter
 * input above it. Each keystroke filters the table rows to those matching the
 * entered text. Original plugin: https://github.com/xdreamer/searchtablejs
 *
 * Local modifications vs. upstream (970c639, 2024-06-12):
 *   1. `getPType()` returns `'block'` instead of `'normal'`. The plugin emits
 *      a `<div>` wrapper, but DokuWiki was wrapping that `<div>` in `<p>` tags
 *      (invalid HTML — div inside p). Switching the pType fixes the wrapping.
 *   2. UNMATCHED content is now dispatched via `call_user_func_array` on the
 *      active renderer, the same pattern sortablejs uses. The previous
 *      `p_render(... , $info)` call referenced an undefined `$info` variable
 *      which produced a PHP 8 warning; the new path is also marginally faster
 *      (no separate renderer instance).
 *   3. Added a Reset button to the filter form that clears the input and
 *      re-runs the filter, restoring all rows. Implemented in `script.js` as
 *      `searchtable.resetfilter()` and wired up from a `<button>` in the
 *      emitted form markup.
 *   4. Removed the legacy `getInfo()` method. Metadata now flows from
 *      plugin.info.txt via the modern PluginTrait::getInfo() base implementation.
 *   5. Removed the bundled Eclipse `.project` file and the unused
 *      `TableFilter_EN/` directory (3rd-party reference code that wasn't
 *      referenced from anywhere in this plugin).
 *   6. Sliced document_start/document_end from the p_get_instructions() list in
 *      the xhtml UNMATCHED branch. Replaying those instructions onto the live page
 *      renderer prematurely flushed the footnotes <div> and drained section-edit
 *      markers whenever non-table text appeared inside <searchtable> on a page
 *      that also had footnotes or multiple sections (duplicate IDs, misplaced
 *      markup). Mirrors the fix applied to the sortablejs sibling.
 *   7. handle() fallback now returns [$state, ''] instead of [] to avoid
 *      undefined-index warnings if an unexpected lexer state is ever passed.
 *   8. $idCounter promoted to protected (DokuWiki convention for subclassability).
 *   9. script.js: replaced stripTags(innerHTML) with native .textContent
 *      (faster, avoids manual regex, handles HTML entities correctly).
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Robert Henjes <robert.henjes@gmx.net> (original)
 */

if (!defined('DOKU_INC')) die();

class syntax_plugin_searchtablejs extends \dokuwiki\Extension\SyntaxPlugin
{
    /** @var int Counter for unique container IDs within a single render pass */
    protected static int $idCounter = 0;

    /**
     * @return string Syntax type — 'container' allows nested markup
     */
    public function getType(): string
    {
        return 'container';
    }

    /**
     * @return string Paragraph type — 'block' prevents the parser from wrapping
     *                this plugin's <div> output in <p> tags (invalid HTML)
     */
    public function getPType(): string
    {
        return 'block';
    }

    /**
     * @return int Parser sort order
     */
    public function getSort(): int
    {
        return 999;
    }

    /**
     * Allowed nested markup types — lets the plugin coexist with edittable,
     * sortablejs, and other inner-table plugins.
     *
     * @return string[]
     */
    public function getAllowedTypes(): array
    {
        return ['container', 'formatting', 'substition'];
    }

    /**
     * Register lexer entry pattern.
     *
     * @param string $mode Current parser mode
     * @return void
     */
    public function connectTo($mode): void
    {
        $this->Lexer->addEntryPattern(
            '<searchtable[^>]*>(?=.*?\x3C/searchtable\x3E)',
            $mode,
            'plugin_searchtablejs'
        );
    }

    /**
     * Register lexer exit pattern.
     *
     * @return void
     */
    public function postConnect(): void
    {
        $this->Lexer->addExitPattern('</searchtable>', 'plugin_searchtablejs');
    }

    /**
     * Parse a lexer match into handler data.
     *
     * @param string       $match   Text matched by the pattern
     * @param int          $state   Lexer state (ENTER, UNMATCHED, EXIT)
     * @param int          $pos     Character position of the match
     * @param Doku_Handler $handler Active handler
     * @return array{int, string}
     */
    public function handle($match, $state, $pos, Doku_Handler $handler): array
    {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                // Strip `<searchtable` ... `>` to get any options string.
                $match = trim(substr($match, 12, -1));
                $scl = ($match !== '') ? ' search' . $match : '';
                return [$state, $scl];

            case DOKU_LEXER_UNMATCHED:
                return [$state, $match];

            case DOKU_LEXER_EXIT:
                return [$state, ''];
        }
        return [$state, ''];
    }

    /**
     * Render the searchtable wrapper and filter form.
     *
     * @param string        $mode     Output format ('xhtml', etc.)
     * @param Doku_Renderer $renderer Active renderer
     * @param array         $data     Data returned by handle()
     * @return bool True if format was handled
     */
    public function render($mode, Doku_Renderer $renderer, $data): bool
    {
        if ($mode !== 'xhtml') return false;

        [$state, $match] = $data;
        switch ($state) {
            case DOKU_LEXER_ENTER:
                self::$idCounter++;
                $id = 'searchtable_' . self::$idCounter;
                $renderer->doc .= '<div class="searchtable' . hsc($match) . '" id="' . $id . '">';
                $renderer->doc .=
                    '<form class="searchtable" onsubmit="return false;">'
                    . '<label class="searchtable-label">' . hsc($this->getLang('filter_label')) . ' '
                    . '<input class="searchtable" name="filtertable" type="text"'
                    .   ' onkeyup="searchtable.filterall(this, \'' . $id . '\')">'
                    . '</label>'
                    . ' <button type="button" class="searchtable-reset"'
                    .   ' onclick="searchtable.resetfilter(\'' . $id . '\')">'
                    .   hsc($this->getLang('reset_btn'))
                    . '</button>'
                    . '</form>';
                break;

            case DOKU_LEXER_UNMATCHED:
                // Dispatch the inner content's instructions onto the active
                // renderer. Slice off the wrapping document_start (index 0) and
                // document_end (index -1) that p_get_instructions() always
                // prepends/appends: replaying them onto the live renderer would
                // prematurely flush the footnotes <div> and drain section-edit
                // markers mid-page (mirrors the fix in the sortablejs sibling).
                $instructions = array_slice(p_get_instructions($match), 1, -1);
                foreach ($instructions as $instruction) {
                    call_user_func_array([$renderer, $instruction[0]], $instruction[1]);
                }
                break;

            case DOKU_LEXER_EXIT:
                $renderer->doc .= '</div>';
                break;
        }
        return true;
    }
}
