<?php
require_once __DIR__.'/../app/db.php';
if(!isset($in_admin_page)){
  header('Location: '.URL_BASE.'admin.php?s=vendedores'); exit;
}
?>
<h5>Vendedores / Rifas</h5>
<?php
// Si se pasa user=ID mostramos las rifas de ese usuario
if(isset($_GET['user'])){
  $uid = intval($_GET['user']);
  try{
    $st = $pdo->prepare("SELECT * FROM rifas WHERE id_usuario = ?");
    $st->execute([$uid]);
    $rifas = $st->fetchAll();
  } catch (PDOException $e){
    $rifas = [];
    $error = $e->getMessage();
  }
  if(!empty($error)){
    echo "<div class='alert alert-danger'>Error: ".htmlspecialchars($error)."</div>";
  } elseif(empty($rifas)){
    echo "<div class='alert alert-warning'>No hay rifas para este usuario.</div>";
  } else {
    echo "<div class='table-responsive'><table class='table table-striped'><thead><tr><th>ID</th><th>Título</th><th>Dígitos</th><th>Precio</th><th>Fecha inicio</th><th>Fecha fin</th><th>Ventas</th></tr></thead><tbody>";
    foreach($rifas as $r){
      $link = 'admin.php?s=super&r=' . intval($r['id']);
      echo "<tr><td>".htmlspecialchars($r['id'])."</td><td>".htmlspecialchars($r['titulo'])."</td><td>".htmlspecialchars($r['digitos'])."</td><td>".htmlspecialchars($r['precio_unitario'] ?? $r['precio'])."</td><td>".htmlspecialchars($r['fecha_inicio'])."</td><td>".htmlspecialchars($r['fecha_fin'])."</td><td><a href='".$link."' class='btn btn-sm btn-outline-primary'>Ver ventas</a></td></tr>";
    }
    echo "</tbody></table></div>";
  }
  return;
}

// Listado de usuarios que venden (usuarios con rifas o rol vendedor)
$error = null;
$rows = [];
try{
  $stmt = $pdo->query("SELECT u.id,u.usuario,u.correo,u.rol, COUNT(r.id) AS rifas FROM usuarios u LEFT JOIN rifas r ON r.id_usuario = u.id GROUP BY u.id HAVING rifas>0 OR u.rol='vendedor'");
  $rows = $stmt->fetchAll();
} catch (PDOException $e){
  $error = $e->getMessage();
}

if($error): ?>
  <div class="alert alert-danger" role="alert">
    Ocurrió un error al obtener los vendedores.
  </div>
<?php elseif(count($rows) === 0): ?>
  <div class="alert alert-warning" role="alert">
    No hay vendedores registrados.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Usuario</th>
          <th>Email</th>
          <th>Rol</th>
          <th>Rifas</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?=htmlspecialchars($r['id'])?></td>
            <td><?=htmlspecialchars($r['usuario'])?></td>
            <td><?=htmlspecialchars($r['correo'] ?? '')?></td>
            <td><?=htmlspecialchars($r['rol'] ?? '')?></td>
            <td><?=htmlspecialchars($r['rifas'])?></td>
            <td>
              <a href="?user=<?=intval($r['id'])?>" class="btn btn-sm btn-outline-secondary">Ver rifas</a>
              <a href="pagos.php?user=<?=intval($r['id'])?>" class="btn btn-sm btn-outline-primary">Ver pagos</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
