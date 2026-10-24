(function () {
	'use strict';

	var composer = document.querySelector('[data-panda-seasons-composer]');
	var modal = document.querySelector('[data-door-modal]');
	var activeDoor = null;
	var activeDoorSnapshot = null;
	var returnFocus = null;
	var designBox = document.getElementById('panda-seasons-design');
	var postBody = document.getElementById('post-body');
	if (designBox && postBody) postBody.insertBefore(designBox, postBody.firstChild);
	var publishPanel = document.querySelector('[data-panda-seasons-publish-panel]');
	var publishBox = document.getElementById('submitdiv');
	if (publishPanel && publishBox) {
		publishPanel.appendChild(publishBox);
		publishBox.classList.remove('closed');
		var publishToggle = publishBox.querySelector('.handlediv');
		if (publishToggle) publishToggle.setAttribute('aria-expanded', 'true');
	}
	var shortcodeBox = document.getElementById('panda-seasons-shortcode');
	if (publishPanel && shortcodeBox) {
		publishPanel.appendChild(shortcodeBox);
		shortcodeBox.classList.remove('closed');
	}

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
	function adminText(key, fallback) { return window.pandaSeasonsAdmin && pandaSeasonsAdmin[key] ? pandaSeasonsAdmin[key] : fallback; }
	function rgbaParts(value) { var m=String(value||'').match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?\s*\)/i); if(m)return {hex:'#'+[m[1],m[2],m[3]].map(function(v){return Math.max(0,Math.min(255,+v)).toString(16).padStart(2,'0');}).join(''),alpha:m[4]==null?1:Math.max(0,Math.min(1,+m[4]))}; return {hex:/^#[0-9a-f]{6}$/i.test(value)?value:'#636363',alpha:1}; }
	function enhanceColorField(field){
		if(!field||field.dataset.rgbaEnhanced)return;
		field.dataset.rgbaEnhanced='1';
		var parts=rgbaParts(field.value), label=field.closest('label'), labelNode=label&&label.querySelector(':scope > span'), labelText=labelNode ? labelNode.textContent : '';
		if(labelNode)labelNode.classList.add('panda-seasons-color-control__label');
		var pickerLabel=window.pandaSeasonsAdmin&&pandaSeasonsAdmin.openColorPicker||'Open color picker', themeLabel=window.pandaSeasonsAdmin&&pandaSeasonsAdmin.globalColors||'Global theme colors', themeTooltip=window.pandaSeasonsAdmin&&pandaSeasonsAdmin.globalColorsTooltip||'Global color settings', alphaLabel=window.pandaSeasonsAdmin&&pandaSeasonsAdmin.alpha||'Transparency';
		var control=document.createElement('div');
		control.className='panda-seasons-color-control';
		control.innerHTML='<div class="panda-seasons-color-control__head"><button type="button" class="panda-seasons-color-control__preview" data-toggle-color-picker aria-label="'+escapeHtml(pickerLabel)+'" aria-expanded="false"></button><div class="panda-seasons-color-control__code-wrap"><input type="text" class="panda-seasons-color-control__code" aria-label="'+escapeHtml(labelText)+'"><button type="button" class="button-link panda-seasons-color-control__icon" data-toggle-theme-colors aria-label="'+escapeHtml(themeTooltip)+'" title="'+escapeHtml(themeTooltip)+'" aria-expanded="false"><span class="dashicons dashicons-admin-site-alt3"></span></button></div></div><div class="panda-seasons-color-control__picker" hidden><input type="text" data-iris-color value="'+parts.hex+'"><div class="panda-seasons-color-control__alpha"><input type="range" aria-label="'+escapeHtml(alphaLabel)+'" min="0" max="1" step="0.01" value="'+parts.alpha+'"></div></div><div class="panda-seasons-color-control__themes" hidden><strong>'+escapeHtml(themeLabel)+'</strong><div data-theme-color-list></div></div>';
		field.parentNode.insertBefore(control,field);
		field.classList.add('panda-seasons-color-control__rgba');
		control.appendChild(field);
		var preview=control.querySelector('.panda-seasons-color-control__preview'), code=control.querySelector('.panda-seasons-color-control__code'), alpha=control.querySelector('[type=range]'), iris=control.querySelector('[data-iris-color]'), themes=control.querySelector('.panda-seasons-color-control__themes'), syncing=false;
		var themeButtons=document.querySelectorAll('.panda-seasons-theme-colors [data-theme-door-color]'),themeList=themes.querySelector('[data-theme-color-list]');
		themeButtons.forEach(function(button){var copy=button.cloneNode(true);copy.removeAttribute('data-theme-door-color');copy.addEventListener('click',function(){alpha.value=1;setHex(button.dataset.themeDoorColor);themes.hidden=true;control.querySelector('[data-toggle-theme-colors]').setAttribute('aria-expanded','false');});themeList.appendChild(copy);});
		if(!themeButtons.length)control.querySelector('[data-toggle-theme-colors]').hidden=true;
		function rgbaValue(hex,a){var r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);return 'rgba('+r+', '+g+', '+b+', '+Number(a.toFixed(2))+')';}
		function paint(){var current=rgbaParts(field.value);preview.style.setProperty('--ps-selected-color',field.value);code.value=current.alpha>=1?current.hex:field.value;alpha.value=current.alpha;}
		function setHex(hex){if(syncing)return;parts.hex=rgbaParts(hex).hex;field.value=rgbaValue(parts.hex,+alpha.value);paint();syncing=true;field.dispatchEvent(new Event('input',{bubbles:true}));field.dispatchEvent(new Event('change',{bubbles:true}));syncing=false;}
		alpha.addEventListener('input',function(){field.value=rgbaValue(parts.hex,+alpha.value);paint();syncing=true;field.dispatchEvent(new Event('input',{bubbles:true}));field.dispatchEvent(new Event('change',{bubbles:true}));syncing=false;});
		code.addEventListener('change',function(){var next=rgbaParts(code.value);parts=next;field.value=next.alpha>=1?next.hex:rgbaValue(next.hex,next.alpha);if(window.jQuery&&jQuery.fn.iris)jQuery(iris).iris('color',next.hex);paint();field.dispatchEvent(new Event('input',{bubbles:true}));field.dispatchEvent(new Event('change',{bubbles:true}));});
		field.addEventListener('change',function(){parts=rgbaParts(field.value);if(!syncing&&window.jQuery&&jQuery.fn.iris){syncing=true;jQuery(iris).iris('color',parts.hex);syncing=false;}paint();});
		control.querySelector('[data-toggle-color-picker]').addEventListener('click',function(event){var panel=control.querySelector('.panda-seasons-color-control__picker'),open=panel.hidden;panel.hidden=!open;themes.hidden=true;event.currentTarget.setAttribute('aria-expanded',open?'true':'false');});
		control.querySelector('[data-toggle-theme-colors]').addEventListener('click',function(event){var open=themes.hidden;themes.hidden=!open;control.querySelector('.panda-seasons-color-control__picker').hidden=true;event.currentTarget.setAttribute('aria-expanded',open?'true':'false');});
		if(window.jQuery&&jQuery.fn.iris){
			jQuery(iris).iris({hide:false,palettes:false,width:238,change:function(event,ui){setHex(ui.color.toString());}});
			var syncPickerHeight=function(){var picker=control.querySelector('.iris-picker');if(picker&&picker.offsetHeight)control.style.setProperty('--ps-color-picker-height',picker.offsetHeight+'px');};
			window.requestAnimationFrame(syncPickerHeight);
			if(window.ResizeObserver){var pickerObserver=new ResizeObserver(syncPickerHeight),pickerElement=control.querySelector('.iris-picker');if(pickerElement)pickerObserver.observe(pickerElement);}
		}else{iris.type='color';iris.addEventListener('input',function(){setHex(iris.value);});}
		paint();
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
				element.style.setProperty('--ps-door-font', isOpen ? (door.openFont || 'inherit') : (door.closedFont || 'inherit'));
				element.style.setProperty('--ps-door-font-size', (isOpen ? (door.openFontSize || 20) : (door.closedFontSize || 20)) + 'px');
				element.style.setProperty('--ps-door-font-weight', isOpen ? (door.openFontWeight || '700') : (door.closedFontWeight || '700'));
				element.style.setProperty('--ps-door-text-align', isOpen ? (door.openTextAlign || 'center') : (door.closedTextAlign || 'center'));
				element.style.setProperty('--ps-door-border-color', isOpen ? (door.openBorderColor || 'rgba(255,255,255,.9)') : (door.closedBorderColor || 'rgba(255,255,255,.9)'));
				element.style.setProperty('--ps-door-border-width', (isOpen ? (door.openBorderWidth == null ? 2 : door.openBorderWidth) : (door.closedBorderWidth == null ? 2 : door.closedBorderWidth)) + 'px');
				element.style.setProperty('--ps-door-border-style', isOpen ? (door.openBorderStyle || 'dashed') : (door.closedBorderStyle || 'dashed'));
				element.style.setProperty('--ps-door-label-bg', isOpen ? (door.openLabelBg || 'rgba(10,18,30,.76)') : (door.closedLabelBg || 'rgba(10,18,30,.76)'));
				element.style.setProperty('--ps-door-label-border-color', isOpen ? (door.openLabelBorderColor || '#fff') : (door.closedLabelBorderColor || '#fff'));
				element.style.setProperty('--ps-door-label-border-width', (isOpen ? (door.openLabelBorderWidth == null ? 1 : door.openLabelBorderWidth) : (door.closedLabelBorderWidth == null ? 1 : door.closedLabelBorderWidth)) + 'px');
				var labelWidthMode = isOpen ? door.openLabelWidthMode : door.closedLabelWidthMode;
				var labelWidth = isOpen ? door.openLabelWidth : door.closedLabelWidth;
				var labelPadding = isOpen ? (door.openLabelPadding == null ? 4 : door.openLabelPadding) : (door.closedLabelPadding == null ? 4 : door.closedLabelPadding);
				var labelSize = labelWidth || 60;
				var roundLabel = String(labelWidthMode || '').indexOf('round-') === 0;
				var labelWidthValue = labelWidthMode === 'percent' || labelWidthMode === 'round-percent' ? labelSize + '%' : labelWidthMode === 'round-px' ? labelSize + 'px' : labelWidthMode === 'round-auto' ? 'calc(2.5em + ' + (labelPadding * 2) + 'px)' : 'auto';
				element.style.setProperty('--ps-door-label-width', labelWidthValue);
				element.style.setProperty('--ps-door-label-height', roundLabel && labelWidthMode !== 'round-percent' ? labelWidthValue : 'auto');
				element.style.setProperty('--ps-door-label-aspect', labelWidthMode === 'round-percent' ? '1' : 'auto');
				element.style.setProperty('--ps-door-label-padding', labelPadding + 'px');
				var stateImage = isOpen ? door.openImageUrl : door.closedImageUrl;
				element.style.setProperty('--ps-door-image', stateImage ? 'url("' + stateImage.replace(/"/g, '\\"') + '")' : 'none');
				var stateLabel = isOpen ? (door.openLabel || door.number) : (door.closedLabel || door.number);
				var labelVisible = isOpen ? door.openLabelVisible !== false : door.closedLabelVisible !== false;
				element.innerHTML = '<span class="panda-seasons-composer__door-actions"><button type="button" data-duplicate-door aria-label="' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.duplicateDoor ? pandaSeasonsAdmin.duplicateDoor : 'Duplicate door') + '" title="' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.duplicateDoor ? pandaSeasonsAdmin.duplicateDoor : 'Duplicate door') + '"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><button type="button" data-delete-stage-door aria-label="' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.deleteDoor ? pandaSeasonsAdmin.deleteDoor : 'Delete door') + '" title="' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.deleteDoor ? pandaSeasonsAdmin.deleteDoor : 'Delete door') + '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></span>' + (labelVisible ? '<span class="panda-seasons-composer__door-number">' + escapeHtml(stateLabel) + '</span>' : '') + '<span class="panda-seasons-composer__resize" data-resize-door aria-hidden="true"></span>';
				stage.appendChild(element);
			});
			empty.hidden = currentDoors().length > 0;
			writeData();
		}

		function findDoor(key) {
			return doors.find(function (door) { return String(door.id || door.clientId) === String(key); });
		}

		function duplicateDoor(door) {
			var copy = JSON.parse(JSON.stringify(door));
			copy.id = 0;
			copy.clientId = 'new-' + Date.now();
			copy.isDraft = true;
			copy.deleted = false;
			copy.number = nextNumber();
			copy.title = (window.pandaSeasonsAdmin && pandaSeasonsAdmin.untitledDoor ? pandaSeasonsAdmin.untitledDoor : 'Door %d').replace('%d', copy.number);
			['desktop', 'mobile'].forEach(function (mode) {
				copy[mode].x = clamp(copy[mode].x + 2, 0, 100 - copy[mode].w);
				copy[mode].y = clamp(copy[mode].y + 2, 0, 100 - copy[mode].h);
			});
			doors.push(copy);
			render();
			openDoorModal(copy, stage.querySelector('[data-editor-door="' + CSS.escape(copy.clientId) + '"]'));
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
				contentType: 'general',
				number: doorNumber,
				unlockDate: '',
				unlockTime: '',
				unlockBehavior: 'auto-open',
				availabilityWindow: 'from-date',
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
				couponId: 0, ctaEnabled: false, ctaTitle: '', ctaText: '', ctaType: 'link', ctaLabel: '', ctaUrl: '', ctaShortcode: '',
				eventId: 0,
				closedLabel: '',
				openLabel: '',
				closedBg: root.dataset.defaultClosed || 'rgba(99, 99, 99, 0.2)',
				closedColor: root.dataset.defaultClosedColor || 'rgba(255, 255, 255, 1)', closedLabelVisible: root.dataset.defaultClosedLabelVisible !== '0', closedFont: root.dataset.defaultLabelFont || 'inherit', closedFontSize: +(root.dataset.defaultLabelFontSize || 20), closedFontWeight: root.dataset.defaultLabelFontWeight || '700', closedTextAlign: 'center', closedBorderColor: root.dataset.defaultClosedBorderColor || 'rgba(255, 255, 255, 0.9)', closedBorderWidth: +(root.dataset.defaultClosedBorderWidth || 2), closedBorderStyle: root.dataset.defaultBorderStyle || 'dashed', closedLabelBg: root.dataset.defaultClosedLabelBg || 'rgba(10, 18, 30, 0.76)', closedLabelBorderColor: root.dataset.defaultClosedLabelBorderColor || 'rgba(255, 255, 255, 1)', closedLabelBorderWidth: +(root.dataset.defaultClosedLabelBorderWidth || 1), closedLabelWidthMode: root.dataset.defaultLabelWidthMode || 'content', closedLabelWidth: +(root.dataset.defaultLabelWidth || 60), closedLabelPadding: +(root.dataset.defaultLabelPadding || 4),
				openBg: root.dataset.defaultOpen || 'rgba(51, 79, 232, 1)',
				openColor: root.dataset.defaultOpenColor || 'rgba(255, 255, 255, 1)', openLabelVisible: root.dataset.defaultOpenLabelVisible !== '0', openFont: root.dataset.defaultLabelFont || 'inherit', openFontSize: +(root.dataset.defaultLabelFontSize || 20), openFontWeight: root.dataset.defaultLabelFontWeight || '700', openTextAlign: 'center', openBorderColor: root.dataset.defaultOpenBorderColor || 'rgba(255, 255, 255, 0.9)', openBorderWidth: +(root.dataset.defaultOpenBorderWidth || 2), openBorderStyle: root.dataset.defaultBorderStyle || 'dashed', openLabelBg: root.dataset.defaultOpenLabelBg || 'rgba(10, 18, 30, 0.76)', openLabelBorderColor: root.dataset.defaultOpenLabelBorderColor || 'rgba(255, 255, 255, 1)', openLabelBorderWidth: +(root.dataset.defaultOpenLabelBorderWidth || 1), openLabelWidthMode: root.dataset.defaultLabelWidthMode || 'content', openLabelWidth: +(root.dataset.defaultLabelWidth || 60), openLabelPadding: +(root.dataset.defaultLabelPadding || 4),
				shape: root.dataset.defaultShape || 'rounded',
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
			if (event.target.closest('.panda-seasons-composer__door-actions')) {
				dragged = false;
				return;
			}
			startPointer(event, element, Boolean(event.target.closest('[data-resize-door]')));
		});
		stage.addEventListener('pointermove', movePointer);
		stage.addEventListener('pointerup', endPointer);
		stage.addEventListener('pointercancel', endPointer);

		stage.addEventListener('click', function (event) {
			var element = event.target.closest('[data-editor-door]');
			if (!element) return;
			var door = findDoor(element.dataset.editorDoor);
			if (event.target.closest('[data-duplicate-door]')) { event.stopPropagation(); if (door) duplicateDoor(door); return; }
			if (event.target.closest('[data-delete-stage-door]')) {
				event.stopPropagation();
				if (door && window.confirm(window.pandaSeasonsAdmin && pandaSeasonsAdmin.deleteConfirm ? pandaSeasonsAdmin.deleteConfirm : 'Move this door to the trash?')) {
					door.deleted = true;
					if (activeDoor === door) closeDoorModal(true);
					render();
				}
				return;
			}
			if (event.target.closest('[data-resize-door]') || dragged) return;
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
			var stateButton = event.target.closest('button[data-preview-state]');
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
				return;
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
		root.getDoors = function () { return doors; };
		root.renderDoors = render;
		root.setPreviewState = function(state){previewState=state;root.dataset.previewState=state;root.querySelectorAll('button[data-preview-state]').forEach(function(button){button.classList.toggle('button-primary',button.dataset.previewState===state);});render();};
		setStageAppearance();
		render();
	}

	function openDoorModal(door, trigger) {
		if (!modal) return;
		activeDoor = door;
		activeDoorSnapshot = JSON.parse(JSON.stringify(door));
		returnFocus = trigger || document.activeElement;
		var colorDefaults = { closedBg: '#1f2937', closedColor: 'rgba(255, 255, 255, 1)', openBg: '#334fe8', openColor: 'rgba(255, 255, 255, 1)' };
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
		var emptyPanel = document.querySelector('[data-door-editor-empty-panel]');
		if (emptyPanel) emptyPanel.hidden = true;
		composer.classList.add('has-active-door');
		modal.querySelector('[data-door-field="number"]').focus();
		modal.querySelectorAll('[data-door-field$="Bg"],[data-door-field$="Color"]').forEach(function(field){enhanceColorField(field);field.dispatchEvent(new Event('change',{bubbles:true}));});
		modal.querySelectorAll('[data-door-field$="LabelWidthMode"]').forEach(updateLabelWidthVisibility);
		updateContentTypeVisibility();
	}

	function closeDoorModal(applyChanges) {
		if (!modal) return;
		if (activeDoor && !applyChanges && activeDoorSnapshot) {
			Object.keys(activeDoor).forEach(function (key) { delete activeDoor[key]; });
			Object.assign(activeDoor, activeDoorSnapshot);
		}
		if (activeDoor && activeDoor.isDraft && !applyChanges) {
			activeDoor.deleted = true;
		}
		if (composer && composer.renderDoors) composer.renderDoors();
		modal.hidden = true;
		var emptyPanel = document.querySelector('[data-door-editor-empty-panel]');
		if (emptyPanel) emptyPanel.hidden = false;
		if (composer) composer.classList.remove('has-active-door');
		activeDoor = null;
		activeDoorSnapshot = null;
		if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
	}

	function syncDoorPreview() {
		if (!activeDoor || !modal) return;
		['number', 'contentType', 'unlockDate', 'unlockTime', 'unlockBehavior', 'availabilityWindow', 'title', 'mediaId', 'mediaUrl', 'mediaType', 'downloadId', 'downloadUrl', 'downloadLabel', 'buttonLabel', 'buttonUrl', 'closedLabel', 'openLabel', 'closedBg', 'closedColor', 'openBg', 'openColor', 'closedLabelVisible', 'openLabelVisible', 'closedFont', 'openFont', 'closedFontSize', 'openFontSize', 'closedFontWeight', 'openFontWeight', 'closedTextAlign', 'openTextAlign', 'closedBorderColor', 'openBorderColor', 'closedBorderWidth', 'openBorderWidth', 'closedBorderStyle', 'openBorderStyle', 'closedLabelBg', 'openLabelBg', 'closedLabelBorderColor', 'openLabelBorderColor', 'closedLabelBorderWidth', 'openLabelBorderWidth', 'closedLabelWidthMode', 'openLabelWidthMode', 'closedLabelWidth', 'openLabelWidth', 'closedLabelPadding', 'openLabelPadding', 'shape', 'openEffect', 'closedImageId', 'closedImageUrl', 'openImageId', 'openImageUrl'].forEach(function (name) {
			var field = modal.querySelector('[data-door-field="' + name + '"]');
			if (!field) return;
			activeDoor[name] = field.type === 'checkbox' ? field.checked : (name === 'number' ? Math.max(1, parseInt(field.value, 10) || 1) : (/Id$/.test(name) ? Math.max(0, parseInt(field.value, 10) || 0) : field.value));
		});
		activeDoor.content = getEditorContent('panda_seasons_door_content_editor');
		if (composer && composer.renderDoors) composer.renderDoors();
		var openPreview = modal.querySelector('[data-door-preview]:not([hidden])');
		if (openPreview) previewDoor(false);
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
			syncDoorPreview();
		});
		frame.open();
	}

	function clearDoorAttachment(kind) {
		var prefix = kind === 'media' ? 'media' : 'download';
		modal.querySelector('[data-door-field="' + prefix + 'Id"]').value = '';
		modal.querySelector('[data-door-field="' + prefix + 'Url"]').value = '';
		if (kind === 'media') modal.querySelector('[data-door-field="mediaType"]').value = '';
		updateDoorAssets();
		syncDoorPreview();
	}

	function selectDoorStateImage(kind) {
		var frame = wp.media({ title: window.pandaSeasonsAdmin && pandaSeasonsAdmin.selectImage ? pandaSeasonsAdmin.selectImage : 'Select image', library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			modal.querySelector('[data-door-field="' + kind + 'Id"]').value = attachment.id || 0;
			modal.querySelector('[data-door-field="' + kind + 'Url"]').value = attachment.url || '';
			updateDoorAssets();
			syncDoorPreview();
		});
		frame.open();
	}

	function clearDoorStateImage(kind) {
		modal.querySelector('[data-door-field="' + kind + 'Id"]').value = '';
		modal.querySelector('[data-door-field="' + kind + 'Url"]').value = '';
		updateDoorAssets();
		syncDoorPreview();
	}

	function previewDoor(scrollToPreview) {
		var preview = modal.querySelector('[data-door-preview]');
		if (!preview) return;
		var value = function (name) {
			var field = modal.querySelector('[data-door-field="' + name + '"]');
			return field ? field.value : '';
		};
		var type = value('contentType') || 'general';
		var title = value('title') || (window.pandaSeasonsAdmin && pandaSeasonsAdmin.untitledPreview ? pandaSeasonsAdmin.untitledPreview : 'Door preview');
		var html = '<div class="panda-seasons-door-preview__card"><h3>' + escapeHtml(title) + '</h3>';
		if (type === 'general' || type === 'video') html += mediaMarkup(value('mediaUrl'), value('mediaType'));
		if (type === 'general') html += '<div class="panda-seasons-door-preview__content">' + getEditorContent('panda_seasons_door_content_editor') + '</div>';
		if (type === 'general' && value('downloadUrl')) html += '<p><a href="' + escapeHtml(safeUrl(value('downloadUrl'))) + '">' + escapeHtml(value('downloadLabel') || value('downloadUrl').split('/').pop()) + '</a></p>';
		if (type === 'general' && value('buttonUrl') && value('buttonLabel')) html += '<p><a class="button button-primary" href="' + escapeHtml(safeUrl(value('buttonUrl'))) + '">' + escapeHtml(value('buttonLabel')) + '</a></p>';
		(type === 'product' ? ['productId'] : type === 'general' ? ['eventId'] : []).forEach(function (name) {
			var select = modal.querySelector('[data-door-field="' + name + '"]');
			if (select && select.value) html += '<p class="panda-seasons-door-preview__related"><strong>' + escapeHtml(select.options[select.selectedIndex].text) + '</strong></p>';
		});
		html += '</div>';
		preview.innerHTML = html;
		preview.hidden = false;
		if (scrollToPreview !== false) preview.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	document.addEventListener('click', function (event) {
		var copyShortcode = event.target.closest('[data-copy-shortcode]');
		if (copyShortcode) {
			var copyInput = copyShortcode.parentNode.querySelector('input');
			if (copyInput && navigator.clipboard) navigator.clipboard.writeText(copyInput.value);
			else if (copyInput) { copyInput.select(); document.execCommand('copy'); }
			return;
		}
		var stateTab = event.target.closest('[data-door-state-tab]');
		if (stateTab) { modal.querySelectorAll('[data-door-state-tab]').forEach(function (button) { button.classList.toggle('button-primary', button === stateTab); button.setAttribute('aria-selected',button===stateTab?'true':'false'); }); modal.querySelectorAll('[data-door-state-panel]').forEach(function (panel) { panel.hidden = panel.dataset.doorStatePanel !== stateTab.dataset.doorStateTab; }); if(composer&&composer.setPreviewState)composer.setPreviewState(stateTab.dataset.doorStateTab); return; }
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
			activeDoorSnapshot = JSON.parse(JSON.stringify(activeDoor));
		}

		if (event.target.closest('[data-delete-door]') && activeDoor) {
			var warning = window.pandaSeasonsAdmin && pandaSeasonsAdmin.deleteConfirm ? pandaSeasonsAdmin.deleteConfirm : 'Move this door to the trash?';
			if (!window.confirm(warning)) return;
			activeDoor.deleted = true;
			if (composer && composer.renderDoors) composer.renderDoors();
			closeDoorModal(true);
		}

	});

	document.addEventListener('input', function (event) {
		if (event.target.closest('[data-door-modal]') && event.target.matches('[data-door-field]')) syncDoorPreview();
	});
	document.addEventListener('change', function (event) {
		if (event.target.closest('[data-door-modal]') && event.target.matches('[data-door-field]')) syncDoorPreview();
		if (event.target.matches('[data-door-field="contentType"]')) updateContentTypeVisibility();
		if (event.target.matches('[data-door-field$="LabelWidthMode"],[data-default-door-setting="labelWidthMode"]')) updateLabelWidthVisibility(event.target);
		if (event.target.matches('[data-default-door-color]') && composer) composer.dataset['default' + (event.target.dataset.defaultDoorColor === 'closed' ? 'Closed' : 'Open')] = event.target.value;
		if (event.target.matches('[data-default-door-setting]') && composer) composer.dataset['default' + event.target.dataset.defaultDoorSetting.charAt(0).toUpperCase() + event.target.dataset.defaultDoorSetting.slice(1)] = event.target.type === 'checkbox' ? (event.target.checked ? '1' : '0') : event.target.value;
		if (event.target.matches('[data-default-door-shape]') && composer) composer.dataset.defaultShape = event.target.value;
	});
	function updateLabelWidthVisibility(select) {
		var row = select && select.closest('.panda-seasons-label-size-row');
		if (!row) return;
		var widthField = row.querySelector('[data-door-field$="LabelWidth"], [data-default-door-setting="labelWidth"]');
		var label = widthField && widthField.closest('label');
		var needsSize = ['percent', 'round-px', 'round-percent'].indexOf(select.value) !== -1;
		if (label) label.hidden = !needsSize;
		if (widthField) {
			widthField.max = select.value === 'round-px' ? '300' : '100';
			var caption = label && label.querySelector('[data-label-size-caption]');
			if (caption) caption.textContent = select.value === 'round-px' ? adminText('diameterPx','Diameter (px)') : select.value === 'round-percent' ? adminText('diameterPercent','Diameter (%)') : adminText('widthPercent','Width (%)');
		}
	}
	function updateContentTypeVisibility() {
		if (!modal) return;
		var field = modal.querySelector('[data-door-field="contentType"]');
		var type = field ? field.value : 'general';
		modal.querySelectorAll('[data-content-type-panel]').forEach(function (panel) {
			panel.hidden = panel.dataset.contentTypePanel.split(/\s+/).indexOf(type) === -1;
			if (!panel.hidden && (type === 'general' || type === 'video')) panel.open = true;
		});
		var contentPanel = modal.querySelector('[data-content-type-panel="general video"]');
		if (contentPanel) {
			var editor = contentPanel.querySelector('.panda-seasons-door-modal__content-editor');
			var download = contentPanel.querySelector('[data-door-asset="download"]');
			var downloadLabel = contentPanel.querySelector('[data-door-field="downloadLabel"]');
			if (editor) editor.hidden = type === 'video';
			if (download) download.hidden = type === 'video';
			if (downloadLabel && downloadLabel.closest('label')) downloadLabel.closest('label').hidden = type === 'video';
		}
		var quizEnabled = modal.querySelector('[data-door-field="quizEnabled"]');
		if (quizEnabled) quizEnabled.checked = type === 'quiz';
	}
	document.querySelectorAll('[data-door-field$="LabelWidthMode"],[data-default-door-setting="labelWidthMode"]').forEach(updateLabelWidthVisibility);

	document.querySelectorAll('[data-default-door-color],[data-default-door-setting$="Color"],[data-default-door-setting$="Bg"]').forEach(enhanceColorField);
	var defaultsPanel=document.querySelector('.panda-seasons-default-colors');
	var adminGrid=document.querySelector('.panda-seasons-admin-grid'),editorLayout=document.querySelector('.panda-seasons-editor-layout');
	if(adminGrid&&editorLayout){
		var mainTabs=document.createElement('div'),editorPanel=document.createElement('section'),settingsPanel=document.createElement('section');
		mainTabs.className='panda-seasons-main-tabs';mainTabs.setAttribute('role','tablist');
		mainTabs.innerHTML='<button type="button" class="button button-primary" role="tab" aria-selected="true" data-main-editor-tab="editor">'+escapeHtml(adminText('editCalendar','Edit calendar'))+'</button><button type="button" class="button" role="tab" aria-selected="false" data-main-editor-tab="settings">'+escapeHtml(adminText('generalSettings','General defaults'))+'</button>';
		editorPanel.className='panda-seasons-main-panel';editorPanel.dataset.mainEditorPanel='editor';
		settingsPanel.className='panda-seasons-main-panel';settingsPanel.dataset.mainEditorPanel='settings';settingsPanel.hidden=true;
		adminGrid.parentNode.insertBefore(mainTabs,adminGrid);mainTabs.parentNode.insertBefore(editorPanel,adminGrid);mainTabs.parentNode.insertBefore(settingsPanel,adminGrid);
		editorPanel.appendChild(editorLayout);settingsPanel.appendChild(adminGrid);
		document.addEventListener('click',function(event){var button=event.target.closest('[data-main-editor-tab]');if(!button)return;var target=button.dataset.mainEditorTab;mainTabs.querySelectorAll('[data-main-editor-tab]').forEach(function(item){var active=item===button;item.classList.toggle('button-primary',active);item.setAttribute('aria-selected',active?'true':'false');});document.querySelectorAll('[data-main-editor-panel]').forEach(function(panel){panel.hidden=panel.dataset.mainEditorPanel!==target;});if(target==='editor'&&composer&&composer.renderDoors)composer.renderDoors();});
	}
	if(defaultsPanel){var motion=document.createElement('div');motion.className='panda-seasons-default-motion is-wide';var effectField=document.querySelector('[name="panda_seasons_open_effect"]'),speedField=document.querySelector('[name="panda_seasons_effect_speed"]'),sampleField=document.querySelector('.panda-seasons-default-preview');if(effectField)motion.appendChild(effectField.closest('label'));if(speedField)motion.appendChild(speedField.closest('label'));if(sampleField)motion.appendChild(sampleField);defaultsPanel.insertBefore(motion,defaultsPanel.children[1]||null);}
	if(defaultsPanel&&composer){
		var applyDefaultsButton=document.createElement('button');applyDefaultsButton.type='button';applyDefaultsButton.className='button button-primary panda-seasons-apply-defaults-button';applyDefaultsButton.textContent=adminText('applyDefaults','Apply global settings to existing doors');defaultsPanel.appendChild(applyDefaultsButton);
		var defaultsDialog=document.createElement('div');defaultsDialog.className='panda-seasons-defaults-dialog';defaultsDialog.hidden=true;defaultsDialog.innerHTML='<div class="panda-seasons-defaults-dialog__backdrop" data-close-defaults-dialog></div><div class="panda-seasons-defaults-dialog__window" role="dialog" aria-modal="true" aria-labelledby="panda-seasons-defaults-title"><h2 id="panda-seasons-defaults-title">'+escapeHtml(adminText('defaultsTitle','Apply global settings'))+'</h2><p>'+escapeHtml(adminText('defaultsHelp','Choose each setting that may replace existing individual values.'))+'</p><div class="panda-seasons-defaults-dialog__actions"><button type="button" class="button" data-select-all-defaults>'+escapeHtml(adminText('selectAll','Select all'))+'</button><button type="button" class="button" data-select-no-defaults>'+escapeHtml(adminText('selectNone','Select none'))+'</button></div><div class="panda-seasons-defaults-table" data-defaults-comparison></div><div class="panda-seasons-defaults-dialog__footer"><button type="button" class="button" data-close-defaults-dialog>'+escapeHtml(adminText('cancel','Cancel'))+'</button><button type="button" class="button button-primary" data-apply-selected-defaults>'+escapeHtml(adminText('applySelected','Apply selected values'))+'</button></div></div>';document.body.appendChild(defaultsDialog);
		var defaultMappings=[
			{selector:'[name="panda_seasons_default_closed_background"]',props:['closedBg']},{selector:'[name="panda_seasons_default_open_background"]',props:['openBg']},
			{selector:'[name="panda_seasons_default_closed_text_color"]',props:['closedColor']},{selector:'[name="panda_seasons_default_open_text_color"]',props:['openColor']},
			{selector:'[name="panda_seasons_default_closed_border_color"]',props:['closedBorderColor']},{selector:'[name="panda_seasons_default_open_border_color"]',props:['openBorderColor']},
			{selector:'[name="panda_seasons_default_closed_border_width"]',props:['closedBorderWidth']},{selector:'[name="panda_seasons_default_open_border_width"]',props:['openBorderWidth']},
			{selector:'[name="panda_seasons_default_closed_label_background"]',props:['closedLabelBg']},{selector:'[name="panda_seasons_default_open_label_background"]',props:['openLabelBg']},
			{selector:'[name="panda_seasons_default_closed_label_border_color"]',props:['closedLabelBorderColor']},{selector:'[name="panda_seasons_default_open_label_border_color"]',props:['openLabelBorderColor']},
			{selector:'[name="panda_seasons_default_closed_label_border_width"]',props:['closedLabelBorderWidth']},{selector:'[name="panda_seasons_default_open_label_border_width"]',props:['openLabelBorderWidth']},
			{selector:'[name="panda_seasons_default_closed_label_visible"]',props:['closedLabelVisible']},{selector:'[name="panda_seasons_default_open_label_visible"]',props:['openLabelVisible']},
			{selector:'[name="panda_seasons_default_label_font"]',props:['closedFont','openFont']},{selector:'[name="panda_seasons_default_label_font_size"]',props:['closedFontSize','openFontSize']},
			{selector:'[name="panda_seasons_default_label_font_weight"]',props:['closedFontWeight','openFontWeight']},{selector:'[name="panda_seasons_default_label_width_mode"]',props:['closedLabelWidthMode','openLabelWidthMode']},
			{selector:'[name="panda_seasons_default_label_width"]',props:['closedLabelWidth','openLabelWidth']},{selector:'[name="panda_seasons_default_label_padding"]',props:['closedLabelPadding','openLabelPadding']},
			{selector:'[name="panda_seasons_default_border_style"]',props:['closedBorderStyle','openBorderStyle']},{selector:'[name="panda_seasons_default_shape"]',props:['shape']},{selector:'[name="panda_seasons_open_effect"]',props:['openEffect']}
		];
		function inputValue(input){return input.type==='checkbox'?input.checked:(input.type==='number'?number(input.value,0):input.value);}
		function displayValue(value){if(value===true)return adminText('yes','Yes');if(value===false)return adminText('no','No');return String(value==null?'—':value);}
		function openDefaultsDialog(){var doors=composer.getDoors().filter(function(door){return !door.deleted;});var rows=[];defaultMappings.forEach(function(mapping,index){var input=document.querySelector(mapping.selector);if(!input)return;var value=inputValue(input),values=[];doors.forEach(function(door){mapping.props.forEach(function(prop){values.push(displayValue(door[prop]));});});var unique=values.filter(function(item,pos){return values.indexOf(item)===pos;});var label=input.closest('label'),labelNode=label&&label.querySelector('span');rows.push('<label class="panda-seasons-defaults-row"><input type="checkbox" data-default-mapping="'+index+'"><span>'+escapeHtml(labelNode?labelNode.textContent:mapping.props.join(', '))+'</span><code>'+escapeHtml(unique.length===1?unique[0]:adminText('differentValues','Different individual values'))+'</code><code>'+escapeHtml(displayValue(value))+'</code></label>');});defaultsDialog.querySelector('[data-defaults-comparison]').innerHTML='<div class="panda-seasons-defaults-row panda-seasons-defaults-row--head"><span>'+escapeHtml(adminText('overwrite','Overwrite'))+'</span><span>'+escapeHtml(adminText('setting','Setting'))+'</span><span>'+escapeHtml(adminText('oldValue','Old value'))+'</span><span>'+escapeHtml(adminText('newValue','New value'))+'</span></div>'+rows.join('');defaultsDialog.hidden=false;document.body.classList.add('panda-seasons-modal-open');}
		applyDefaultsButton.addEventListener('click',openDefaultsDialog);
		defaultsDialog.addEventListener('click',function(event){if(event.target.closest('[data-close-defaults-dialog]')){defaultsDialog.hidden=true;document.body.classList.remove('panda-seasons-modal-open');return;}if(event.target.closest('[data-select-all-defaults]'))defaultsDialog.querySelectorAll('[data-default-mapping]').forEach(function(box){box.checked=true;});if(event.target.closest('[data-select-no-defaults]'))defaultsDialog.querySelectorAll('[data-default-mapping]').forEach(function(box){box.checked=false;});if(event.target.closest('[data-apply-selected-defaults]')){var selected=defaultsDialog.querySelectorAll('[data-default-mapping]:checked');selected.forEach(function(box){var mapping=defaultMappings[+box.dataset.defaultMapping],input=document.querySelector(mapping.selector),value=inputValue(input);composer.getDoors().forEach(function(door){if(!door.deleted)mapping.props.forEach(function(prop){door[prop]=value;});});});composer.renderDoors();defaultsDialog.hidden=true;document.body.classList.remove('panda-seasons-modal-open');}});
	}
	var defaultPreview=document.querySelector('[data-default-door-preview]');
	function defaultValue(name,fallback){var input=document.querySelector('[name="'+name+'"]');return input ? (input.type==='checkbox' ? input.checked : input.value) : fallback;}
	function updateDefaultPreview(){
		if(!defaultPreview)return;
		var state=defaultPreview.dataset.state||'closed', prefix=state==='open'?'open':'closed';
		defaultPreview.style.setProperty('--ps-sample-bg',defaultValue('panda_seasons_default_'+prefix+'_background','#636363'));
		defaultPreview.style.setProperty('--ps-sample-color',defaultValue('panda_seasons_default_'+prefix+'_text_color','#fff'));
		defaultPreview.style.setProperty('--ps-sample-border-color',defaultValue('panda_seasons_default_'+prefix+'_border_color','#fff'));
		defaultPreview.style.setProperty('--ps-sample-border-width',defaultValue('panda_seasons_default_'+prefix+'_border_width','2')+'px');
		defaultPreview.style.setProperty('--ps-sample-border-style',defaultValue('panda_seasons_default_border_style','dashed'));
		defaultPreview.style.setProperty('--ps-sample-font',defaultValue('panda_seasons_default_label_font','inherit'));
		defaultPreview.style.setProperty('--ps-sample-font-size',defaultValue('panda_seasons_default_label_font_size','20')+'px');
		defaultPreview.style.setProperty('--ps-sample-font-weight',defaultValue('panda_seasons_default_label_font_weight','700'));
		var sampleLabel=defaultPreview.querySelector('span');
		sampleLabel.hidden=!defaultValue('panda_seasons_default_'+prefix+'_label_visible',true);
		sampleLabel.style.background=defaultValue('panda_seasons_default_'+prefix+'_label_background','rgba(10,18,30,.76)');
		sampleLabel.style.borderColor=defaultValue('panda_seasons_default_'+prefix+'_label_border_color','#fff');
		sampleLabel.style.borderWidth=defaultValue('panda_seasons_default_'+prefix+'_label_border_width','1')+'px';
		sampleLabel.style.borderStyle='solid';
		sampleLabel.style.padding=defaultValue('panda_seasons_default_label_padding','4')+'px';
		var labelMode=defaultValue('panda_seasons_default_label_width_mode','content'),labelSize=defaultValue('panda_seasons_default_label_width','60'),isRound=labelMode.indexOf('round-')===0;
		sampleLabel.style.width=labelMode==='percent'||labelMode==='round-percent'?labelSize+'%':labelMode==='round-px'?labelSize+'px':labelMode==='round-auto'?'calc(2.5em + '+(2*number(defaultValue('panda_seasons_default_label_padding','4'),4))+'px)':'auto';
		sampleLabel.style.height=isRound&&labelMode!=='round-percent'?sampleLabel.style.width:'auto';
		sampleLabel.style.aspectRatio=labelMode==='round-percent'?'1':'auto';
		sampleLabel.style.borderRadius=isRound?'999px':'999px';
		sampleLabel.style.boxSizing='border-box';
		defaultPreview.classList.remove('shape-square','shape-rounded','shape-round');
		defaultPreview.classList.add('shape-'+defaultValue('panda_seasons_default_shape','rounded'));
	}
	document.addEventListener('click',function(event){
		var tab=event.target.closest('[data-default-state-tab]');
		if(tab){var state=tab.dataset.defaultStateTab;document.querySelectorAll('[data-default-state-tab]').forEach(function(button){var active=button===tab;button.classList.toggle('button-primary',active);button.setAttribute('aria-selected',active?'true':'false');});document.querySelectorAll('[data-default-state-panel]').forEach(function(panel){panel.hidden=panel.dataset.defaultStatePanel!==state;});if(defaultPreview){defaultPreview.dataset.state=state;updateDefaultPreview();}return;}
		if(event.target.closest('[data-default-door-preview]')){var effect=defaultValue('panda_seasons_open_effect','door-left'),speed=defaultValue('panda_seasons_effect_speed','normal'),sample=defaultPreview.querySelector('button'),duration={fast:350,normal:700,slow:1200}[speed]||700,turn=effect==='door-right'?'rotateY(78deg)':effect==='page'?'rotateY(-120deg) scale(.92)':effect==='none'?'scale(1.04)':'rotateY(-78deg)';sample.style.transformOrigin=effect==='door-right'?'right center':'left center';if(sample.animate){sample.animate([{transform:'perspective(500px) rotateY(0)'},{transform:'perspective(500px) '+turn},{transform:'perspective(500px) rotateY(0)'}],{duration:duration,easing:'ease-in-out'});}else{defaultPreview.className=defaultPreview.className.replace(/\bis-animating\b|\beffect-\S+|\bspeed-\S+/g,'').trim();void defaultPreview.offsetWidth;defaultPreview.classList.add('is-animating','effect-'+effect,'speed-'+speed);}}
	});
	document.addEventListener('input',function(event){if(event.target.closest('.panda-seasons-default-colors')||event.target.name==='panda_seasons_open_effect'||event.target.name==='panda_seasons_effect_speed')updateDefaultPreview();});
	document.addEventListener('change',function(event){if(event.target.closest('.panda-seasons-default-colors')||event.target.name==='panda_seasons_open_effect'||event.target.name==='panda_seasons_effect_speed')updateDefaultPreview();});
	updateDefaultPreview();
	var activeDefaultColorField = document.querySelector('[data-default-door-color="closed"]');
	document.addEventListener('focusin', function (event) {
		if (event.target.matches('[data-default-door-color],[data-default-door-setting$="Color"],[data-default-door-setting$="Bg"],[data-door-field$="Bg"],[data-door-field$="Color"]')) activeDefaultColorField = event.target;
	});
	document.addEventListener('click', function (event) {
		var swatch = event.target.closest('[data-theme-door-color]');
		if (!swatch || !activeDefaultColorField) return;
		activeDefaultColorField.value = swatch.dataset.themeDoorColor;
		activeDefaultColorField.dispatchEvent(new Event('change', { bubbles: true }));
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && modal && !modal.hidden) closeDoorModal();
		var calendarPreview=document.querySelector('[data-calendar-preview]');
		if(event.key==='Escape'&&calendarPreview&&!calendarPreview.hidden)closeCalendarPreview(calendarPreview);
	});
	function closeCalendarPreview(preview){preview.hidden=true;preview.querySelector('iframe').src='about:blank';document.body.classList.remove('panda-seasons-preview-open');}
	document.addEventListener('click',function(event){
		var opener=event.target.closest('[data-open-calendar-preview]');
		if(opener){var preview=document.querySelector('[data-calendar-preview]');if(!preview)return;var desktopInput=document.querySelector('[name="panda_seasons_cover_desktop"]'),mobileInput=document.querySelector('[name="panda_seasons_cover_mobile"]'),previewUrl=new URL(opener.dataset.previewUrl,window.location.href);previewUrl.searchParams.set('cover_desktop',desktopInput?desktopInput.value:'');previewUrl.searchParams.set('cover_mobile',mobileInput?mobileInput.value:'');previewUrl.searchParams.set('cache_bust',Date.now());preview.hidden=false;preview.classList.remove('is-mobile');preview.querySelector('iframe').src=previewUrl.toString();document.body.classList.add('panda-seasons-preview-open');return;}
		var close=event.target.closest('[data-close-calendar-preview]');if(close){closeCalendarPreview(close.closest('[data-calendar-preview]'));return;}
		var size=event.target.closest('[data-calendar-preview-size]');if(size){var shell=size.closest('[data-calendar-preview]');shell.classList.toggle('is-mobile',size.dataset.calendarPreviewSize==='mobile');shell.querySelectorAll('[data-calendar-preview-size]').forEach(function(button){button.classList.toggle('button-primary',button===size);});}
	});
}());
