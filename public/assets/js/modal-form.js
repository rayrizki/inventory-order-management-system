'use strict';

(function () {
  document.querySelectorAll('[data-modal-target]').forEach(function (trigger) {
    const dialog = document.querySelector(trigger.dataset.modalTarget);
    if (!dialog) {
      return;
    }

    trigger.addEventListener('click', function (event) {
      event.preventDefault();

      const form = dialog.querySelector('form');
      if (form) {
        form.action = trigger.dataset.formAction || form.action;

        dialog.querySelectorAll('[data-field]').forEach(function (field) {
          const key = field.dataset.field;
          field.value = trigger.dataset[toCamelCase(key)] || '';
          field.dataset.touched = 'false';
        });

        dialog.querySelectorAll('.form-hint--error').forEach(function (hint) {
          hint.hidden = true;
        });
      }

      dialog.showModal();
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (button) {
    button.addEventListener('click', function () {
      button.closest('dialog').close();
    });
  });

  function toCamelCase(text) {
    return text.replaceAll(/-([a-z])/g, function (_, letter) {
      return letter.toUpperCase();
    });
  }
})();
