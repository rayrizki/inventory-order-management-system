<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * Pola Post/Redirect/Get dipakai di seluruh Controller: setiap aksi POST yang
 * selesai diakhiri dengan `header('Location: ...')` lalu `exit`. Pasangan itu
 * tertulis 44 kali dan literal "Location: " sendiri berulang 4-9 kali per
 * Controller (temuan SonarQube php:S1192).
 *
 * Diangkat ke satu tempat bukan semata demi menghapus duplikasi literal, tapi
 * karena pasangannya rawan: `header()` TIDAK menghentikan eksekusi, jadi lupa
 * menulis `exit` setelahnya membuat kode di bawahnya tetap berjalan dan body
 * response ikut terkirim di belakang header redirect. Dengan satu method
 * bertipe `never`, kelalaian itu tidak mungkin terjadi lagi - dan PHPStan ikut
 * memverifikasi tidak ada kode tak terjangkau sesudah pemanggilannya.
 *
 * Trait, bukan kelas yang disuntik lewat constructor: ini pembungkus tipis di
 * atas fungsi global PHP, tanpa state maupun dependency - menambah objek
 * beserta wiring-nya di sembilan Controller hanya menambah upacara tanpa
 * menyelesaikan masalah nyata. Pola yang sama dengan
 * App\Service\NormalizesSearchTerm.
 */
trait SendsRedirects
{
    /**
     * 303 See Other, bukan 302: setelah POST berhasil, 303 memerintahkan
     * browser mengambil lokasi baru dengan GET - itu yang membuat refresh
     * halaman tidak mengirim ulang form (Post/Redirect/Get).
     */
    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
