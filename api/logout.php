<?php
// Endpoint de logout — cierra la sesión activa
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/models/Log.php';

// Se registra antes de cerrar la sesión (después ya no se sabe quién era)
$usuario = Autorizacion::usuarioActual();
if ($usuario !== null) {
    Log::registrar($usuario['ci'], 'Cierra sesión', Log::SESION);
}
Autorizacion::cerrarSesion();

echo json_encode(['mensaje' => 'Sesión cerrada']);
