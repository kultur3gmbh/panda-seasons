(function () {
	'use strict';

	var composer = document.querySelector('[data-panda-seasons-composer]');
	var modal = document.querySelector('[data-door-modal]');
	var activeDoor = null;
	var activeDoorSnapshot = null;
	var returnFocus = null;
	var calendarForm = document.getElementById('post');
	var calendarDirty = false;
	var calendarSaving = false;
	function calendarStatus(message, state) {
		var status = document.querySelector('[data-calendar-save-state]');
		if (!status) return;
		status.textContent = message || '';
		status.dataset.state = state || '';
	}
	function markCalendarDirty() {
		calendarDirty = true;
		calendarStatus(adminText('unsavedChanges', 'Unsaved changes'), 'dirty');
	}
	var inspectorStateKey = 'panda-seasons-inspector:' + window.location.pathname + window.location.search;
	var inspectorState = {};
	try { inspectorState = JSON.parse(window.sessionStorage.getItem(inspectorStateKey) || '{}') || {}; } catch (error) { inspectorState = {}; }
	function saveInspectorState() {
		try { window.sessionStorage.setItem(inspectorStateKey, JSON.stringify(inspectorState)); } catch (error) { /* Session storage may be disabled. */ }
	}
	var designBox = document.getElementById('panda-seasons-design');
	var postBody = document.getElementById('post-body');
	if (designBox && postBody) postBody.insertBefore(designBox, postBody.firstChild);
	var publishPanel = document.querySelector('[data-panda-seasons-publish-panel]');
	var publishBox = document.getElementById('submitdiv');
	if (publishPanel && publishBox) {
		publishPanel.appendChild(publishBox);
		publishBox.classList.add('closed');
		var publishHeading = publishBox.querySelector('.postbox-header h2');
		if (publishHeading) publishHeading.textContent = adminText('calendarStatusTitle', 'Calendar status');
		var publishToggle = publishBox.querySelector('.handlediv');
		if (publishToggle) publishToggle.setAttribute('aria-expanded', 'false');
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
	function labelPlacement(position) {
		var parts = String(position || 'center').split('-');
		var vertical = parts.length === 1 ? 'center' : parts[0];
		var horizontal = parts.length === 1 ? 'center' : parts[1];
		return { align: {top:'start',center:'center',bottom:'end'}[vertical] || 'center', justify: {left:'start',center:'center',right:'end'}[horizontal] || 'center' };
	}

	function escapeHtml(value) {
		var element = document.createElement('div');
		element.textContent = String(value == null ? '' : value);
		return element.innerHTML;
	}
	function safeRichHtml(value) {
		var template = document.createElement('template');
		template.innerHTML = String(value == null ? '' : value);
		template.content.querySelectorAll('script,style,iframe,object,embed,form').forEach(function (node) { node.remove(); });
		template.content.querySelectorAll('*').forEach(function (node) {
			Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
				if (/^on/i.test(attribute.name) || (/^(?:href|src)$/i.test(attribute.name) && /^javascript:/i.test(attribute.value))) node.removeAttribute(attribute.name);
			});
		});
		return template.innerHTML;
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

	function uploadAttachment(file, callback) {
		if (!file || !window.pandaSeasonsAdmin || !pandaSeasonsAdmin.uploadUrl || !pandaSeasonsAdmin.uploadNonce) return;
		var data = new FormData();
		data.append('name', file.name);
		data.append('action', 'upload-attachment');
		data.append('_wpnonce', pandaSeasonsAdmin.uploadNonce);
		data.append('async-upload', file, file.name);
		fetch(pandaSeasonsAdmin.uploadUrl, { method: 'POST', credentials: 'same-origin', body: data }).then(function (response) { return response.json(); }).then(function (result) {
			if (result && result.success && result.data) callback(result.data);
		}).catch(function () {});
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
			downloadBox.querySelector('[data-door-asset-preview]').innerHTML = downloadUrl ? '<span>' + escapeHtml(downloadUrl.split('/').pop()) + '</span><span class="dashicons dashicons-media-document" aria-hidden="true"></span>' : '<span>' + escapeHtml(adminText('selectDownload', 'Select a download file')) + '</span><span class="dashicons dashicons-media-document" aria-hidden="true"></span>';
			downloadBox.querySelector('[data-remove-door-download]').hidden = !downloadUrl;
		}
		['image'].forEach(function (kind) {
			var box = modal.querySelector('[data-door-asset="' + kind + '"]');
			if (!box) return;
			var url = modal.querySelector('[data-door-field="imageUrl"]').value;
			box.querySelector('[data-door-asset-preview]').innerHTML = url ? '<img src="' + escapeHtml(safeUrl(url)) + '" alt="">' : '<span>' + escapeHtml(adminText('noImageSelected', 'No image selected')) + '</span>';
			box.querySelector('[data-remove-door-image="' + kind + '"]').hidden = !url;
			modal.querySelectorAll('[data-image-overlay-wrap]').forEach(function (overlayWrap) { overlayWrap.hidden = !url; });
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
		var layerDataField = root.querySelector('[data-layer-data]');
		var layerEditor = document.querySelector('[data-layer-editor]');
		var empty = root.querySelector('[data-editor-empty]');
		var images = { desktop: '', mobile: '' };
		var doors = [];
		var layers = [];
		var activeLayer = null;
		var activeLayerSnapshot = null;
		var layerPointerState = null;
		var view = 'desktop';
		var previewState = 'actual';
		var pointerState = null;
		var dragged = false;
		var toolbar = root.querySelector('.panda-seasons-composer__toolbar');
		var previewButton = root.querySelector('[data-open-calendar-preview]');
		var snapControl = root.querySelector('[data-snap-grid]');
		if (toolbar && previewButton) {
			var controls = document.createElement('div');
			controls.className = 'panda-seasons-composer__preview-controls';
			controls.appendChild(previewButton);
			toolbar.appendChild(controls);
		}

		try { doors = JSON.parse(dataField.value || '[]'); } catch (ignore) { doors = []; }
		try { layers = JSON.parse(layerDataField && layerDataField.value || '[]'); } catch (ignore) { layers = []; }
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
		layers.forEach(function (layer, index) {
			layer.id = layer.id || ('layer-' + Date.now() + '-' + index);
			layer.desktop = cleanPosition(layer.desktop, 10 + (index % 5) * 8, 10 + (index % 5) * 8, 'desktop');
			layer.mobile = cleanPosition(layer.mobile, 8 + (index % 3) * 10, 8 + (index % 3) * 10, 'mobile');
		});

		function writeData() {
			dataField.value = JSON.stringify(doors);
			if (layerDataField) layerDataField.value = JSON.stringify(layers.filter(function (layer) { return !layer.deleted; }));
		}

		function findLayer(key) { return layers.find(function (layer) { return String(layer.id) === String(key); }); }

		function renderLayers() {
			stage.querySelectorAll('[data-editor-layer]').forEach(function (element) { element.remove(); });
			layers.filter(function (layer) { return !layer.deleted; }).sort(function (a, b) { return number(a.z, 1) - number(b.z, 1); }).forEach(function (layer) {
				var position = layer[view];
				var element = document.createElement('div');
				element.className = 'panda-seasons-composer__layer is-' + (layer.type || 'color') + (activeLayer === layer ? ' is-selected' : '');
				element.dataset.editorLayer = layer.id;
				element.tabIndex = 0;
				element.style.left = position.x + '%'; element.style.top = position.y + '%'; element.style.width = position.w + '%'; element.style.height = position.h + '%';
				element.style.zIndex = number(layer.z, 1);
				element.style.background = layer.background || 'transparent';
				element.style.color = layer.color || '#1f2937';
				element.style.fontSize = number(layer.fontSize, 24) + 'px';
				element.style.padding = number(layer.padding, 8) + 'px';
				element.style.borderRadius = number(layer.radius, 0) + 'px';
				if ((layer.type === 'surface' || layer.type === 'image') && layer.imageUrl) element.innerHTML = '<img src="' + escapeHtml(safeUrl(layer.imageUrl)) + '" alt="" style="object-position:' + escapeHtml(String(layer.imagePosition || 'center').replace('-', ' ')) + '">';
				else if (layer.type === 'text') element.innerHTML = '<div class="panda-seasons-composer__rich-text">' + safeRichHtml(layer.text || '') + '</div>';
				element.insertAdjacentHTML('beforeend', '<span class="panda-seasons-composer__resize" data-resize-layer aria-hidden="true"></span>');
				stage.appendChild(element);
			});
		}

		function syncLayerEditor() {
			if (!layerEditor || !activeLayer) return;
			layerEditor.querySelectorAll('[data-layer-field]').forEach(function (field) {
				var value = activeLayer[field.dataset.layerField];
				field.value = value == null ? '' : value;
				if (field.dataset.rgbaEnhanced) field.dispatchEvent(new Event('change', { bubbles: false }));
			});
			layerEditor.querySelectorAll('[data-layer-field-wrap]').forEach(function (wrap) { wrap.hidden = wrap.dataset.layerFieldWrap !== activeLayer.type; });
			if (activeLayer.type === 'text') setEditorContent('panda_seasons_layer_text_editor', activeLayer.text || '');
			var imagePreview = layerEditor.querySelector('[data-layer-image-preview]');
			if (imagePreview) imagePreview.innerHTML = activeLayer.imageUrl ? '<img src="' + escapeHtml(safeUrl(activeLayer.imageUrl)) + '" alt="">' : '<span>' + escapeHtml(adminText('imageDropHint', 'Select image or drop it here')) + '</span>';
			layerEditor.querySelectorAll('[data-layer-field="background"],[data-layer-field="color"]').forEach(enhanceColorField);
		}

		function openLayerEditor(layer) {
			if (activeLayer && activeLayer !== layer) saveLayerEditor();
			if (activeDoor) { syncDoorPreview(); closeDoorModal(true); }
			activeLayer = layer;
			activeLayerSnapshot = JSON.parse(JSON.stringify(layer));
			if (modal && !modal.hidden) closeDoorModal(true);
			if (layerEditor) { layerEditor.hidden = false; syncLayerEditor(); }
			var emptyPanel = document.querySelector('[data-door-editor-empty-panel]'); if (emptyPanel) emptyPanel.hidden = true;
			render();
		}

		function closeLayerEditor(applyChanges) {
			if (activeLayer && applyChanges) {
				activeLayer.text = getEditorContent('panda_seasons_layer_text_editor');
				delete activeLayer.isNew;
			} else if (activeLayer) {
				if (activeLayer.isNew) activeLayer.deleted = true;
				else if (activeLayerSnapshot) { Object.keys(activeLayer).forEach(function (key) { delete activeLayer[key]; }); Object.assign(activeLayer, activeLayerSnapshot); }
			}
			activeLayer = null; activeLayerSnapshot = null;
			if (layerEditor) layerEditor.hidden = true;
			var emptyPanel = document.querySelector('[data-door-editor-empty-panel]'); if (emptyPanel) emptyPanel.hidden = false;
			render();
		}

		function saveLayerEditor() {
			if (!activeLayer) return;
			activeLayer.text = getEditorContent('panda_seasons_layer_text_editor');
			delete activeLayer.isNew;
			activeLayerSnapshot = JSON.parse(JSON.stringify(activeLayer));
			render();
			syncLayerEditor();
			markCalendarDirty();
			var status = layerEditor && layerEditor.querySelector('[data-layer-save-status]');
			if (status) { status.textContent = adminText('elementApplied', 'Changes applied. Save the calendar to store them permanently.'); window.setTimeout(function () { status.textContent = ''; }, 3200); }
		}

		function addLayer(type, position) {
			var count = layers.filter(function (layer) { return !layer.deleted; }).length;
			var layer = { id: 'layer-' + Date.now(), type: type, isNew: true, text: type === 'text' ? adminText('newTextArea', 'Text area') : '', imageId: 0, imageUrl: '', imagePosition: 'center', background: type === 'surface' ? 'rgba(255, 255, 255, 0.8)' : 'rgba(255, 255, 255, 0)', color: 'rgba(31, 41, 55, 1)', fontSize: 24, padding: 8, z: count + 1, url: '', radius: 0, desktop: cleanPosition(null, 10 + (count % 5) * 5, 10 + (count % 5) * 5, 'desktop'), mobile: cleanPosition(null, 8 + (count % 3) * 7, 8 + (count % 3) * 7, 'mobile') };
			if (position) { layer[view].x = clamp(position.x - layer[view].w / 2, 0, 100 - layer[view].w); layer[view].y = clamp(position.y - layer[view].h / 2, 0, 100 - layer[view].h); }
			layers.push(layer); openLayerEditor(layer);
		}

		function selectLayerImage() {
			if (!activeLayer || !window.wp || !wp.media) return;
			var frame = wp.media({ title: adminText('selectImage', 'Select image'), library: { type: 'image' }, multiple: false });
			frame.on('select', function () { var attachment = frame.state().get('selection').first().toJSON(); activeLayer.imageId = attachment.id || 0; activeLayer.imageUrl = attachment.url || ''; syncLayerEditor(); render(); markCalendarDirty(); });
			frame.open();
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
				var backgroundInput = document.querySelector('[data-cover-background="' + view + '"]');
				stage.style.setProperty('--ps-editor-background', backgroundInput ? backgroundInput.value : 'rgba(23,32,51,1)');
			root.setAttribute('data-view', view);
		}

		function render() {
			stage.querySelectorAll('[data-editor-door]').forEach(function (element) { element.remove(); });
			renderLayers();
			currentDoors().forEach(function (door) {
				var position = door[view];
				var element = document.createElement('div');
				var now = new Date(), unlock = door.unlockDate ? new Date(door.unlockDate + 'T' + (door.unlockTime || '00:00')) : null, sameDay = unlock && unlock.getFullYear() === now.getFullYear() && unlock.getMonth() === now.getMonth() && unlock.getDate() === now.getDate();
				var actualOpen = door.openingMode === 'manual-day' ? false : (door.openingMode === 'auto-day' ? Boolean(sameDay && unlock <= now) : (!unlock || unlock <= now));
				var isOpen = previewState === 'open' || (previewState === 'actual' && actualOpen);
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
					var labelPadding = 4;
				var labelSize = labelWidth || 60;
					var labelShape = isOpen ? door.openLabelShape : door.closedLabelShape;
					var roundLabel = labelShape === 'round' || labelShape === 'square';
					var labelWidthValue = labelWidthMode === 'percent' ? labelSize + '%' : labelWidthMode === 'px' ? labelSize + 'px' : 'auto';
				element.style.setProperty('--ps-door-label-width', labelWidthValue);
				element.style.setProperty('--ps-door-label-height', roundLabel ? labelWidthValue : 'auto');
				element.style.setProperty('--ps-door-label-aspect', roundLabel ? '1' : 'auto');
					element.style.setProperty('--ps-door-label-radius', labelShape === 'round' ? '999px' : labelShape === 'rounded' ? '8px' : '0');
				element.style.setProperty('--ps-door-label-padding', labelPadding + 'px');
				var placement = labelPlacement(isOpen ? door.openLabelPosition : door.closedLabelPosition);
				element.style.setProperty('--ps-door-label-align', placement.align);
				element.style.setProperty('--ps-door-label-justify', placement.justify);
					var stateImage = door.imageUrl;
				var stateImageOverlay = isOpen ? door.openImageOverlay : door.closedImageOverlay;
				element.style.setProperty('--ps-door-image', stateImage ? 'url("' + stateImage.replace(/"/g, '\\"') + '")' : 'none');
				element.style.setProperty('--ps-door-image-overlay', stateImage ? (stateImageOverlay || 'rgba(0,0,0,0)') : 'rgba(0,0,0,0)');
					element.style.setProperty('--ps-door-image-position', String(door.imagePosition || 'center').replace('-', ' '));
				var stateLabel = isOpen ? (door.openLabel || door.number) : (door.closedLabel || door.number);
				var labelVisible = isOpen ? door.openLabelVisible !== false : door.closedLabelVisible !== false;
				element.innerHTML = '<span class="panda-seasons-composer__door-actions"><button type="button" data-duplicate-door aria-label="' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.duplicateDoor ? pandaSeasonsAdmin.duplicateDoor : 'Duplicate door') + '" title="' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.duplicateDoor ? pandaSeasonsAdmin.duplicateDoor : 'Duplicate door') + '"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><button type="button" data-delete-stage-door aria-label="' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.deleteDoor ? pandaSeasonsAdmin.deleteDoor : 'Delete door') + '" title="' + escapeHtml(window.pandaSeasonsAdmin && pandaSeasonsAdmin.deleteDoor ? pandaSeasonsAdmin.deleteDoor : 'Delete door') + '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></span>' + (labelVisible ? '<span class="panda-seasons-composer__door-number">' + escapeHtml(stateLabel) + '</span>' : '') + '<span class="panda-seasons-composer__resize" data-resize-door aria-hidden="true"></span>';
				stage.appendChild(element);
			});
			empty.hidden = currentDoors().length > 0 || layers.some(function (layer) { return !layer.deleted; });
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
					contentHeading: '',
				number: doorNumber,
				unlockDate: '',
				unlockTime: '',
				openingMode: (document.querySelector('[name="panda_seasons_default_opening_mode"]') || {}).value || 'auto-permanent',
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
				couponId: 0, ctaMode: 'default', ctaEnabled: false, ctaTitle: '', ctaText: '', ctaType: 'link', ctaLabel: '', ctaUrl: '', ctaShortcode: '',
				eventId: 0,
				closedLabel: '',
				openLabel: '',
				closedBg: root.dataset.defaultClosed || 'rgba(99, 99, 99, 0.2)',
				closedColor: root.dataset.defaultClosedColor || 'rgba(255, 255, 255, 1)', closedLabelVisible: root.dataset.defaultClosedLabelVisible !== '0', closedFont: root.dataset.defaultLabelFont || 'inherit', closedFontSize: +(root.dataset.defaultLabelFontSize || 20), closedFontWeight: root.dataset.defaultLabelFontWeight || '700', closedTextAlign: 'center', closedBorderColor: root.dataset.defaultClosedBorderColor || 'rgba(255, 255, 255, 0.9)', closedBorderWidth: +(root.dataset.defaultClosedBorderWidth || 2), closedBorderStyle: root.dataset.defaultBorderStyle || 'dashed', closedLabelBg: root.dataset.defaultClosedLabelBg || 'rgba(10, 18, 30, 0.76)', closedLabelBorderColor: root.dataset.defaultClosedLabelBorderColor || 'rgba(255, 255, 255, 1)', closedLabelBorderWidth: +(root.dataset.defaultClosedLabelBorderWidth || 1), closedLabelShape: (document.querySelector('[name="panda_seasons_default_closed_label_shape"]') || {}).value || 'rounded', closedLabelPosition: (document.querySelector('[name="panda_seasons_default_closed_label_position"]') || {}).value || 'center', closedLabelWidthMode: root.dataset.defaultLabelWidthMode || 'content', closedLabelWidth: +(root.dataset.defaultLabelWidth || 60), closedLabelPadding: 4, closedImageOverlay: root.dataset.defaultClosedImageOverlay || 'rgba(0, 0, 0, 0)',
				openBg: root.dataset.defaultOpen || 'rgba(51, 79, 232, 1)',
				openColor: root.dataset.defaultOpenColor || 'rgba(255, 255, 255, 1)', openLabelVisible: root.dataset.defaultOpenLabelVisible !== '0', openFont: root.dataset.defaultLabelFont || 'inherit', openFontSize: +(root.dataset.defaultLabelFontSize || 20), openFontWeight: root.dataset.defaultLabelFontWeight || '700', openTextAlign: 'center', openBorderColor: root.dataset.defaultOpenBorderColor || 'rgba(255, 255, 255, 0.9)', openBorderWidth: +(root.dataset.defaultOpenBorderWidth || 2), openBorderStyle: root.dataset.defaultBorderStyle || 'dashed', openLabelBg: root.dataset.defaultOpenLabelBg || 'rgba(10, 18, 30, 0.76)', openLabelBorderColor: root.dataset.defaultOpenLabelBorderColor || 'rgba(255, 255, 255, 1)', openLabelBorderWidth: +(root.dataset.defaultOpenLabelBorderWidth || 1), openLabelShape: (document.querySelector('[name="panda_seasons_default_open_label_shape"]') || {}).value || 'rounded', openLabelPosition: (document.querySelector('[name="panda_seasons_default_open_label_position"]') || {}).value || 'center', openLabelWidthMode: root.dataset.defaultLabelWidthMode || 'content', openLabelWidth: +(root.dataset.defaultLabelWidth || 60), openLabelPadding: 4, openImageOverlay: root.dataset.defaultOpenImageOverlay || 'rgba(0, 0, 0, 0)',
				shape: root.dataset.defaultShape || 'rounded',
				openEffect: 'inherit',
					imageId: 0,
					imageUrl: '',
					imagePosition: 'center',
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

		function snap(value, axis) {
			var snapInput = root.querySelector('[data-snap-grid]');
			if (!snapInput || !snapInput.checked) return Math.round(value * 10) / 10;
			var rectangle = stage.getBoundingClientRect();
			var dimension = axis === 'y' ? rectangle.height : rectangle.width;
			var step = dimension > 0 ? 1000 / dimension : 1;
			return Math.round(value / step) * step;
		}

		function alignAllToGrid() {
			var rectangle = stage.getBoundingClientRect();
			var stepX = rectangle.width > 0 ? 1000 / rectangle.width : 1;
			var stepY = rectangle.height > 0 ? 1000 / rectangle.height : 1;
			function alignPosition(position) {
				var right = Math.round((position.x + position.w) / stepX) * stepX;
				var bottom = Math.round((position.y + position.h) / stepY) * stepY;
				position.x = clamp(Math.round(position.x / stepX) * stepX, 0, 96);
				position.y = clamp(Math.round(position.y / stepY) * stepY, 0, 96);
				position.w = clamp(right - position.x, 4, 100 - position.x);
				position.h = clamp(bottom - position.y, 4, 100 - position.y);
			}
			currentDoors().forEach(function (door) { alignPosition(door[view]); });
			layers.filter(function (layer) { return !layer.deleted; }).forEach(function (layer) { alignPosition(layer[view]); });
			render();
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
				position.w = clamp(snap(pointerState.start.w + deltaX, 'x'), 4, 100 - position.x);
				position.h = clamp(snap(pointerState.start.h + deltaY, 'y'), 4, 100 - position.y);
			} else {
				position.x = clamp(snap(pointerState.start.x + deltaX, 'x'), 0, 100 - position.w);
				position.y = clamp(snap(pointerState.start.y + deltaY, 'y'), 0, 100 - position.h);
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
			var element = event.target.closest('[data-editor-layer]');
			if (!element || event.button !== 0) return;
			var layer = findLayer(element.dataset.editorLayer);
			if (!layer) return;
			var rectangle = stage.getBoundingClientRect();
			layerPointerState = { layer: layer, element: element, resize: Boolean(event.target.closest('[data-resize-layer]')), startX: event.clientX, startY: event.clientY, start: Object.assign({}, layer[view]), width: rectangle.width, height: rectangle.height };
			dragged = false; element.setPointerCapture(event.pointerId); event.preventDefault();
		});
		stage.addEventListener('pointermove', function (event) {
			if (!layerPointerState) return;
			var deltaX = (event.clientX - layerPointerState.startX) / layerPointerState.width * 100, deltaY = (event.clientY - layerPointerState.startY) / layerPointerState.height * 100;
			if (Math.abs(deltaX) > .2 || Math.abs(deltaY) > .2) dragged = true;
			var position = layerPointerState.layer[view];
			if (layerPointerState.resize) { position.w = clamp(snap(layerPointerState.start.w + deltaX, 'x'), 4, 100 - position.x); position.h = clamp(snap(layerPointerState.start.h + deltaY, 'y'), 4, 100 - position.y); }
			else { position.x = clamp(snap(layerPointerState.start.x + deltaX, 'x'), 0, 100 - position.w); position.y = clamp(snap(layerPointerState.start.y + deltaY, 'y'), 0, 100 - position.h); }
			layerPointerState.element.style.left = position.x + '%'; layerPointerState.element.style.top = position.y + '%'; layerPointerState.element.style.width = position.w + '%'; layerPointerState.element.style.height = position.h + '%'; writeData();
		});
		stage.addEventListener('pointerup', function () { layerPointerState = null; });
		stage.addEventListener('pointercancel', function () { layerPointerState = null; });
		stage.addEventListener('click', function (event) { var element = event.target.closest('[data-editor-layer]'); if (!element || dragged || event.target.closest('[data-resize-layer]')) return; var layer = findLayer(element.dataset.editorLayer); if (layer) openLayerEditor(layer); });

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
			if (event.target.closest('[data-align-grid]')) { alignAllToGrid(); return; }
			var layerButton = event.target.closest('[data-add-layer]');
			if (layerButton) { addLayer(layerButton.dataset.addLayer); return; }
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

		if (layerEditor) {
			var layerTextarea = document.getElementById('panda_seasons_layer_text_editor');
			if (layerTextarea) layerTextarea.addEventListener('input', function () { if (activeLayer && activeLayer.type === 'text') { activeLayer.text = layerTextarea.value; render(); } });
			(function bindLayerEditor(attempt) {
				var editor = window.tinymce && window.tinymce.get('panda_seasons_layer_text_editor');
				if (!editor) { if (attempt < 20) window.setTimeout(function () { bindLayerEditor(attempt + 1); }, 250); return; }
				editor.on('input change keyup undo redo', function () { if (activeLayer && activeLayer.type === 'text') { activeLayer.text = editor.getContent(); render(); markCalendarDirty(); } });
			}(0));
			layerEditor.addEventListener('input', function (event) { var field = event.target.closest('[data-layer-field]'); if (!field || !activeLayer) return; var name = field.dataset.layerField; activeLayer[name] = ['imageId','fontSize','padding','z','radius'].indexOf(name) !== -1 ? Math.max(0, parseInt(field.value, 10) || 0) : field.value; render(); });
			layerEditor.addEventListener('change', function (event) { var field = event.target.closest('[data-layer-field]'); if (!field || !activeLayer) return; activeLayer[field.dataset.layerField] = field.value; render(); });
			layerEditor.addEventListener('click', function (event) {
				if (event.target.closest('[data-select-layer-image]')) { selectLayerImage(); return; }
				if (event.target.closest('[data-delete-layer]') && activeLayer) { activeLayer.deleted = true; closeLayerEditor(true); return; }
				if (event.target.closest('[data-apply-layer-editor]')) { saveLayerEditor(); return; }
				if (event.target.closest('[data-close-layer-editor]')) closeLayerEditor(false);
			});
		}

		var addButton = root.querySelector('[data-add-door]');
		if (addButton) {
			addButton.draggable = true;
			addButton.addEventListener('dragstart', function (event) { event.dataTransfer.setData('text/panda-seasons-door', 'new'); event.dataTransfer.effectAllowed = 'copy'; });
			stage.addEventListener('dragover', function (event) { if (Array.prototype.indexOf.call(event.dataTransfer.types, 'text/panda-seasons-door') !== -1) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; } });
			stage.addEventListener('drop', function (event) { if (!event.dataTransfer.getData('text/panda-seasons-door')) return; event.preventDefault(); var rect = stage.getBoundingClientRect(); addDoor({ x: (event.clientX - rect.left) / rect.width * 100, y: (event.clientY - rect.top) / rect.height * 100 }); });
		}
		root.querySelectorAll('[data-add-layer]').forEach(function (button) {
			button.draggable = true;
			button.addEventListener('dragstart', function (event) { event.dataTransfer.setData('text/panda-seasons-layer', button.dataset.addLayer); event.dataTransfer.effectAllowed = 'copy'; });
		});
		stage.addEventListener('dragover', function (event) { if (Array.prototype.indexOf.call(event.dataTransfer.types, 'text/panda-seasons-layer') !== -1) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; } });
		stage.addEventListener('drop', function (event) { var type = event.dataTransfer.getData('text/panda-seasons-layer'); if (!type) return; event.preventDefault(); var rect = stage.getBoundingClientRect(); addLayer(type, { x: (event.clientX - rect.left) / rect.width * 100, y: (event.clientY - rect.top) / rect.height * 100 }); });

		document.querySelectorAll('[data-editor-ratio]').forEach(function (input) {
			input.addEventListener('input', function () { if (input.dataset.editorRatio === view) setStageAppearance(); });
		});

		root.updateImage = function (targetView, url) {
			images[targetView] = url || '';
			setStageAppearance();
		};
		root.getDoors = function () { return doors; };
		root.applySavedDoorIds = function (ids) {
			var index = 0;
			doors.forEach(function (door) {
				if (door.deleted) return;
				if (ids[index]) { door.id = parseInt(ids[index], 10) || door.id; door.isDraft = false; }
				index++;
			});
			render();
		};
		root.commitSavedState = function () { if (activeLayer) activeLayerSnapshot = JSON.parse(JSON.stringify(activeLayer)); };
		root.renderDoors = render;
		root.prepareForSave = function () { if (activeLayer) saveLayerEditor(); render(); };
		root.updateBackground = setStageAppearance;
		root.setPreviewState = function(state){previewState=state;root.dataset.previewState=state;root.querySelectorAll('button[data-preview-state]').forEach(function(button){button.classList.toggle('button-primary',button.dataset.previewState===state);});render();};
		if (snapControl) { stage.classList.toggle('has-grid', snapControl.checked); snapControl.addEventListener('change', function () { stage.classList.toggle('has-grid', snapControl.checked); }); }
		setStageAppearance();
		render();
	}

	function openDoorModal(door, trigger) {
		if (!modal) return;
		if (activeDoor && activeDoor !== door) syncDoorPreview();
		var layerEditor = document.querySelector('[data-layer-editor]'); if (layerEditor) layerEditor.hidden = true;
		activeDoor = door;
		activeDoorSnapshot = JSON.parse(JSON.stringify(door));
		returnFocus = trigger || document.activeElement;
		var modalTitle = modal.querySelector('#panda-seasons-door-modal-title');
		if (modalTitle) modalTitle.textContent = adminText('editDoorTitle', 'Edit door %1$s – %2$s').replace('%1$s', door.number || '').replace('%2$s', door.title || door.closedLabel || adminText('untitledDoor', 'Door %d').replace('%d', door.number || ''));
		var colorDefaults = { closedBg: '#1f2937', closedColor: 'rgba(255, 255, 255, 1)', openBg: '#334fe8', openColor: 'rgba(255, 255, 255, 1)' };
		modal.querySelectorAll('[data-door-field]').forEach(function (field) {
			var value = door[field.dataset.doorField];
			if ((value == null || value === '') && field.type === 'color') value = colorDefaults[field.dataset.doorField] || '#ffffff';
			if (field.type === 'checkbox') field.checked = Boolean(value);
			else field.value = value == null ? '' : value;
		});
		setEditorContent('panda_seasons_door_content_editor', door.content || '');
		setEditorContent('panda_seasons_door_cta_editor', door.ctaText || '');
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
		modal.querySelectorAll('[data-door-field$="Bg"],[data-door-field$="Color"],[data-door-field$="Overlay"]').forEach(function(field){enhanceColorField(field);field.dispatchEvent(new Event('change',{bubbles:true}));});
		modal.querySelectorAll('[data-door-field$="LabelWidthMode"]').forEach(updateLabelWidthVisibility);
		updateContentTypeVisibility();
		updateCtaVisibility();
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
		['number', 'contentType', 'contentHeading', 'unlockDate', 'unlockTime', 'openingMode', 'title', 'mediaId', 'mediaUrl', 'mediaType', 'downloadId', 'downloadUrl', 'downloadLabel', 'buttonLabel', 'buttonUrl', 'ctaMode', 'ctaEnabled', 'ctaTitle', 'ctaType', 'ctaLabel', 'ctaUrl', 'ctaShortcode', 'eventId', 'closedLabel', 'openLabel', 'closedBg', 'closedColor', 'openBg', 'openColor', 'closedLabelVisible', 'openLabelVisible', 'closedFont', 'openFont', 'closedFontSize', 'openFontSize', 'closedFontWeight', 'openFontWeight', 'closedTextAlign', 'openTextAlign', 'closedBorderColor', 'openBorderColor', 'closedBorderWidth', 'openBorderWidth', 'closedBorderStyle', 'openBorderStyle', 'closedLabelBg', 'openLabelBg', 'closedLabelBorderColor', 'openLabelBorderColor', 'closedLabelBorderWidth', 'openLabelBorderWidth', 'closedLabelShape', 'openLabelShape', 'closedLabelPosition', 'openLabelPosition', 'closedLabelWidthMode', 'openLabelWidthMode', 'closedLabelWidth', 'openLabelWidth', 'shape', 'openEffect', 'imageId', 'imageUrl', 'imagePosition', 'closedImageOverlay', 'openImageOverlay'].forEach(function (name) {
			var field = modal.querySelector('[data-door-field="' + name + '"]');
			if (!field) return;
			activeDoor[name] = field.type === 'checkbox' ? field.checked : (name === 'number' ? Math.max(1, parseInt(field.value, 10) || 1) : (/Id$/.test(name) ? Math.max(0, parseInt(field.value, 10) || 0) : field.value));
		});
		activeDoor.content = getEditorContent('panda_seasons_door_content_editor');
		activeDoor.ctaText = getEditorContent('panda_seasons_door_cta_editor');
		activeDoor.emailContent = getEditorContent('panda_seasons_door_email_editor');
		activeDoor.quizQuestions = collectQuizQuestions();
		if (composer && composer.renderDoors) composer.renderDoors();
		var openPreview = modal.querySelector('[data-door-preview]:not([hidden])');
		if (openPreview) previewDoor(false);
	}

	function arrangeDoorEditorSections() {
		if (!modal) return;
		var fields = modal.querySelector('.panda-seasons-door-modal__fields');
		if (!fields) return;
		var findSection = function (selector) { var field=modal.querySelector(selector); return field && field.closest('details, .panda-seasons-content-type'); };
		var ordered = [findSection('[data-door-section="basics"]'), findSection('[data-door-section="appearance"]'), findSection('[data-door-section="content-type"]'), findSection('[data-content-type-panel="general video"]'), findSection('[data-content-type-panel="product"]'), findSection('[data-content-type-panel="quiz"]'), findSection('[data-door-section="button"]'), findSection('[data-door-field="ctaEnabled"]'), findSection('[data-door-field="emailSubject"]')];
		ordered.forEach(function (section) { if (section) fields.appendChild(section); });
	}
	arrangeDoorEditorSections();
	if (modal) {
		modal.querySelectorAll('details[data-door-section]').forEach(function (section) {
			var name = section.dataset.doorSection;
			section.open = Object.prototype.hasOwnProperty.call(inspectorState, name) ? Boolean(inspectorState[name]) : (name !== 'button' && section.open);
			section.addEventListener('toggle', function () { inspectorState[name] = section.open; saveInspectorState(); });
		});
		if (inspectorState.doorStateTab) {
			var rememberedStateTab = modal.querySelector('[data-door-state-tab="' + inspectorState.doorStateTab + '"]');
			if (rememberedStateTab) {
				modal.querySelectorAll('[data-door-state-tab]').forEach(function (button) { button.classList.toggle('button-primary', button === rememberedStateTab); button.setAttribute('aria-selected', button === rememberedStateTab ? 'true' : 'false'); });
				modal.querySelectorAll('[data-door-state-panel]').forEach(function (panel) { panel.hidden = panel.dataset.doorStatePanel !== inspectorState.doorStateTab; });
			}
		}
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
			setDoorAttachment(kind, attachment);
		});
		frame.open();
	}

	function setDoorAttachment(kind, attachment) {
		if (!modal || !attachment) return;
		if (kind === 'media') {
			modal.querySelector('[data-door-field="mediaId"]').value = attachment.id || 0;
			modal.querySelector('[data-door-field="mediaUrl"]').value = attachment.url || '';
			modal.querySelector('[data-door-field="mediaType"]').value = attachment.type || String(attachment.mime || '').split('/')[0] || '';
		} else {
			modal.querySelector('[data-door-field="downloadId"]').value = attachment.id || 0;
			modal.querySelector('[data-door-field="downloadUrl"]').value = attachment.url || '';
			var label = modal.querySelector('[data-door-field="downloadLabel"]');
			if (label && !label.value) label.value = attachment.title || attachment.filename || '';
		}
		updateDoorAssets(); syncDoorPreview(); markCalendarDirty();
	}

	function saveActiveDoor(door, status) {
		if (!door || !window.pandaSeasonsAdmin || !pandaSeasonsAdmin.ajaxUrl || !pandaSeasonsAdmin.saveDoorNonce || !pandaSeasonsAdmin.calendarId) return;
		if (status) status.textContent = adminText('savingDoor', 'Saving door…');
		var data = new FormData();
		data.append('action', 'panda_seasons_save_editor_door');
		data.append('nonce', pandaSeasonsAdmin.saveDoorNonce);
		data.append('calendar_id', pandaSeasonsAdmin.calendarId);
		data.append('door', JSON.stringify(door));
		fetch(pandaSeasonsAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (response) { return response.json(); })
			.then(function (result) {
				if (!result || !result.success || !result.data) throw new Error(result && result.data && result.data.message ? result.data.message : adminText('doorSaveFailed', 'The door could not be saved. Please try again.'));
				door.id = parseInt(result.data.doorId, 10) || door.id;
				door.mediaId = parseInt(result.data.mediaId, 10) || 0;
				door.mediaUrl = result.data.mediaUrl || '';
				door.mediaType = result.data.mediaType || '';
				door.isDraft = false;
				if (activeDoor === door) {
					var mediaId = modal.querySelector('[data-door-field="mediaId"]');
					var mediaUrl = modal.querySelector('[data-door-field="mediaUrl"]');
					var mediaType = modal.querySelector('[data-door-field="mediaType"]');
					if (mediaId) mediaId.value = door.mediaId;
					if (mediaUrl) mediaUrl.value = door.mediaUrl;
					if (mediaType) mediaType.value = door.mediaType;
					updateDoorAssets();
					activeDoorSnapshot = JSON.parse(JSON.stringify(door));
				}
				if (composer && composer.renderDoors) composer.renderDoors();
				if (status) { status.textContent = result.data.message || adminText('doorSaved', 'Door saved.'); window.setTimeout(function () { status.textContent = ''; }, 2200); }
			})
			.catch(function (error) { if (status) status.textContent = error && error.message && error.message !== 'save_failed' ? error.message : adminText('doorSaveFailed', 'The door could not be saved. Please try again.'); });
	}

	function clearDoorAttachment(kind) {
		var prefix = kind === 'media' ? 'media' : 'download';
		modal.querySelector('[data-door-field="' + prefix + 'Id"]').value = '';
		modal.querySelector('[data-door-field="' + prefix + 'Url"]').value = '';
		if (kind === 'media') modal.querySelector('[data-door-field="mediaType"]').value = '';
		updateDoorAssets();
		syncDoorPreview();
		markCalendarDirty();
	}

	function selectDoorStateImage(kind) {
		var frame = wp.media({ title: window.pandaSeasonsAdmin && pandaSeasonsAdmin.selectImage ? pandaSeasonsAdmin.selectImage : 'Select image', library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			modal.querySelector('[data-door-field="' + kind + 'Id"]').value = attachment.id || 0;
			modal.querySelector('[data-door-field="' + kind + 'Url"]').value = attachment.url || '';
			updateDoorAssets();
			syncDoorPreview();
			markCalendarDirty();
		});
		frame.open();
	}

	function clearDoorStateImage(kind) {
		modal.querySelector('[data-door-field="' + kind + 'Id"]').value = '';
		modal.querySelector('[data-door-field="' + kind + 'Url"]').value = '';
		updateDoorAssets();
		syncDoorPreview();
		markCalendarDirty();
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
		if (event.target.closest('[data-apply-door-appearance]') && activeDoor && composer) {
			var applyDialog = document.querySelector('[data-apply-door-dialog]');
			if (applyDialog) applyDialog.hidden = false;
			return;
		}
		if (event.target.closest('[data-close-apply-door-dialog]')) { var closeApplyDialog=document.querySelector('[data-apply-door-dialog]'); if(closeApplyDialog)closeApplyDialog.hidden=true; return; }
		if (event.target.closest('[data-confirm-apply-door-settings]') && activeDoor && composer) {
			var dialog=document.querySelector('[data-apply-door-dialog]'), selected=[];
			if(dialog)dialog.querySelectorAll('input[type="checkbox"]:checked').forEach(function(input){selected.push(input.value);});
			if(!selected.length)return;
			syncDoorPreview();
			var groups={
				appearance:['shape','openEffect','imagePosition','closedBg','openBg','closedColor','openColor','closedLabelVisible','openLabelVisible','closedFont','openFont','closedFontSize','openFontSize','closedFontWeight','openFontWeight','closedTextAlign','openTextAlign','closedBorderColor','openBorderColor','closedBorderWidth','openBorderWidth','closedBorderStyle','openBorderStyle','closedLabelBg','openLabelBg','closedLabelBorderColor','openLabelBorderColor','closedLabelBorderWidth','openLabelBorderWidth','closedLabelShape','openLabelShape','closedLabelPosition','openLabelPosition','closedLabelWidthMode','openLabelWidthMode','closedLabelWidth','openLabelWidth','closedImageOverlay','openImageOverlay'],
				related:['buttonLabel','buttonUrl','eventId'],
				cta:['ctaMode','ctaEnabled','ctaTitle','ctaText','ctaType','ctaLabel','ctaUrl','ctaShortcode']
			};
			composer.getDoors().forEach(function(door){if(door.deleted||door===activeDoor)return;selected.forEach(function(group){(groups[group]||[]).forEach(function(prop){door[prop]=activeDoor[prop];});});});
			if(selected.indexOf('appearance')!==-1){
				var defaultLinks={shape:'panda_seasons_default_shape',openEffect:'panda_seasons_open_effect',closedBg:'panda_seasons_default_closed_background',openBg:'panda_seasons_default_open_background',closedLabelVisible:'panda_seasons_default_closed_label_visible',openLabelVisible:'panda_seasons_default_open_label_visible',closedLabelShape:'panda_seasons_default_closed_label_shape',openLabelShape:'panda_seasons_default_open_label_shape',closedLabelPosition:'panda_seasons_default_closed_label_position',openLabelPosition:'panda_seasons_default_open_label_position',closedFont:'panda_seasons_default_label_font',closedFontSize:'panda_seasons_default_label_font_size',closedFontWeight:'panda_seasons_default_label_font_weight',closedLabelWidthMode:'panda_seasons_default_label_width_mode',closedLabelWidth:'panda_seasons_default_label_width',closedBorderStyle:'panda_seasons_default_border_style',closedColor:'panda_seasons_default_closed_text_color',openColor:'panda_seasons_default_open_text_color',closedBorderColor:'panda_seasons_default_closed_border_color',openBorderColor:'panda_seasons_default_open_border_color',closedBorderWidth:'panda_seasons_default_closed_border_width',openBorderWidth:'panda_seasons_default_open_border_width',closedLabelBg:'panda_seasons_default_closed_label_background',openLabelBg:'panda_seasons_default_open_label_background',closedLabelBorderColor:'panda_seasons_default_closed_label_border_color',openLabelBorderColor:'panda_seasons_default_open_label_border_color',closedLabelBorderWidth:'panda_seasons_default_closed_label_border_width',openLabelBorderWidth:'panda_seasons_default_open_label_border_width',closedImageOverlay:'panda_seasons_default_closed_image_overlay',openImageOverlay:'panda_seasons_default_open_image_overlay'};
				Object.keys(defaultLinks).forEach(function(prop){var input=document.querySelector('[name="'+defaultLinks[prop]+'"]');if(!input||(prop==='openEffect'&&activeDoor[prop]==='inherit'))return;if(input.type==='checkbox')input.checked=Boolean(activeDoor[prop]);else input.value=activeDoor[prop];input.dispatchEvent(new Event('change',{bubbles:true}));});
			}
			composer.renderDoors(); if(dialog)dialog.hidden=true; return;
		}
		var refreshImages = event.target.closest('[data-refresh-calendar-images]');
		if (refreshImages) {
			var stamp = Date.now();
			document.querySelectorAll('[data-media-field]').forEach(function (field) {
				var hidden = field.querySelector('input[type="hidden"]'), image = field.querySelector('.panda-seasons-media-preview img');
				if (!hidden || !hidden.value || !image) return;
				var refreshed = new URL(image.src, window.location.href); refreshed.searchParams.set('ps_refresh', stamp); image.src = refreshed.toString();
				var targetView = hidden.name.indexOf('mobile') !== -1 ? 'mobile' : 'desktop'; if (composer && composer.updateImage) composer.updateImage(targetView, refreshed.toString());
			});
			refreshImages.classList.add('button-primary'); window.setTimeout(function () { refreshImages.classList.remove('button-primary'); }, 900); return;
		}
		var copyShortcode = event.target.closest('[data-copy-shortcode]');
		if (copyShortcode) {
			var copyInput = copyShortcode.parentNode.querySelector('input');
			if (copyInput && navigator.clipboard) navigator.clipboard.writeText(copyInput.value);
			else if (copyInput) { copyInput.select(); document.execCommand('copy'); }
			return;
		}
		var stateTab = event.target.closest('[data-door-state-tab]');
		if (stateTab) { modal.querySelectorAll('[data-door-state-tab]').forEach(function (button) { button.classList.toggle('button-primary', button === stateTab); button.setAttribute('aria-selected',button===stateTab?'true':'false'); }); modal.querySelectorAll('[data-door-state-panel]').forEach(function (panel) { panel.hidden = panel.dataset.doorStatePanel !== stateTab.dataset.doorStateTab; }); inspectorState.doorStateTab=stateTab.dataset.doorStateTab;saveInspectorState();if(composer&&composer.setPreviewState)composer.setPreviewState(stateTab.dataset.doorStateTab); return; }
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
					field.querySelector('.panda-seasons-media-preview').innerHTML = '<span>' + escapeHtml(adminText('imageDropHint', 'Select image or drop it here')) + '</span>';
				remove.hidden = true;
				if (composer && composer.updateImage) composer.updateImage(targetView, '');
				markCalendarDirty();
				return;
			}
			var frame = wp.media({ title: adminText('selectImage', 'Select image'), library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				hidden.value = attachment.id;
				field.querySelector('.panda-seasons-media-preview').innerHTML = '<img src="' + previewUrl + '" alt="">';
				field.querySelector('[data-remove-media]').hidden = false;
				if (composer && composer.updateImage) composer.updateImage(targetView, attachment.url);
				markCalendarDirty();
			});
			frame.open();
			return;
		}

		if (event.target.closest('[data-close-door-modal]')) closeDoorModal();

		if (event.target.closest('[data-save-door]') && activeDoor) {
			modal.querySelectorAll('[data-door-field]').forEach(function (field) {
				var name = field.dataset.doorField;
				if (field.type === 'checkbox') activeDoor[name] = field.checked;
				else activeDoor[name] = name === 'number' ? Math.max(1, parseInt(field.value, 10) || 1) : (/Id$/.test(name) ? Math.max(0, parseInt(field.value, 10) || 0) : field.value);
			});
			activeDoor.content = getEditorContent('panda_seasons_door_content_editor');
			activeDoor.ctaText = getEditorContent('panda_seasons_door_cta_editor');
			activeDoor.emailContent = getEditorContent('panda_seasons_door_email_editor');
			activeDoor.quizQuestions = collectQuizQuestions();
			activeDoor.isDraft = false;
			if (composer && composer.renderDoors) composer.renderDoors();
			var status = modal.querySelector('[data-door-save-status]');
			activeDoorSnapshot = JSON.parse(JSON.stringify(activeDoor));
			markCalendarDirty();
			if (status) { status.textContent = adminText('elementApplied', 'Changes applied. Save the calendar to store them permanently.'); window.setTimeout(function () { status.textContent = ''; }, 3200); }
		}

		if (event.target.closest('[data-delete-door]') && activeDoor) {
			var warning = window.pandaSeasonsAdmin && pandaSeasonsAdmin.deleteConfirm ? pandaSeasonsAdmin.deleteConfirm : 'Move this door to the trash?';
			if (!window.confirm(warning)) return;
			activeDoor.deleted = true;
			if (composer && composer.renderDoors) composer.renderDoors();
			closeDoorModal(true);
		}

	});

	document.addEventListener('keydown', function (event) {
		if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-select-media],[data-select-door-media],[data-select-door-download],[data-select-layer-image]')) { event.preventDefault(); event.target.click(); }
	});
	document.querySelectorAll('[data-media-field] .panda-seasons-media-preview').forEach(function (dropzone) {
		dropzone.addEventListener('dragover', function (event) { if (event.dataTransfer && event.dataTransfer.files.length) { event.preventDefault(); dropzone.classList.add('is-dragover'); } });
		dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('is-dragover'); });
		dropzone.addEventListener('drop', function (event) {
			if (!event.dataTransfer || !event.dataTransfer.files.length) return;
			event.preventDefault(); dropzone.classList.remove('is-dragover');
			var file = event.dataTransfer.files[0]; if (!/^image\//.test(file.type)) return;
			uploadAttachment(file, function (attachment) { var field = dropzone.closest('[data-media-field]'), hidden = field.querySelector('input[type="hidden"]'), targetView = hidden.name.indexOf('mobile') !== -1 ? 'mobile' : 'desktop'; hidden.value = attachment.id || 0; dropzone.innerHTML = '<img src="' + escapeHtml(attachment.url || '') + '" alt="">'; field.querySelector('[data-remove-media]').hidden = false; if (composer && composer.updateImage) composer.updateImage(targetView, attachment.url || ''); markCalendarDirty(); });
		});
	});
	document.querySelectorAll('[data-door-asset="media"] [data-select-door-media]').forEach(function (dropzone) {
		function carriesFiles(event) {
			if (!event.dataTransfer) return false;
			return (event.dataTransfer.types && Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') !== -1) || (event.dataTransfer.items && Array.prototype.some.call(event.dataTransfer.items, function (item) { return item.kind === 'file'; })) || Boolean(event.dataTransfer.files && event.dataTransfer.files.length);
		}
		['dragenter', 'dragover'].forEach(function (name) {
			dropzone.addEventListener(name, function (event) { if (!carriesFiles(event)) return; event.preventDefault(); event.stopPropagation(); dropzone.classList.add('is-dragover'); if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy'; }, true);
		});
		dropzone.addEventListener('dragleave', function (event) { event.stopPropagation(); dropzone.classList.remove('is-dragover'); }, true);
		dropzone.addEventListener('drop', function (event) {
			if (!carriesFiles(event)) return;
			event.preventDefault(); event.stopPropagation(); dropzone.classList.remove('is-dragover');
			if (!event.dataTransfer.files || !event.dataTransfer.files.length) return;
			uploadAttachment(event.dataTransfer.files[0], function (attachment) { setDoorAttachment('media', attachment); });
		}, true);
	});
	var layerDropzone = document.querySelector('[data-select-layer-image]');
	if (layerDropzone) {
		layerDropzone.addEventListener('dragover', function (event) { if (event.dataTransfer && event.dataTransfer.files.length) { event.preventDefault(); layerDropzone.classList.add('is-dragover'); } });
		layerDropzone.addEventListener('dragleave', function () { layerDropzone.classList.remove('is-dragover'); });
		layerDropzone.addEventListener('drop', function (event) {
			if (!event.dataTransfer || !event.dataTransfer.files.length) return;
			event.preventDefault(); layerDropzone.classList.remove('is-dragover');
			var file = event.dataTransfer.files[0]; if (!/^image\//.test(file.type)) return;
			uploadAttachment(file, function (attachment) {
				var id = layerDropzone.parentNode.querySelector('[data-layer-field="imageId"]'), url = layerDropzone.parentNode.querySelector('[data-layer-field="imageUrl"]');
				if (id) id.value = attachment.id || 0;
				if (url) url.value = attachment.url || '';
				layerDropzone.querySelector('[data-layer-image-preview]').innerHTML = '<img src="' + escapeHtml(attachment.url || '') + '" alt="">';
				if (activeLayer) { activeLayer.imageId = attachment.id || 0; activeLayer.imageUrl = attachment.url || ''; if (composer && composer.renderDoors) composer.renderDoors(); }
				markCalendarDirty();
			});
		});
	}

	document.addEventListener('input', function (event) {
		if (event.target.closest('[data-door-modal]') && event.target.matches('[data-door-field]')) syncDoorPreview();
	});
	document.addEventListener('change', function (event) {
		if (event.target.closest('[data-door-modal]') && event.target.matches('[data-door-field]')) syncDoorPreview();
		if (event.target.matches('[data-door-field="contentType"]')) updateContentTypeVisibility();
			if (event.target.matches('[data-door-field="ctaType"],[data-door-field="ctaMode"]')) updateCtaVisibility();
			if (event.target.matches('[data-default-cta-type]')) updateDefaultCtaVisibility();
		if (event.target.matches('[data-door-field$="LabelWidthMode"],[data-default-door-setting="labelWidthMode"]')) updateLabelWidthVisibility(event.target);
		if (event.target.matches('[data-default-door-color]') && composer) composer.dataset['default' + (event.target.dataset.defaultDoorColor === 'closed' ? 'Closed' : 'Open')] = event.target.value;
		if (event.target.matches('[data-default-door-setting]') && composer) composer.dataset['default' + event.target.dataset.defaultDoorSetting.charAt(0).toUpperCase() + event.target.dataset.defaultDoorSetting.slice(1)] = event.target.type === 'checkbox' ? (event.target.checked ? '1' : '0') : event.target.value;
		if (event.target.matches('[data-default-door-shape]') && composer) composer.dataset.defaultShape = event.target.value;
		if (event.target.matches('[data-cover-background]') && composer && composer.updateBackground) composer.updateBackground();
	});
	function updateLabelWidthVisibility(select) {
		var row = select && select.closest('.panda-seasons-label-size-row');
		if (!row) return;
		var widthField = row.querySelector('[data-door-field$="LabelWidth"], [data-default-door-setting="labelWidth"]');
		var label = widthField && widthField.closest('label');
		var needsSize = ['percent', 'px'].indexOf(select.value) !== -1;
		if (label) label.hidden = !needsSize;
		if (widthField) {
			widthField.max = select.value === 'px' ? '300' : '100';
			var caption = label && label.querySelector('[data-label-size-caption]');
			if (caption) caption.textContent = select.value === 'px' ? adminText('widthPx','Width (px)') : adminText('widthPercent','Width (%)');
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
	function updateCtaVisibility() {
		if (!modal) return;
		var modeField = modal.querySelector('[data-door-field="ctaMode"]');
		var customFields = modal.querySelector('[data-cta-custom-fields]');
		if (customFields) customFields.hidden = modeField && modeField.value === 'default';
		var typeField = modal.querySelector('[data-door-field="ctaType"]');
		var type = typeField ? typeField.value : 'link';
		modal.querySelectorAll('[data-cta-field]').forEach(function (field) {
			field.hidden = field.dataset.ctaField !== type;
		});
	}
	function updateDefaultCtaVisibility() {
		var typeField = document.querySelector('[data-default-cta-type]');
		if (!typeField) return;
		document.querySelectorAll('[data-default-cta-field]').forEach(function (field) { field.hidden = field.dataset.defaultCtaField !== typeField.value; });
	}
	updateDefaultCtaVisibility();
	document.querySelectorAll('[data-door-field$="LabelWidthMode"],[data-default-door-setting="labelWidthMode"]').forEach(updateLabelWidthVisibility);
	if (modal) {
		var doorFields = modal.querySelector('.panda-seasons-door-modal__fields');
		['basics', 'appearance', 'content-type', 'content', 'button', 'product', 'quiz', 'cta', 'email'].forEach(function (section) {
			var sectionElement = doorFields && doorFields.querySelector('[data-door-section="' + section + '"]');
			if (sectionElement) doorFields.appendChild(sectionElement);
		});
	}

	document.querySelectorAll('[data-default-door-color],[data-default-door-setting$="Color"],[data-default-door-setting$="Bg"],[data-default-door-setting$="Overlay"],[data-cover-background],[name="panda_seasons_popup_background"],[name="panda_seasons_popup_text_color"],[name="panda_seasons_popup_border_color"]').forEach(enhanceColorField);
	var defaultsPanel=document.querySelector('.panda-seasons-default-colors');
	var adminGrid=document.querySelector('.panda-seasons-settings-layout'),editorLayout=document.querySelector('.panda-seasons-editor-layout');
	if(adminGrid&&editorLayout){
		var mainTabs=document.createElement('div'),editorPanel=document.createElement('section'),settingsPanel=document.createElement('section'),emailPanel=document.createElement('section'),emailBox=document.getElementById('panda-seasons-email');
		mainTabs.className='panda-seasons-main-tabs';mainTabs.setAttribute('role','tablist');
		mainTabs.innerHTML='<button type="button" class="button button-primary" role="tab" aria-selected="true" data-main-editor-tab="editor">'+escapeHtml(adminText('editCalendar','Edit calendar'))+'</button><button type="button" class="button" role="tab" aria-selected="false" data-main-editor-tab="settings">'+escapeHtml(adminText('generalSettings','General defaults'))+'</button><button type="button" class="button" role="tab" aria-selected="false" data-main-editor-tab="email">'+escapeHtml(adminText('emailSettings','Registration and email delivery'))+'</button>';
		editorPanel.className='panda-seasons-main-panel';editorPanel.dataset.mainEditorPanel='editor';
		settingsPanel.className='panda-seasons-main-panel';settingsPanel.dataset.mainEditorPanel='settings';settingsPanel.hidden=true;
		emailPanel.className='panda-seasons-main-panel panda-seasons-email-panel';emailPanel.dataset.mainEditorPanel='email';emailPanel.hidden=true;
		adminGrid.parentNode.insertBefore(mainTabs,adminGrid);mainTabs.parentNode.insertBefore(editorPanel,adminGrid);mainTabs.parentNode.insertBefore(settingsPanel,adminGrid);mainTabs.parentNode.insertBefore(emailPanel,adminGrid);
		var titleBox=document.getElementById('titlediv');
		if(titleBox)mainTabs.parentNode.insertBefore(titleBox,mainTabs);
		editorPanel.appendChild(editorLayout);settingsPanel.appendChild(adminGrid);
		if(emailBox){emailPanel.appendChild(emailBox);emailBox.classList.remove('closed');var emailToggle=emailBox.querySelector('.handlediv');if(emailToggle)emailToggle.setAttribute('aria-expanded','true');}
		document.addEventListener('click',function(event){var button=event.target.closest('[data-main-editor-tab]');if(!button)return;var target=button.dataset.mainEditorTab;mainTabs.querySelectorAll('[data-main-editor-tab]').forEach(function(item){var active=item===button;item.classList.toggle('button-primary',active);item.setAttribute('aria-selected',active?'true':'false');});document.querySelectorAll('[data-main-editor-panel]').forEach(function(panel){panel.hidden=panel.dataset.mainEditorPanel!==target;});if(target==='editor'&&composer&&composer.renderDoors)composer.renderDoors();});
	}
	if(defaultsPanel&&composer){
		var applyDefaultsButton=document.createElement('button');applyDefaultsButton.type='button';applyDefaultsButton.className='button button-primary panda-seasons-apply-defaults-button';applyDefaultsButton.textContent=adminText('applyDefaults','Apply global settings to existing doors');defaultsPanel.appendChild(applyDefaultsButton);
		var defaultsDialog=document.createElement('div');defaultsDialog.className='panda-seasons-defaults-dialog';defaultsDialog.hidden=true;defaultsDialog.innerHTML='<div class="panda-seasons-defaults-dialog__backdrop" data-close-defaults-dialog></div><div class="panda-seasons-defaults-dialog__window" role="dialog" aria-modal="true" aria-labelledby="panda-seasons-defaults-title"><h2 id="panda-seasons-defaults-title">'+escapeHtml(adminText('defaultsTitle','Apply global settings'))+'</h2><p>'+escapeHtml(adminText('defaultsHelp','Choose each setting that may replace existing individual values.'))+'</p><div class="panda-seasons-defaults-dialog__actions"><button type="button" class="button" data-select-all-defaults>'+escapeHtml(adminText('selectAll','Select all'))+'</button><button type="button" class="button" data-select-no-defaults>'+escapeHtml(adminText('selectNone','Select none'))+'</button></div><div class="panda-seasons-defaults-table" data-defaults-comparison></div><div class="panda-seasons-defaults-dialog__footer"><button type="button" class="button" data-close-defaults-dialog>'+escapeHtml(adminText('cancel','Cancel'))+'</button><button type="button" class="button button-primary" data-apply-selected-defaults>'+escapeHtml(adminText('applySelected','Apply selected values'))+'</button></div></div>';document.body.appendChild(defaultsDialog);
		var defaultMappings=[
			{selector:'[name="panda_seasons_default_closed_background"]',props:['closedBg']},{selector:'[name="panda_seasons_default_open_background"]',props:['openBg']},
			{selector:'[name="panda_seasons_default_closed_image_overlay"]',props:['closedImageOverlay']},{selector:'[name="panda_seasons_default_open_image_overlay"]',props:['openImageOverlay']},
			{selector:'[name="panda_seasons_default_closed_text_color"]',props:['closedColor']},{selector:'[name="panda_seasons_default_open_text_color"]',props:['openColor']},
			{selector:'[name="panda_seasons_default_closed_border_color"]',props:['closedBorderColor']},{selector:'[name="panda_seasons_default_open_border_color"]',props:['openBorderColor']},
			{selector:'[name="panda_seasons_default_closed_border_width"]',props:['closedBorderWidth']},{selector:'[name="panda_seasons_default_open_border_width"]',props:['openBorderWidth']},
			{selector:'[name="panda_seasons_default_closed_label_background"]',props:['closedLabelBg']},{selector:'[name="panda_seasons_default_open_label_background"]',props:['openLabelBg']},
			{selector:'[name="panda_seasons_default_closed_label_border_color"]',props:['closedLabelBorderColor']},{selector:'[name="panda_seasons_default_open_label_border_color"]',props:['openLabelBorderColor']},
			{selector:'[name="panda_seasons_default_closed_label_border_width"]',props:['closedLabelBorderWidth']},{selector:'[name="panda_seasons_default_open_label_border_width"]',props:['openLabelBorderWidth']},
			{selector:'[name="panda_seasons_default_closed_label_shape"]',props:['closedLabelShape']},{selector:'[name="panda_seasons_default_open_label_shape"]',props:['openLabelShape']},{selector:'[name="panda_seasons_default_closed_label_position"]',props:['closedLabelPosition']},{selector:'[name="panda_seasons_default_open_label_position"]',props:['openLabelPosition']},{selector:'[name="panda_seasons_default_closed_label_visible"]',props:['closedLabelVisible']},{selector:'[name="panda_seasons_default_open_label_visible"]',props:['openLabelVisible']},
			{selector:'[name="panda_seasons_default_label_font"]',props:['closedFont','openFont']},{selector:'[name="panda_seasons_default_label_font_size"]',props:['closedFontSize','openFontSize']},
			{selector:'[name="panda_seasons_default_label_font_weight"]',props:['closedFontWeight','openFontWeight']},{selector:'[name="panda_seasons_default_label_width_mode"]',props:['closedLabelWidthMode','openLabelWidthMode']},
			{selector:'[name="panda_seasons_default_label_width"]',props:['closedLabelWidth','openLabelWidth']},{selector:'[name="panda_seasons_default_label_padding"]',props:['closedLabelPadding','openLabelPadding']},
			{selector:'[name="panda_seasons_default_border_style"]',props:['closedBorderStyle','openBorderStyle']},{selector:'[name="panda_seasons_default_shape"]',props:['shape']},{selector:'[name="panda_seasons_open_effect"]',props:['openEffect']},{selector:'[name="panda_seasons_default_opening_mode"]',props:['openingMode']}
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
		var labelMode=defaultValue('panda_seasons_default_label_width_mode','content'),labelSize=defaultValue('panda_seasons_default_label_width','60'),labelShape=defaultValue('panda_seasons_default_'+prefix+'_label_shape','rounded'),isRound=labelShape==='round'||labelShape==='square';
		sampleLabel.style.width=labelMode==='percent'?labelSize+'%':labelMode==='px'?labelSize+'px':'auto';
		sampleLabel.style.height=isRound?sampleLabel.style.width:'auto';
		sampleLabel.style.aspectRatio=isRound?'1':'auto';
		sampleLabel.style.borderRadius=labelShape==='round'?'999px':labelShape==='rounded'?'8px':'0';
		sampleLabel.style.boxSizing='border-box';
		var samplePlacement=labelPlacement(defaultValue('panda_seasons_default_'+prefix+'_label_position','center')),sampleButton=defaultPreview.querySelector('button');
		if(sampleButton){sampleButton.style.alignItems=samplePlacement.align;sampleButton.style.justifyItems=samplePlacement.justify;}
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
		if (event.target.matches('[data-default-door-color],[data-default-door-setting$="Color"],[data-default-door-setting$="Bg"],[data-default-door-setting$="Overlay"],[data-door-field$="Bg"],[data-door-field$="Color"],[data-door-field$="Overlay"]')) activeDefaultColorField = event.target;
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
	function loadCalendarPreview(preview,baseUrl){if(!preview||!baseUrl)return;var desktopInput=document.querySelector('[name="panda_seasons_cover_desktop"]'),mobileInput=document.querySelector('[name="panda_seasons_cover_mobile"]'),previewUrl=new URL(baseUrl,window.location.href),dateInput=preview.querySelector('[data-calendar-preview-date]'),timeInput=preview.querySelector('[data-calendar-preview-time]');previewUrl.searchParams.set('cover_desktop',desktopInput?desktopInput.value:'');previewUrl.searchParams.set('cover_mobile',mobileInput?mobileInput.value:'');previewUrl.searchParams.set('preview_date',dateInput?dateInput.value:'');previewUrl.searchParams.set('preview_time',timeInput?timeInput.value:'');previewUrl.searchParams.set('cache_bust',Date.now());preview.dataset.previewUrl=baseUrl;preview.querySelector('iframe').src=previewUrl.toString();}
	document.addEventListener('click',function(event){
		var opener=event.target.closest('[data-open-calendar-preview]');
		if(opener){
			if(calendarDirty&&window.confirm(adminText('saveBeforePreview','There are unsaved changes. Save them before opening the preview?'))){saveCalendar(function(){opener.click();});return;}
			var preview=document.querySelector('[data-calendar-preview]');if(!preview)return;preview.hidden=false;preview.classList.remove('is-mobile');loadCalendarPreview(preview,opener.dataset.previewUrl);document.body.classList.add('panda-seasons-preview-open');return;
		}
		var applyPreviewDate=event.target.closest('[data-calendar-preview-date-apply]');if(applyPreviewDate){var datePreview=applyPreviewDate.closest('[data-calendar-preview]');loadCalendarPreview(datePreview,datePreview.dataset.previewUrl);return;}
		var close=event.target.closest('[data-close-calendar-preview]');if(close){closeCalendarPreview(close.closest('[data-calendar-preview]'));return;}
		var size=event.target.closest('[data-calendar-preview-size]');if(size){var shell=size.closest('[data-calendar-preview]');shell.classList.toggle('is-mobile',size.dataset.calendarPreviewSize==='mobile');shell.querySelectorAll('[data-calendar-preview-size]').forEach(function(button){button.classList.toggle('button-primary',button===size);});}
	});

	function prepareCalendarState() {
		if (activeDoor) syncDoorPreview();
		if (composer && composer.prepareForSave) composer.prepareForSave();
	}
	function saveCalendar(afterSave) {
		if (!calendarForm || calendarSaving) return;
		prepareCalendarState();
		calendarSaving = true;
		calendarStatus(adminText('savingCalendar', 'Saving calendar…'), 'saving');
		var data = new FormData(calendarForm);
		data.set('action', 'panda_seasons_save_calendar');
		data.set('calendar_id', window.pandaSeasonsAdmin && pandaSeasonsAdmin.calendarId ? pandaSeasonsAdmin.calendarId : data.get('post_ID'));
		fetch(window.pandaSeasonsAdmin && pandaSeasonsAdmin.ajaxUrl ? pandaSeasonsAdmin.ajaxUrl : window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (response) { return response.json(); })
			.then(function (result) {
				if (!result || !result.success) throw new Error(result && result.data && result.data.message ? result.data.message : adminText('calendarSaveFailed', 'The calendar could not be saved. Please try again.'));
				if (composer && composer.applySavedDoorIds) composer.applySavedDoorIds(result.data.doorIds || []);
				if (composer && composer.commitSavedState) composer.commitSavedState();
				if (activeDoor) activeDoorSnapshot = JSON.parse(JSON.stringify(activeDoor));
				calendarDirty = false;
				calendarStatus(result.data.message || adminText('calendarSaved', 'Calendar saved.'), 'saved');
				if (typeof afterSave === 'function') afterSave();
			})
			.catch(function (error) {
				calendarDirty = true;
				calendarStatus(error && error.message ? error.message : adminText('calendarSaveFailed', 'The calendar could not be saved. Please try again.'), 'error');
			})
			.finally(function () { calendarSaving = false; });
	}
	if (calendarForm) {
		calendarForm.addEventListener('input', function (event) { if (!event.target.closest('#submitdiv')) markCalendarDirty(); });
		calendarForm.addEventListener('change', function (event) { if (!event.target.closest('#submitdiv')) markCalendarDirty(); });
		calendarForm.addEventListener('submit', function () { prepareCalendarState(); calendarDirty = false; });
	}
	(function bindDoorRichTextDirtyState(attempt) {
		var ready = true;
		['panda_seasons_door_content_editor', 'panda_seasons_door_cta_editor', 'panda_seasons_door_email_editor'].forEach(function (id) {
			var editor = window.tinymce && window.tinymce.get(id);
			if (!editor) { ready = false; return; }
			if (editor.settings && editor.settings.pandaSeasonsDirtyBound) return;
			editor.on('input change keyup undo redo', markCalendarDirty);
			if (editor.settings) editor.settings.pandaSeasonsDirtyBound = true;
		});
		if (!ready && attempt < 30) window.setTimeout(function () { bindDoorRichTextDirtyState(attempt + 1); }, 250);
	}(0));
	document.addEventListener('click', function (event) {
		if (event.target.closest('[data-save-calendar]')) { saveCalendar(); return; }
		if (event.target.closest('[data-add-door],[data-add-layer],[data-delete-stage-door],[data-delete-door],[data-delete-layer],[data-align-grid],[data-duplicate-door]')) markCalendarDirty();
		var templateOpen = event.target.closest('[data-open-template-dialog]'), templateClose = event.target.closest('[data-close-template-dialog]'), dialog = document.querySelector('[data-template-dialog]');
		if (templateOpen && dialog) { if (calendarDirty) { window.alert(adminText('saveBeforeTemplate','Save the calendar before creating a template.')); return; } dialog.hidden = false; document.body.classList.add('panda-seasons-modal-open'); return; }
		if (templateClose && dialog) { dialog.hidden = true; document.body.classList.remove('panda-seasons-modal-open'); }
	});
	document.addEventListener('pointerup', function (event) { if (event.target.closest('[data-editor-stage]')) markCalendarDirty(); });
	window.addEventListener('beforeunload', function (event) { if (!calendarDirty) return; event.preventDefault(); event.returnValue = ''; });
}());
