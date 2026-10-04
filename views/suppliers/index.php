<?php
/**
 * Daftar Supplier - tampilannya dibagi dengan Customer lewat satu template
 * bersama (lihat `views/shared/supplier-customer-list.php` untuk alasannya
 * dan untuk pemicu kapan pembagian ini harus dibubarkan).
 *
 * @var \App\Entity\Supplier[] $suppliers
 * @var int $totalSuppliers
 */
$records = $suppliers;
$totalRecords = $totalSuppliers;
$entityLabel = 'Supplier';
$entityKey = 'supplier';
$listUrl = '/suppliers';
$navKey = 'suppliers';
$emptyStateContext = 'purchase order';

require_once __DIR__ . '/../shared/supplier-customer-list.php';
