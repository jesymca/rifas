<?php
require_once 'app/db.php';
$id_user=$_SESSION['uid'];
// asegurarnos de que la tabla pagos_vendedor tenga columnas para plan y aprobado
try{
  $tbl = $pdo->query("SHOW TABLES LIKE 'pagos_vendedor'")->fetch();
  if($tbl){
    $colPlan = $pdo->query("SHOW COLUMNS FROM pagos_vendedor LIKE 'plan'")->fetch();
    if(!$colPlan) $pdo->exec("ALTER TABLE pagos_vendedor ADD COLUMN plan VARCHAR(10) DEFAULT NULL");
    $colAprob = $pdo->query("SHOW COLUMNS FROM pagos_vendedor LIKE 'aprobado'")->fetch();
    if(!$colAprob) $pdo->exec("ALTER TABLE pagos_vendedor ADD COLUMN aprobado TINYINT(1) DEFAULT 0");
  }
  // Asegurar columnas en usuarios: plan y plan_pending
  $tblu = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch();
  if($tblu){
    $colUPlan = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'plan'")->fetch();
    if(!$colUPlan) $pdo->exec("ALTER TABLE usuarios ADD COLUMN plan VARCHAR(10) DEFAULT NULL");
    $colUPend = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'plan_pending'")->fetch();
    if(!$colUPend) $pdo->exec("ALTER TABLE usuarios ADD COLUMN plan_pending VARCHAR(10) DEFAULT NULL");
  }
} catch(PDOException $e){
  // si la tabla no existe o no se puede modificar en este entorno evitamos fallo fatal
} 

if($_POST){
  $plan = $_POST['plan'] ?? null;
  $st=$pdo->prepare("INSERT INTO pagos_vendedor (id_usuario,monto,banco,referencia,fecha_pago,plan)
                     VALUES (?,?,?,?,?,?)");
  $st->execute([$id_user,$_POST['monto'],$_POST['banco'],$_POST['ref'],$_POST['fecha'],$plan]);
  // Guardar la selección de plan en usuarios.plan_pending para referencia hasta que el pago sea aprobado
  try{
    $pdo->prepare("UPDATE usuarios SET plan_pending = ? WHERE id = ?")->execute([$plan, $id_user]);
  } catch(PDOException $e){
    // no fatal si la columna no existe o hay problema
  }
  echo "<div class='alert alert-info'>Pago declarado. Espera aprobación.</div>";
}
try{
  $st=$pdo->prepare("SELECT pago_hasta,plan FROM usuarios WHERE id=?");
  $st->execute([$id_user]);
  $rowUser = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e){
  // si no existe la columna 'plan' intentamos solo pago_hasta
  $st=$pdo->prepare("SELECT pago_hasta FROM usuarios WHERE id=?");
  $st->execute([$id_user]);
  $rowUser = $st->fetch(PDO::FETCH_ASSOC);
  $rowUser['plan'] = null;
}
$ph = $rowUser['pago_hasta'] ?? null;
$plan_pre = $_GET['plan'] ?? $rowUser['plan'] ?? 'BRONCE';
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
      <div class="mb-3">
        <label>Plan que estás pagando</label>
        <select name="plan" class="form-select">
          <option value="BRONCE" <?=($plan_pre=='BRONCE')? 'selected':''?>>BRONCE</option>
          <option value="PLATA"  <?=($plan_pre=='PLATA')? 'selected':''?>>PLATA</option>
          <option value="ORO"    <?=($plan_pre=='ORO')? 'selected':''?>>ORO</option>
        </select>
      </div>
      <button class="btn btn-warning">Enviar comprobante</button>
    </form>
  </div>
</div>