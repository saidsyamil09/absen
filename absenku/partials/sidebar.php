<?php
$user = current_user();
?>
<aside id="sidebar" class="sidebar bg-dark text-white position-fixed">
  <div class="sidebar-header p-3">
    <h5 class="mb-0">E-Absensi</h5>
    <small>SMAN 2 LUWU TIMUR</small>
  </div>
  <ul class="nav flex-column p-2">
    <li class="nav-item"><a class="nav-link text-white" href="?page=dashboard"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
    <li class="nav-item"><a class="nav-link text-white" href="?page=rekap"><i class="fas fa-chart-bar mr-2"></i>Rekap Absensi Harian</a></li>
  <!--  <li class="nav-item"><a class="nav-link text-white" href="?page=scan"><i class="fas fa-qrcode mr-2"></i>Scan (Absensi)</a></li>  -->
  <!--  <li class="nav-item"><a class="nav-link text-white" href="?page=surat"><i class="fas fa-envelope mr-2"></i>Surat Panggilan</a></li>  -->
  <!--  <li class="nav-item"><a class="nav-link text-white" href="export_csv.php"><i class="fas fa-file-csv mr-2"></i>Export CSV</a></li> -->
  <!--  <li class="nav-item"><a class="nav-link text-white" href="?page=letter_print"><i class="fas fa-print mr-2"></i>Cetak Surat</a></li> -->
    <li class="nav-item mt-3"><a class="nav-link text-white" href="?page=logout"><i class="fas fa-sign-out-alt mr-2"></i>Logout (<?= htmlspecialchars($user['username']) ?>)</a></li>
  </ul>
</aside>