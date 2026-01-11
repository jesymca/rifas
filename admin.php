<?php
require_once 'app/config.php';
require_once 'app/auth.php';
requireLogin();
if(!esAdmin()){ header('Location: panel.php'); exit; }
$isSuper = isSuperAdmin();
$in_admin_page = true; // indicador para que las subpáginas no incluyan el header/footer cuando se muestran dentro de admin.php
$s = $_GET['s'] ?? ($isSuper ? 'super' : 'vendedores');
include 'assets/header.php';
?>
<div class="container">
  <h4><?= ($isSuper ? 'Panel Superadministrador' : 'Panel Admin') ?></h4>
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?=($s=='vendedores')? 'active':''?>" href="?s=vendedores">Vendedores</a></li>
    <li class="nav-item"><a class="nav-link <?=($s=='compradores')? 'active':''?>" href="?s=compradores">Compradores</a></li>
    <li class="nav-item"><a class="nav-link <?=($s=='pagos')? 'active':''?>" href="?s=pagos">Pagos pendientes</a></li>
    <?php if($isSuper): ?>
      <li class="nav-item"><a class="nav-link <?=($s=='super')? 'active':''?>" href="?s=super">Superadmin</a></li>
    <?php endif; ?>
  </ul>
  <?php
    if($s=='vendedores') include 'admin/vendedores.php';
    if($s=='compradores') include 'admin/compradores.php';
    if($s=='pagos') include 'admin/pagos.php';
    if($s=='super' && $isSuper) include 'admin/super.php';
  ?>
</div>
<?php include 'assets/footer.php';