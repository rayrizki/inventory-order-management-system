'use strict';

// SEMENTARA: kredensial dicek langsung di client dengan nilai dummy.
// Ini bukan validasi asli - hanya untuk menguji tampilan error sebelum
// AuthService + database siap (lihat docs/quality/tech-debt.md #1).
// Setelah backend jalan, pengecekan "salah/benar" harus pindah ke server
// (AUTH-01); JS di sini hanya boleh tersisa untuk cek field kosong.
(function () {
  let form = document.querySelector('.auth-page form');
  if (!form) {
    return;
  }

  let emailInput = document.getElementById('email');
  let passwordInput = document.getElementById('password');
  let emailRequiredHint = document.getElementById('email-required');
  let passwordRequiredHint = document.getElementById('password-required');
  let credentialError = document.getElementById('login-error');

  let DUMMY_EMAIL = 'admin';
  let DUMMY_PASSWORD = 'admin';

  function markField(input, hintEl, isEmpty) {
    input.dataset.touched = 'true';
    hintEl.hidden = !isEmpty;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    let emailEmpty = emailInput.value.trim() === '';
    let passwordEmpty = passwordInput.value.trim() === '';

    markField(emailInput, emailRequiredHint, emailEmpty);
    markField(passwordInput, passwordRequiredHint, passwordEmpty);

    if (emailEmpty || passwordEmpty) {
      credentialError.hidden = true;
      return;
    }

    let isValidCredential =
      emailInput.value.trim() === DUMMY_EMAIL &&
      passwordInput.value === DUMMY_PASSWORD;

    credentialError.hidden = isValidCredential;

    if (isValidCredential) {
      globalThis.alert('Login berhasil (sementara - belum terhubung ke backend).');
    }
  });
})();
