(function ($) {
	'use strict';

	function escapeHtml(value) {
		var node = document.createElement('div');
		node.textContent = String(value == null ? '' : value);
		return node.innerHTML;
	}

	function enhanceColorField(field, render) {
		if (!field || field.dataset.rgbaEnhanced) return;
		field.dataset.rgbaEnhanced = '1';
		var label = field.closest('label');
		if (label) label.classList.add('contactpanda-color-field');
		if (!window.ContactPandaAdminUI || typeof window.ContactPandaAdminUI.enhanceColorPicker !== 'function') return;
		window.ContactPandaAdminUI.enhanceColorPicker(field);
		field.addEventListener('change', render);
		field.addEventListener('input', render);
	}
	function uploadAttachment(file, callback) {
		if (!file || !pandaSeasonsFreeAdmin.uploadUrl || !pandaSeasonsFreeAdmin.uploadNonce) return;
		var data = new FormData();
		data.append('name', file.name);
		data.append('action', 'upload-attachment');
		data.append('_wpnonce', pandaSeasonsFreeAdmin.uploadNonce);
		data.append('async-upload', file, file.name);
		fetch(pandaSeasonsFreeAdmin.uploadUrl, {method: 'POST', credentials: 'same-origin', body: data}).then(function (response) { return response.json(); }).then(function (result) { if (result && result.success && result.data) callback(result.data); });
	}

	function start(root) {
		var json = root.querySelector('[data-doors-json]');
		var doors = [];
		try { doors = JSON.parse(json.textContent || '[]'); } catch (e) { doors = []; }
		var deleted = [];
		var selected = null;
		var layout = 'desktop';
		var state = 'closed';
		var canvas = root.querySelector('[data-canvas]');
		var inspector = root.querySelector('[data-inspector]');
		var inspectorEmpty = root.querySelector('[data-inspector-empty]');
		var contentId = 'panda_seasons_free_door_content';
		root.querySelectorAll('.panda-seasons-free-color').forEach(function (field) { enhanceColorField(field, render); });

		function defaults() {
			var result = {};
			root.querySelectorAll('[data-default]').forEach(function (input) { result[input.dataset.default] = input.value; });
			return result;
		}

		function editorContent() {
			if (window.tinymce && tinymce.get(contentId)) return tinymce.get(contentId).getContent();
			var textarea = document.getElementById(contentId);
			return textarea ? textarea.value : '';
		}

		function setEditorContent(value) {
			var textarea = document.getElementById(contentId);
			if (textarea) textarea.value = value || '';
			if (window.tinymce && tinymce.get(contentId)) tinymce.get(contentId).setContent(value || '');
		}

		function syncInspector() {
			if (!selected) return;
			inspector.querySelectorAll('[data-door-field]').forEach(function (input) {
				selected[input.dataset.doorField] = input.value;
			});
			selected.number = Math.max(1, parseInt(selected.number, 10) || 1);
			selected.content = editorContent();
		}

		function syncPositionFields() {
			if (!selected || !selected[layout]) return;
			inspector.querySelectorAll('[data-position-field]').forEach(function (input) {
				var key = input.dataset.positionField;
				var value = parseFloat(input.value);
				if (Number.isFinite(value)) selected[layout][key] = value;
			});
			selected[layout].w = Math.max(2, Math.min(100 - selected[layout].x, selected[layout].w));
			selected[layout].h = Math.max(2, Math.min(100 - selected[layout].y, selected[layout].h));
			selected[layout].x = Math.max(0, Math.min(100 - selected[layout].w, selected[layout].x));
			selected[layout].y = Math.max(0, Math.min(100 - selected[layout].h, selected[layout].y));
		}

		function loadPositionFields() {
			if (!selected || !selected[layout]) return;
			var label = inspector.querySelector('[data-position-layout]');
			if (label) label.textContent = layout === 'mobile' ? pandaSeasonsFreeAdmin.mobile : pandaSeasonsFreeAdmin.desktop;
			inspector.querySelectorAll('[data-position-field]').forEach(function (input) {
				input.value = Math.round((selected[layout][input.dataset.positionField] || 0) * 100) / 100;
			});
		}

		function loadInspector(door) {
			selected = door;
			if (inspectorEmpty) inspectorEmpty.hidden = true;
			inspector.hidden = false;
			inspector.querySelector('[data-door-title]').textContent = door.number || '';
			inspector.querySelectorAll('[data-door-field]').forEach(function (input) {
				var value = door[input.dataset.doorField] || '';
				input.value = value;
				if (input.classList.contains('panda-seasons-free-color')) input.dispatchEvent(new Event('change', {bubbles: true}));
			});
			setEditorContent(door.content || '');
			loadPositionFields();
		}

		function positionStyle(position) {
			position = position || {x: 0, y: 0, w: 15, h: 15};
			return 'left:' + position.x + '%;top:' + position.y + '%;width:' + position.w + '%;height:' + position.h + '%;';
		}

		function render() {
			syncInspector();
			var image = root.dataset.coverUrl || '';
			var background = (root.querySelector('[data-calendar-color]') || {}).value || '#f4f4f4';
			var ratioInput = root.querySelector('[data-ratio="' + layout + '"]');
			var ratio = Math.max(.2, parseFloat(ratioInput ? ratioInput.value : 1.5) || 1.5);
			canvas.style.aspectRatio = String(ratio);
			canvas.style.backgroundColor = background;
			canvas.style.backgroundImage = image ? 'url("' + image.replace(/"/g, '\\"') + '")' : 'none';
			canvas.innerHTML = '';
			var fallback = defaults();
			doors.forEach(function (door) {
				var el = document.createElement('button');
				el.type = 'button';
				el.className = 'panda-seasons-free-editor-door' + (door === selected ? ' is-selected' : '');
				el.dataset.doorKey = door.key;
				el.style.cssText = positionStyle(door[layout]);
				el.style.background = door[state + 'Bg'] || fallback[state + 'Bg'];
				el.style.color = door[state + 'Color'] || fallback[state + 'Color'];
				el.style.borderColor = door[state + 'BorderColor'] || fallback[state + 'BorderColor'] || 'transparent';
				el.style.borderWidth = Math.max(0, parseInt(door[state + 'BorderWidth'] || fallback[state + 'BorderWidth'], 10) || 0) + 'px';
				el.style.borderStyle = door[state + 'BorderStyle'] || fallback.borderStyle || 'none';
				el.dataset.shape = door.shape || fallback.shape || 'rounded';
				el.innerHTML = '<span>' + String(door.number || '') + '</span><i aria-hidden="true"></i>';
				el.addEventListener('click', function (event) {
					event.stopPropagation();
					syncInspector();
					loadInspector(door);
					render();
				});
				canvas.appendChild(el);
				$(el).draggable({
					containment: 'parent',
					stop: function (event, ui) {
						var rect = canvas.getBoundingClientRect();
						door[layout].x = Math.max(0, Math.min(100 - door[layout].w, ui.position.left / rect.width * 100));
						door[layout].y = Math.max(0, Math.min(100 - door[layout].h, ui.position.top / rect.height * 100));
						loadPositionFields();
						render();
					}
				}).resizable({
					handles: {se: el.querySelector('i')},
					containment: 'parent',
					minWidth: 28,
					minHeight: 28,
					stop: function (event, ui) {
						var rect = canvas.getBoundingClientRect();
						door[layout].w = Math.max(2, Math.min(100 - door[layout].x, ui.size.width / rect.width * 100));
						door[layout].h = Math.max(2, Math.min(100 - door[layout].y, ui.size.height / rect.height * 100));
						loadPositionFields();
						render();
					}
				});
			});
		}

		function nextNumber() {
			return doors.reduce(function (highest, door) { return Math.max(highest, parseInt(door.number, 10) || 0); }, 0) + 1;
		}

		function makeDoor(number, x, y) {
			var fallback = defaults();
			return {
				id: 0,
				key: 'new-' + Date.now() + '-' + number + '-' + Math.random().toString(16).slice(2),
				number: number,
				unlockDate: '', unlockTime: '', heading: '', content: '', shape: fallback.shape || 'rounded',
				closedBg: '', closedColor: '', closedBorderColor: '', closedBorderWidth: '', closedBorderStyle: fallback.borderStyle || 'none',
				openBg: '', openColor: '', openBorderColor: '', openBorderWidth: '', openBorderStyle: fallback.borderStyle || 'none',
				desktop: {x: x == null ? 0 : x, y: y == null ? 0 : y, w: 100 / 6, h: 16},
				mobile: {x: ((number - 1) % 3) * (100 / 3), y: Math.min(88, Math.floor((number - 1) / 3) * 12), w: 100 / 3, h: 12}
			};
		}

		function addDoor(position) {
			syncInspector();
			var number = nextNumber();
			var column = (doors.length % 6) * (100 / 6);
			var row = Math.floor(doors.length / 6) * 16;
			var door = makeDoor(number, position ? position.x : column, position ? position.y : Math.min(84, row));
			doors.push(door);
			loadInspector(door);
			render();
		}

		doors.forEach(function (door, index) { door.key = door.id ? 'door-' + door.id : 'door-' + index; });
		var addButton = root.querySelector('[data-add-door]');
		addButton.addEventListener('click', function () { addDoor(); });
		addButton.addEventListener('dragstart', function (event) { event.dataTransfer.setData('text/panda-seasons-free', 'door'); event.dataTransfer.effectAllowed = 'copy'; });
		canvas.addEventListener('dragover', function (event) { if (Array.prototype.indexOf.call(event.dataTransfer.types, 'text/panda-seasons-free') !== -1) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; canvas.classList.add('is-door-drop-target'); } });
		canvas.addEventListener('dragleave', function () { canvas.classList.remove('is-door-drop-target'); });
		canvas.addEventListener('drop', function (event) {
			if (event.dataTransfer.getData('text/panda-seasons-free') !== 'door') return;
			event.preventDefault(); canvas.classList.remove('is-door-drop-target');
			var rect = canvas.getBoundingClientRect();
			var width = layout === 'mobile' ? 100 / 3 : 100 / 6, height = layout === 'mobile' ? 12 : 16;
			addDoor({x: Math.max(0, Math.min(100 - width, (event.clientX - rect.left) / rect.width * 100 - width / 2)), y: Math.max(0, Math.min(100 - height, (event.clientY - rect.top) / rect.height * 100 - height / 2))});
		});
		root.querySelectorAll('[data-layout]').forEach(function (button) {
			button.addEventListener('click', function () {
				layout = button.dataset.layout;
				root.querySelectorAll('[data-layout]').forEach(function (item) { item.setAttribute('aria-pressed', item === button ? 'true' : 'false'); });
				loadPositionFields();
				render();
			});
		});
		root.querySelectorAll('[data-state]').forEach(function (button) {
			button.addEventListener('click', function () {
				state = button.dataset.state;
				root.querySelectorAll('[data-state]').forEach(function (item) { item.setAttribute('aria-pressed', item === button ? 'true' : 'false'); });
				render();
			});
		});
		root.querySelector('[data-apply-door]').addEventListener('click', function () { syncInspector(); render(); });
		inspector.querySelectorAll('[data-position-field]').forEach(function (input) {
			input.addEventListener('change', function () { syncPositionFields(); loadPositionFields(); render(); });
		});
		root.querySelector('[data-delete-door]').addEventListener('click', function () {
			if (!selected || !window.confirm(pandaSeasonsFreeAdmin.confirmDelete)) return;
			if (selected.id) deleted.push(selected.id);
			doors = doors.filter(function (door) { return door !== selected; });
			selected = null;
			inspector.hidden = true;
			if (inspectorEmpty) inspectorEmpty.hidden = false;
			render();
		});
		root.querySelectorAll('[data-default],[data-calendar-color],[data-ratio]').forEach(function (input) { input.addEventListener('change', render); });

		function setFieldValue(selector, value) {
			var input = root.querySelector(selector);
			if (!input) return;
			input.value = value;
			input.dispatchEvent(new Event('change', {bubbles: true}));
		}

		function ensureTwentyFourDoors() {
			while (doors.length < 24) doors.push(makeDoor(nextNumber(), 0, 0));
		}

		function applyTemplate(name) {
			if (!window.confirm(pandaSeasonsFreeAdmin.confirmTemplate)) return;
			syncInspector();
			ensureTwentyFourDoors();
			var modern = name === 'modern';
			setFieldValue('[data-default="shape"]', modern ? 'round' : 'square');
			setFieldValue('[data-default="closedBg"]', modern ? 'rgba(20, 92, 82, 0.88)' : 'rgba(143, 34, 45, 0.9)');
			setFieldValue('[data-default="closedColor"]', '#ffffff');
			setFieldValue('[data-default="openBg"]', modern ? 'rgba(235, 224, 190, 0.82)' : 'rgba(250, 244, 226, 0.88)');
			setFieldValue('[data-default="openColor"]', modern ? '#173f39' : '#6e1d27');
			doors.slice(0, 24).forEach(function (door, index) {
				var columns = 6;
				var rows = 4;
				door.desktop = {x: (index % columns) * (100 / columns), y: Math.floor(index / columns) * (100 / rows), w: 100 / columns, h: 100 / rows};
				door.mobile = {x: (index % 3) * (100 / 3), y: Math.floor(index / 3) * 12.5, w: 100 / 3, h: 12.5};
				door.shape = modern ? 'round' : 'square';
				door.closedBg = ''; door.closedColor = ''; door.closedBorderColor = ''; door.closedBorderWidth = ''; door.closedBorderStyle = '';
				door.openBg = ''; door.openColor = ''; door.openBorderColor = ''; door.openBorderWidth = ''; door.openBorderStyle = '';
			});
			if (selected) loadInspector(selected);
			render();
		}

		root.querySelectorAll('[data-template]').forEach(function (button) { button.addEventListener('click', function () { applyTemplate(button.dataset.template); }); });

		var coverPicker = root.querySelector('[data-cover-picker]');
		var sharedCoverPicker = null;
		var syncingSharedCoverPicker = false;
		function setCover(image) {
			root.querySelector('[data-cover-id]').value = image.id || '';
			root.dataset.coverUrl = image.url || (image.sizes && image.sizes.large ? image.sizes.large.url : '');
			var preview = root.querySelector('[data-cover-preview]');
			preview.innerHTML = root.dataset.coverUrl ? '<img src="' + escapeHtml(root.dataset.coverUrl) + '" alt="">' : '';
			root.querySelector('[data-remove-cover]').hidden = !root.dataset.coverUrl;
			coverPicker.classList.toggle('has-image', Boolean(root.dataset.coverUrl));
			if (sharedCoverPicker && !syncingSharedCoverPicker) {
				syncingSharedCoverPicker = true;
				sharedCoverPicker.contactPandaSetImages(root.dataset.coverUrl ? [{id: image.id || 0, url: root.dataset.coverUrl, alt: ''}] : []);
				syncingSharedCoverPicker = false;
			}
			render();
		}
		if (coverPicker && window.ContactPandaAdminUI && typeof window.ContactPandaAdminUI.createImagePicker === 'function') {
			sharedCoverPicker = window.ContactPandaAdminUI.createImagePicker({
				dropLabel: pandaSeasonsFreeAdmin.selectImage,
				attachmentId: Number(root.querySelector('[data-cover-id]').value || 0),
				url: root.dataset.coverUrl || '',
				onChange: function (image) {
					if (syncingSharedCoverPicker) return;
					syncingSharedCoverPicker = true;
					setCover(image || {id: '', url: ''});
					syncingSharedCoverPicker = false;
				}
			});
			coverPicker.parentNode.insertBefore(sharedCoverPicker, coverPicker);
			coverPicker.hidden = true;
		}
		var form = root.closest('form');
		if (form) form.addEventListener('submit', function () {
			syncInspector();
			root.querySelector('[data-doors-input]').value = JSON.stringify(doors.map(function (door) {
				var copy = Object.assign({}, door); delete copy.key; return copy;
			}));
			root.querySelector('[data-deleted-input]').value = JSON.stringify(deleted);
		});
		render();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-seasons-free-editor]').forEach(start);
	});
})(jQuery);

