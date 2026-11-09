<?php
// Endpoint de pacientes (solo lectura)
//   GET /api/pacientes?documento=12345678 -> {"existe": true, ...} o {"existe": false}
// Sirve para que el formulario avise si el paciente ya está registrado.
// Cuando se conecte la API externa por CI, este es el lugar para consultarla.
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/models/Paciente.php';

Autorizacion::exigirMetodo('GET');
Autorizacion::exigirRol(Autorizacion::ROLES_TRASLADOS);

$documento = trim($_GET['documento'] ?? '');
if (!preg_match('/^\d{6,10}$/', $documento)) {
    throw new ReglaNegocioException('El documento debe tener entre 6 y 10 dígitos.');
}

$paciente = (new Paciente())->buscarPorDocumento($documento);
echo json_encode($paciente === null
    ? ['existe' => false]
    : ['existe' => true, 'id_paciente' => (int) $paciente['id_paciente'], 'documento' => $paciente['documento']]);
