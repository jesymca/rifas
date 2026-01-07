<?php
require_once 'app/db.php';
$id_user=$_SESSION['uid'];
if($_POST){
  $st=$pdo->prepare("INSERT INTO pagos_vendedor (id_usuario,monto,banco,referencia,fecha_pago)
                     VALUES (?,?,?,?,?)");
  $st->execute([$id_user,$_POST['monto'],$_POST['banco'],$_POST['ref'],$_POST['fecha']]);
  echo "<div class='alert alert-info'>Pago declarado. Espera aprobación.</div>";
}
$st=$pdo->prepare("SELECT pago_hasta FROM usuarios WHERE id=?");
$st->execute([$id_user]);
$ph=$st->fetchColumn();
?>
<div class="card">
  <div class="card-body">
    <h5>Declarar pago mensual (30 días)</h5>
    <p class="text-warning">Tu acceso vence el <b><?=$ph?></b></p>
    <form method="post">
      <div class="mb-3"><input type="number" name="monto" class="form-control" placeholder="Monto en BS" required></div>
      <div class="mb-3"><input name="banco" class="form-control" placeholder="Banco emisor" required></div>
      <div class="mb-3"><input name="ref" class="form-control" placeholder="Número de referencia" required></div>
      <div class="mb-3"><input type="date" name="fecha" class="form-control" required></div>
      <button class="btn btn-warning">Enviar comprobante</button>
    </form>
  </div>
</div>