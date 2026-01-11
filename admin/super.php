<?php
require_once __DIR__.'/../app/db.php';
if(!isset($in_admin_page)){
  header('Location: '.URL_BASE.'admin.php?s=super'); exit;
}
// Dashboard del Superadmin: ver vendedores, cantidad de rifas, pagos pendientes
try{
  $stmt = $pdo->query("SELECT u.id,u.usuario,u.correo,u.rol,u.pago_hasta,u.plan,u.plan_pending, COUNT(DISTINCT r.id) AS rifas_count, SUM(CASE WHEN pv.aprobado = 0 THEN 1 ELSE 0 END) AS pagos_pendientes
                        FROM usuarios u
                        LEFT JOIN rifas r ON r.id_usuario = u.id
                        LEFT JOIN pagos_vendedor pv ON pv.id_usuario = u.id
                        GROUP BY u.id
                        ORDER BY rifas_count DESC, pagos_pendientes DESC");
  $users = $stmt->fetchAll();
} catch (PDOException $e){
  $users = [];
}
?>
<h5>Superadmin — Gestión de clientes</h5>
<p class="text-muted">Lista de usuarios que venden rifas, cantidad de rifas y pagos pendientes. Puedes revisar y validar pagos aquí.</p>
<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <tr>
        <th>ID</th>
        <th>Usuario</th>
        <th>Email</th>
        <th>Rol</th>
        <th>Rifas</th>
        <th>Pagos pendientes</th>
        <th>Pago hasta</th>
        <th>Plan</th>
        <th>Plan pendiente</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($users as $u): ?>
        <tr>
          <td><?=htmlspecialchars($u['id'])?></td>
          <td><?=htmlspecialchars($u['usuario'])?></td>
          <td><?=htmlspecialchars($u['correo'] ?? '')?></td>
          <td><?=htmlspecialchars($u['rol'] ?? '')?></td>
          <td><?=htmlspecialchars($u['rifas_count'])?></td>
          <td><?=htmlspecialchars($u['pagos_pendientes'] ?? 0)?></td>
          <td><?=htmlspecialchars($u['pago_hasta'] ?? '')?></td>
          <td><?=htmlspecialchars($u['plan'] ?? '')?></td>
          <td><?=htmlspecialchars($u['plan_pending'] ?? '')?></td>
          <td>
            <a href="admin/pagos.php?user=<?=intval($u['id'])?>" class="btn btn-sm btn-outline-primary">Ver pagos</a>
            <a href="admin/vendedores.php?user=<?=intval($u['id'])?>" class="btn btn-sm btn-outline-secondary">Ver rifas</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h5 class="mt-4">Pagos pendientes (global)</h5>
<p class="text-muted">Accede al listado de pagos pendientes para aprobar o rechazar.</p>
<p><a href="admin/pagos.php" class="btn btn-primary">Ir a Pagos pendientes</a></p>
