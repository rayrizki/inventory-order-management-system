<?php
/**
 * Daftar Customer - tampilannya dibagi dengan Supplier lewat satu template
 * bersama (lihat `views/shared/supplier-customer-list.php` untuk alasannya
 * dan untuk pemicu kapan pembagian ini harus dibubarkan).
 *
 * @var \App\Entity\Customer[] $customers
 * @var int $totalCustomers
 */
$records = $customers;
$totalRecords = $totalCustomers;
$entityLabel = 'Customer';
$entityKey = 'customer';
$listUrl = '/customers';
$navKey = 'customers';
$emptyStateContext = 'sales order';

require __DIR__ . '/../shared/supplier-customer-list.php';
