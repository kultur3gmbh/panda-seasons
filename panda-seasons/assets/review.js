(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var data = document.querySelector('[data-editor-data]');
		if (!data || !window.pandaSeasonsReview) return;
		var composer = document.querySelector('[data-panda-seasons-composer]');
		if (!composer) return;
		var german = (document.documentElement.lang || '').indexOf('de') === 0;
		function label(de, en) { return german ? de : en; }
		var box = document.createElement('section');
		box.className = 'panda-seasons-review';
		box.style.cssText = 'grid-column:1/-1;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:16px 0;max-width:100%;box-sizing:border-box';
		var actions = document.createElement('div');
		actions.style.cssText = 'display:flex;gap:10px;flex-wrap:wrap';
		box.appendChild(actions);
		var output = document.createElement('div');
		output.setAttribute('aria-live', 'polite');
		box.appendChild(output);
		var shell = composer.closest('[data-panda-editor]') || composer;
		shell.parentNode.insertBefore(box, shell);
		var mainTabs = document.querySelector('.panda-seasons-main-tabs');
		var resultsPanel = document.createElement('section'); resultsPanel.className = 'panda-seasons-main-panel'; resultsPanel.dataset.mainEditorPanel = 'results'; resultsPanel.hidden = true;
		var resultsOutput = document.createElement('div'); resultsOutput.setAttribute('aria-live', 'polite'); resultsPanel.appendChild(resultsOutput);
		var resultsTab = document.createElement('button'); resultsTab.type = 'button'; resultsTab.className = 'button'; resultsTab.setAttribute('role', 'tab'); resultsTab.setAttribute('aria-selected', 'false'); resultsTab.dataset.mainEditorTab = 'results'; resultsTab.textContent = label('Kalender-Auswertungen', 'Calendar results');
		if (mainTabs) {
			mainTabs.appendChild(resultsTab); mainTabs.parentNode.appendChild(resultsPanel);
			mainTabs.addEventListener('click', function (event) { if (!event.target.closest('[data-main-editor-tab]')) return; var selected = event.target.closest('[data-main-editor-tab]'); resultsPanel.hidden = selected !== resultsTab; mainTabs.querySelectorAll('[data-main-editor-tab]').forEach(function(tab){tab.classList.toggle('is-primary',tab===selected);}); resultsTab.classList.toggle('button-primary', selected === resultsTab); resultsTab.setAttribute('aria-selected', selected === resultsTab ? 'true' : 'false'); });
			resultsTab.addEventListener('click', function () { request('results', resultsTab); });
		}
		function button(text, handler) {
			var node = document.createElement('button'); node.type = 'button'; node.className = 'button'; node.textContent = text; node.addEventListener('click', handler); actions.appendChild(node); return node;
		}
		function text(parent, value, tag) { var node = document.createElement(tag || 'p'); node.textContent = value; parent.appendChild(node); return node; }
		function request(mode, trigger) {
			var output = mode === 'results' ? resultsOutput : box.querySelector('[aria-live]');
			// Blur commits TinyMCE before the ordinary editor sync updates its model.
			if (document.activeElement) document.activeElement.blur();
			var active = document.querySelector('[data-door-modal]:not([hidden]) [data-door-field="number"]');
			if (active) active.dispatchEvent(new Event('change', {bubbles: true}));
			if (composer.prepareForSave) composer.prepareForSave();
			var body = new FormData();
			body.set('action', 'panda_seasons_review'); body.set('nonce', pandaSeasonsReview.nonce); body.set('mode', mode);
			body.set('calendar', (document.querySelector('[name="post_ID"]') || {}).value || '0');
			body.set('doors', JSON.stringify(composer.getDoors()));
			body.set('email_enabled', (document.querySelector('[name="panda_seasons_email_enabled"]') || {}).checked ? '1' : '');
			trigger.disabled = true; output.replaceChildren(); text(output, label('Wird geladen …', 'Loading …'));
			fetch(pandaSeasonsReview.url, {method: 'POST', credentials: 'same-origin', body: body}).then(function (response) { return response.json(); }).then(function (result) {
				if (!result.success) throw new Error(result.data && result.data.message || label('Prüfung fehlgeschlagen.', 'Review failed.'));
				output.replaceChildren();
				if (mode === 'check') {
					text(output, label('Kalender prüfen', 'Check calendar'), 'h3');
					var issues = result.data.issues;
					if (!issues.length) text(output, label('Keine Probleme bei Inhalt, Produkten, Datumswerten und Tagesmails gefunden.', 'No issues found in content, products, dates and daily emails.'));
					issues.forEach(function (issue) {
						var line = document.createElement('p'), link = document.createElement('button');
						link.type = 'button'; link.className = 'button-link'; link.textContent = label('Türchen ', 'Door ') + issue.number + ': ' + issue.message;
						link.addEventListener('click', function () { composer.openReviewDoor(issue.index); }); line.appendChild(link); output.appendChild(line);
					});
				} else {
					text(output, label('Was hat mein Kalender gebracht?', 'Calendar results'), 'h3');
					text(output, label('Öffnungen zählen Ereignisse, keine einzelnen Personen. Gutscheine: eindeutig zugeordnete Einlösungen in bezahlten, nicht vollständig erstatteten Bestellungen seit Einführung dieser Zuordnung. Geteilte und historische Gutscheine werden nicht nachträglich zugeordnet.', 'Openings count events, not unique visitors. Coupons count uniquely attributed redemptions in paid orders that have not been fully refunded, since attribution was introduced. Shared and historical coupons are not assigned retrospectively.'));
					var wrap = document.createElement('div'); wrap.style.overflowX = 'auto'; output.appendChild(wrap);
					var table = document.createElement('table'); table.className = 'widefat striped'; wrap.appendChild(table);
					var head = table.createTHead().insertRow();
					[label('Türnummer', 'Door number'), label('Türtitel', 'Door title'), label('Inhaltstyp', 'Content type'), label('Titel des Türinhalts', 'Door content title'), 'Views', label('Gutscheineinlösungen', 'Coupon redemptions'), label('Quizteilnehmer', 'Quiz participants')].forEach(function (value) { text(head, value, 'th'); });
					var tbody = table.createTBody();
					result.data.rows.forEach(function (item) { var row = tbody.insertRow(); [item.number, item.title, item.type, item.heading, item.openings, item.redemptions == null ? '—' : item.redemptions, item.participants == null ? '—' : item.participants].forEach(function (value) { text(row, value, 'td'); }); });
					text(output, label('Quizteilnehmer: Browser-Sitzungen mit mindestens einer abgesendeten Antwort, auch bei falschen Antworten. Wiederholungen in derselben Sitzung zählen einmal. Erfassung seit Version 0.9.7; frühere Teilnahmen sind nicht enthalten.', 'Quiz participants: browser sessions with at least one submitted answer, including incorrect answers. Repeat submissions in the same session count once. Recorded since version 0.9.7; earlier entries are not included.'));
					if (result.data.drawExport) { var link = document.createElement('a'); link.className = 'button'; link.href = result.data.drawExport; link.textContent = label('Verlosungsteilnahmen exportieren', 'Export prize draw entries'); output.appendChild(link); }
					if (result.data.limited) text(output, label('Die Gutscheinübersicht umfasst die letzten 1.000 zugeordneten Bestellungen.', 'Coupon results cover the last 1,000 attributed orders.'));
				}
			}).catch(function (error) { output.replaceChildren(); text(output, error.message); }).finally(function () { trigger.disabled = false; });
		}
		button(label('Kalender prüfen', 'Check calendar'), function (event) { request('check', event.currentTarget); });
		function discountFields() {
			var mode = document.querySelector('[data-door-field="discountMode"]');
			if (!mode) return;
			var current = mode.value || 'none';
			document.querySelectorAll('[data-discount-new]').forEach(function (node) { node.hidden = current !== 'new'; });
			document.querySelectorAll('[data-discount-existing]').forEach(function (node) { node.hidden = current !== 'existing'; });
			var reward = (document.querySelector('[data-door-field="quizReward"]') || {}).value || 'message';
			var source = (document.querySelector('[data-door-field="quizCouponMode"]') || {}).value || 'new';
			document.querySelectorAll('[data-quiz-coupon]').forEach(function (node) { node.hidden = reward !== 'coupon'; });
			document.querySelectorAll('[data-quiz-coupon-new]').forEach(function (node) { node.hidden = reward !== 'coupon' || source !== 'new'; });
			document.querySelectorAll('[data-quiz-coupon-template]').forEach(function (node) { node.hidden = reward !== 'coupon' || source !== 'existing'; });
		}
		document.addEventListener('change', discountFields);
		function syncDateTimes() {
			document.querySelectorAll('[data-discount-datetime]').forEach(function(input){var part=input.dataset.discountDatetime,date=document.querySelector('[data-door-field="discount'+part+'"]'),time=document.querySelector('[data-door-field="discount'+part+'Time"]'),opening=document.querySelector('[data-door-field="unlockDate"]');var day=date.value||(opening?opening.value:'');input.value=day?day+'T'+(time.value||(part==='Start'?'00:00':'23:59')):'';});
		}
		document.querySelectorAll('[data-discount-datetime]').forEach(function(input){input.addEventListener('input',function(){var part=input.dataset.discountDatetime,values=input.value.split('T'),date=document.querySelector('[data-door-field="discount'+part+'"]'),time=document.querySelector('[data-door-field="discount'+part+'Time"]');date.value=values[0]||'';time.value=values[1]||(part==='Start'?'00:00':'23:59');date.dispatchEvent(new Event('input',{bubbles:true}));time.dispatchEvent(new Event('input',{bubbles:true}));});});
		var unlock=document.querySelector('[data-door-field="unlockDate"]');if(unlock)unlock.addEventListener('change',syncDateTimes);
		var modal = document.querySelector('[data-door-modal]');
		if (modal) new MutationObserver(function(){discountFields();syncDateTimes();}).observe(modal, {attributes: true, attributeFilter: ['hidden']});
		['contentHeading','emailSubject','emailHeading'].forEach(function(name){
			var input=document.querySelector('[data-door-field="'+name+'"]'); if(!input)return;
			var tokens=document.createElement('div');tokens.className='panda-seasons-offer-tokens';
			function insert(value){var a=input.selectionStart||0,b=input.selectionEnd||0;input.value=input.value.slice(0,a)+value+input.value.slice(b);input.focus();input.setSelectionRange(a+value.length,a+value.length);input.dispatchEvent(new Event('input',{bubbles:true}));}
			[['{date}',label('Datum','Date')],['{discount}',label('Rabatt','Discount')],['{product}',label('Produktname','Product name')]].forEach(function(entry){var chip=document.createElement('button');chip.type='button';chip.className='button';chip.textContent=entry[1]+' '+entry[0];chip.draggable=true;chip.addEventListener('click',function(){insert(entry[0]);});chip.addEventListener('dragstart',function(event){event.dataTransfer.setData('text/panda-offer-token',entry[0]);event.dataTransfer.setData('text/plain',entry[0]);});tokens.appendChild(chip);});
			input.parentNode.appendChild(tokens);input.addEventListener('dragover',function(event){if(Array.from(event.dataTransfer.types).includes('text/panda-offer-token')){event.preventDefault();input.classList.add('is-token-drop-target');}});input.addEventListener('dragleave',function(){input.classList.remove('is-token-drop-target');});input.addEventListener('drop',function(event){var value=event.dataTransfer.getData('text/panda-offer-token');if(!value)return;event.preventDefault();input.classList.remove('is-token-drop-target');insert(value);});
		});
		var heading=document.querySelector('[data-door-field="contentHeading"]');
		var basics=document.querySelector('[data-door-section="basics"] .panda-seasons-door-modal__section-grid');
		if(heading&&basics)basics.appendChild(heading.closest('label'));
		if(heading){var suggest=document.createElement('button');suggest.type='button';suggest.className='button';suggest.dataset.suggestOfferHeading='';suggest.textContent=label('Standard vorschlagen','Suggest default');heading.parentNode.appendChild(suggest);}
		discountFields();
	});
})();
