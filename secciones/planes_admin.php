<?php
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/auth.php';
if(!isSuperAdmin()){
  echo "<div class='alert alert-danger'>Acceso denegado.</div>";
  return;
}
// Crear tabla de planes si no existe
try{
  $pdo->exec("CREATE TABLE IF NOT EXISTS planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  // Sembrar planes por defecto si la tabla está vacía
  $cnt = $pdo->query("SELECT COUNT(*) FROM planes")->fetchColumn();
  if($cnt == 0){
    $ins = $pdo->prepare("INSERT INTO planes (slug,nombre,descripcion,precio) VALUES (?,?,?,?)");
    $ins->execute(['BRONCE','BRONCE','Una rifa básica (6 dígitos) - permite 1 rifa y subida de foto',10.00]);
    $ins->execute(['PLATA','PLATA','Rifa con premios por últimos dígitos y número invertido',25.00]);
    $ins->execute(['ORO','ORO','Rifas avanzadas con múltiples sorteos y opciones',50.00]);
  }
}catch(PDOException $e){
  echo "<div class='alert alert-warning'>No se pudo asegurar la tabla de planes: ".htmlspecialchars($e->getMessage())."</div>";
}

// Manejar acciones POST: add / edit / delete
$action = $_POST['action'] ?? null;
if($action === 'add'){
  $slug = strtoupper(trim($_POST['slug'] ?? ''));
  $nombre = trim($_POST['nombre'] ?? '');
  $desc = trim($_POST['descripcion'] ?? '');
  $precio = floatval($_POST['precio'] ?? 0);
  if($slug && $nombre){
    try{
      $pdo->prepare("INSERT INTO planes (slug,nombre,descripcion,precio) VALUES (?,?,?,?)")
          ->execute([$slug,$nombre,$desc,$precio]);
      echo "<div class='alert alert-success'>Plan $slug creado.</div>";
    }catch(PDOException $e){ echo "<div class='alert alert-danger'>Error al crear plan: ".htmlspecialchars($e->getMessage())."</div>"; }
  } else echo "<div class='alert alert-danger'>Slug y nombre son requeridos.</div>";
}
if($action === 'edit'){
  $id = intval($_POST['id'] ?? 0);
  $nombre = trim($_POST['nombre'] ?? '');
  $desc = trim($_POST['descripcion'] ?? '');
  $precio = floatval($_POST['precio'] ?? 0);
  $activo = isset($_POST['activo']) ? 1 : 0;
  if($id && $nombre){
    try{
      $pdo->prepare("UPDATE planes SET nombre=?, descripcion=?, precio=?, activo=? WHERE id=?")
          ->execute([$nombre,$desc,$precio,$activo,$id]);
      echo "<div class='alert alert-success'>Plan actualizado.</div>";
    }catch(PDOException $e){ echo "<div class='alert alert-danger'>Error al actualizar: ".htmlspecialchars($e->getMessage())."</div>"; }
  } else echo "<div class='alert alert-danger'>Nombre es requerido.</div>";
}
if($action === 'delete'){
  $id = intval($_POST['id'] ?? 0);
  if($id){
    try{
      $slug = $pdo->prepare("SELECT slug FROM planes WHERE id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM planes WHERE id=?")->execute([$id]);
      echo "<div class='alert alert-success'>Plan eliminado.</div>";
    }catch(PDOException $e){ echo "<div class='alert alert-danger'>Error al eliminar: ".htmlspecialchars($e->getMessage())."</div>"; }
  }
}

// Obtener lista de planes
$planes = [];
try{
  $st = $pdo->query("SELECT * FROM planes ORDER BY precio ASC, id ASC");
  $planes = $st->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $e){ }
?>

<h4>Gestión de planes</h4>
<p class="text-muted">Aquí puedes crear, editar o eliminar planes. El <b>slug</b> (ej. BRONCE) determina reglas internas del sistema y debe mantenerse si quieres conservar las reglas actuales.</p>

<div class="row">
  <div class="col-md-6">
    <h5>Planes existentes</h5>
    <table class="table table-sm">
      <thead><tr><th>Slug</th><th>Nombre</th><th>Precio</th><th>Activo</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach($planes as $p): ?>
          <tr>
            <td><?=htmlspecialchars($p['slug'])?></td>
            <td><?=htmlspecialchars($p['nombre'])?></td>
            <td><?=number_format($p['precio'],2,',','.')?></td>
            <td><?=($p['activo']?'Sí':'No')?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('edit_<?= $p['id'] ?>').style.display='block'">Editar</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Eliminar plan <?=htmlspecialchars($p['slug'])?>?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
              </form>
            </td>
          </tr>
          <tr id="edit_<?= $p['id'] ?>" style="display:none;"><td colspan="5">
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <div class="col-md-4"><input name="nombre" class="form-control" value="<?=htmlspecialchars($p['nombre'])?>" required></div>
              <div class="col-md-3"><input name="precio" class="form-control" value="<?=htmlspecialchars($p['precio'])?>"></div>
              <div class="col-md-3"><input name="descripcion" class="form-control" value="<?=htmlspecialchars($p['descripcion'])?>"></div>
              <div class="col-md-1"><label class="form-check-label"><input type="checkbox" name="activo" <?= $p['activo'] ? 'checked':''?>> Activo</label></div>
              <div class="col-md-1"><button class="btn btn-sm btn-primary">Guardar</button></div>
            </form>
          </td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="col-md-6">
    <h5>Crear nuevo plan</h5>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="mb-2"><input name="slug" class="form-control" placeholder="Slug (ej. BRONCE)" required></div>
      <div class="mb-2"><input name="nombre" class="form-control" placeholder="Nombre del plan" required></div>
      <div class="mb-2"><input name="precio" type="number" step="0.01" class="form-control" placeholder="Precio (BS)"></div>
      <div class="mb-2"><textarea name="descripcion" class="form-control" placeholder="Descripción"></textarea></div>
      <button class="btn btn-success">Crear plan</button>
    </form>
  </div>
</div>
