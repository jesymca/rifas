<?php
// Autenticación básica
require_once __DIR__.'/db.php';

function user(){
  return $_SESSION['usuario'] ?? null;
}

function esAdmin(){
  if(!user()) return false;
  if(!empty($_SESSION['rol'])) return $_SESSION['rol']==='admin';
  return ($_SESSION['usuario'] ?? '')==='admin';
}

function requireLogin(){
  if(!user()){ header('Location: login.php'); exit; }
}

function login($usuario, $clave){
  global $pdo;
  $st = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = ? OR correo = ? LIMIT 1');
  $st->execute([$usuario, $usuario]);
  $u = $st->fetch();
  if($u && password_verify($clave, $u['clave'])){
    // establecer sesión
    $_SESSION['usuario'] = $u['usuario'];
    $_SESSION['uid'] = $u['id'] ?? null;
    $_SESSION['pago_hasta'] = $u['pago_hasta'] ?? null;
    if(isset($u['rol'])) $_SESSION['rol'] = $u['rol'];
    return true;
  }
  return false;
}

function requirePago(){
  // Los admins no necesitan declarar pago
  if(esAdmin()) return;
  $pago = $_SESSION['pago_hasta'] ?? null;
  // si no hay fecha o ya expiró, mandar a la sección de declarar pago
  if(!$pago || $pago < date('Y-m-d')){
    header('Location: panel.php?s=pago'); exit;
  }
}

function logout(){
  session_unset();
  session_destroy();
}
