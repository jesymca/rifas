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

// Asegurar tabla de planes y obtener lista de planes (fallback a BRONCE/PLATA/ORO)
$planes = [['slug'=>'BRONCE','nombre'=>'BRONCE'],['slug'=>'PLATA','nombre'=>'PLATA'],['slug'=>'ORO','nombre'=>'ORO']];
try{
  $pdo->exec("CREATE TABLE IF NOT EXISTS planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $st = $pdo->query("SELECT slug,nombre,precio FROM planes WHERE activo=1 ORDER BY precio ASC, id ASC");
  $f = $st->fetchAll(PDO::FETCH_ASSOC);
  if($f && count($f)>0) $planes = $f;
  // si está vacía, sembrar defaults
  if(count($f)===0){
    $ins = $pdo->prepare("INSERT INTO planes (slug,nombre,descripcion,precio) VALUES (?,?,?,?)");
    $ins->execute(['BRONCE','BRONCE','Una rifa básica (6 dígitos)',10.00]);
    $ins->execute(['PLATA','PLATA','Rifa con premios por últimos dígitos y número invertido',25.00]);
    $ins->execute(['ORO','ORO','Rifas avanzadas con múltiples sorteos',50.00]);
    $st = $pdo->query("SELECT slug,nombre,precio FROM planes WHERE activo=1 ORDER BY precio ASC, id ASC");
    $f = $st->fetchAll(PDO::FETCH_ASSOC);
    if($f && count($f)>0) $planes = $f;
  }
}catch(PDOException $e){
  // ignoramos y usamos los defaults en $planes
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
          <?php foreach($planes as $p): $slug = $p['slug'] ?? $p['slug']; $label = $p['nombre'] ?? $p['slug']; ?>
            <option value="<?=htmlspecialchars($slug)?>" <?=($plan_pre===$slug)? 'selected':''?>><?=htmlspecialchars($label)?> <?=(isset($p['precio'])? ' - '.number_format($p['precio'],2,',','.'):'')?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-warning">Enviar comprobante</button>
    </form>
  </div>
</div>