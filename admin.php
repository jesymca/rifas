<?php
require_once 'app/config.php';
require_once 'app/auth.php';
requireLogin();
if(!esAdmin()){ header('Location: panel.php'); exit; }
include 'assets/header.php';
?>
<div class="container">
  <h4>Super Admin</h4>
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" href="?s=vendedores">Vendedores</a></li>
    <li class="nav-item"><a class="nav-link" href="?s=compradores">Compradores</a></li>
    <li class="nav-item"><a class="nav-link" href="?s=pagos">Pagos pendientes</a></li>
  </ul>
  <?php
    $s=$_GET['s']??'vendedores';
    if($s=='vendedores') include 'admin/vendedores.php';
    if($s=='compradores') include 'admin/compradores.php';
    if($s=='pagos') include 'admin/pagos.php';
  ?>
</div>
<?php include 'assets/footer.php';