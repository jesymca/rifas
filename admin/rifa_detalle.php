<?php
require_once __DIR__.'/../app/db.php';
// Solo accesible desde admin.php para mantener rutas coherentes
if(!isset($in_admin_page)){
  $r = isset($_GET['r'])?intval($_GET['r']):0;
  header('Location: '.URL_BASE.'admin.php?s=super'.($r?('&r='.$r):'')); exit;
}

$r = intval($_GET['r'] ?? 0);
if(!$r){ echo "<div class='alert alert-warning'>Rifa no especificada.</div>"; return; }

try{
  $st = $pdo->prepare("SELECT * FROM rifas WHERE id = ? LIMIT 1");
  $st->execute([$r]);
  $rifa = $st->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e){ $rifa = false; }

if(!$rifa){ echo "<div class='alert alert-danger'>Rifa no encontrada.</div>"; return; }

// Totales
$total_numbers = null;
$sold_count = null;
$sold_list = [];
try{
  $st = $pdo->prepare("SELECT COUNT(*) FROM numeros WHERE id_rifa = ?");
  $st->execute([$r]);
  $total_numbers = (int)$st->fetchColumn();
} catch(PDOException $e){ $total_numbers = null; }

// Intentar detectar ventas en tabla 'ventas'
try{
  $hasVentas = $pdo->query("SHOW TABLES LIKE 'ventas'")->fetch();
  if($hasVentas){
    $col = $pdo->query("SHOW COLUMNS FROM ventas LIKE 'id_rifa'")->fetch();
    if($col){
      // Si existe columna 'cantidad' sumarla, si no contar filas
      $hasCantidad = $pdo->query("SHOW COLUMNS FROM ventas LIKE 'cantidad'")->fetch();
      if($hasCantidad){
        $st = $pdo->prepare("SELECT SUM(cantidad) FROM ventas WHERE id_rifa = ?");
        $st->execute([$r]);
        $sold_count = (int)$st->fetchColumn();
      } else {
        $st = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE id_rifa = ?");
        $st->execute([$r]);
        $sold_count = (int)$st->fetchColumn();
      }
      // intentar listar entradas (limitadas)
      $lst = $pdo->prepare("SELECT * FROM ventas WHERE id_rifa = ? ORDER BY id DESC LIMIT 200");
      $lst->execute([$r]);
      $sold_list = $lst->fetchAll(PDO::FETCH_ASSOC);
    }
  }
} catch(PDOException $e){ /* ignore */ }

// Si no hay 'ventas', intentar en 'numeros' columna 'vendido' o 'id_compra'
if($sold_count===null){
  try{
    $colV = $pdo->query("SHOW COLUMNS FROM numeros LIKE 'vendido'")->fetch();
    $colID = $pdo->query("SHOW COLUMNS FROM numeros LIKE 'id_compra'")->fetch();
    if($colV){
      $st = $pdo->prepare("SELECT COUNT(*) FROM numeros WHERE id_rifa = ? AND vendido = 1");
      $st->execute([$r]);
      $sold_count = (int)$st->fetchColumn();
      $lst = $pdo->prepare("SELECT numero FROM numeros WHERE id_rifa = ? AND vendido = 1 ORDER BY numero LIMIT 500");
      $lst->execute([$r]);
      $sold_list = $lst->fetchAll(PDO::FETCH_ASSOC);
    } elseif($colID){
      $st = $pdo->prepare("SELECT COUNT(*) FROM numeros WHERE id_rifa = ? AND id_compra IS NOT NULL");
      $st->execute([$r]);
      $sold_count = (int)$st->fetchColumn();
      $lst = $pdo->prepare("SELECT numero FROM numeros WHERE id_rifa = ? AND id_compra IS NOT NULL ORDER BY numero LIMIT 500");
      $lst->execute([$r]);
      $sold_list = $lst->fetchAll(PDO::FETCH_ASSOC);
    }
  } catch(PDOException $e){ /* ignore */ }
}

// Mostrar
?>
<h5>Detalle rifa: <?=htmlspecialchars($rifa['titulo'])?> (ID <?=htmlspecialchars($rifa['id'])?>)</h5>
<p><strong>Dígitos:</strong> <?=htmlspecialchars($rifa['digitos'] ?? '')?> — <strong>Precio:</strong> <?=htmlspecialchars($rifa['precio_unitario'] ?? '')?></p>
<p><strong>Total números:</strong> <?=($total_numbers===null? 'N/D': $total_numbers)?> — <strong>Vendidos:</strong> <?=($sold_count===null? 'N/D': $sold_count)?></p>
<?php if(count($sold_list)): ?>
  <h6 class="mt-3">Listado de ventas / números vendidos (máx 500)</h6>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr>
        <?php foreach(array_keys($sold_list[0]) as $c): ?><th><?=htmlspecialchars($c)?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
        <?php foreach($sold_list as $row): ?>
          <tr>
            <?php foreach($row as $v): ?>
              <td><?=htmlspecialchars($v)?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="alert alert-info">No hay datos de ventas disponibles para esta rifa (o no existe la tabla de ventas).</div>
<?php endif; ?>
<?php
// Fin
?>