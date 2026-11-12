(function () {
	'use strict';

	var activeDialog = null;
	var previousFocus = null;

	function closeDialog(dialog) {
		if (!dialog) return;
		dialog.hidden = true;
		document.body.classList.remove('panda-seasons-dialog-open');
		activeDialog = null;
		if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
		previousFocus = null;
	}

	document.addEventListener('click', function (event) {
		var opener = event.target.closest('[data-subscriber-dialog-open]');
		if (opener) {
			var dialog = document.getElementById(opener.getAttribute('data-subscriber-dialog-open'));
			if (!dialog) return;
			previousFocus = opener;
			activeDialog = dialog;
			dialog.hidden = false;
			document.body.classList.add('panda-seasons-dialog-open');
			var closeButton = dialog.querySelector('[data-subscriber-dialog-close]');
			if (closeButton) closeButton.focus();
			return;
		}

		var closer = event.target.closest('[data-subscriber-dialog-close]');
		if (closer) closeDialog(closer.closest('.panda-seasons-subscriber-dialog'));
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key && activeDialog) closeDialog(activeDialog);
	});
}());
