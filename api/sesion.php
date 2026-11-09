<?php
// Endpoint de verificación de sesión — lo consultan las páginas protegidas al cargar
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';

$usuario = Autorizacion::usuarioActual();   // null si no hay sesión o si lo inhabilitaron

echo json_encode($usuario === null
    ? ['autenticado' => false]
    : ['autenticado' => true, 'usuario' => $usuario]);
