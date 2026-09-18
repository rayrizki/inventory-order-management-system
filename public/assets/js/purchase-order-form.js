'use strict';

// Baris item PO ditambah/dihapus di client (Vanilla JS, tanpa framework) -
// <template> di-clone supaya markup baris tidak perlu ditulis dua kali
// (server-side saat re-render error, client-side saat "+ Tambah Item").
(function () {
  const container = document.getElementById('po-items-body');
  const addButton = document.getElementById('po-add-item');
  const template = document.getElementById('po-item-template');

  if (!container || !addButton || !template) {
    return;
  }

  let rowIndex = container.querySelectorAll('.po-item-row').length;

  function bindRemove(row) {
    const removeBtn = row.querySelector('.po-item-remove');
    if (!removeBtn) {
      return;
    }

    removeBtn.addEventListener('click', function () {
      // Minimal satu baris harus tersisa - PO tidak bisa disubmit tanpa item.
      if (container.querySelectorAll('.po-item-row').length > 1) {
        row.remove();
      }
    });
  }

  container.querySelectorAll('.po-item-row').forEach(bindRemove);

  addButton.addEventListener('click', function () {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector('.po-item-row');

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
