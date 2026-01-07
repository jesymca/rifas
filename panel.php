<?php
require_once 'app/config.php';
require_once 'app/auth.php';
requireLogin();
requirePago();   // si no ha pagado lo manda a declarar pago
include 'assets/header.php';
?>
<div class="container">
  <h4>Hola <?=htmlspecialchars(user())?> – tu pago vence el <?=$_SESSION['pago_hasta']?></h4>
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
        $s=$_GET['s']??'config';
        if($s=='config')  include 'secciones/config_rifa.php';
        if($s=='numeros') include 'secciones/lista_numeros.php';
        if($s=='ventas')  include 'secciones/ventas.php';
        if($s=='pago')    include 'secciones/declarar_pago.php';
      ?>
    </div>
  </div>
</div>
<?php include 'assets/footer.php';