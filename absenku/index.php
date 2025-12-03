<?php
// Router: jika belum login => arahkan ke login (kecuali halaman login)
require_once __DIR__ . '/db.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

$public_pages = ['login', 'create_admin'];
if (!in_array($page, $public_pages)) {
    // require login for all other pages
    require_login();
}

$allowed = ['dashboard','rekap','surat','detail','login','logout','scan','create_admin','letter_print'];
if (!in_array($page, $allowed)) $page = 'dashboard';

if ($page !== 'login' && $page !== 'create_admin') {
    include __DIR__ . '/partials/header.php';
    include __DIR__ . '/partials/sidebar.php';
    echo '<div class="content p-4" style="margin-left:240px;">';
    include __DIR__ . "/pages/{$page}.php";
    echo '</div>';
    include __DIR__ . '/partials/footer.php';
} else {
    include __DIR__ . "/pages/{$page}.php";
}