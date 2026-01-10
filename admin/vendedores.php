<?php
require_once __DIR__.'/../app/db.php';
?>
<h5>Vendedores</h5>
<?php
// Intentamos obtener vendedores de la base de datos
$error = null;
$rows = [];
try{
  $stmt = $pdo->query("SELECT * FROM vendedores");
  $rows = $stmt->fetchAll();
} catch (PDOException $e){
  // Si falla la consulta (tabla no existe, etc.) tratamos como "sin datos" pero registramos el error
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
          <?php foreach(array_keys($rows[0]) as $col): ?>
            <th><?=htmlspecialchars($col)?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <?php foreach($r as $v): ?>
              <td><?=htmlspecialchars($v)?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
