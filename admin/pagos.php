<?php
require_once __DIR__.'/../app/db.php';
// Si se accede directamente a este script, redirigir a admin.php para mantener rutas coherentes
if(!isset($in_admin_page)){
  header('Location: '.URL_BASE.'admin.php?s=pagos'); exit;
}
// Asegurar columnas metadata en pagos_vendedor
try{
  $tbl = $pdo->query("SHOW TABLES LIKE 'pagos_vendedor'")->fetch();
  if($tbl){
    $c1 = $pdo->query("SHOW COLUMNS FROM pagos_vendedor LIKE 'aprobado_por'")->fetch();
    if(!$c1) $pdo->exec("ALTER TABLE pagos_vendedor ADD COLUMN aprobado_por VARCHAR(100) DEFAULT NULL");
    $c2 = $pdo->query("SHOW COLUMNS FROM pagos_vendedor LIKE 'aprobado_fecha'")->fetch();
    if(!$c2) $pdo->exec("ALTER TABLE pagos_vendedor ADD COLUMN aprobado_fecha DATETIME DEFAULT NULL");
    $c3 = $pdo->query("SHOW COLUMNS FROM pagos_vendedor LIKE 'aprobado_comentario'")->fetch();
    if(!$c3) $pdo->exec("ALTER TABLE pagos_vendedor ADD COLUMN aprobado_comentario TEXT DEFAULT NULL");
  }
} catch(PDOException $e){ /* skip errors */ }

// Aprobar pagos pendientes (solo admin)
if(isset($_GET['approve'])){
  try{
    $id = intval($_GET['approve']);
    $st=$pdo->prepare("SELECT * FROM pagos_vendedor WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if($p){
      // marcar aprobado y actualizar usuario
      $pdo->beginTransaction();
      $pdo->prepare("UPDATE pagos_vendedor SET aprobado=1, aprobado_por=?, aprobado_fecha=NOW() WHERE id=?")->execute([($_SESSION['usuario'] ?? 'admin'), $id]);
      $hasta = date('Y-m-d', strtotime('+30 days'));
      // obtener plan desde el pago o desde usuarios.plan_pending si no viene en el pago
      $plan_to_set = $p['plan'] ?? null;
      try{
        if(!$plan_to_set){
          $stp = $pdo->prepare("SELECT plan_pending FROM usuarios WHERE id = ? LIMIT 1");
          $stp->execute([$p['id_usuario']]);
          $plan_to_set = $stp->fetchColumn();
        }
      } catch(PDOException $e){
        // ignorar
      }
      // Actualizar usuario: pago_hasta y plan (si existe), y limpiar plan_pending
      if($plan_to_set){
        $pdo->prepare("UPDATE usuarios SET pago_hasta=?, plan=?, plan_pending = NULL WHERE id=?")->execute([$hasta, $plan_to_set, $p['id_usuario']]);
      } else {
        $pdo->prepare("UPDATE usuarios SET pago_hasta=? WHERE id=?")->execute([$hasta, $p['id_usuario']]);
      }
      $pdo->commit();
      echo "<div class='alert alert-success'>Pago aprobado. Usuario habilitado hasta $hasta</div>";
    }
  } catch(PDOException $e){
    echo "<div class='alert alert-danger'>Error al aprobar pago: ".htmlspecialchars($e->getMessage())."</div>";
  }
}

// Rechazar pago
if(isset($_GET['decline'])){
  try{
    $id = intval($_GET['decline']);
    $reason = $_GET['reason'] ?? null;
    $st=$pdo->prepare("SELECT * FROM pagos_vendedor WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if($p){
      $pdo->prepare("UPDATE pagos_vendedor SET aprobado=2, aprobado_por=?, aprobado_fecha=NOW(), aprobado_comentario=? WHERE id=?")->execute([($_SESSION['usuario'] ?? 'admin'), $reason, $id]);
      echo "<div class='alert alert-info'>Pago rechazado.".($reason? ' Motivo: '.htmlspecialchars($reason): '')."</div>";
    }
  } catch(PDOException $e){
    echo "<div class='alert alert-danger'>Error al rechazar pago: ".htmlspecialchars($e->getMessage())."</div>";
  }
}
?>
<h5>Pagos pendientes</h5>
<?php
$error = null;
$rows = [];
try{
  if(isset($_GET['user'])){
    $uid = intval($_GET['user']);
    $stmt = $pdo->prepare("SELECT pv.*, u.usuario FROM pagos_vendedor pv LEFT JOIN usuarios u ON u.id = pv.id_usuario WHERE pv.id_usuario = ? ORDER BY pv.fecha_pago DESC");
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll();
  } else {
    $stmt = $pdo->query("SELECT pv.*, u.usuario FROM pagos_vendedor pv LEFT JOIN usuarios u ON u.id = pv.id_usuario WHERE pv.aprobado = 0 ORDER BY pv.fecha_pago DESC");
    $rows = $stmt->fetchAll();
  }
} catch (PDOException $e){
  $error = $e->getMessage();
}

if($error): ?>
  <div class="alert alert-danger" role="alert">
    Ocurrió un error al obtener los pagos.
  </div>
<?php elseif(count($rows) === 0): ?>
  <div class="alert alert-warning" role="alert">
    No hay pagos pendientes.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Usuario</th>
          <th>Monto</th>
          <th>Banco</th>
          <th>Referencia</th>
          <th>Fecha pago</th>
          <th>Plan</th>
          <th>Estado</th>
          <th>Aprobado por</th>
          <th>Fecha</th>
          <th>Comentario</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?=htmlspecialchars($r['id'])?></td>
            <td><?=htmlspecialchars($r['usuario'] ?? $r['id_usuario'])?></td>
            <td><?=htmlspecialchars($r['monto'])?></td>
            <td><?=htmlspecialchars($r['banco'])?></td>
            <td><?=htmlspecialchars($r['referencia'])?></td>
            <td><?=htmlspecialchars($r['fecha_pago'])?></td>
            <td><?=htmlspecialchars($r['plan'])?></td>
            <td><?php
                $stt = $r['aprobado'] ?? 0;
                if($stt == 1) echo '<span class="badge bg-success">Aprobado</span>';
                else if($stt == 2) echo '<span class="badge bg-danger">Rechazado</span>';
                else echo '<span class="badge bg-warning text-dark">Pendiente</span>';
              ?></td>
            <td><?=htmlspecialchars($r['aprobado_por'] ?? '')?></td>
            <td><?=htmlspecialchars($r['aprobado_fecha'] ?? '')?></td>
            <td><?=htmlspecialchars($r['aprobado_comentario'] ?? '')?></td>
            <td>
              <a href="?approve=<?=intval($r['id'])?>" class="btn btn-sm btn-success">Aprobar</a>
              <a href="javascript:if(confirm('¿Rechazar pago?')){ var reason = prompt('Motivo (opcional)'); window.location.href='?decline=<?=intval($r['id'])?>'+(reason?('&reason='+encodeURIComponent(reason)):''); }" class="btn btn-sm btn-danger">Rechazar</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

