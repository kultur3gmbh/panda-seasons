(function (blocks, element, components, blockEditor, ServerSideRender) {
	'use strict';
	var el = element.createElement;
	function selectionHint(id, options) {
		var selected = options.filter(function (option) { return String(option.value) === String(id || ''); })[0];
		var text = selected && id ? String(pandaSeasonsBlock.selectedLabel || 'Selected calendar: %s').replace('%s', selected.label) : (pandaSeasonsBlock.noneSelected || 'No calendar selected');
		return el('small', { style: { display: 'block', marginTop: '6px', color: '#6b7280', fontSize: '11px' } }, text);
	}
	blocks.registerBlockType('panda-seasons/calendar', {
		title: (window.pandaSeasonsBlock && pandaSeasonsBlock.title) || 'Panda Seasons calendar',
		icon: 'calendar-alt',
		category: 'widgets',
		attributes: { id: { type: 'integer', default: 0 } },
		edit: function (props) {
			var options = (window.pandaSeasonsBlock && pandaSeasonsBlock.calendars) || [];
			return el('div', blockEditor.useBlockProps(),
				el(components.SelectControl, { label: pandaSeasonsBlock.selectLabel, value: String(props.attributes.id || ''), options: options, onChange: function (value) { props.setAttributes({ id: parseInt(value, 10) || 0 }); } }),
				props.attributes.id ? el(ServerSideRender, { block: 'panda-seasons/calendar', attributes: props.attributes }) : null,
				selectionHint(props.attributes.id, options)
			);
		},
		save: function () { return null; }
	});
	blocks.registerBlockType('panda-seasons/signup', {
		title: (window.pandaSeasonsBlock && pandaSeasonsBlock.signupTitle) || 'Panda Seasons calendar signup',
		icon: 'email-alt',
		category: 'widgets',
		attributes: { id: { type: 'integer', default: 0 } },
		edit: function (props) {
			var options = (window.pandaSeasonsBlock && pandaSeasonsBlock.calendars) || [];
			return el('div', blockEditor.useBlockProps(),
				el(components.SelectControl, { label: pandaSeasonsBlock.selectLabel, value: String(props.attributes.id || ''), options: options, onChange: function (value) { props.setAttributes({ id: parseInt(value, 10) || 0 }); } }),
				props.attributes.id ? el(ServerSideRender, { block: 'panda-seasons/signup', attributes: props.attributes }) : null,
				selectionHint(props.attributes.id, options)
			);
		},
		save: function () { return null; }
	});
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender));
