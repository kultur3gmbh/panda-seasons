(function () {
	'use strict';
	document.addEventListener('click', function (event) {
		var doorButton = event.target.closest('.panda-seasons-free__door.is-available > button');
		if (doorButton) {
			var calendar = doorButton.closest('.panda-seasons-free');
			var modal = calendar.querySelector('[data-psf-modal]');
			var template = document.getElementById(doorButton.getAttribute('aria-controls'));
			if (!modal || !template) return;
			modal.querySelector('[data-psf-content]').innerHTML = '';
			modal.querySelector('[data-psf-content]').appendChild(template.content.cloneNode(true));
			modal.hidden = false;
			doorButton.closest('.panda-seasons-free__door').classList.add('is-open');
			modal.querySelector('.panda-seasons-free__close').focus();
			document.documentElement.classList.add('panda-seasons-free-modal-open');
		}
		if (event.target.closest('[data-psf-close]')) close(event.target.closest('[data-psf-modal]'));
	});
	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key) document.querySelectorAll('[data-psf-modal]:not([hidden])').forEach(close);
	});
	function close(modal) {
		if (!modal) return;
		modal.hidden = true;
		document.documentElement.classList.remove('panda-seasons-free-modal-open');
	}
})();

