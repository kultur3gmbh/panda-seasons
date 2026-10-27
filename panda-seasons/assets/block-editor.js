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
		attributes: { id: { type: 'integer', default: 0 }, showTitle: { type: 'boolean', default: true }, showSignup: { type: 'boolean', default: true }, frame: { type: 'boolean', default: false }, shadow: { type: 'boolean', default: false }, marginTop: { type: 'string', default: '' }, marginBottom: { type: 'string', default: '' } },
		edit: function (props) {
			var options = (window.pandaSeasonsBlock && pandaSeasonsBlock.calendars) || [];
			return el('div', blockEditor.useBlockProps(),
				el(components.SelectControl, { label: pandaSeasonsBlock.selectLabel, value: String(props.attributes.id || ''), options: options, onChange: function (value) { props.setAttributes({ id: parseInt(value, 10) || 0 }); } }),
				el(components.ToggleControl, { label: pandaSeasonsBlock.showTitle, checked: props.attributes.showTitle !== false, onChange: function (value) { props.setAttributes({ showTitle: value }); } }),
				el(components.ToggleControl, { label: pandaSeasonsBlock.showSignup, checked: props.attributes.showSignup !== false, onChange: function (value) { props.setAttributes({ showSignup: value }); } }),
				el(components.ToggleControl, { label: pandaSeasonsBlock.frame, checked: !!props.attributes.frame, onChange: function (value) { props.setAttributes({ frame: value }); } }),
				el(components.ToggleControl, { label: pandaSeasonsBlock.shadow, checked: !!props.attributes.shadow, onChange: function (value) { props.setAttributes({ shadow: value }); } }),
				el(components.TextControl, { label: pandaSeasonsBlock.marginTop, value: props.attributes.marginTop || '', onChange: function (value) { props.setAttributes({ marginTop: value }); } }),
				el(components.TextControl, { label: pandaSeasonsBlock.marginBottom, value: props.attributes.marginBottom || '', onChange: function (value) { props.setAttributes({ marginBottom: value }); } }),
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
		attributes: { id: { type: 'integer', default: 0 }, marginTop: { type: 'string', default: '' }, marginBottom: { type: 'string', default: '' } },
		edit: function (props) {
			var options = (window.pandaSeasonsBlock && pandaSeasonsBlock.calendars) || [];
			return el('div', blockEditor.useBlockProps(),
				el(components.SelectControl, { label: pandaSeasonsBlock.selectLabel, value: String(props.attributes.id || ''), options: options, onChange: function (value) { props.setAttributes({ id: parseInt(value, 10) || 0 }); } }),
				el(components.TextControl, { label: pandaSeasonsBlock.marginTop, value: props.attributes.marginTop || '', onChange: function (value) { props.setAttributes({ marginTop: value }); } }),
				el(components.TextControl, { label: pandaSeasonsBlock.marginBottom, value: props.attributes.marginBottom || '', onChange: function (value) { props.setAttributes({ marginBottom: value }); } }),
				props.attributes.id ? el(ServerSideRender, { block: 'panda-seasons/signup', attributes: props.attributes }) : null,
				selectionHint(props.attributes.id, options)
			);
		},
		save: function () { return null; }
	});
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender));
