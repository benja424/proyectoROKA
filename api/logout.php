<?php
// Endpoint de logout — destruye la sesión activa
header('Content-Type: application/json; charset=utf-8');

session_start();
session_unset();
session_destroy();

echo json_encode(['mensaje' => 'Sesión cerrada']);