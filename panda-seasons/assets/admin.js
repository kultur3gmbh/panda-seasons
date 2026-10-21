(function () {
	'use strict';
	document.addEventListener('click', function (event) {
		var select = event.target.closest('[data-select-media]');
		var remove = event.target.closest('[data-remove-media]');
		if (!select && !remove) return;
		var field = event.target.closest('[data-media-field]');
		if (!field) return;
		if (remove) {
			field.querySelector('input[type="hidden"]').value = '';
			field.querySelector('.panda-seasons-media-preview').innerHTML = '';
			remove.hidden = true;
			return;
		}
		var frame = wp.media({ title: select.textContent, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			field.querySelector('input[type="hidden"]').value = attachment.id;
			field.querySelector('.panda-seasons-media-preview').innerHTML = '<img src="' + (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url) + '" alt="">';
			field.querySelector('[data-remove-media]').hidden = false;
		});
		frame.open();
	});
}());
