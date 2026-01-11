<?php
require_once __DIR__.'/../app/db.php';
if(!isset($in_admin_page)){
  header('Location: '.URL_BASE.'admin.php?s=compradores'); exit;
}
?>
<h5>Compradores</h5>
<?php
$error = null;
$rows = [];
try{
  $stmt = $pdo->query("SELECT * FROM compradores");
  $rows = $stmt->fetchAll();
} catch (PDOException $e){
  $error = $e->getMessage();
}

if($error): ?>
  <div class="alert alert-danger" role="alert">
    Ocurrió un error al obtener los compradores.
  </div>
<?php elseif(count($rows) === 0): ?>
  <div class="alert alert-warning" role="alert">
    No hay compradores registrados.
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
