<?php
require_once 'app/config.php';
require_once 'app/db.php';
require_once 'app/auth.php';

if($_POST){
  $usuario=trim($_POST['usuario']);
  $correo =trim($_POST['correo']);
  $clave  =password_hash($_POST['clave'],PASSWORD_BCRYPT);

  $st=$pdo->prepare("INSERT INTO usuarios (usuario,correo,clave) VALUES (?,?,?)");
  try{
     $st->execute([$usuario,$correo,$clave]);
     $_SESSION['ok']='Cuenta creada. Inicia sesión.';
     header('Location: login.php'); exit;
  }catch(PDOException $e){
     $error='Usuario o correo ya existe.';
  }
}
include 'assets/header.php'; ?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-4">
      <h4>Regístrate</h4>
      <?php if(!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
      <form method="post">
        <div class="mb-3"><input name="usuario" class="form-control" placeholder="Usuario" required></div>
        <div class="mb-3"><input type="email" name="correo" class="form-control" placeholder="Correo" required></div>
        <div class="mb-3"><input type="password" name="clave" class="form-control" placeholder="Clave" required></div>
        <button class="btn btn-primary w-100">Crear cuenta</button>
      </form>
    </div>
  </div>
</div>
<?php include 'assets/footer.php';