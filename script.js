/*
  SearchTable — local fork

  Provides client-side text filtering for tables wrapped in <searchtable>.
  Each searchtable div has a unique id; the filter input's onkeyup handler
  passes that id back so we can find the right table.

  Local changes vs upstream:
   - Added searchtable.resetfilter() so the Reset button in syntax.php works.
   - Use strict-mode-friendly object literal (no trailing comma).
   - Wrapped in an IIFE to avoid leaking helper vars; the global `searchtable`
     object is still exposed because inline onclick/onkeyup handlers in the
     emitted HTML reference it.
   - Replaced stripTags(innerHTML) with native .textContent — faster, avoids
     manual regex, and correctly handles HTML entities.
*/
(function () {
    'use strict';

    function getTableByID(id) {
        var container = document.getElementById(id);
        if (!container) return null;
        var tables = container.getElementsByTagName('table');
        return tables.length ? tables[0] : null;
    }

    function visibleRowsLoop(table, predicate) {
        // r starts at 1 to skip the header row.
        for (var r = 1; r < table.rows.length; r++) {
            var row = table.rows[r];
            row.style.display = predicate(row) ? '' : 'none';
        }
    }

    window.searchtable = {
        /** Filter on one specific cell column. */
        filtersingle: function (term, id, cellNr) {
            var table = getTableByID(id);
            if (!table) return;
            var needle = (term.value || '').toLowerCase();
            visibleRowsLoop(table, function (row) {
                var cell = row.cells[cellNr];
                if (!cell) return false;
                return cell.textContent.toLowerCase().indexOf(needle) >= 0;
            });
        },

        /** Filter requiring ALL space-separated words present in the row. */
        filterwords: function (term, id) {
            var table = getTableByID(id);
            if (!table) return;
            var words = (term.value || '').toLowerCase().split(' ');
            visibleRowsLoop(table, function (row) {
                var text = row.textContent.toLowerCase();
                for (var i = 0; i < words.length; i++) {
                    if (text.indexOf(words[i]) < 0) return false;
                }
                return true;
            });
        },

        /** Default filter: substring match against the entire row. */
        filterall: function (term, id) {
            var table = getTableByID(id);
            if (!table) return;
            var needle = (term.value || '').toLowerCase();
            visibleRowsLoop(table, function (row) {
                return row.textContent.toLowerCase().indexOf(needle) >= 0;
            });
        },

        /** Reset: clear the input and re-run the default filter to show all rows. */
        resetfilter: function (id) {
            var container = document.getElementById(id);
            if (!container) return;
            var input = container.querySelector('input[name="filtertable"]');
            if (!input) return;
            input.value = '';
            window.searchtable.filterall(input, id);
            input.focus();
        },

        /** Kept for backward compatibility — older callers may reference this. */
        getTableByID: getTableByID
    };
})();
