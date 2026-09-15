<?php
/**
 * config/correo.php
 * Configuración SMTP para el envío de correo (PHPMailer).
 * Los valores (host, puerto, usuario, password de aplicación, remitente)
 * se leen desde config/entorno.php: ahí se reemplazan antes de subir
 * al hosting real.
 */
require_once __DIR__ . '/entorno.php';