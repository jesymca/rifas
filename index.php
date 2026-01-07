<?php
require_once 'app/config.php';
$title='Rifas Venezuela';
include __DIR__.'/assets/header.php';
?>
<div class="container text-center mt-5">
  <h1 class="fw-bold mb-4">Rifas en línea</h1>
  <p class="lead">Compra tu número y gana con los sorteos de la Lotería del Táchira.</p>
  <a href="registro.php" class="btn btn-primary btn-lg">Vender rifas</a>
  <a href="login.php"    class="btn btn-outline-primary btn-lg ms-2">Ingresar</a>
</div>
<?php include __DIR__.'/assets/footer.php';