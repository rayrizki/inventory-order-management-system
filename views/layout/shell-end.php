            </main>
        </div>
    </div>

    <dialog id="confirm-dialog" class="modal modal--confirm" aria-labelledby="confirm-dialog-title">
        <div class="modal__header">
            <h2 id="confirm-dialog-title">Konfirmasi</h2>
            <button type="button" class="modal__close" data-modal-close aria-label="Tutup">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal__body">
            <p data-confirm-message></p>
            <div class="form-actions">
                <button type="button" class="btn btn-danger" data-confirm-accept>Ya, Hapus</button>
                <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
            </div>
        </div>
    </dialog>

    <script src="/assets/js/app-shell.js" defer></script>
    <script src="/assets/js/form-validation.js" defer></script>
    <script src="/assets/js/list-controls.js" defer></script>
    <script src="/assets/js/modal-form.js" defer></script>
    <script src="/assets/js/confirm-dialog.js" defer></script>
</body>
</html>
