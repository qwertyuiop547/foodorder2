<?php
session_start();

require_once 'auth.php';

$role = $_POST['role'] ?? ($_GET['role'] ?? null);

logout($role, '../public/login.php');
?>
