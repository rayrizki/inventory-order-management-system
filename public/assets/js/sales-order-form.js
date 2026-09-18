'use strict';

// Sama persis dengan purchase-order-form.js (baris item ditambah/dihapus
// lewat clone <template>), cuma beda prefix id/class supaya kedua form bisa
// hidup di halaman yang berbeda tanpa bentrok.
(function () {
  const container = document.getElementById('so-items-body');
  const addButton = document.getElementById('so-add-item');
  const template = document.getElementById('so-item-template');

  if (!container || !addButton || !template) {
    return;
  }

  let rowIndex = container.querySelectorAll('.so-item-row').length;

  function bindRemove(row) {
    const removeBtn = row.querySelector('.so-item-remove');
    if (!removeBtn) {
      return;
    }

    removeBtn.addEventListener('click', function () {
      // Minimal satu baris harus tersisa - SO tidak bisa disubmit tanpa item.
      if (container.querySelectorAll('.so-item-row').length > 1) {
        row.remove();
      }
    });
  }

  container.querySelectorAll('.so-item-row').forEach(bindRemove);

  addButton.addEventListener('click', function () {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector('.so-item-row');

    fragment.querySelectorAll('[name]').forEach(function (el) {
      el.name = el.name.replace('__INDEX__', String(rowIndex));
    });
    fragment.querySelectorAll('[id]').forEach(function (el) {
      el.id = el.id.replace('__INDEX__', String(rowIndex));
    });
    fragment.querySelectorAll('[for]').forEach(function (el) {
      el.htmlFor = el.htmlFor.replace('__INDEX__', String(rowIndex));
    });

    container.appendChild(fragment);
    bindRemove(row);
    rowIndex += 1;
  });
})();
