<?php
/** @var \App\Session\CurrentUser $currentUser */
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../layout/shell-start.php';
?>
            <h1>Selamat datang, <?= htmlspecialchars($currentUser->role->value) ?></h1>
<?php require __DIR__ . '/../layout/shell-end.php'; ?>
