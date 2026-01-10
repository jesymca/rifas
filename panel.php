<?php

ini_set('display_errors', 1); 
error_reporting(E_ALL);


require_once 'app/config.php';
require_once 'app/auth.php';
requireLogin();
// No forzamos el redireccionamiento aquí: permitimos ver la sección de configuración
include 'assets/header.php';
?>
<?php
  $s=$_GET['s']??'config';
  $hasPago = hasPago();
?>
<div class="container">
  <h4>Hola <?=htmlspecialchars(user())?> – tu pago vence el <?=$_SESSION['pago_hasta']?></h4>
  <?php if($s === 'config' && !$hasPago): ?>
    <!-- Usuario sin pago: mostramos solo las 3 opciones de planes en ancho completo -->
    <div class="row">
      <div class="col-12">
        <?php include 'secciones/config_rifa.php'; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="row">
      <div class="col-md-3">
        <div class="list-group">
          <a href="?s=config"  class="list-group-item list-group-item-action">Configurar rifa</a>
          <a href="?s=numeros" class="list-group-item list-group-item-action">Ver números</a>
          <a href="?s=ventas"  class="list-group-item list-group-item-action">Ventas</a>
          <a href="?s=pago"    class="list-group-item list-group-item-action">Declarar pago</a>
        </div>
      </div>
      <div class="col-md-9">
        <?php
          // permitir ver la página de configuración incluso si no hay pago; otras secciones requieren pago
          if($s !== 'config' && $s !== 'pago') requirePago();
          if($s=='config')  include 'secciones/config_rifa.php';
          if($s=='numeros') include 'secciones/lista_numeros.php';
          if($s=='ventas')  include 'secciones/ventas.php';
          if($s=='pago')    include 'secciones/declarar_pago.php';
        ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include 'assets/footer.php';