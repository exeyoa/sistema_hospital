<?php
$host = "localhost";
$db_name = "hospital_db";
$usuario = "root";
$password = "";

try {
    $conexion = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // No mostramos $e->getMessage() al usuario (RNF-08): podría revelar
    // el nombre de la base de datos, usuario, estructura, etc.
    // El detalle real queda en el log del servidor para quien depure.
    error_log('Error de conexión a la base de datos: ' . $e->getMessage());
    die('No se pudo conectar con el sistema. Intenta más tarde o contacta al administrador.');
}
?>