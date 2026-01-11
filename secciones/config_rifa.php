<?php
require_once 'app/db.php';
require_once 'app/auth.php';
$id_user=$_SESSION['uid'];
$hasPago = hasPago();
$plan_user = getUserPlan() ?? null;

// Asegurar columnas en rifas para configuraciones avanzadas
try{
  $tbl = $pdo->query("SHOW TABLES LIKE 'rifas'")->fetch();
  if($tbl){
    $colConfig = $pdo->query("SHOW COLUMNS FROM rifas LIKE 'config'")->fetch();
    if(!$colConfig) $pdo->exec("ALTER TABLE rifas ADD COLUMN config TEXT DEFAULT NULL");
    $colFoto = $pdo->query("SHOW COLUMNS FROM rifas LIKE 'foto'")->fetch();
    if(!$colFoto) $pdo->exec("ALTER TABLE rifas ADD COLUMN foto VARCHAR(255) DEFAULT NULL");
  }
} catch (PDOException $e){
  // En entornos sin la tabla 'rifas' evitamos que se propague la excepción y provocque 500
} 

// Obtener planes desde DB si existe la tabla 'planes' (fallback a BRONCE/PLATA/ORO)
$planes = null;
try{
  $st = $pdo->query("SELECT slug,nombre,descripcion,precio,activo FROM planes WHERE activo=1 ORDER BY precio ASC, id ASC");
  $pp = $st->fetchAll(PDO::FETCH_ASSOC);
  if($pp && count($pp)>0) $planes = $pp;
}catch(PDOException $e){ /* ignore */ }

if($_SERVER['REQUEST_METHOD']==='POST' && $hasPago){
  // recolectar datos
  $plan = $plan_user ?? ($_POST['plan'] ?? 'BRONCE');
  $titulo = $_POST['titulo'] ?? '';
  $fi = $_POST['fi'] ?? '';
  $ff = $_POST['ff'] ?? '';
  $digitos = intval($_POST['digitos'] ?? 6);
  $precio = floatval($_POST['precio'] ?? 0);
  $paquetes = $_POST['paquetes'] ?? [];
  $metodos = $_POST['metodos'] ?? [];
  $sorteos = [];
  if(isset($_POST['sorteo'])) $sorteos = [$_POST['sorteo']];
  else $sorteos = $_POST['sorteos'] ?? [];
  $inverso = isset($_POST['inverso']) ? 1 : 0;
  $ultimos = isset($_POST['ultimos']) ? 1 : 0;
  $ult_n = intval($_POST['ult_n'] ?? 0);
  $premio_ult = $_POST['premio_ult'] ?? '';
  $premio_inv = $_POST['premio_inv'] ?? '';

  // validaciones según plan
  $errors = [];
  if($plan === 'BRONCE' && $digitos !== 6){ $errors[] = 'Plan Bronce permite solo rifas de 6 dígitos.'; }
  if(in_array($plan, ['BRONCE','PLATA'])){
    $stc=$pdo->prepare("SELECT COUNT(*) FROM rifas WHERE id_usuario = ?");
    $stc->execute([$id_user]);
    if($stc->fetchColumn() > 0) $errors[] = 'Tu plan permite solo 1 rifa.';
  }
  if($plan === 'BRONCE' && count($sorteos) !== 1){ $errors[] = 'Plan Bronce permite configurar solo 1 sorteo (mañana/tarde/noche).'; }
  if($plan === 'PLATA' && count($sorteos) !== 1){ $errors[] = 'Plan Plata permite configurar 1 sorteo.'; }
  if($plan === 'ORO' && count($sorteos) < 1){ $errors[] = 'Plan Oro permite configurar uno o más sorteos.'; }
  if($ultimos && ($ult_n < 1 || $ult_n > $digitos)) $errors[] = 'Cantidad de dígitos para últimos inválida.';

  // manejar subida de foto (solo plan Bronce permite subir foto)
  $foto_name = null;
  if(isset($_FILES['foto']) && $_FILES['foto']['error']===0){
    if($plan !== 'BRONCE'){
      $errors[] = 'Solo el plan Bronce permite subir foto.';
    } else {
      $f = $_FILES['foto'];
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg','jpeg','png','gif'];
      if(!in_array($ext, $allowed)) $errors[] = 'Tipo de archivo no permitido para la foto.';
      else {
        $foto_name = uniqid('r_').'.'.$ext;
        if(!move_uploaded_file($f['tmp_name'], __DIR__.'/../uploads/'.$foto_name)){
          $errors[] = 'Error al guardar la foto.';
        }
      }
    }
  }

  if(empty($errors)){
    // asegurar columnas y almacenar configuración como JSON
    $config = ['sorteos'=>$sorteos, 'inverso'=>$inverso, 'ultimos'=>$ultimos, 'ult_n'=>$ult_n, 'premio_ult'=>$premio_ult, 'premio_inv'=>$premio_inv, 'plan'=>$plan];
    $st=$pdo->prepare("INSERT INTO rifas (id_usuario,titulo,fecha_inicio,fecha_fin,digitos,precio_unitario,paquetes,metodos_pago,config,foto)
                       VALUES (?,?,?,?,?,?,?,?,?,?)");
    $paq=json_encode($paquetes);
    $mp=json_encode($metodos);
    $cfg=json_encode($config);
    $st->execute([$id_user,$titulo,$fi,$ff,$digitos,$precio,$paq,$mp,$cfg,$foto_name]);
    $id_rifa=$pdo->lastInsertId();
    // generar números
    $max=pow(10,$digitos)-1;
    $st=$pdo->prepare("INSERT INTO numeros (id_rifa,numero) VALUES (?,?)");
    $pdo->beginTransaction();
    for($i=0;$i<=$max;$i++){
        $num=str_pad($i,$digitos,'0',STR_PAD_LEFT);
        $st->execute([$id_rifa,$num]);
    }
    $pdo->commit();
    echo "<div class='alert alert-success'>Rifa creada con ".($max+1)." números.</div>";
  } else {
    echo "<div class='alert alert-danger'><ul><li>".implode("</li><li>", $errors)."</li></ul></div>";
  }
}
?>
<?php if(!$hasPago): ?>
  <h5>Elige un plan para configurar rifas</h5>
  <p class="text-muted">Selecciona un plan y luego procede a declarar el pago para activarlo. El administrador aprobará tu pago y podrás configurar tus rifas.</p>
  <div class="row gy-3">
    <?php if($planes !== null): ?>
      <?php foreach($planes as $p): $slug = $p['slug']; $name = $p['nombre'] ?? $p['slug']; $price = isset($p['precio'])? number_format($p['precio'],2,',','.') : null; ?>
        <div class="col-md-4">
          <div class="card h-100 <?=($slug==='BRONCE')? 'border-secondary': (($slug==='PLATA')? 'border-primary':'border-warning') ?>">
            <div class="card-body">
              <h5 class="card-title"><?=htmlspecialchars(strtoupper($name))?> <?php if($slug==='BRONCE') echo '<span class="badge bg-secondary">Recomendado</span>'; ?></h5>
              <h6 class="card-subtitle mb-2 text-muted"><?=htmlspecialchars($p['descripcion'] ?? '')?></h6>
              <?php if($price): ?><p class="mt-2"><strong>Precio: </strong><?= $price ?> BS</p><?php endif; ?>
              <a href="<?=URL_BASE?>panel.php?s=pago&plan=<?=urlencode($slug)?>" onclick="location.href='<?=URL_BASE?>panel.php?s=pago&plan=<?=urlencode($slug)?>'; return false;" class="btn <?=($slug==='BRONCE')? 'btn-outline-primary': (($slug==='PLATA')? 'btn-primary':'btn-warning text-dark') ?>">Seleccionar y pagar <?=htmlspecialchars(strtoupper($name))?></a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <!-- Mantener tarjetas estáticas por compatibilidad -->
      <div class="col-md-4">
        <div class="card h-100 border-secondary">
          <div class="card-body">
            <h5 class="card-title">BRONCE <span class="badge bg-secondary">Recomendado</span></h5>
            <h6 class="card-subtitle mb-2 text-muted">Una rifa básica (6 dígitos)</h6>
            <ul>
              <li>Permite 1 sola rifa de 6 dígitos</li>
              <li>Subir una foto para el sorteo</li>
              <li>Permite configurar 1 sorteo (Mañana / Tarde / Noche)</li>
              <li>Interfaz simple y rápida para vendedores nuevos</li>
            </ul>
            <p class="mt-2"><small class="text-muted">Ideal si vendes una sola rifa diaria.</small></p>
            <a href="<?=URL_BASE?>panel.php?s=pago&plan=BRONCE" onclick="location.href='<?=URL_BASE?>panel.php?s=pago&plan=BRONCE'; return false;" class="btn btn-outline-primary">Seleccionar y pagar BRONCE</a>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 border-primary">
          <div class="card-body">
            <h5 class="card-title">PLATA <span class="badge bg-primary">Popular</span></h5>
            <h6 class="card-subtitle mb-2 text-muted">Más opciones de premio</h6>
            <ul>
              <li>1 rifa (3 o 6 dígitos)</li>
              <li>Premios por últimos X dígitos (ej. últimos 5 dígitos)</li>
              <li>Premios para número invertido (p. ej. 123456 ↔ 654321)</li>
              <li>Los boletos comercializados participan en los sorteos configurados</li>
            </ul>
            <p class="mt-2"><small class="text-muted">Perfecto para rifas con premios más completos.</small></p>
            <a href="<?=URL_BASE?>panel.php?s=pago&plan=PLATA" onclick="location.href='<?=URL_BASE?>panel.php?s=pago&plan=PLATA'; return false;" class="btn btn-primary">Seleccionar y pagar PLATA</a>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 border-warning">
          <div class="card-body">
            <h5 class="card-title">ORO <span class="badge bg-warning text-dark">Avanzado</span></h5>
            <h6 class="card-subtitle mb-2 text-muted">Todas las opciones + múltiples sorteos</h6>
            <ul>
              <li>Todo lo de PLATA (últimos, invertido)</li>
              <li>Permite configurar más de 1 sorteo (Mañana, Tarde y/o Noche)</li>
              <li>Ideal para vendedores con alto volumen y múltiples sorteos por día</li>
              <li>Mayor flexibilidad en la configuración de premios</li>
            </ul>
            <p class="mt-2"><small class="text-muted">Recomendado si operas varios sorteos diarios (Lotería del Táchira: Mañana/Tarde/Noche).</small></p>
            <a href="<?=URL_BASE?>panel.php?s=pago&plan=ORO" onclick="location.href='<?=URL_BASE?>panel.php?s=pago&plan=ORO'; return false;" class="btn btn-warning text-dark">Seleccionar y pagar ORO</a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <div class="mt-3">
    <p class="small text-muted">Al seleccionar un plan serás dirigido a la pantalla para declarar el pago correspondiente; después de la aprobación por el administrador tu plan quedará activo y podrás configurar tus rifas.</p>
  </div>
<?php else: ?>
  <?php $plan_label = strtoupper($plan_user ?? 'BRONCE'); if(isset($planes) && is_array($planes)){
    foreach($planes as $pp){ if($pp['slug'] === ($plan_user ?? 'BRONCE')){ $plan_label = strtoupper($pp['nombre']); break; } }
  } ?>
  <h5>Configurar rifa (Plan: <?=htmlspecialchars($plan_label)?>)</h5>
  <form method="post" enctype="multipart/form-data" id="form_rifa">
    <div class="mb-3"><input name="titulo" class="form-control" placeholder="Nombre del sorteo" required></div>
    <div class="row mb-3">
      <div class="col"><input type="date" name="fi" class="form-control" required></div>
      <div class="col"><input type="date" name="ff" class="form-control" required></div>
    </div>
    <div class="mb-3">
      <label>Cantidad de dígitos</label>
      <select name="digitos" class="form-select" id="digitos_select"><option value="3">3</option><option value="6">6</option></select>
    </div>
    <div class="mb-3"><input type="number" name="precio" class="form-control" placeholder="Precio unitario (BS)" required></div>

    <div class="mb-3">
      <label>Sorteos (Lotería del Táchira) — 3 sorteos diarios: Mañana, Tarde y Noche</label>
      <div id="sorteos_group">
        <?php if(($plan_user ?? 'BRONCE') === 'ORO'): ?>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="sorteos[]" value="manana"> Mañana</div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="sorteos[]" value="tarde"> Tarde</div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="sorteos[]" value="noche"> Noche</div>
        <?php else: ?>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="sorteo" value="manana"> Mañana</div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="sorteo" value="tarde"> Tarde</div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="sorteo" value="noche"> Noche</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if(($plan_user ?? 'BRONCE') === 'BRONCE'): ?>
      <div class="mb-3">
        <label>Foto del sorteo (solo BRONCE)</label>
        <input type="file" name="foto" class="form-control">
      </div>
    <?php endif; ?>

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="inverso" id="inverso_chk">
      <label class="form-check-label" for="inverso_chk">Habilitar premio por número invertido</label>
    </div>
    <div class="mb-3" id="premio_inverso_box" style="display:none;">
      <input class="form-control" name="premio_inv" placeholder="Monto o descripción premio para número invertido">
    </div>

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="ultimos" id="ultimos_chk">
      <label class="form-check-label" for="ultimos_chk">Habilitar premio por últimos dígitos</label>
    </div>
    <div class="row mb-3" id="ultimos_box" style="display:none;">
      <div class="col-md-3"><input type="number" name="ult_n" class="form-control" placeholder="Cantidad dígitos"></div>
      <div class="col-md-9"><input class="form-control" name="premio_ult" placeholder="Monto o descripción premio por últimos dígitos"></div>
    </div>

    <h5>Métodos de pago que aceptas</h5>
    <div id="metodos">
      <div class="row mb-2">
        <div class="col"><select name="metodos[0][tipo]" class="form-select"><option value="pm">PagoMóvil</option><option value="tr">Transferencia</option></select></div>
        <div class="col"><input name="metodos[0][banco]" class="form-control" placeholder="Banco"></div>
        <div class="col"><input name="metodos[0][ci]" class="form-control" placeholder="V-12345678"></div>
        <div class="col"><input name="metodos[0][tlf]" class="form-control" placeholder="0414-1234567"></div>
      </div>
    </div>

    <input type="hidden" name="plan" value="<?=htmlspecialchars($plan_user ?? 'BRONCE')?>">
    <button class="btn btn-primary">Guardar rifa</button>
  </form>

  <script>
    (function(){
      const plan = "<?=($plan_user ?? 'BRONCE')?>";
      // Si plan es BRONCE forzamos dígitos a 6
      const digSel = document.getElementById('digitos_select');
      if(plan === 'BRONCE'){
        for(const opt of digSel.options){ if(opt.value==='3') opt.disabled=true; }
        digSel.value='6';
      }
      // mostrar/ocultar inverso
      const invChk = document.getElementById('inverso_chk');
      const invBox = document.getElementById('premio_inverso_box');
      invChk.addEventListener('change',()=>{ invBox.style.display = invChk.checked ? 'block':'none'; });
      // ultimos
      const uChk = document.getElementById('ultimos_chk');
      const uBox = document.getElementById('ultimos_box');
      uChk.addEventListener('change',()=>{ uBox.style.display = uChk.checked ? 'flex':'none'; });
      // cuando se seleccione 3 dígitos limitar ultimos_n
      digSel.addEventListener('change',()=>{
        const n = parseInt(digSel.value,10);
        const ultN = document.querySelector('input[name="ult_n"]');
        if(ultN){ ultN.max = n; }
      });
      // en BRONCE forzar que solo un sorteo (radiobuttons) — ya está hecho con radio
    })();
  </script>
<?php endif; ?>