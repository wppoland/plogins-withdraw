/**
 * Plogins Withdraw - progressive enhancement only. The form works without JS
 * (server validates); this just blocks submitting the items step with nothing
 * selected, for a friendlier message.
 */
(function () {
	"use strict";
	var form = document.querySelector(".withdraw-form--items form");
	if (!form) {
		return;
	}
	form.addEventListener("submit", function (e) {
		var qtys = form.querySelectorAll('input[name^="withdraw_qty"]');
		var total = 0;
		qtys.forEach(function (input) {
			total += parseInt(input.value, 10) || 0;
		});
		if (total < 1) {
			e.preventDefault();
			window.alert(form.getAttribute("data-empty-msg") || "Please select at least one item to withdraw from.");
		}
	});
})();
