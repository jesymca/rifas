<?php
require_once 'app/db.php';
$id_user=$_SESSION['uid'];
if($_POST){
  // validar fechas, digitos, etc.
  $st=$pdo->prepare("INSERT INTO rifas (id_usuario,titulo,fecha_inicio,fecha_fin,digitos,precio_unitario,paquetes,metodos_pago,permite_inverso,permite_ultimos5)
                     VALUES (?,?,?,?,?,?,?,?,?,?)");
  $paq=json_encode($_POST['paquetes']);   // array 2,7,10...
  $mp =json_encode($_POST['metodos']);
  $st->execute([$id_user,$_POST['titulo'],$_POST['fi'],$_POST['ff'],$_POST['digitos'],
                $_POST['precio'],$paq,$mp,$_POST['inverso'],$_POST['ult5']]);
  $id_rifa=$pdo->lastInsertId();
  // generar números
  $max=pow(10,$_POST['digitos'])-1;
  $st=$pdo->prepare("INSERT INTO numeros (id_rifa,numero) VALUES (?,?)");
  $pdo->beginTransaction();
  for($i=0;$i<=$max;$i++){
      $num=str_pad($i,$_POST['digitos'],'0',STR_PAD_LEFT);
      $st->execute([$id_rifa,$num]);
  }
  $pdo->commit();
  echo "<div class='alert alert-success'>Rifa creada con ".($max+1)." números.</div>";
}
?>
<form method="post">
  <div class="mb-3"><input name="titulo" class="form-control" placeholder="Nombre del sorteo" required></div>
  <div class="row mb-3">
    <div class="col"><input type="date" name="fi" class="form-control" required></div>
    <div class="col"><input type="date" name="ff" class="form-control" required></div>
  </div>
  <div class="mb-3">
    <label>Cantidad de dígitos</label>
    <select name="digitos" class="form-select"><option>3</option><option>6</option></select>
  </div>
  <div class="mb-3"><input type="number" name="precio" class="form-control" placeholder="Precio unitario (BS)" required></div>
  <div class="mb-3">
    <label>Paquetes (selecciona)</label>
    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="paquetes[]" value="2"> 2</div>
    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="paquetes[]" value="7"> 7</div>
    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="paquetes[]" value="10"> 10</div>
    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="paquetes[]" value="15"> 15</div>
    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="paquetes[]" value="20"> 20</div>
  </div>
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="inverso" value="1">
    <label class="form-check-label">Aceptar número inverso como 2do premio</label>
  </div>
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="ult5" value="1">
    <label class="form-check-label">Aceptar coincidencia últimos 5 dígitos</label>
  </div>
  <h5>Métodos de pago que aceptas</h5>
  <div id="metodos">
    <div class="row mb-2">
      <div class="col"><select name="metodos[0][tipo]" class="form-select"><option value="pm">PagoMóvil</option><option value="tr">Transferencia</option></select></div>
      <div class="col"><input name="metodos[0][banco]" class="form-control" placeholder="Banco"></div>
      <div class="col"><input name="metodos[0][ci]" class="form-control" placeholder="V-12345678"></div>
      <div class="col"><input name="metodos[0][tlf]" class="form-control" placeholder="0414-1234567"></div>
    </div>
  </div>
  <button class="btn btn-primary">Guardar rifa</button>
</form>