(function () {
	'use strict';

	var definitions = {
		view: ['visibility'], edit: ['edit'], duplicate: ['admin-page'], delete: ['trash', 'danger'],
		confirm: ['yes-alt', 'success'], cancel: ['dismiss', 'danger'], email: ['email-alt'], report: ['chart-bar'],
		external: ['external'], publish: ['megaphone', 'primary'], archive: ['archive'], deactivate: ['', 'danger', 'power'],
		remove: ['minus'], refresh: ['update'], website: ['admin-site-alt3'], export: ['', '', 'file-output'],
		import: ['', '', 'file-input'], download: ['download'], add: ['plus-alt2', 'primary'], drag: ['move'],
		moveUp: ['arrow-up-alt2'], moveDown: ['arrow-down-alt2'], undo: ['undo'], redo: ['redo'], image: ['format-image'],
		desktop: ['desktop'], tablet: ['tablet'], mobile: ['smartphone'], template: ['layout'],
		templateCreate: ['insert', 'primary'], templateGallery: ['grid-view'], draft: ['edit-page'], save: ['saved', 'success'],
		schedule: ['calendar-alt'], levelUp: ['', '', 'corner-left-up'], back: ['arrow-left-alt2'], print: ['printer'],
		attachment: ['paperclip'], search: ['search'], filter: ['filter'], list: ['list-view'], unlock: ['unlock'], restore: ['undo'],
		apply: ['yes-alt'], accept: ['', '', 'arrow-right-to-line'], activate: ['', '', 'power'], reset: ['undo'],
		test: ['admin-tools'], start: ['controls-play']
	};
	var labels = [
		[/^(view|preview|details|show|anzeigen|ansehen|vorschau|details anzeigen|view \/ edit|ansehen \/ bearbeiten)$/i, 'view'],
		[/^(edit|bearbeiten|ändern)( .+)?$/i, 'edit'], [/^(duplicate|copy|duplizieren|kopieren)( .+)?$/i, 'duplicate'],
		[/^(delete|trash|löschen|in den papierkorb|in papierkorb verschieben)( .+)?$/i, 'delete'],
		[/^(remove assignment|zuordnung entfernen|remove|entfernen)$/i, 'remove'],
		[/^(deactivate|disable|pause|deaktivieren|sperren|pausieren)$/i, 'deactivate'],
		[/^(activate|approve|confirm|aktivieren|genehmigen|bestätigen)$/i, 'confirm'],
		[/^(cancel|close|abbrechen|schließen)$/i, 'cancel'], [/^(send|resend|send test|versenden|erneut senden|test senden|email|e-mail)$/i, 'email'],
		[/^(publish|veröffentlichen)$/i, 'publish'], [/^(archive|archivieren)$/i, 'archive'],
		[/^(export|exportieren)$/i, 'export'], [/^(import|importieren)$/i, 'import'], [/^(download|herunterladen)$/i, 'download'],
		[/^(restore|wiederherstellen)$/i, 'restore'], [/^(save as draft|draft|als entwurf speichern|entwurf speichern)$/i, 'draft'],
		[/^(save|save changes|speichern|änderungen speichern)$/i, 'save'], [/^(schedule|schedule publication|planen|veröffentlichung planen)$/i, 'schedule'],
		[/^(refresh|sync|synchronisieren|aktualisieren)$/i, 'refresh'], [/^(report|log|reporting|bericht|protokoll|auswertung)$/i, 'report'],
		[/^(template|vorlage)$/i, 'template'], [/^(create template|vorlage erstellen)$/i, 'templateCreate'],
		[/^(template gallery|vorlagengalerie)$/i, 'templateGallery'], [/^(one level up|up one level|eine ebene höher|zur übergeordneten ebene)$/i, 'levelUp'],
		[/^(back|zurück)$/i, 'back'], [/^(print|print list|drucken|liste drucken)$/i, 'print'],
		[/^(search|suchen)$/i, 'search'], [/^(filter|filtern)$/i, 'filter'], [/^(open|öffnen)$/i, 'external']
	];
	var largeLabels = [
		[/^(save as draft|save draft|draft|als entwurf speichern|entwurf speichern)$/i, 'draft'],
		[/^(publish|publish automation|publish \/ update|update \/ publish|veröffentlichen|automatisierung veröffentlichen)( .+)?$/i, 'publish'],
		[/^(schedule|schedule publication|planen|veröffentlichung planen)( .+)?$/i, 'schedule'],
		[/^(save|save changes|save and use|speichern|änderungen speichern|speichern und verwenden|portaleinstellungen speichern)( .+)?$/i, 'save'],
		[/^(apply|apply changes|anwenden)( .+)?$/i, 'apply'], [/^(accept|adopt|use|übernehmen)( .+)?$/i, 'accept'],
		[/^(update|refresh|sync|aktualisieren|erneuern|synchronisieren)( .+)?$/i, 'refresh'],
		[/^(activate|enable|aktivieren|freischalten)( .+)?$/i, 'activate'], [/^(deactivate|disable|deaktivieren|sperren)( .+)?$/i, 'deactivate'],
		[/^(reset|restore defaults|zurücksetzen|standard wiederherstellen)( .+)?$/i, 'reset'],
		[/^(test|test connection|verbindung testen|prüfen)( .+)?$/i, 'test'], [/^(cancel|abbrechen|close|schließen)$/i, 'cancel'],
		[/^(check for updates|nach updates suchen|retry|erneut versuchen)( .+)?$/i, 'refresh'],
		[/^(confirm|bestätigen)( .+)?$/i, 'apply'], [/^(continue|fortfahren)( .+)?$/i, 'accept'],
		[/^(preview|vorschau)( .+)?$/i, 'view'], [/^(send test|test senden|send|versenden)( .+)?$/i, 'email'],
		[/^(import|importieren)( .+)?$/i, 'import'], [/^(export|exportieren)( .+)?$/i, 'export'],
		[/^(download|herunterladen)( .+)?$/i, 'download'], [/^(print|drucken)( .+)?$/i, 'print'],
		[/^(start|run|execute|migration starten|starten|ausführen)( .+)?$/i, 'start'],
		[/^(add|new|create|hinzufügen|neu|erstellen)( .+)?$/i, 'add'], [/^(copy|duplicate|kopieren|duplizieren)( .+)?$/i, 'duplicate'],
		[/^(clear|leeren)( .+)?$/i, 'reset'], [/^(open|öffnen)( .+)?$/i, 'external'], [/^(edit|bearbeiten)( .+)?$/i, 'edit'],
		[/^(search|suchen)( .+)?$/i, 'search'], [/^(filter|filtern)( .+)?$/i, 'filter']
	];
	var largeSuffixes = [
		[/\b(draft|entwurf)\b/i, 'draft'],
		[/\b(publish|publishing|veröffentlichen|veröffentlichung)\b/i, 'publish'], [/\b(schedule|scheduled|scheduling|planen|geplant)\b/i, 'schedule'],
		[/\b(deactivate|disable|deaktivieren|sperren)\b/i, 'deactivate'], [/\b(activate|enable|aktivieren|freischalten)\b/i, 'activate'],
		[/\b(save|saving|saved|speichern|gespeichert)\b/i, 'save'], [/\b(apply|applying|anwenden)\b/i, 'apply'],
		[/\b(accept|adopt|use)\b|übernehmen/i, 'accept'],
		[/\b(update|updates|updating|updated|updaten|refresh|sync|fetch|retrieve|reload|aktualisieren|aktualisierung|aktualisierungen|erneuern|synchronisieren|abrufen)\b|neu laden/i, 'refresh'],
		[/\b(reset|restore|zurücksetzen|wiederherstellen)\b/i, 'reset'], [/\b(test|check|validate|testen|prüfen|validieren)\b/i, 'test'],
		[/\b(send|sending|versenden|senden)\b/i, 'email'], [/\b(import|importieren)\b/i, 'import'],
		[/\b(export|exportieren)\b/i, 'export'], [/\b(download|herunterladen)\b/i, 'download'],
		[/\b(start|run|execute|starten|ausführen)\b/i, 'start'], [/\b(add|create|new|hinzufügen|erstellen|neu)\b/i, 'add'],
		[/\b(copy|duplicate|kopieren|duplizieren)\b/i, 'duplicate'],
		[/\b(delete|trash|löschen|papierkorb)\b/i, 'delete'], [/\b(confirm|approve|bestätigen|genehmigen)\b/i, 'confirm'],
		[/\b(cancel|close|abbrechen|schließen)\b/i, 'cancel'], [/\b(disconnect|separate|trennen)\b/i, 'deactivate'],
		[/\b(preview|view|show|anzeigen|ansehen|vorschau)\b/i, 'view'], [/\b(print|drucken)\b/i, 'print'],
		[/\b(open|öffnen)\b/i, 'external'], [/\b(search|suchen)\b/i, 'search'], [/\b(filter|filtern)\b/i, 'filter']
	];
	var compactContainers = [
		'td', '.contactpanda-list-action', '.contactpanda-inline-row-action', '.contactpanda-template-actions',
		'.contactpanda-gallery-row-actions', '.contactpanda-system-email-block-actions', '.cpb-actions', '.cpb-card-actions',
		'.pandawoo-template-actions', '.pandawoo-pro-license-actions', '.panda-seasons-actions',
		'.panda-seasons-composer__door-actions', '.panda-seasons-composer__layer-actions', '.cpls-actions', '.cpls-row-actions'
	].join(',');
	var selector = [
		'[data-contactpanda-action]', '.contactpanda-list-action', '.contactpanda-person-row-action', '.contactpanda-inline-row-action',
		'.contactpanda-eye-action', '.contactpanda-trash-row-action', '.contactpanda-form-actions a', '.contactpanda-form-actions button',
		'.contactpanda-order-actions a', '.contactpanda-order-actions button', '.contactpanda-template-actions a', '.contactpanda-template-actions button',
		'.contactpanda-gallery-row-actions a', '.contactpanda-gallery-row-actions button', '.contactpanda-system-email-block-actions a',
		'.contactpanda-system-email-block-actions button', '.contactpanda-system-email-add a', '.contactpanda-system-email-add button',
		'.cpb-actions a', '.cpb-actions button', '.cpb-card-actions a', '.cpb-card-actions button',
		'.pandawoo-template-actions a', '.pandawoo-template-actions button', '.pandawoo-inline-actions a', '.pandawoo-inline-actions button',
		'.pandawoo-pro-license-actions a', '.pandawoo-pro-license-actions button', '.panda-seasons-actions a', '.panda-seasons-actions button',
		'.panda-seasons-composer__door-actions button', '.panda-seasons-composer__layer-actions button',
		'.cpls-actions a', '.cpls-actions button', '.cpls-row-actions a', '.cpls-row-actions button'
	].join(',');

	function findAll(root, query) {
		root = root || document;
		var matches = [];
		if (root.nodeType === 1 && root.matches(query)) matches.push(root);
		return matches.concat(Array.prototype.slice.call(root.querySelectorAll(query)));
	}

	function proxyInputAction(control, text) {
		if (control.tagName !== 'INPUT') return control;
		var proxy = document.createElement('button');
		var inputType = (control.getAttribute('type') || 'submit').toLowerCase();
		proxy.type = inputType === 'reset' ? 'reset' : inputType === 'submit' ? 'submit' : 'button';
		proxy.className = control.className;
		proxy.textContent = text;
		proxy.disabled = control.disabled;
		Array.prototype.slice.call(control.attributes).forEach(function (attribute) {
			if (attribute.name !== 'type' && attribute.name !== 'value' && attribute.name !== 'class') proxy.setAttribute(attribute.name, attribute.value);
		});
		if (control.name) proxy.name = control.name;
		if (control.value) proxy.value = control.value;
		proxy.dataset.contactpandaLargeActionReady = '1';
		control.dataset.contactpandaLargeActionReady = '1';
		if (proxy.type === 'button') {
			control.classList.add('contactpanda-large-action-source');
			control.hidden = true;
			control.style.setProperty('display', 'none', 'important');
			control.tabIndex = -1;
			control.setAttribute('aria-hidden', 'true');
			control.parentNode.insertBefore(proxy, control);
			proxy.addEventListener('click', function () { if (!control.disabled) control.click(); });
		} else {
			control.parentNode.replaceChild(proxy, control);
		}
		return proxy;
	}

	function enhance(control) {
		if (!control || control.dataset.pandaActionReady === '1' || control.dataset.contactpandaActionReady === '1') return;
		var text = (control.getAttribute('aria-label') || control.getAttribute('title') || control.textContent || '').trim().replace(/\s+/g, ' ');
		var action = control.dataset.contactpandaAction || '';
		if (!action) labels.some(function (entry) { if (entry[0].test(text)) { action = entry[1]; return true; } return false; });
		var definition = definitions[action];
		if (!definition) {
			if (control.querySelector('.dashicons')) {
				control.dataset.pandaActionReady = '1';
				control.classList.add('contactpanda-list-action');
			}
			return;
		}
		if (!text) text = action.replace(/([A-Z])/g, ' $1').replace(/^./, function (letter) { return letter.toUpperCase(); });
		control.dataset.pandaActionReady = '1';
		control.dataset.contactpandaAction = action;
		var labelled = control.dataset.contactpandaActionMode === 'labelled' || control.classList.contains('contactpanda-action-labelled');
		control.classList.add(labelled ? 'contactpanda-action-labelled' : 'contactpanda-list-action', 'contactpanda-action-' + action.replace(/([A-Z])/g, '-$1').toLowerCase());
		if (definition[1]) control.classList.add('is-' + definition[1]);
		if (control.disabled || control.getAttribute('aria-disabled') === 'true') control.classList.add('is-disabled');
		control.title = text;
		control.setAttribute('aria-label', text);
		var glyph = document.createElement('span');
		glyph.setAttribute('aria-hidden', 'true');
		glyph.className = definition[2] ? 'contactpanda-action-icon contactpanda-action-icon-' + definition[2] : 'dashicons dashicons-' + definition[0];
		if (labelled) { control.prepend(glyph); return; }
		control.replaceChildren(glyph);
		var hidden = document.createElement('span');
		hidden.className = 'screen-reader-text';
		hidden.textContent = text;
		control.appendChild(hidden);
	}

	function enhanceLarge(control) {
		if (!control || control.dataset.contactpandaLargeActionReady === '1') return;
		if (control.matches('.button-small,.button-link,.button-link-delete,[data-contactpanda-action-mode="icon"]') || control.closest(compactContainers + ',.wp-picker-container,.contactpanda-unified-color,.media-modal,.wp-editor-wrap,.mce-toolbar,.components-toolbar')) return;
		var text = (control.value || control.textContent || control.getAttribute('aria-label') || control.getAttribute('title') || '').trim().replace(/\s+/g, ' ');
		var action = control.dataset.contactpandaAction || '';
		if (!action) largeSuffixes.some(function (entry) { if (entry[0].test(text)) { action = entry[1]; return true; } return false; });
		if (!action) largeLabels.some(function (entry) { if (entry[0].test(text)) { action = entry[1]; return true; } return false; });
		var definition = definitions[action];
		if (!text) return;
		if (definition) control = proxyInputAction(control, text);
		control.dataset.contactpandaLargeActionReady = '1';
		control.classList.add('contactpanda-large-action');
		if (control.classList.contains('button-primary') || action === 'publish') control.classList.add('is-primary');
		if (control.disabled || control.getAttribute('aria-disabled') === 'true') control.classList.add('is-disabled');
		if (!definition) return;
		control.dataset.contactpandaActionReady = '1';
		control.dataset.contactpandaAction = action;
		control.classList.add('contactpanda-large-action-' + action.replace(/([A-Z])/g, '-$1').toLowerCase());
		Array.prototype.slice.call(control.children).forEach(function (child) {
			if (child.classList.contains('dashicons') || child.classList.contains('contactpanda-action-icon')) child.remove();
		});
		var glyph = document.createElement('span');
		glyph.setAttribute('aria-hidden', 'true');
		glyph.className = definition[2] ? 'contactpanda-action-icon contactpanda-action-icon-' + definition[2] : 'dashicons dashicons-' + definition[0];
		control.prepend(glyph);
	}

	function scan(root) {
		findAll(root, 'button.button,a.button,input.button,input[type="submit"],input[type="button"],input[type="reset"],button[type="submit"],[data-contactpanda-action-mode="labelled"]').forEach(enhanceLarge);
		findAll(root, selector).forEach(enhance);
		findAll(root, 'table').forEach(function (table) {
			var indexes = [];
			table.querySelectorAll('thead th').forEach(function (heading, index) {
				if (/^(actions|aktionen)$/i.test(heading.textContent.trim()) || /(column-actions|actions?-column)/.test(heading.className)) indexes.push(index);
			});
			table.querySelectorAll('tbody tr').forEach(function (row) {
				indexes.forEach(function (index) {
					if (row.children[index]) row.children[index].querySelectorAll('a,button').forEach(enhance);
				});
			});
		});
	}

	function createImagePicker(options) {
		options = options || {};
		var item = options.url ? {id:Number(options.attachmentId || 0),url:options.url,alt:options.alt || ''} : null;
		var root = document.createElement('div'); root.className = 'contactpanda-image-picker';
		var drop = document.createElement('button'); drop.type = 'button'; drop.className = 'contactpanda-image-picker-drop'; drop.innerHTML = '<span class="dashicons dashicons-format-image" aria-hidden="true"></span><strong>' + (options.dropLabel || 'Bild auswählen oder hier ablegen') + '</strong><span class="contactpanda-image-picker-preview"></span><small class="contactpanda-image-picker-status" aria-live="polite"></small>';
		var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'contactpanda-image-picker-remove contactpanda-list-action'; remove.setAttribute('aria-label', options.removeLabel || 'Bild entfernen'); remove.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
		function render(notify) { var preview=drop.querySelector('.contactpanda-image-picker-preview'); preview.innerHTML=item?'<img src="'+String(item.url).replace(/"/g,'&quot;')+'" alt="">':''; remove.hidden=!item; if(notify&&options.onChange)options.onChange(item); }
		function select(image) { item=image?{id:Number(image.id||image.attachment_id||0),url:image.url||image.source_url||'',alt:image.alt||image.alt_text||''}:null; render(true); }
		function open() { if(!window.wp||!wp.media)return; var frame=wp.media({title:options.title||'Bild auswählen',button:{text:options.buttonLabel||'Bild verwenden'},multiple:false,library:{type:'image'}}); frame.on('select',function(){select(frame.state().get('selection').first().toJSON());}); frame.open(); }
		function upload(files) { var file=Array.prototype.find.call(files||[],function(candidate){return /^image\//.test(candidate.type);}); if(!file||!window.wp||!wp.apiFetch)return; var data=new FormData(); data.append('file',file,file.name); wp.apiFetch({path:'/wp/v2/media',method:'POST',body:data}).then(select); }
		drop.addEventListener('click',open); drop.addEventListener('dragover',function(event){event.preventDefault();}); drop.addEventListener('drop',function(event){event.preventDefault();upload(event.dataTransfer.files);}); remove.addEventListener('click',function(){select(null);});
		root.append(drop,remove); root.contactPandaSetImages=function(items){select(items&&items[0]?items[0]:null);}; render(false); return root;
	}

	window.ContactPandaAdminUI = window.ContactPandaAdminUI || {};
	if (typeof window.ContactPandaAdminUI.createImagePicker !== 'function') window.ContactPandaAdminUI.createImagePicker = createImagePicker;
	window.ContactPandaAdminUI.enhanceLargeActions = window.ContactPandaAdminUI.enhanceLargeActions || scan;

	document.addEventListener('DOMContentLoaded', function () {
		scan(document);
		new MutationObserver(function (records) {
			records.forEach(function (record) {
				record.addedNodes.forEach(function (node) { if (node.nodeType === 1) scan(node); });
			});
		}).observe(document.body, {childList: true, subtree: true});
	});
}());

