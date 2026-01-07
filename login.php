<?php
require_once 'app/config.php';
require_once 'app/auth.php';
if($_POST){
  if(login($_POST['usuario'],$_POST['clave'])){
     header('Location: panel.php'); exit;
  }else{
     $error='Credenciales incorrectas.';
  }
}
include 'assets/header.php'; ?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-4">
      <h4>Ingresar</h4>
      <?php if(!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
      <form method="post">
        <div class="mb-3"><input name="usuario" class="form-control" placeholder="Usuario o correo" required></div>
        <div class="mb-3"><input type="password" name="clave" class="form-control" placeholder="Clave" required></div>
        <button class="btn btn-primary w-100">Entrar</button>
      </form>
    </div>
  </div>
</div>
<?php include 'assets/footer.php';