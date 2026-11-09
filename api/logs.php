<?php
// Endpoint de logs — solo root, solo lectura
//   GET /api/logs                         -> últimos 200 registros
//   GET /api/logs?modulo=Traslados&accion=Crea&usuario_ci=...&desde=2026-11-01&hasta=2026-11-08
//   GET /api/logs?opciones=1              -> valores para llenar los filtros
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/models/Log.php';

Autorizacion::exigirMetodo('GET');
Autorizacion::exigirRol(Autorizacion::ROLES_ROOT);

$log = new Log();

if (isset($_GET['opciones'])) {
    echo json_encode($log->opcionesFiltro());
    exit;
}

// Las fechas deben venir como AAAA-MM-DD; si no, se ignoran
$fecha = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v ?? '') ? $v : null;

echo json_encode($log->listar([
    'modulo'     => trim($_GET['modulo'] ?? ''),
    'accion'     => trim($_GET['accion'] ?? ''),
    'usuario_ci' => trim($_GET['usuario_ci'] ?? ''),
    'desde'      => $fecha($_GET['desde'] ?? null),
    'hasta'      => $fecha($_GET['hasta'] ?? null),
]));
