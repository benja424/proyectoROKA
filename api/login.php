<?php
// Endpoint de login — recibe usuario y contraseña y abre la sesión
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/models/Log.php';

Autorizacion::exigirMetodo('POST');
$datos = Autorizacion::leerJson();
$user  = trim((string) ($datos['user'] ?? ''));
$pass  = (string) ($datos['pass'] ?? '');

if ($user === '' || $pass === '') {
    throw new ReglaNegocioException('Ingresá usuario y contraseña.', 400);
}

$usuario = (new Usuario())->login($user, $pass);

if ($usuario === null) {
    throw new ReglaNegocioException('Usuario o contraseña incorrectos', 401);
}
if ($usuario['estado'] !== 'Activo') {
    throw new ReglaNegocioException('Usuario inhabilitado. Contactá al administrador.', 403);
}
unset($usuario['estado']);

// Nuevo id de sesión al iniciar (evita que alguien reutilice un id de sesión anterior)
Autorizacion::iniciarSesion();
session_regenerate_id(true);
$_SESSION['usuario'] = $usuario;

Log::registrar($usuario['ci'], 'Inicia sesión', Log::SESION);
echo json_encode(['mensaje' => 'Login correcto', 'usuario' => $usuario]);
