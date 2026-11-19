(function () {
	'use strict';
	if (window.PandaCanvasEditor) return;
	window.PandaCanvasEditor = {
		bindPaletteDrag: function (button, canvas, insert) {
			var drag = null,
				marker = null,
				ignoreClick = false;
			function clear() {
				if (marker) marker.remove();
				marker = null;
				drag = null;
				canvas.classList.remove('is-canvas-drop-target');
			}
			button.style.touchAction = 'none';
			button.addEventListener('pointerdown', function (event) {
				if (event.button !== 0) return;
				drag = { id: event.pointerId, x: event.clientX, y: event.clientY, moved: false };
				try {
					button.setPointerCapture(event.pointerId);
				} catch (ignore) {}
			});
			button.addEventListener(
				'dragstart',
				function (event) {
					event.preventDefault();
				},
				true,
			);
			document.addEventListener('pointermove', function (event) {
				if (!drag || event.pointerId !== drag.id) return;
				if (Math.hypot(event.clientX - drag.x, event.clientY - drag.y) < 6 && !drag.moved) return;
				drag.moved = true;
				event.preventDefault();
				var r = canvas.getBoundingClientRect(),
					inside =
						event.clientX >= r.left &&
						event.clientX <= r.right &&
						event.clientY >= r.top &&
						event.clientY <= r.bottom;
				canvas.classList.toggle('is-canvas-drop-target', inside);
				if (!marker) {
					marker = document.createElement('div');
					marker.className = 'panda-canvas-drop-marker';
					marker.setAttribute('aria-hidden', 'true');
					marker.textContent = button.textContent;
					canvas.appendChild(marker);
				}
				marker.hidden = !inside;
				marker.style.left =
					Math.max(0, Math.min(82, ((event.clientX - r.left) / r.width) * 100 - 9)) + '%';
				marker.style.top =
					Math.max(0, Math.min(82, ((event.clientY - r.top) / r.height) * 100 - 9)) + '%';
			});
			document.addEventListener('pointerup', function (event) {
				if (!drag || event.pointerId !== drag.id) return;
				var moved = drag.moved,
					r = canvas.getBoundingClientRect(),
					inside =
						event.clientX >= r.left &&
						event.clientX <= r.right &&
						event.clientY >= r.top &&
						event.clientY <= r.bottom;
				if (moved) {
					ignoreClick = true;
					setTimeout(function () {
						ignoreClick = false;
					}, 0);
				}
				clear();
				if (moved && inside)
					insert({
						x: ((event.clientX - r.left) / r.width) * 100,
						y: ((event.clientY - r.top) / r.height) * 100,
					});
			});
			document.addEventListener('pointercancel', clear);
			button.addEventListener(
				'click',
				function (event) {
					if (!ignoreClick) return;
					event.preventDefault();
					event.stopImmediatePropagation();
				},
				true,
			);
		},
	};
})();
