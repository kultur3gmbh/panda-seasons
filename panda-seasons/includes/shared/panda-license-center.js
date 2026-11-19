(function () {
 'use strict';
 var feedback = document.getElementById('contactpanda-license-feedback');
 if (feedback) feedback.focus({preventScroll: false});
 document.querySelectorAll('.panda-license-center form').forEach(function (form) {
  form.addEventListener('submit', function () {
   form.setAttribute('aria-busy', 'true');
   form.querySelectorAll('button').forEach(function (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
  });
 });
}());
