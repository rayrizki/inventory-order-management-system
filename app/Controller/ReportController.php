<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Service\ReportService;
use App\Session\AuthGuard;
use DateTimeImmutable;

/**
 * REPORT-01. Hak unduh mengikuti baris "Mengunduh laporan (CSV)" pada tabel
 * peran brief §1.2: Admin boleh keduanya, Sales hanya "order miliknya"
 * (Purchase Order tidak termasuk, dan Sales Order disaring ke miliknya
 * sendiri), Warehouse Staff hanya "laporan stok" (pergerakan StockLedger).
 */
final class ReportController
{
    private const DEFAULT_RANGE_DAYS = 30;

    /** Siapa yang boleh mengunduh laporan pergerakan stok. */
    private const STOCK_REPORT_ROLES = [Role::Admin, Role::WarehouseStaff];

    /** Siapa yang boleh mengunduh laporan status order. */
    private const ORDER_REPORT_ROLES = [Role::Admin, Role::Sales];

    public function __construct(
        private readonly ReportService $reportService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        $currentUser = $this->guard->requireLogin();

        // Halaman laporan terbuka untuk semua role yang login; yang berbeda
        // adalah laporan mana yang ditawarkan - view memakai dua flag ini,
        // dan tiap endpoint unduh tetap memeriksa sendiri di server.
        $canDownloadStockReport = in_array($currentUser->role, self::STOCK_REPORT_ROLES, true);
        $canDownloadOrderReport = in_array($currentUser->role, self::ORDER_REPORT_ROLES, true);

        [$from, $to] = $this->resolveDateRange();

        require_once __DIR__ . '/../../views/reports/index.php';
    }

    public function exportStockLedgerCsv(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::STOCK_REPORT_ROLES);

        [$from, $to] = $this->resolveDateRange();
        $rows = $this->reportService->getStockLedgerReport($from, $to);

        $this->streamCsv(
            "stock-ledger_{$from}_{$to}.csv",
            ['Tanggal', 'SKU', 'Produk', 'Gudang', 'Tipe Pergerakan', 'Quantity', 'Referensi', 'Dilakukan Oleh'],
            $rows,
        );
    }

    public function exportOrdersCsv(): void
    {
        $currentUser = $this->guard->requireLogin();
        $this->guard->requireRole($currentUser, self::ORDER_REPORT_ROLES);

        // Sales: "order miliknya" - penyaringan dilakukan di server dari id
        // session, bukan dari parameter yang bisa diubah user.
        $onlyCreatedBy = $currentUser->role === Role::Sales ? $currentUser->id : null;

        [$from, $to] = $this->resolveDateRange();
        $rows = $this->reportService->getOrdersReport($from, $to, $onlyCreatedBy);

        $this->streamCsv(
            "order-status_{$from}_{$to}.csv",
            ['Tipe', 'Nomor', 'Tanggal', 'Pihak Terkait', 'Status', 'Dibuat Oleh', 'Disetujui Oleh'],
            $rows,
        );
    }

    /**
     * @return array{0: string, 1: string} [from, to] format Y-m-d, from <=
     *     to. Default 30 hari terakhir kalau query string hilang/tidak
     *     valid - bukan error, halaman laporan tetap harus bisa dibuka
     *     tanpa parameter sama sekali (mis. pertama kali diklik dari menu).
     */
    private function resolveDateRange(): array
    {
        $to = $this->parseDate($_GET['to'] ?? null) ?? date('Y-m-d');
        $from = $this->parseDate($_GET['from'] ?? null) ?? date('Y-m-d', strtotime($to . ' -' . self::DEFAULT_RANGE_DAYS . ' days'));

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    private function parseDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date->format('Y-m-d') : null;
    }

    /**
     * @param string[] $header
     * @param list<array<string, mixed>> $rows
     */
    private function streamCsv(string $filename, array $header, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, $header);
        foreach ($rows as $row) {
            fputcsv($output, array_map($this->neutralizeFormula(...), array_values($row)));
        }
        fclose($output);
        exit;
    }

    /**
     * Nilai yang diawali = + - @ (juga TAB/CR di depannya) diperlakukan
     * sebagai rumus oleh Excel/LibreOffice saat CSV dibuka. Karena isi kolom
     * berasal dari data yang diketik user (nama produk, supplier, customer,
     * user), satu baris seperti `=cmd|...` bisa berubah jadi rumus hidup di
     * komputer orang yang membuka laporan. Diawali kutip tunggal supaya
     * spreadsheet membacanya sebagai teks biasa; nilai yang tidak berbahaya
     * dibiarkan apa adanya agar laporan tetap enak dibaca.
     */
    private function neutralizeFormula(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\t\r]*[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }
}
