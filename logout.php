<?php
require_once 'app/config.php';
require_once 'app/auth.php';
logout();
header('Location: login.php'); exit;
