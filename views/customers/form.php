<?php
/**
 * Form Customer (fallback non-JS / saat validasi gagal) - dibagi dengan
 * Supplier lewat `views/shared/supplier-customer-form.php`.
 *
 * @var \App\Entity\Customer|null $customer null = create, ada isinya = edit
 */
$record = $customer;
$entityLabel = 'Customer';
$entityKey = 'customer';
$listUrl = '/customers';
$navKey = 'customers';

require __DIR__ . '/../shared/supplier-customer-form.php';
