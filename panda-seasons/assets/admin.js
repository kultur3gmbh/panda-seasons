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

	function setEditorContent(editorId, value) {
		var textarea = document.getElementById(editorId);
		if (textarea) textarea.value = value || '';
		if (window.tinymce) {
			var editor = window.tinymce.get(editorId);
			if (editor) editor.setContent(value || '');
		}
	}

	function getEditorContent(editorId) {
		if (window.tinymce) {
			var editor = window.tinymce.get(editorId);
			if (editor && !editor.isHidden()) return editor.getContent();
		}
		var textarea = document.getElementById(editorId);
		return textarea ? textarea.value : '';
	}

	function safeUrl(value) {
		try {
			if (!/^https?:\/\//i.test(String(value || ''))) return '';
			var parsed = new URL(value);
			return ['http:', 'https:'].indexOf(parsed.protocol) !== -1 ? parsed.href : '';
		} catch (ignore) { return ''; }
	}

	function mediaMarkup(url, type) {
		url = safeUrl(url);
		if (!url) return '';
		if (type === 'image') return '<img src="' + escapeHtml(url) + '" alt="">';
		if (type === 'video') return '<video src="' + escapeHtml(url) + '" controls></video>';
		if (type === 'audio') return '<audio src="' + escapeHtml(url) + '" controls></audio>';
		return '<a href="' + escapeHtml(url) + '">' + escapeHtml(url.split('/').pop()) + '</a>';
	}

	function updateDoorAssets() {
		if (!modal) return;
		var mediaBox = modal.querySelector('[data-door-asset="media"]');
		var downloadBox = modal.querySelector('[data-door-asset="download"]');
		if (mediaBox) {
			var mediaUrl = modal.querySelector('[data-door-field="mediaUrl"]').value;
			var mediaType = modal.querySelector('[data-door-field="mediaType"]').value;
			var mediaMarkupValue = mediaMarkup(mediaUrl, mediaType);
			mediaBox.querySelector('[data-door-asset-preview]').innerHTML = mediaMarkupValue || '<span class="panda-seasons-door-modal__drop-hint">' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.mediaDropHint ? pandaSeasonsAdmin.mediaDropHint : 'Drag an image, video or audio file here or select it from the media library.') + '</span>';
			mediaBox.querySelector('[data-remove-door-media]').hidden = !mediaUrl;
		}
		if (downloadBox) {
			var downloadUrl = modal.querySelector('[data-door-field="downloadUrl"]').value;
			downloadBox.querySelector('[data-door-asset-preview]').innerHTML = downloadUrl ? '<span class="dashicons dashicons-download" aria-hidden="true"></span> ' + escapeHtml(downloadUrl.split('/').pop()) : '';
			downloadBox.querySelector('[data-remove-door-download]').hidden = !downloadUrl;
		}
		['closedImage', 'openImage'].forEach(function (kind) {
			var box = modal.querySelector('[data-door-asset="' + kind + '"]');
			if (!box) return;
			var url = modal.querySelector('[data-door-field="' + kind + 'Url"]').value;
			box.querySelector('[data-door-asset-preview]').innerHTML = url ? '<img src="' + escapeHtml(safeUrl(url)) + '" alt="">' : '';
			box.querySelector('[data-remove-door-image="' + kind + '"]').hidden = !url;
		});
	}

	function renderQuizQuestions(questions) {
		var container = modal && modal.querySelector('[data-quiz-questions]');
		if (!container) return;
		questions = Array.isArray(questions) && questions.length ? questions : [{ question: '', answers: ['', ''], correct: 1 }];
		container.innerHTML = questions.map(function (item, index) {
			return '<div class="panda-seasons-quiz-question" data-quiz-question-editor>' +
				'<div class="panda-seasons-quiz-question__header"><strong>' + escapeHtml((window.pandaSeasonsAdmin && pandaSeasonsAdmin.questionLabel || 'Question') + ' ' + (index + 1)) + '</strong><button type="button" class="button-link-delete" data-remove-quiz-question>' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.remove || 'Remove') + '</button></div>' +
				'<label><span>' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.questionLabel || 'Question') + '</span><input type="text" data-quiz-question-text value="' + escapeHtml(item.question || '') + '"></label>' +
				'<label><span>' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.answersLabel || 'Answers (one per line)') + '</span><textarea rows="4" data-quiz-question-answers>' + escapeHtml((item.answers || []).join('\n')) + '</textarea></label>' +
				'<label><span>' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.correctLabel || 'Correct answer number') + '</span><input type="number" min="1" max="20" data-quiz-question-correct value="' + escapeHtml(item.correct || 1) + '"></label></div>';
		}).join('');
	}

	function collectQuizQuestions() {
		return Array.prototype.map.call(modal.querySelectorAll('[data-quiz-question-editor]'), function (row) {
			return { question: row.querySelector('[data-quiz-question-text]').value.trim(), answers: row.querySelector('[data-quiz-question-answers]').value.split(/\r?\n/).map(function (answer) { return answer.trim(); }).filter(Boolean), correct: Math.max(1, parseInt(row.querySelector('[data-quiz-question-correct]').value, 10) || 1) };
		});
	}

	function composerController(root) {
		var stage = root.querySelector('[data-editor-stage]');
		var dataField = root.querySelector('[data-editor-data]');
		var empty = root.querySelector('[data-editor-empty]');
		var images = { desktop: '', mobile: '' };
		var doors = [];
		var view = 'desktop';
		var previewState = 'actual';
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
				var isOpen = previewState === 'open' || (previewState === 'actual' && (!door.unlockDate || new Date(door.unlockDate + 'T' + (door.unlockTime || '00:00')) <= new Date()));
				element.className = 'panda-seasons-composer__door is-' + (isOpen ? 'open' : 'closed') + ' shape-' + (door.shape || 'rounded');
				element.dataset.editorDoor = door.id || door.clientId;
				element.setAttribute('role', 'button');
				element.setAttribute('tabindex', '0');
				element.setAttribute('aria-label', door.title || String(door.number));
				element.style.left = position.x + '%';
				element.style.top = position.y + '%';
				element.style.width = position.w + '%';
				element.style.height = position.h + '%';
				element.style.setProperty('--ps-door-bg', isOpen ? (door.openBg || '#334fe8') : (door.closedBg || '#1f2937'));
				element.style.setProperty('--ps-door-color', isOpen ? (door.openColor || '#ffffff') : (door.closedColor || '#ffffff'));
				var stateImage = isOpen ? door.openImageUrl : door.closedImageUrl;
				element.style.setProperty('--ps-door-image', stateImage ? 'url("' + stateImage.replace(/"/g, '\\"') + '")' : 'none');
				var stateLabel = isOpen ? (door.openLabel || door.number) : (door.closedLabel || door.number);
				element.innerHTML = '<span class="panda-seasons-composer__door-number">' + escapeHtml(stateLabel) + '</span><span class="panda-seasons-composer__door-title">' + escapeHtml(door.title || '') + '</span><span class="panda-seasons-composer__resize" data-resize-door aria-hidden="true"></span>';
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

		function addDoor(position) {
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
				unlockTime: '',
				emailSubject: '',
				emailContent: '',
				mediaId: 0,
				mediaUrl: '',
				mediaType: '',
				downloadId: 0,
				downloadUrl: '',
				downloadLabel: '',
				buttonLabel: '',
				buttonUrl: '',
				productId: 0,
				eventId: 0,
				closedLabel: '',
				openLabel: '',
				closedBg: 'rgba(31, 41, 55, 1)',
				closedColor: '#ffffff',
				openBg: 'rgba(51, 79, 232, 1)',
				openColor: '#ffffff',
				shape: 'rounded',
				openEffect: 'inherit',
				closedImageId: 0,
				closedImageUrl: '',
				openImageId: 0,
				openImageUrl: '',
				quizEnabled: false,
				quizQuestions: [{ question: '', answers: ['', ''], correct: 1 }],
				quizSuccess: '',
				quizFailure: '',
				desktop: cleanPosition(null, 4 + (sequence % 6) * 15, 5 + Math.floor(sequence / 6) * 21, 'desktop'),
				mobile: cleanPosition(null, 5 + (sequence % 3) * 31, 5 + Math.floor(sequence / 3) * 21, 'mobile')
			};
			if (position) {
				var size = door[view];
				size.x = clamp(position.x - size.w / 2, 0, 100 - size.w);
				size.y = clamp(position.y - size.h / 2, 0, 100 - size.h);
			}
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
			var stateButton = event.target.closest('[data-preview-state]');
			if (stateButton) {
				previewState = stateButton.dataset.previewState;
				root.dataset.previewState = previewState;
				root.querySelectorAll('[data-preview-state]').forEach(function (button) { button.classList.toggle('button-primary', button === stateButton); });
				render();
				return;
			}
			var viewButton = event.target.closest('[data-editor-view]');
			if (viewButton) {
				view = viewButton.dataset.editorView;
				root.querySelectorAll('[data-editor-view]').forEach(function (button) { button.classList.toggle('button-primary', button === viewButton); });
				setStageAppearance();
				render();
			}
			if (event.target.closest('[data-add-door]')) addDoor();
		});

		var addButton = root.querySelector('[data-add-door]');
		if (addButton) {
			addButton.draggable = true;
			addButton.addEventListener('dragstart', function (event) { event.dataTransfer.setData('text/panda-seasons-door', 'new'); event.dataTransfer.effectAllowed = 'copy'; });
			stage.addEventListener('dragover', function (event) { if (Array.prototype.indexOf.call(event.dataTransfer.types, 'text/panda-seasons-door') !== -1) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; } });
			stage.addEventListener('drop', function (event) { if (!event.dataTransfer.getData('text/panda-seasons-door')) return; event.preventDefault(); var rect = stage.getBoundingClientRect(); addDoor({ x: (event.clientX - rect.left) / rect.width * 100, y: (event.clientY - rect.top) / rect.height * 100 }); });
		}

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
		var colorDefaults = { closedBg: '#1f2937', closedColor: '#ffffff', openBg: '#334fe8', openColor: '#ffffff' };
		modal.querySelectorAll('[data-door-field]').forEach(function (field) {
			var value = door[field.dataset.doorField];
			if ((value == null || value === '') && field.type === 'color') value = colorDefaults[field.dataset.doorField] || '#ffffff';
			if (field.type === 'checkbox') field.checked = Boolean(value);
			else if (field.dataset.doorField === 'quizAnswers' && Array.isArray(value)) field.value = value.join('\n');
			else field.value = value == null ? '' : value;
		});
		setEditorContent('panda_seasons_door_content_editor', door.content || '');
		setEditorContent('panda_seasons_door_email_editor', door.emailContent || '');
		renderQuizQuestions(door.quizQuestions);
		updateDoorAssets();
		var preview = modal.querySelector('[data-door-preview]');
		if (preview) { preview.hidden = true; preview.innerHTML = ''; }
		modal.hidden = false;
		composer.classList.add('has-active-door');
		modal.querySelector('[data-door-field="number"]').focus();
	}

	function closeDoorModal() {
		if (!modal) return;
		if (activeDoor && activeDoor.isDraft) {
			activeDoor.deleted = true;
			if (composer && composer.renderDoors) composer.renderDoors();
		}
		modal.hidden = true;
		if (composer) composer.classList.remove('has-active-door');
		activeDoor = null;
		if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
	}

	if (composer) composerController(composer);

	function selectDoorAttachment(kind) {
		var config = kind === 'media' ? { type: ['image', 'video', 'audio'] } : {};
		var frame = wp.media({
			title: kind === 'media' ? (window.pandaSeasonsAdmin && pandaSeasonsAdmin.selectMedia ? pandaSeasonsAdmin.selectMedia : 'Select media') : (window.pandaSeasonsAdmin && pandaSeasonsAdmin.selectDownload ? pandaSeasonsAdmin.selectDownload : 'Select download file'),
			library: config,
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			if (kind === 'media') {
				modal.querySelector('[data-door-field="mediaId"]').value = attachment.id || 0;
				modal.querySelector('[data-door-field="mediaUrl"]').value = attachment.url || '';
				modal.querySelector('[data-door-field="mediaType"]').value = attachment.type || '';
			} else {
				modal.querySelector('[data-door-field="downloadId"]').value = attachment.id || 0;
				modal.querySelector('[data-door-field="downloadUrl"]').value = attachment.url || '';
				var label = modal.querySelector('[data-door-field="downloadLabel"]');
				if (label && !label.value) label.value = attachment.title || attachment.filename || '';
			}
			updateDoorAssets();
		});
		frame.open();
	}

	function clearDoorAttachment(kind) {
		var prefix = kind === 'media' ? 'media' : 'download';
		modal.querySelector('[data-door-field="' + prefix + 'Id"]').value = '';
		modal.querySelector('[data-door-field="' + prefix + 'Url"]').value = '';
		if (kind === 'media') modal.querySelector('[data-door-field="mediaType"]').value = '';
		updateDoorAssets();
	}

	function selectDoorStateImage(kind) {
		var frame = wp.media({ title: window.pandaSeasonsAdmin && pandaSeasonsAdmin.selectImage ? pandaSeasonsAdmin.selectImage : 'Select image', library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			modal.querySelector('[data-door-field="' + kind + 'Id"]').value = attachment.id || 0;
			modal.querySelector('[data-door-field="' + kind + 'Url"]').value = attachment.url || '';
			updateDoorAssets();
		});
		frame.open();
	}

	function clearDoorStateImage(kind) {
		modal.querySelector('[data-door-field="' + kind + 'Id"]').value = '';
		modal.querySelector('[data-door-field="' + kind + 'Url"]').value = '';
		updateDoorAssets();
	}

	function previewDoor() {
		var preview = modal.querySelector('[data-door-preview]');
		if (!preview) return;
		var value = function (name) {
			var field = modal.querySelector('[data-door-field="' + name + '"]');
			return field ? field.value : '';
		};
		var title = value('title') || (window.pandaSeasonsAdmin && pandaSeasonsAdmin.untitledPreview ? pandaSeasonsAdmin.untitledPreview : 'Door preview');
		var html = '<div class="panda-seasons-door-preview__card"><h3>' + escapeHtml(title) + '</h3>';
		html += mediaMarkup(value('mediaUrl'), value('mediaType'));
		html += '<div class="panda-seasons-door-preview__content">' + getEditorContent('panda_seasons_door_content_editor') + '</div>';
		if (value('downloadUrl')) html += '<p><a href="' + escapeHtml(safeUrl(value('downloadUrl'))) + '">' + escapeHtml(value('downloadLabel') || value('downloadUrl').split('/').pop()) + '</a></p>';
		if (value('buttonUrl') && value('buttonLabel')) html += '<p><a class="button button-primary" href="' + escapeHtml(safeUrl(value('buttonUrl'))) + '">' + escapeHtml(value('buttonLabel')) + '</a></p>';
		['productId', 'eventId'].forEach(function (name) {
			var select = modal.querySelector('[data-door-field="' + name + '"]');
			if (select && select.value) html += '<p class="panda-seasons-door-preview__related"><strong>' + escapeHtml(select.options[select.selectedIndex].text) + '</strong></p>';
		});
		html += '</div>';
		preview.innerHTML = html;
		preview.hidden = false;
		preview.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	document.addEventListener('click', function (event) {
		var stateTab = event.target.closest('[data-door-state-tab]');
		if (stateTab) { modal.querySelectorAll('[data-door-state-tab]').forEach(function (button) { button.classList.toggle('button-primary', button === stateTab); }); modal.querySelectorAll('[data-door-state-panel]').forEach(function (panel) { panel.hidden = panel.dataset.doorStatePanel !== stateTab.dataset.doorStateTab; }); return; }
		if (event.target.closest('[data-add-quiz-question]')) { var questions = collectQuizQuestions(); questions.push({ question: '', answers: ['', ''], correct: 1 }); renderQuizQuestions(questions); return; }
		var removeQuestion = event.target.closest('[data-remove-quiz-question]');
		if (removeQuestion) { var remaining = collectQuizQuestions(); remaining.splice(Array.prototype.indexOf.call(modal.querySelectorAll('[data-quiz-question-editor]'), removeQuestion.closest('[data-quiz-question-editor]')), 1); renderQuizQuestions(remaining); return; }
		var selectStateImage = event.target.closest('[data-select-door-image]');
		if (selectStateImage) { selectDoorStateImage(selectStateImage.dataset.selectDoorImage); return; }
		var removeStateImage = event.target.closest('[data-remove-door-image]');
		if (removeStateImage) { clearDoorStateImage(removeStateImage.dataset.removeDoorImage); return; }
		if (event.target.closest('[data-select-door-media]')) { selectDoorAttachment('media'); return; }
		if (event.target.closest('[data-select-door-download]')) { selectDoorAttachment('download'); return; }
		if (event.target.closest('[data-remove-door-media]')) { clearDoorAttachment('media'); return; }
		if (event.target.closest('[data-remove-door-download]')) { clearDoorAttachment('download'); return; }
		if (event.target.closest('[data-preview-door]')) { previewDoor(); return; }

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
				var name = field.dataset.doorField;
				if (field.type === 'checkbox') activeDoor[name] = field.checked;
				else if (name === 'quizAnswers') activeDoor[name] = field.value.split(/\r?\n/).map(function (answer) { return answer.trim(); }).filter(Boolean);
				else activeDoor[name] = name === 'number' ? Math.max(1, parseInt(field.value, 10) || 1) : (/Id$/.test(name) ? Math.max(0, parseInt(field.value, 10) || 0) : field.value);
			});
			activeDoor.content = getEditorContent('panda_seasons_door_content_editor');
			activeDoor.emailContent = getEditorContent('panda_seasons_door_email_editor');
			activeDoor.quizQuestions = collectQuizQuestions();
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
