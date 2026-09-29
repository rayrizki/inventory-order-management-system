'use strict';

/**
 * VAL-01: validasi di frontend DAN backend, dengan backend tetap sebagai
 * sumber kebenaran. Form sengaja memakai `novalidate` supaya pesan bawaan
 * browser (gelembung bahasa Inggris, posisinya tidak bisa diatur) tidak
 * muncul - tapi `novalidate` hanya mematikan TAMPILAN bawaan itu, bukan
 * Constraint Validation API-nya. Jadi atribut min/step/type="number" yang
 * sudah ada di markup tetap bisa dibaca lewat `input.validity` dan
 * ditampilkan memakai elemen hint milik aplikasi sendiri.
 *
 * Sebelumnya file ini hanya memeriksa field kosong, sehingga harga atau qty
 * negatif baru ketahuan setelah round-trip ke server.
 */
(function () {
  function hintFor(input) {
    return input.id ? document.getElementById(input.id + '-required') : null;
  }

  function constraintMessage(input) {
    var validity = input.validity;

    if (validity.badInput) {
      return 'Isi dengan angka yang valid.';
    }

    if (validity.rangeUnderflow) {
      return 'Nilai tidak boleh kurang dari ' + input.min + '.';
    }

    if (validity.rangeOverflow) {
      return 'Nilai tidak boleh lebih dari ' + input.max + '.';
    }

    if (validity.stepMismatch) {
      return 'Gunakan kelipatan ' + input.step + '.';
    }

    if (validity.typeMismatch || validity.patternMismatch) {
      return 'Format isian belum sesuai.';
    }

    return null;
  }

  function wireForm(form) {
    var fields = Array.prototype.slice.call(
      form.querySelectorAll('[required], input[type="number"]')
    );

    // Teks awal hint adalah pesan "wajib diisi" (atau pesan error dari
    // server) - disimpan supaya bisa dikembalikan setelah sempat diganti
    // pesan constraint.
    fields.forEach(function (input) {
      var hint = hintFor(input);

      if (hint && hint.dataset.defaultMessage === undefined) {
        hint.dataset.defaultMessage = hint.textContent.trim();
      }
    });

    form.addEventListener('submit', function (event) {
      var blocked = false;

      fields.forEach(function (input) {
        var hint = hintFor(input);
        var isEmpty = input.value.trim() === '';
        var message = null;

        input.dataset.touched = 'true';

        if (input.required && isEmpty) {
          message = hint ? hint.dataset.defaultMessage : '';
        } else if (!isEmpty) {
          message = constraintMessage(input);
        }

        if (hint) {
          if (message) {
            hint.textContent = message;
          }

          hint.hidden = message === null;
        }

        if (message !== null) {
          blocked = true;
        }
      });

      if (blocked) {
        event.preventDefault();
      }
    });
  }

  document.querySelectorAll('form[novalidate]').forEach(wireForm);
})();
