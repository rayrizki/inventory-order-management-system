'use strict';

(function () {
  const dialog = document.getElementById('confirm-dialog');
  if (!dialog) {
    return;
  }

  const messageEl = dialog.querySelector('[data-confirm-message]');
  const acceptButton = dialog.querySelector('[data-confirm-accept]');
  let pendingForm = null;

  acceptButton.addEventListener('click', function () {
    dialog.close();

    if (pendingForm) {
      pendingForm.submit();
      pendingForm = null;
    }
  });

  dialog.addEventListener('close', function () {
    pendingForm = null;
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      pendingForm = form;
      messageEl.textContent = form.dataset.confirm;
      dialog.showModal();
    });
  });
})();
