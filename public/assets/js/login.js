'use strict';

// Validasi di sini hanya untuk "field kosong" (kenyamanan UX, VAL-01).
// Benar/salah kredensial ditentukan sepenuhnya oleh server (POST /login ->
// AuthService::authenticate()) - JS ini tidak pernah menilai kredensial.
(function () {
  const form = document.querySelector('.auth-page form');
  if (!form) {
    return;
  }

  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const emailRequiredHint = document.getElementById('email-required');
  const passwordRequiredHint = document.getElementById('password-required');

  function markField(input, hintEl, isEmpty) {
    input.dataset.touched = 'true';
    hintEl.hidden = !isEmpty;
  }

  form.addEventListener('submit', function (event) {
    const emailEmpty = emailInput.value.trim() === '';
    const passwordEmpty = passwordInput.value.trim() === '';

    markField(emailInput, emailRequiredHint, emailEmpty);
    markField(passwordInput, passwordRequiredHint, passwordEmpty);

    if (emailEmpty || passwordEmpty) {
      event.preventDefault();
    }
    // Kalau kedua field terisi, form dibiarkan submit asli ke POST /login.
  });
})();
