<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Service\ReportService;
use App\Session\AuthGuard;
use DateTimeImmutable;

/**
 * REPORT-01. Cuma Admin yang boleh mengunduh laporan (brief SS1.2, tabel
 * peran: baris "Mengunduh laporan (CSV)" cuma tercentang di kolom Admin).
 */
final class ReportController
{
    private const DEFAULT_RANGE_DAYS = 30;

    public function __construct(
        private readonly ReportService $reportService,
        private readonly AuthGuard $guard,
    ) {
    }

    public function index(): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        [$from, $to] = $this->resolveDateRange();

        require __DIR__ . '/../../views/reports/index.php';
    }

    public function exportStockLedgerCsv(): void
    {
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

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
        $this->guard->requireRole($this->guard->requireLogin(), [Role::Admin]);

        [$from, $to] = $this->resolveDateRange();
        $rows = $this->reportService->getOrdersReport($from, $to);

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
            fputcsv($output, array_values($row));
        }
        fclose($output);
        exit;
    }
}
