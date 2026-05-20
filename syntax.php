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
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Robert Henjes <robert.henjes@gmx.net> (original)
 */

class syntax_plugin_searchtablejs extends DokuWiki_Syntax_Plugin
{
    public function getType()
    {
        return 'container';
    }

    public function getPType()
    {
        // Was 'normal' upstream, producing <p><div>...</div></p> (invalid HTML).
        // 'block' tells the parser not to wrap our output in <p>.
        return 'block';
    }

    public function getSort()
    {
        return 999;
    }

    /** Lets the plugin coexist with edittable and other inner-table plugins. */
    public function getAllowedTypes()
    {
        return ['container', 'formatting', 'substition'];
    }

    public function connectTo($mode)
    {
        $this->Lexer->addEntryPattern(
            '<searchtable[^>]*>(?=.*?\x3C/searchtable\x3E)',
            $mode,
            'plugin_searchtablejs'
        );
    }

    public function postConnect()
    {
        $this->Lexer->addExitPattern('</searchtable>', 'plugin_searchtablejs');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
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
        return [];
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== 'xhtml') return false;

        [$state, $match] = $data;
        switch ($state) {
            case DOKU_LEXER_ENTER:
                // mt_rand returns an int; cast for safe embedding.
                $id = (string)mt_rand();
                $renderer->doc .= '<div class="searchtable' . $match . '" id="' . $id . '">';
                $renderer->doc .=
                    '<form class="searchtable" onsubmit="return false;">'
                    . '<label class="searchtable-label">Filter: '
                    . '<input class="searchtable" name="filtertable" type="text"'
                    .   ' onkeyup="searchtable.filterall(this, \'' . $id . '\')">'
                    . '</label>'
                    . ' <button type="button" class="searchtable-reset"'
                    .   ' onclick="searchtable.resetfilter(\'' . $id . '\')">Reset</button>'
                    . '</form>';
                break;

            case DOKU_LEXER_UNMATCHED:
                // Dispatch the inner content's instructions onto the active
                // renderer. This is the same pattern sortablejs uses; it keeps
                // cellbg's $renderer->doc inspection working when searchtable
                // wraps a sortable wraps colored cells.
                $instructions = p_get_instructions($match);
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
