<?php
/**
 * Form Supplier (fallback non-JS / saat validasi gagal) - dibagi dengan
 * Customer lewat `views/shared/supplier-customer-form.php`.
 *
 * @var \App\Entity\Supplier|null $supplier null = create, ada isinya = edit
 */
$record = $supplier;
$entityLabel = 'Supplier';
$entityKey = 'supplier';
$listUrl = '/suppliers';
$navKey = 'suppliers';

require_once __DIR__ . '/../shared/supplier-customer-form.php';
