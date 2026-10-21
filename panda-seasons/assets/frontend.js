(function () {
	'use strict';

	var lastTrigger = null;

	function closeModal(modal) {
		if (!modal) return;
		modal.hidden = true;
		modal.querySelector('[data-season-modal-content]').innerHTML = '';
		document.body.classList.remove('panda-seasons-has-open-modal');
		if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
		lastTrigger = null;
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.panda-seasons__door-face');
		if (button && !button.disabled) {
			var calendar = button.closest('.panda-seasons');
			var door = button.closest('.panda-seasons__door');
			var template = door.querySelector('.panda-seasons__door-content');
			var modal = calendar.querySelector('[data-season-modal]');
			if (!template || !modal) return;
			lastTrigger = button;
			modal.querySelector('[data-season-modal-content]').appendChild(template.content.cloneNode(true));
			modal.hidden = false;
			document.body.classList.add('panda-seasons-has-open-modal');
			modal.querySelector('.panda-seasons__modal-close').focus();
			return;
		}

		var close = event.target.closest('[data-close-season-modal]');
		if (close) closeModal(close.closest('[data-season-modal]'));
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') return;
		var modal = document.querySelector('[data-season-modal]:not([hidden])');
		if (modal) closeModal(modal);
	});
}());
