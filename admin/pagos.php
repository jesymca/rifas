<?php
require_once __DIR__.'/../app/db.php';
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
      $pdo->prepare("UPDATE pagos_vendedor SET aprobado=1 WHERE id=?")->execute([$id]);
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

?>
<h5>Pagos pendientes</h5>
<?php
$error = null;
$rows = [];
try{
  $stmt = $pdo->query("SELECT pv.*, u.usuario FROM pagos_vendedor pv LEFT JOIN usuarios u ON u.id = pv.id_usuario WHERE pv.aprobado = 0 ORDER BY pv.fecha_pago DESC");
  $rows = $stmt->fetchAll();
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
            <td><a href="?approve=<?=intval($r['id'])?>" class="btn btn-sm btn-success">Aprobar</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
