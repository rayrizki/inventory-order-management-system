<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Duplicate Code: delapan Service menulis ulang method yang persis sama untuk
 * merapikan kata kunci pencarian - trim, lalu ubah string kosong jadi null
 * supaya Repository bisa membedakan "tidak ada filter" dari "cari string
 * kosong". Aturannya satu dan sama di seluruh modul, jadi diangkat ke satu
 * tempat; trait dipilih (bukan kelas helper yang disuntik lewat constructor)
 * karena ini murni fungsi kecil tanpa state maupun dependency - menambah
 * satu objek beserta wiring-nya di delapan Service tidak menyelesaikan
 * masalah nyata apa pun.
 *
 * PurchaseOrderService dan SalesOrderService punya aturan tambahan (membuang
 * awalan "PO-"/"SO-" yang diketik user dari nomor di layar), jadi keduanya
 * meng-alias method ini lalu memanggilnya setelah awalannya dibuang.
 */
trait NormalizesSearchTerm
{
    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }
}
