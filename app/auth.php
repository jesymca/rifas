<?php
// Autenticación básica
require_once __DIR__.'/db.php';

function user(){
  return $_SESSION['usuario'] ?? null;
}

function esAdmin(){
  if(!user()) return false;
  if(!empty($_SESSION['rol'])) return in_array($_SESSION['rol'], ['admin','superadmin']);
  return ($_SESSION['usuario'] ?? '')==='admin';
}

function isSuperAdmin(){
  if(!user()) return false;
  // rol explícito 'superadmin' o el usuario 'admin' se considera dueño
  if(!empty($_SESSION['rol']) && $_SESSION['rol']==='superadmin') return true;
  return (($_SESSION['usuario'] ?? '')==='admin');
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
  if(!hasPago()){
    header('Location: panel.php?s=pago'); exit;
  }
}

function hasPago(){
  if(empty($_SESSION['uid'])) return false;
  $pago = $_SESSION['pago_hasta'] ?? null;
  // si la sesión tiene fecha válida
  if($pago && $pago >= date('Y-m-d')) return true;
  // consultar la base de datos para actualizar sesión
  global $pdo;
  try{
    // Intentamos obtener plan si existe la columna
    $st = $pdo->prepare("SELECT pago_hasta, plan FROM usuarios WHERE id = ? LIMIT 1");
    $st->execute([$_SESSION['uid']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
  } catch (PDOException $e){
    // Si la columna 'plan' no existe, intentamos solo con pago_hasta
    try{
      $st = $pdo->prepare("SELECT pago_hasta FROM usuarios WHERE id = ? LIMIT 1");
      $st->execute([$_SESSION['uid']]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e2){
      return false;
    }
  }

  if($row){
    if(!empty($row['pago_hasta'])) $_SESSION['pago_hasta'] = $row['pago_hasta'];
    if(!empty($row['plan'])) $_SESSION['plan'] = $row['plan'];
    return (!empty($row['pago_hasta']) && $row['pago_hasta'] >= date('Y-m-d'));
  }
  return false;
}

function getUserPlan(){
  if(isset($_SESSION['plan'])) return $_SESSION['plan'];
  if(empty($_SESSION['uid'])) return null;
  global $pdo;
  try{
    $st = $pdo->prepare("SELECT plan FROM usuarios WHERE id = ? LIMIT 1");
    $st->execute([$_SESSION['uid']]);
    $plan = $st->fetchColumn();
    if($plan) $_SESSION['plan'] = $plan;
    return $plan;
  } catch (PDOException $e){
    // Si la columna 'plan' no existe o hay otro error, devolvemos null
    return null;
  }
}

function logout(){
  session_unset();
  session_destroy();
}
