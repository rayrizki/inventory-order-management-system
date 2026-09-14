'use strict';

(function () {
  document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
    field.addEventListener('change', function () {
      if (field.form) {
        field.form.submit();
      }
    });
  });

  // Tombol tutup pada banner sukses/error (data-dismiss-alert). Selain
  // menyembunyikan banner-nya, ?status= dibuang dari URL supaya refresh
  // halaman tidak memunculkan pesan yang sama lagi.
  document.querySelectorAll('[data-dismiss-alert]').forEach(function (button) {
    button.addEventListener('click', function () {
      const alertEl = button.closest('.form-success, .form-info, .form-warning, .form-error');
      if (alertEl) {
        alertEl.remove();
      }

      const url = new URL(window.location.href);
      if (url.searchParams.has('status')) {
        url.searchParams.delete('status');
        window.history.replaceState({}, '', url);
      }
    });
  });
})();
