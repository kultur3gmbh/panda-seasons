(function ($) {
	'use strict';

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
		var contentId = 'panda_seasons_free_door_content';
		$(root).find('.panda-seasons-free-color').wpColorPicker({
			change: function () { window.setTimeout(render, 0); },
			clear: function () { window.setTimeout(render, 0); }
		});

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

		function loadInspector(door) {
			selected = door;
			inspector.hidden = false;
			inspector.querySelector('[data-door-title]').textContent = door.number || '';
			inspector.querySelectorAll('[data-door-field]').forEach(function (input) {
				var value = door[input.dataset.doorField] || '';
				input.value = value;
				if (input.classList.contains('panda-seasons-free-color')) $(input).wpColorPicker('color', value);
			});
			setEditorContent(door.content || '');
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
						render();
					}
				});
			});
		}

		function nextNumber() {
			return doors.reduce(function (highest, door) { return Math.max(highest, parseInt(door.number, 10) || 0); }, 0) + 1;
		}

		function addDoor() {
			syncInspector();
			var number = nextNumber();
			var column = (doors.length % 6) * (100 / 6);
			var row = Math.floor(doors.length / 6) * 16;
			var door = {
				id: 0,
				key: 'new-' + Date.now() + '-' + number,
				number: number,
				unlockDate: '', unlockTime: '', heading: '', content: '',
				closedBg: '', closedColor: '', openBg: '', openColor: '',
				desktop: {x: column, y: Math.min(84, row), w: 100 / 6, h: 16},
				mobile: {x: (doors.length % 3) * (100 / 3), y: Math.min(88, Math.floor(doors.length / 3) * 12), w: 100 / 3, h: 12}
			};
			doors.push(door);
			loadInspector(door);
			render();
		}

		doors.forEach(function (door, index) { door.key = door.id ? 'door-' + door.id : 'door-' + index; });
		root.querySelector('[data-add-door]').addEventListener('click', addDoor);
		root.querySelectorAll('[data-layout]').forEach(function (button) {
			button.addEventListener('click', function () {
				layout = button.dataset.layout;
				root.querySelectorAll('[data-layout]').forEach(function (item) { item.setAttribute('aria-pressed', item === button ? 'true' : 'false'); });
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
		root.querySelector('[data-delete-door]').addEventListener('click', function () {
			if (!selected || !window.confirm(pandaSeasonsFreeAdmin.confirmDelete)) return;
			if (selected.id) deleted.push(selected.id);
			doors = doors.filter(function (door) { return door !== selected; });
			selected = null;
			inspector.hidden = true;
			render();
		});
		root.querySelectorAll('[data-default],[data-calendar-color],[data-ratio]').forEach(function (input) { input.addEventListener('change', render); });

		var mediaFrame;
		root.querySelector('[data-select-cover]').addEventListener('click', function () {
			if (!mediaFrame) {
				mediaFrame = wp.media({title: pandaSeasonsFreeAdmin.selectImage, button: {text: pandaSeasonsFreeAdmin.useImage}, multiple: false, library: {type: 'image'}});
				mediaFrame.on('select', function () {
					var image = mediaFrame.state().get('selection').first().toJSON();
					root.querySelector('[data-cover-id]').value = image.id;
					root.dataset.coverUrl = (image.sizes && image.sizes.large ? image.sizes.large.url : image.url);
					var preview = root.querySelector('[data-cover-preview]');
					preview.style.backgroundImage = 'url("' + root.dataset.coverUrl.replace(/"/g, '\\"') + '")';
					preview.querySelector('span').hidden = true;
					root.querySelector('[data-remove-cover]').hidden = false;
					render();
				});
			}
			mediaFrame.open();
		});
		root.querySelector('[data-remove-cover]').addEventListener('click', function () {
			root.querySelector('[data-cover-id]').value = '';
			root.dataset.coverUrl = '';
			var preview = root.querySelector('[data-cover-preview]');
			preview.style.backgroundImage = 'none';
			preview.querySelector('span').hidden = false;
			this.hidden = true;
			render();
		});

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

