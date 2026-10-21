(function () {
	'use strict';
	document.addEventListener('click', function (event) {
		var button = event.target.closest('.panda-seasons__door-face');
		if (!button || button.disabled) return;
		var door = button.closest('.panda-seasons__door');
		var content = door.querySelector('.panda-seasons__door-content');
		var open = door.classList.toggle('is-open');
		button.setAttribute('aria-expanded', open ? 'true' : 'false');
		content.hidden = !open;
		if (open) content.focus({ preventScroll: true });
	});
}());
