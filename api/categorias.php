<?php
// Endpoint de categorías (solo lectura, público: lo usan el panel y el portal de pacientes)
//   GET /api/categorias
// Las categorías nuevas se crean al cargar un documento o una encuesta (ver ServicioCategoria).
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/models/Categoria.php';

Autorizacion::exigirMetodo('GET');
echo json_encode((new Categoria())->listar());
