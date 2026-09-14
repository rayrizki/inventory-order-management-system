'use strict';

(function () {
  function wireForm(form) {
    const requiredInputs = form.querySelectorAll('[required]');

    form.addEventListener('submit', function (event) {
      let hasEmpty = false;

      requiredInputs.forEach(function (input) {
        const hint = document.getElementById(input.id + '-required');
        const isEmpty = input.value.trim() === '';

        input.dataset.touched = 'true';

        if (hint) {
          hint.hidden = !isEmpty;
        }

        if (isEmpty) {
          hasEmpty = true;
        }
      });

      if (hasEmpty) {
        event.preventDefault();
      }
    });
  }

  document.querySelectorAll('form[novalidate]').forEach(wireForm);
})();
