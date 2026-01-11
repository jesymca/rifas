<?php
// install.php — script de instalación rápida para crear la base de datos inicial
// Uso: acceder desde el navegador en un entorno local. El script intenta ejecutar
// el SQL en sql/inicial.sql si existe o una copia embebida si no.

require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';

// Seguridad mínima: sólo permitir ejecución desde localhost
if(!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])){
  echo "<h3>Instalador disponible sólo desde localhost por seguridad.</h3>";
  exit;
}

$installedFlag = __DIR__ . '/.installed';
if(file_exists($installedFlag)){
  echo "<div class='container mt-4'><div class='alert alert-info'>La aplicación parece ya estar instalada. Para reinstalar, elimina el archivo <code>.installed</code> en la raíz del proyecto y vuelve aquí.</div><p><a href='login.php' class='btn btn-primary'>Ir a login</a></p></div>";
  exit;
}

// Helper para ejecutar SQL dividido por ; ignorando errores puntuales
function execSqlStatements($pdo, $sql){
  $stmts = preg_split('/;\s*\n/', $sql);
  $errors = [];
  foreach($stmts as $s){
    $s = trim($s);
    if($s === '' ) continue;
    try{
      $pdo->exec($s);
    } catch (PDOException $e){
      // Registrar y continuar — muchas sentencias serán redundantes o fallarán en entornos particulares
      $errors[] = $e->getMessage();
    }
  }
  return $errors;
}

$default_sql_path = __DIR__ . '/sql/inicial.sql';
$embedded_sql = <<<'SQL'
-- minimal fallback SQL: crea tablas esenciales si no existe el dump
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  correo VARCHAR(120) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  rol ENUM('vendedor','admin','superadmin') DEFAULT 'vendedor',
  pago_hasta DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  plan VARCHAR(10) DEFAULT NULL,
  plan_pending VARCHAR(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  precio DECIMAL(10,2) DEFAULT 0,
  activo TINYINT(1) DEFAULT 1,
  creado DATETIME DEFAULT CURRENT_TIMESTAMP,
  actualizado DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seed default plans
INSERT IGNORE INTO planes (slug,nombre,descripcion,precio) VALUES
('BRONCE','BRONCE','Una rifa básica (6 dígitos) - permite 1 rifa y subida de foto',10.00),
('PLATA','PLATA','Rifa con premios por últimos dígitos y número invertido',25.00),
('ORO','ORO','Rifas avanzadas con múltiples sorteos y opciones',50.00);
SQL;

$errors = [];
$ok = false;
$messages = [];
// Leer valores actuales de app/config.php para prellenar el formulario
$config_file_path = __DIR__ . '/app/config.php';
$current_config = ['DB_HOST'=>'localhost','DB_NAME'=>'rifas_local','DB_USER'=>'root','DB_PASS'=>'','URL_BASE'=>'http://localhost/rifas/','TZ'=>date_default_timezone_get()];
if(file_exists($config_file_path)){
  $cfg_src = file_get_contents($config_file_path);
  if(preg_match("/define\(\s*'DB_HOST'\s*,\s*'([^']*)'\s*\)/", $cfg_src, $m)) $current_config['DB_HOST'] = $m[1];
  if(preg_match("/define\(\s*'DB_NAME'\s*,\s*'([^']*)'\s*\)/", $cfg_src, $m)) $current_config['DB_NAME'] = $m[1];
  if(preg_match("/define\(\s*'DB_USER'\s*,\s*'([^']*)'\s*\)/", $cfg_src, $m)) $current_config['DB_USER'] = $m[1];
  if(preg_match("/define\(\s*'DB_PASS'\s*,\s*'([^']*)'\s*\)/", $cfg_src, $m)) $current_config['DB_PASS'] = $m[1];
  if(preg_match("/define\(\s*'URL_BASE'\s*,\s*'([^']*)'\s*\)/", $cfg_src, $m)) $current_config['URL_BASE'] = $m[1];
  if(preg_match("/date_default_timezone_set\(\s*'([^']*)'\s*\)/", $cfg_src, $m)) $current_config['TZ'] = $m[1];
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_install'])){
  // Ejecutar SQL
  if(file_exists($default_sql_path)){
    $sql = file_get_contents($default_sql_path);
  } else {
    $sql = $embedded_sql;
  }
  $pdo->beginTransaction();
  $errs = execSqlStatements($pdo, $sql);
  // Crear usuario admin con contraseña suministrada
  $admin_user = trim($_POST['admin_user'] ?? 'admin');
  $admin_email = trim($_POST['admin_email'] ?? 'admin@localhost');
  $admin_pass = trim($_POST['admin_pass'] ?? 'admin');
  if($admin_user === '' || $admin_pass===''){
    $errors[] = 'Usuario y contraseña de admin son requeridos.';
  } else {
    try{
      $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
      $st = $pdo->prepare("INSERT INTO usuarios (usuario,correo,clave,rol) VALUES (?,?,?,?)");
      $st->execute([$admin_user,$admin_email,$hash,'superadmin']);
    }catch(PDOException $e){
      $errors[] = 'No se pudo crear usuario admin: '. $e->getMessage();
    }
  }

  if(empty($errors)){
    try{
      $pdo->commit();
      // crear flag de instalado
      file_put_contents($installedFlag, "installed at " . date('c') . "\n");
      $ok = true;

      // Si el usuario solicitó, intentamos actualizar app/config.php (haciendo backup primero)
      if(!empty($_POST['update_config'])){
        $cfgFile = $config_file_path;
        $db_host = trim($_POST['db_host'] ?? $current_config['DB_HOST']);
        $db_name = trim($_POST['db_name'] ?? $current_config['DB_NAME']);
        $db_user = trim($_POST['db_user'] ?? $current_config['DB_USER']);
        $db_pass = trim($_POST['db_pass'] ?? $current_config['DB_PASS']);
        $url_base = trim($_POST['url_base'] ?? $current_config['URL_BASE']);
        $tz = trim($_POST['timezone'] ?? $current_config['TZ']);

        $newCfg = "<?php\n";
        $newCfg .= "define('DB_HOST',".var_export($db_host, true).");\n";
        $newCfg .= "define('DB_NAME',".var_export($db_name, true).");\n";
        $newCfg .= "define('DB_USER',".var_export($db_user, true).");\n";
        $newCfg .= "define('DB_PASS',".var_export($db_pass, true).");\n";
        $newCfg .= "define('URL_BASE',".var_export($url_base, true).");   // cambiar en producción\n";
        $newCfg .= "date_default_timezone_set(".var_export($tz, true).");\n";
        $newCfg .= "session_start();\n";

        // Backup
        if(file_exists($cfgFile)){
          $bak = $cfgFile.'.bak.'.date('Ymd_His');
          if(@copy($cfgFile,$bak)){
            $messages[] = "Copia de seguridad creada: $bak";
          } else {
            $messages[] = "No se pudo crear copia de seguridad en: $bak";
          }
        }

        $written = false;
        if(is_writable($cfgFile) || (!file_exists($cfgFile) && is_writable(dirname($cfgFile)))){
          $written = (@file_put_contents($cfgFile, $newCfg) !== false);
          $written ? $messages[] = 'Archivo app/config.php actualizado correctamente.' : $messages[] = 'No se pudo escribir app/config.php directamente.';
        } else {
          // fallback: escribir en /tmp para pegar manualmente
          $tmp = sys_get_temp_dir().'/rifas_config_sugerido_'.time().'.php';
          @file_put_contents($tmp,$newCfg);
          $messages[] = "No se pudo escribir app/config.php; se generó el archivo sugerido: $tmp";
        }
      }

    }catch(Exception $e){
      $pdo->rollBack();
      $errors[] = 'Error al finalizar la instalación: ' . $e->getMessage();
    }
  } else {
    $pdo->rollBack();
  }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalador - Rifas</title>
  <link rel="stylesheet" href="assets/css/custom.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h3>Instalador inicial</h3>
  <?php if($ok): ?>
    <div class="alert alert-success">Instalación completada correctamente. <a href="login.php">Ir a login</a></div>
  <?php else: ?>
    <?php if(!empty($errors)): ?>
      <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
    <?php endif; ?>
    <?php if(!empty($messages)): ?>
      <div class="alert alert-info"><ul><?php foreach($messages as $m) echo '<li>'.htmlspecialchars($m).'</li>'; ?></ul></div>
    <?php endif; ?>

    <p>Este asistente ejecutará las sentencias SQL para crear las tablas necesarias (si no existen) y creará un usuario <strong>superadmin</strong>. Opcionalmente puedes actualizar <code>app/config.php</code> con los datos de conexión.</p>
    <form method="post" class="row g-3">
      <div class="col-md-3"><label class="form-label">DB Host</label><input name="db_host" class="form-control" value="<?=htmlspecialchars($current_config['DB_HOST'])?>"></div>
      <div class="col-md-3"><label class="form-label">DB Name</label><input name="db_name" class="form-control" value="<?=htmlspecialchars($current_config['DB_NAME'])?>"></div>
      <div class="col-md-3"><label class="form-label">DB User</label><input name="db_user" class="form-control" value="<?=htmlspecialchars($current_config['DB_USER'])?>"></div>
      <div class="col-md-3"><label class="form-label">DB Pass</label><input name="db_pass" class="form-control" value="<?=htmlspecialchars($current_config['DB_PASS'])?>"></div>

      <div class="col-md-6"><label class="form-label">URL_BASE</label><input name="url_base" class="form-control" value="<?=htmlspecialchars($current_config['URL_BASE'])?>"></div>
      <div class="col-md-6"><label class="form-label">Timezone (ej. America/Caracas)</label><input name="timezone" class="form-control" value="<?=htmlspecialchars($current_config['TZ'])?>"></div>

      <div class="col-md-4"><label class="form-label">Usuario admin</label><input name="admin_user" class="form-control" value="admin"></div>
      <div class="col-md-4"><label class="form-label">Email admin</label><input name="admin_email" class="form-control" value="admin@rifas.local"></div>
      <div class="col-md-4"><label class="form-label">Contraseña</label><input name="admin_pass" class="form-control" value="admin"></div>

      <div class="col-12 mt-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="confirm" required><label class="form-check-label" for="confirm">Confirmo que deseo ejecutar las sentencias SQL en esta máquina local.</label></div></div>

      <div class="col-12 mt-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="update_config" id="update_config" checked><label class="form-check-label" for="update_config">Actualizar <code>app/config.php</code> automáticamente (copia de seguridad será creada)</label></div></div>

      <div class="col-12"><input type="hidden" name="do_install" value="1"><button class="btn btn-primary">Ejecutar instalación</button></div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
