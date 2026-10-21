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
			var effect = door.dataset.doorEffect || calendar.dataset.openEffect || 'none';
			var duration = calendar.classList.contains('panda-seasons--speed-fast') ? 320 : (calendar.classList.contains('panda-seasons--speed-slow') ? 850 : 560);
			var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			var showContent = function () {
				modal.querySelector('[data-season-modal-content]').appendChild(template.content.cloneNode(true));
				modal.hidden = false;
				document.body.classList.add('panda-seasons-has-open-modal');
				modal.querySelector('.panda-seasons__modal-close').focus();
				window.setTimeout(function () { door.classList.remove('is-opening'); }, 80);
			};
			if ('none' === effect || reducedMotion) showContent();
			else {
				door.classList.add('is-opening', 'effect-' + effect);
				window.setTimeout(showContent, duration);
			}
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

	document.addEventListener('submit', function (event) {
		var quiz = event.target.closest('[data-season-quiz]');
		if (!quiz) return;
		event.preventDefault();
		var selected = quiz.querySelector('input[type="radio"]:checked');
		if (!selected) return;
		var correct = String(selected.value) === String(quiz.dataset.correct);
		var result = quiz.querySelector('[data-quiz-result]');
		result.textContent = correct ? quiz.dataset.success : quiz.dataset.failure;
		result.classList.toggle('is-correct', correct);
		result.classList.toggle('is-wrong', !correct);
	});
}());
