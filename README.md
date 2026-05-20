# Filter Tables plugin for DokuWiki — local fork

Wraps a table in `<searchtable>...</searchtable>` to add a JavaScript filter input above it. Each keystroke filters rows to those matching the entered text. A Reset button clears the filter and restores all rows.

```
<searchtable>
^ Test ^ Col1 ^ Col2 ^
| Row1 | Val1 | Val2 |
| Row2 | Val3 | Val4 |
</searchtable>
```

Can be combined with `<sortable>`:

```
<searchtable>
<sortable>
^ Test ^ Col1 ^ Col2 ^
| Row1 | Val1 | Val2 |
| Row2 | Val3 | Val4 |
</sortable>
</searchtable>
```

Original plugin: [github.com/xdreamer/searchtablejs](https://github.com/xdreamer/searchtablejs). This is a local fork tracking upstream `970c639` (2024-06-12).

## What changed in the local fork

| Change | Why |
| --- | --- |
| `getPType()` now returns `'block'` (was `'normal'`) | Fixes invalid HTML. Upstream emits a `<div>`, which the parser was wrapping in `<p>` tags — `<p><div>...</div></p>` is not valid HTML. Browsers tolerate it by auto-closing the `<p>`, but the rendered DOM was unpredictable. With `'block'`, the parser leaves the `<div>` alone. |
| UNMATCHED content dispatched via `call_user_func_array([$renderer, ...])` instead of `p_render('xhtml', p_get_instructions($match), $info)` | The old call passed an undefined `$info` variable (PHP 8 warning). The new path matches the pattern sortablejs uses — dispatches each instruction onto the active renderer rather than spinning up a nested render call. Faster and warning-free. |
| Added a **Reset** button to the filter form, plus a `searchtable.resetfilter(id)` JS helper | Requested feature from the upstream plugin page. Clears the input and re-runs the filter so all rows show again. |
| Wrapped the filter prompt in a `<label>` element | Accessibility — clicking "Filter:" now focuses the input. |
| Removed the hand-coded `getInfo()` method | The base `PluginTrait::getInfo()` reads metadata from `plugin.info.txt`. Hand-coded `getInfo()` would shadow updates we make there. |
| Removed Eclipse `.project` IDE file and the bundled `TableFilter_EN/` directory | The IDE file is workspace metadata that has no business shipping. The `TableFilter_EN/` directory is a copy of a third-party tablefilter library that was never referenced from anywhere in the plugin — leftover reference material. |
| `script.js` rewritten in an IIFE with strict mode; behavior preserved | Modernized. No trailing-comma object literal issue (which the upstream README notes broke IE8 — IE8 is no longer relevant, but a clean rewrite is easier to maintain). Filter functions retain their original signatures (`filterall`, `filtersingle`, `filterwords`) so any custom HTML using them still works. |
| `style.css` simplified | Dropped the upstream `inputshadow.png` background (Web 2.0 era look). The input now inherits styling from the active DokuWiki template. The Reset button gets minimal padding/cursor styling. |
| `plugin.info.txt` `date` field set to `2077-06-12` | Suppresses the **Update** button in the Extension Manager so another admin can't accidentally overwrite this fork with upstream. |

## Install

Drop the folder into `lib/plugins/searchtablejs/`, or use Admin → Extension Manager → Manual Install to upload the zip.

## Restoring upstream

Diff against upstream `970c639` shows the changes above. The biggest delta is `script.js` (full rewrite) — easier to replace wholesale than to patch.

## Compatibility

Tested on DokuWiki `2025-05-14b "Librarian"`. Composes correctly with the local `cellbg` and `sortablejs` forks. Filter input is hidden in print output via `print.css`.

## License

GPL 2, matching the original plugin.
