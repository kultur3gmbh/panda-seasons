(function () {
	'use strict';

	var lastTrigger = null;

	function fitAutomaticLabel(door) {
		if (!door) return;
		var label = door.querySelector('.panda-seasons__door-number');
		if (!label) return;
		var state = door.classList.contains('is-open-visual') ? 'open' : 'closed';
		var mode = label.dataset[state + 'WidthMode'];
		var shape = label.dataset[state + 'Shape'];
		label.style.removeProperty('width');
		label.style.removeProperty('height');
		if (mode !== 'content' || (shape !== 'round' && shape !== 'square')) return;
		label.style.width = 'max-content';
		label.style.height = 'auto';
		var bounds = label.getBoundingClientRect();
		var diameter = Math.ceil(Math.max(bounds.width, bounds.height));
		if (diameter > 0) {
			label.style.width = diameter + 'px';
			label.style.height = diameter + 'px';
		}
	}

	function fitAllAutomaticLabels() {
		document.querySelectorAll('.panda-seasons__door').forEach(fitAutomaticLabel);
	}

	function trackDoorClick(calendar, door) {
		if (!calendar || !door || calendar.classList.contains('is-preview') || !window.pandaSeasonsFrontend || !pandaSeasonsFrontend.ajaxUrl) return;
		var data = new FormData();
		data.append('action', 'panda_seasons_track_door_click');
		data.append('nonce', pandaSeasonsFrontend.clickNonce || '');
		data.append('calendar_id', calendar.dataset.calendar || '');
		data.append('door_id', door.dataset.doorId || '');
		window.fetch(pandaSeasonsFrontend.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data, keepalive: true}).catch(function () {});
	}

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
			trackDoorClick(calendar, door);
			lastTrigger = button;
			var effect = door.dataset.doorEffect || calendar.dataset.openEffect || 'none';
			var duration = calendar.classList.contains('panda-seasons--speed-fast') ? 320 : (calendar.classList.contains('panda-seasons--speed-slow') ? 850 : 560);
			var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			var numberLabel = door.querySelector('.panda-seasons__door-number');
			var markOpened = function () {
				door.classList.remove('is-closed-visual', 'is-current');
				door.classList.add('is-open-visual');
				if (numberLabel && numberLabel.dataset.openLabel) numberLabel.textContent = numberLabel.dataset.openLabel;
				fitAutomaticLabel(door);
			};
			var showContent = function () {
				markOpened();
				modal.querySelector('[data-season-modal-content]').appendChild(template.content.cloneNode(true));
				modal.hidden = false;
				document.body.classList.add('panda-seasons-has-open-modal');
				modal.querySelector('.panda-seasons__modal-close').focus();
				window.setTimeout(function () { door.classList.remove('is-opening'); }, 80);
			};
			if (door.classList.contains('is-open-visual') || 'none' === effect || reducedMotion) showContent();
			else {
				door.classList.add('is-opening', 'effect-' + effect);
				window.setTimeout(showContent, duration);
			}
			return;
		}

		var close = event.target.closest('[data-close-season-modal]');
		if (close) closeModal(close.closest('[data-season-modal]'));
		var previewAction = event.target.closest('.panda-seasons.is-preview a');
		if (previewAction) event.preventDefault();
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') return;
		var modal = document.querySelector('[data-season-modal]:not([hidden])');
		if (modal) closeModal(modal);
	});

	document.addEventListener('DOMContentLoaded', function () {
		fitAllAutomaticLabels();
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitAllAutomaticLabels);
	});

	document.addEventListener('submit', function (event) {
		var quiz = event.target.closest('[data-season-quiz]');
		if (!quiz) return;
		event.preventDefault();
		if (!window.pandaSeasonsFrontend || quiz.dataset.quizPreview === '1') return;
		var button = quiz.querySelector('[type="submit"]'), result = quiz.querySelector('[data-quiz-result]');
		var body = new FormData(quiz);
		body.set('action', 'panda_seasons_quiz'); body.set('nonce', pandaSeasonsFrontend.quizNonce || ''); body.set('door', quiz.dataset.doorId);
		button.disabled = true; result.textContent = '';
		fetch(pandaSeasonsFrontend.ajaxUrl, {method:'POST', credentials:'same-origin', body:body}).then(function (response) { return response.json(); }).then(function (response) {
			if (!response.success) throw new Error(response.data && response.data.message || 'Please try again.');
			result.textContent = response.data.message || '';
			result.classList.toggle('is-correct', !!response.data.correct); result.classList.toggle('is-wrong', !response.data.correct);
			if (response.data.code) { var code = document.createElement('code'); code.textContent = response.data.code; result.appendChild(document.createTextNode(' ')); result.appendChild(code); }
			if (response.data.expires) result.appendChild(document.createTextNode(' (' + response.data.expires + ')'));
		}).catch(function (error) { result.textContent = error.message; }).finally(function () { button.disabled = false; });
	});
}());
