(function (blocks, element, components, blockEditor, serverSideRender) {
	'use strict';
	var el = element.createElement;
	blocks.registerBlockType('panda-seasons/calendar', {
		title: 'Panda Seasons Free',
		icon: 'calendar-alt',
		category: 'widgets',
		attributes: {id: {type: 'integer', default: 0}, showTitle: {type: 'boolean', default: true}},
		edit: function (props) {
			var options = [{label: pandaSeasonsFreeBlock.selectCalendar, value: 0}].concat(pandaSeasonsFreeBlock.calendars || []);
			return el(element.Fragment, {},
				el(blockEditor.InspectorControls, {}, el(components.PanelBody, {title: 'Panda Seasons Free', initialOpen: true},
					el(components.SelectControl, {label: pandaSeasonsFreeBlock.selectCalendar, value: props.attributes.id, options: options, onChange: function (value) { props.setAttributes({id: parseInt(value, 10) || 0}); }}),
					el(components.ToggleControl, {label: pandaSeasonsFreeBlock.showTitle, checked: props.attributes.showTitle, onChange: function (value) { props.setAttributes({showTitle: value}); }})
				)),
				props.attributes.id ? el(serverSideRender, {block: 'panda-seasons/calendar', attributes: props.attributes}) : el(components.Placeholder, {icon: 'calendar-alt', label: 'Panda Seasons Free'}, el(components.SelectControl, {value: 0, options: options, onChange: function (value) { props.setAttributes({id: parseInt(value, 10) || 0}); }}))
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender);

