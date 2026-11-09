<?php
// Endpoint de vehículos (solo lectura)
//   GET /api/vehiculos                                  -> todos con su disponibilidad actual
//   GET /api/vehiculos?salida=2026-11-06T14:30          -> además indica cuáles están libres a esa hora
//   GET /api/vehiculos?...&requerido=Ambulancia         -> además indica cuáles son compatibles
//   GET /api/vehiculos?...&excluir=5                    -> ignora el traslado 5 (al editarlo)
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/negocio/ServicioTraslado.php';

Autorizacion::exigirMetodo('GET');
Autorizacion::exigirRol(Autorizacion::ROLES_TRASLADOS);

$requerido = trim($_GET['requerido'] ?? '');
$salida    = trim($_GET['salida'] ?? '');
$excluir   = (int) ($_GET['excluir'] ?? 0);

echo json_encode((new ServicioTraslado())->vehiculosParaTraslado(
    $requerido === '' ? null : $requerido,
    $salida === '' ? null : $salida,
    $excluir > 0 ? $excluir : null
));
