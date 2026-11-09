<?php
// Entrega el PDF de un documento para mostrarlo en el visor (PDF.js).
//   GET /api/pdf?id=5
// La carpeta uploads/ no es accesible directamente: los PDF solo salen por acá.
// Se envía "inline" y sin caché para que el navegador lo muestre, no lo guarde.
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/negocio/ServicioDocumento.php';

Autorizacion::exigirMetodo('GET');
$ruta = (new ServicioDocumento())->rutaPdf((int) ($_GET['id'] ?? 0));

header('Content-Type: application/pdf');
header('Content-Disposition: inline');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
