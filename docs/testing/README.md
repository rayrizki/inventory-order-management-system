# Testing

Isi folder ini:

- **`manual-acceptance-checklist.md`** - skenario uji manual menyeluruh yang
  dijalankan sekali penuh dari folder bersih sebelum tag release dibuat
  (§5.1). Urutannya mengikuti agenda demo §8.1, jadi sekaligus jadi latihan
  demo. Mulai dari sini.
- **`*-slice.md`** - skenario dan hasil test per slice, ditulis saat slice
  itu dikerjakan (auth, master data, purchase order, sales order, user
  management, dashboard & laporan).
- **`seed-data-verification.md`** - pembuktian seed memenuhi §7.1.
- **`clean-rebuild-verification.md`** - hasil uji build dari kondisi bersih,
  termasuk karakteristik startup yang sudah diketahui.

Yang dicakup keseluruhannya: skenario test (termasuk input tidak valid untuk
VAL-01), hasil unit + integration test, bukti responsive UI-01 dan empty
state VIEW-01, serta known bugs/limitations (yang terbuka dicatat di
`docs/quality/tech-debt.md`).
