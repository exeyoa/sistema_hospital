<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

if ($usuario === '' || $password === '') {
    $_SESSION['error_login'] = 'Completa usuario y contraseña.';
    header('Location: login.php');
    exit;
}

// Trae el usuario junto con el nombre de su rol
$sql = "SELECT u.id_usuario, u.nombre, u.password_hash, r.nombre_rol
        FROM usuarios u
        JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.usuario = :usuario AND u.activo = 1";

$stmt = $conexion->prepare($sql);
$stmt->bindParam(':usuario', $usuario);
$stmt->execute();
$fila = $stmt->fetch(PDO::FETCH_ASSOC);

if ($fila && password_verify($password, $fila['password_hash'])) {
    // Credenciales correctas: guardamos la sesión
    $_SESSION['id_usuario'] = $fila['id_usuario'];
    $_SESSION['nombre'] = $fila['nombre'];
    $_SESSION['rol'] = $fila['nombre_rol']; // admin | medico | recepcionista

    header('Location: ' . $_SESSION['rol'] . '.php');
    exit;
} else {
    $_SESSION['error_login'] = 'Usuario o contraseña incorrectos.';
    header('Location: login.php');
    exit;
}
