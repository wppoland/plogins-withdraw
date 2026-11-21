/**
 * Plogins Withdraw - editor side of the withdrawal link block.
 *
 * existing-checked: assets/js/withdraw.js - that file is the storefront form enhancement and loads only on the form page, not in the block editor.
 *
 * No build step: plain wp.* globals. The preview is rendered by the server
 * (the same PHP that renders the storefront). No strings here: the two
 * labels come translated from PHP in window.ploginsWithdrawBlock.
 */
(function (blocks, element, blockEditor, components, ServerSideRender) {
	"use strict";
	var el = element.createElement;
	var i18n = window.ploginsWithdrawBlock || {};

	blocks.registerBlockType("plogins-withdraw/button", {
		edit: function (props) {
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: i18n.panel || "" },
						el(components.TextControl, {
							label: i18n.label || "",
							placeholder: i18n.placeholder || "",
							value: props.attributes.label,
							onChange: function (value) {
								props.setAttributes({ label: value });
							},
						})
					)
				),
				el(
					"div",
					blockEditor.useBlockProps(),
					el(ServerSideRender, {
						block: "plogins-withdraw/button",
						attributes: props.attributes,
					})
				)
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender);
