/**
 * Withdraw settings: warn when no eligible status is checked, because saving
 * then falls back to Completed and Processing. Progressive enhancement;
 * without JS the form still saves the same values.
 */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var group = document.querySelector("[data-withdraw-statuses]");
        var note = document.querySelector("[data-withdraw-statuses-note]");
        if (!group || !note) {
            return;
        }

        var boxes = group.querySelectorAll('input[type="checkbox"]');

        function sync() {
            var anyChecked = false;
            boxes.forEach(function (box) {
                if (box.checked) {
                    anyChecked = true;
                }
            });
            note.hidden = anyChecked;
        }

        sync();
        boxes.forEach(function (box) {
            box.addEventListener("change", sync);
        });
    });
})();
