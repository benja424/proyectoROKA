<?php
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Log.php';

// Lógica de categorías compartida por documentos y encuestas:
// elegir una existente o escribir una nueva ("+ Crear categoría").
class ServicioCategoria
{
    private Categoria $categorias;

    public function __construct()
    {
        $this->categorias = new Categoria();
    }

    // Valida la elección del formulario. Devuelve un mensaje de error o null si está bien.
    public function validar(int $idCategoria, string $nueva): ?string
    {
        if ($nueva !== '') {
            return mb_strlen($nueva) > 60 ? 'El nombre de la categoría admite hasta 60 caracteres.' : null;
        }
        return ($idCategoria > 0 && $this->categorias->existe($idCategoria)) ? null : 'Elegí una categoría.';
    }

    // Devuelve el id a usar: el elegido, o el de la categoría nueva.
    // Si la nueva ya existe (sin importar mayúsculas ni tildes) se usa la existente.
    public function resolver(int $idCategoria, string $nueva, array $usuario): int
    {
        if ($nueva === '') {
            return $idCategoria;
        }
        [$id, $creada] = $this->categorias->obtenerOCrear($nueva);
        if ($creada) {
            Log::registrar($usuario['ci'], 'Crea', Log::CATEGORIAS, $id);
        }
        return $id;
    }
}
