(function () {
	'use strict';

	var composer = document.querySelector('[data-panda-seasons-composer]');
	var modal = document.querySelector('[data-door-modal]');
	var activeDoor = null;
	var returnFocus = null;

	function number(value, fallback) {
		var parsed = parseFloat(value);
		return Number.isFinite(parsed) ? parsed : fallback;
	}

	function clamp(value, min, max) {
		return Math.min(max, Math.max(min, value));
	}

	function escapeHtml(value) {
		var element = document.createElement('div');
		element.textContent = String(value == null ? '' : value);
		return element.innerHTML;
	}

	function setEditorContent(value) {
		var textarea = document.getElementById('panda_seasons_door_content_editor');
		if (textarea) textarea.value = value || '';
		if (window.tinymce) {
			var editor = window.tinymce.get('panda_seasons_door_content_editor');
			if (editor) editor.setContent(value || '');
		}
	}

	function getEditorContent() {
		if (window.tinymce) {
			var editor = window.tinymce.get('panda_seasons_door_content_editor');
			if (editor && !editor.isHidden()) return editor.getContent();
		}
		var textarea = document.getElementById('panda_seasons_door_content_editor');
		return textarea ? textarea.value : '';
	}

	function composerController(root) {
		var stage = root.querySelector('[data-editor-stage]');
		var dataField = root.querySelector('[data-editor-data]');
		var empty = root.querySelector('[data-editor-empty]');
		var images = { desktop: '', mobile: '' };
		var doors = [];
		var view = 'desktop';
		var pointerState = null;
		var dragged = false;

		try { doors = JSON.parse(dataField.value || '[]'); } catch (ignore) { doors = []; }
		try { images = JSON.parse(root.querySelector('[data-editor-images]').textContent || '{}'); } catch (ignore) { images = { desktop: '', mobile: '' }; }

		function cleanPosition(position, fallbackX, fallbackY, targetView) {
			position = position || {};
			var width = clamp(number(position.w, targetView === 'mobile' ? 26 : 14), 4, 100);
			var height = clamp(number(position.h, 18), 4, 100);
			return {
				x: clamp(number(position.x, fallbackX), 0, 100 - width),
				y: clamp(number(position.y, fallbackY), 0, 100 - height),
				w: width,
				h: height
			};
		}

		doors.forEach(function (door, index) {
			door.desktop = cleanPosition(door.desktop, 3 + (index % 6) * 16, 4 + Math.floor(index / 6) * 22, 'desktop');
			door.mobile = cleanPosition(door.mobile, 4 + (index % 3) * 32, 4 + Math.floor(index / 3) * 22, 'mobile');
		});

		function writeData() {
			dataField.value = JSON.stringify(doors);
		}

		function currentDoors() {
			return doors.filter(function (door) { return !door.deleted; });
		}

		function currentImage() {
			return images[view] || images.desktop || '';
		}

		function setStageAppearance() {
			var ratioInput = document.querySelector('[data-editor-ratio="' + view + '"]');
			stage.style.setProperty('--ps-editor-ratio', number(ratioInput && ratioInput.value, view === 'mobile' ? 0.75 : 1.5));
			stage.style.setProperty('--ps-editor-image', currentImage() ? 'url("' + currentImage().replace(/"/g, '\\"') + '")' : 'none');
			root.setAttribute('data-view', view);
		}

		function render() {
			stage.querySelectorAll('[data-editor-door]').forEach(function (element) { element.remove(); });
			currentDoors().forEach(function (door) {
				var position = door[view];
				var element = document.createElement('div');
				element.className = 'panda-seasons-composer__door';
				element.dataset.editorDoor = door.id || door.clientId;
				element.setAttribute('role', 'button');
				element.setAttribute('tabindex', '0');
				element.setAttribute('aria-label', door.title || String(door.number));
				element.style.left = position.x + '%';
				element.style.top = position.y + '%';
				element.style.width = position.w + '%';
				element.style.height = position.h + '%';
				element.innerHTML = '<span class="panda-seasons-composer__door-number">' + escapeHtml(door.number) + '</span><span class="panda-seasons-composer__door-title">' + escapeHtml(door.title || '') + '</span><span class="panda-seasons-composer__resize" data-resize-door aria-hidden="true"></span>';
				stage.appendChild(element);
			});
			empty.hidden = currentDoors().length > 0;
			writeData();
		}

		function findDoor(key) {
			return doors.find(function (door) { return String(door.id || door.clientId) === String(key); });
		}

		function nextNumber() {
			return currentDoors().reduce(function (highest, door) { return Math.max(highest, parseInt(door.number, 10) || 0); }, 0) + 1;
		}

		function addDoor() {
			var sequence = currentDoors().length;
			var doorNumber = nextNumber();
			var label = (window.pandaSeasonsAdmin && pandaSeasonsAdmin.untitledDoor ? pandaSeasonsAdmin.untitledDoor : 'Door %d').replace('%d', doorNumber);
			var door = {
				id: 0,
				clientId: 'new-' + Date.now(),
				isDraft: true,
				title: label,
				content: '',
				number: doorNumber,
				unlockDate: '',
				emailSubject: '',
				desktop: cleanPosition(null, 4 + (sequence % 6) * 15, 5 + Math.floor(sequence / 6) * 21, 'desktop'),
				mobile: cleanPosition(null, 5 + (sequence % 3) * 31, 5 + Math.floor(sequence / 3) * 21, 'mobile')
			};
			doors.push(door);
			render();
			openDoorModal(door, root.querySelector('[data-add-door]'));
		}

		function snap(value) {
			var snapInput = root.querySelector('[data-snap-grid]');
			return snapInput && snapInput.checked ? Math.round(value) : Math.round(value * 10) / 10;
		}

		function startPointer(event, element, resize) {
			if (event.button !== 0) return;
			var door = findDoor(element.dataset.editorDoor);
			if (!door) return;
			var rectangle = stage.getBoundingClientRect();
			pointerState = {
				door: door,
				element: element,
				resize: resize,
				startX: event.clientX,
				startY: event.clientY,
				start: Object.assign({}, door[view]),
				width: rectangle.width,
				height: rectangle.height
			};
			dragged = false;
			element.setPointerCapture(event.pointerId);
			event.preventDefault();
		}

		function movePointer(event) {
			if (!pointerState) return;
			var deltaX = (event.clientX - pointerState.startX) / pointerState.width * 100;
			var deltaY = (event.clientY - pointerState.startY) / pointerState.height * 100;
			if (Math.abs(deltaX) > 0.2 || Math.abs(deltaY) > 0.2) dragged = true;
			var position = pointerState.door[view];
			if (pointerState.resize) {
				position.w = clamp(snap(pointerState.start.w + deltaX), 4, 100 - position.x);
				position.h = clamp(snap(pointerState.start.h + deltaY), 4, 100 - position.y);
			} else {
				position.x = clamp(snap(pointerState.start.x + deltaX), 0, 100 - position.w);
				position.y = clamp(snap(pointerState.start.y + deltaY), 0, 100 - position.h);
			}
			pointerState.element.style.left = position.x + '%';
			pointerState.element.style.top = position.y + '%';
			pointerState.element.style.width = position.w + '%';
			pointerState.element.style.height = position.h + '%';
			writeData();
		}

		function endPointer() {
			pointerState = null;
		}

		stage.addEventListener('pointerdown', function (event) {
			var element = event.target.closest('[data-editor-door]');
			if (!element) return;
			startPointer(event, element, Boolean(event.target.closest('[data-resize-door]')));
		});
		stage.addEventListener('pointermove', movePointer);
		stage.addEventListener('pointerup', endPointer);
		stage.addEventListener('pointercancel', endPointer);

		stage.addEventListener('click', function (event) {
			var element = event.target.closest('[data-editor-door]');
			if (!element || event.target.closest('[data-resize-door]') || dragged) return;
			var door = findDoor(element.dataset.editorDoor);
			if (door) openDoorModal(door, element);
		});

		stage.addEventListener('keydown', function (event) {
			var element = event.target.closest('[data-editor-door]');
			if (!element) return;
			var door = findDoor(element.dataset.editorDoor);
			if (!door) return;
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				openDoorModal(door, element);
				return;
			}
			var movements = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
			if (!movements[event.key]) return;
			event.preventDefault();
			var position = door[view];
			var step = event.shiftKey ? 5 : 1;
			position.x = clamp(position.x + movements[event.key][0] * step, 0, 100 - position.w);
			position.y = clamp(position.y + movements[event.key][1] * step, 0, 100 - position.h);
			render();
			stage.querySelector('[data-editor-door="' + CSS.escape(String(door.id || door.clientId)) + '"]').focus();
		});

		root.addEventListener('click', function (event) {
			var viewButton = event.target.closest('[data-editor-view]');
			if (viewButton) {
				view = viewButton.dataset.editorView;
				root.querySelectorAll('[data-editor-view]').forEach(function (button) { button.classList.toggle('button-primary', button === viewButton); });
				setStageAppearance();
				render();
			}
			if (event.target.closest('[data-add-door]')) addDoor();
		});

		document.querySelectorAll('[data-editor-ratio]').forEach(function (input) {
			input.addEventListener('input', function () { if (input.dataset.editorRatio === view) setStageAppearance(); });
		});

		root.updateImage = function (targetView, url) {
			images[targetView] = url || '';
			setStageAppearance();
		};
		root.renderDoors = render;
		setStageAppearance();
		render();
	}

	function openDoorModal(door, trigger) {
		if (!modal) return;
		activeDoor = door;
		returnFocus = trigger || document.activeElement;
		modal.querySelectorAll('[data-door-field]').forEach(function (field) {
			field.value = door[field.dataset.doorField] == null ? '' : door[field.dataset.doorField];
		});
		setEditorContent(door.content || '');
		modal.hidden = false;
		document.body.classList.add('panda-seasons-modal-open');
		modal.querySelector('[data-door-field="number"]').focus();
	}

	function closeDoorModal() {
		if (!modal) return;
		if (activeDoor && activeDoor.isDraft) {
			activeDoor.deleted = true;
			if (composer && composer.renderDoors) composer.renderDoors();
		}
		modal.hidden = true;
		document.body.classList.remove('panda-seasons-modal-open');
		activeDoor = null;
		if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
	}

	if (composer) composerController(composer);

	document.addEventListener('click', function (event) {
		var select = event.target.closest('[data-select-media]');
		var remove = event.target.closest('[data-remove-media]');
		if (select || remove) {
			var field = event.target.closest('[data-media-field]');
			if (!field) return;
			var hidden = field.querySelector('input[type="hidden"]');
			var targetView = hidden.name.indexOf('mobile') !== -1 ? 'mobile' : 'desktop';
			if (remove) {
				hidden.value = '';
				field.querySelector('.panda-seasons-media-preview').innerHTML = '';
				remove.hidden = true;
				if (composer && composer.updateImage) composer.updateImage(targetView, '');
				return;
			}
			var frame = wp.media({ title: select.textContent, library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				hidden.value = attachment.id;
				field.querySelector('.panda-seasons-media-preview').innerHTML = '<img src="' + previewUrl + '" alt="">';
				field.querySelector('[data-remove-media]').hidden = false;
				if (composer && composer.updateImage) composer.updateImage(targetView, attachment.url);
			});
			frame.open();
			return;
		}

		if (event.target.closest('[data-close-door-modal]')) closeDoorModal();

		if (event.target.closest('[data-save-door]') && activeDoor) {
			modal.querySelectorAll('[data-door-field]').forEach(function (field) {
				activeDoor[field.dataset.doorField] = field.dataset.doorField === 'number' ? Math.max(1, parseInt(field.value, 10) || 1) : field.value;
			});
			activeDoor.content = getEditorContent();
			activeDoor.isDraft = false;
			if (composer && composer.renderDoors) composer.renderDoors();
			closeDoorModal();
		}

		if (event.target.closest('[data-delete-door]') && activeDoor) {
			var warning = window.pandaSeasonsAdmin && pandaSeasonsAdmin.deleteConfirm ? pandaSeasonsAdmin.deleteConfirm : 'Move this door to the trash?';
			if (!window.confirm(warning)) return;
			activeDoor.deleted = true;
			if (composer && composer.renderDoors) composer.renderDoors();
			closeDoorModal();
		}

	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && modal && !modal.hidden) closeDoorModal();
	});
}());
