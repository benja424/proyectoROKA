<?php
// Reglas del dominio de traslados.
// Es la ÚNICA fuente de verdad: el JS del frontend replica estas reglas solo para mostrar
// u ocultar opciones, pero la validación real siempre se hace acá, en el servidor.
class ReglasTraslado
{
    public const AMBULANCIA = 'Ambulancia';

    public const TIPOS_ELEMENTO = ['Paciente', 'Organo', 'Muestra_biologica', 'Insumo_medico', 'Documento', 'Equipamiento'];

    // Elementos que solo pueden viajar en ambulancia (según la entrevista con el hospital)
    public const SOLO_AMBULANCIA = ['Organo', 'Muestra_biologica'];

    // Dos traslados activos del mismo vehículo no pueden salir con menos de estos minutos de diferencia
    public const VENTANA_MINUTOS = 120;

    public const ESTADOS = ['Solicitado', 'En_curso', 'En_destino', 'Retornado', 'Cancelado'];

    // Estados a los que se puede pasar desde cada estado
    public const TRANSICIONES = [
        'Solicitado' => ['En_curso', 'Cancelado'],
        'En_curso'   => ['En_destino', 'Cancelado'],
        'En_destino' => ['Retornado'],
        'Retornado'  => [],
        'Cancelado'  => [],
    ];

    // Convierte cualquier valor del JSON (true, 1, "1", "true") a booleano
    public static function aBool(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    // ¿Qué tipo de vehículo exige este traslado? Devuelve 'Ambulancia' o null si es de elección libre.
    public static function tipoVehiculoRequerido(array $d): ?string
    {
        $tipo = $d['tipo_elemento'] ?? '';

        if ($tipo === 'Paciente') {
            $noAutonomo = array_key_exists('autonomo', $d) && !self::aBool($d['autonomo']);

            if (self::aBool($d['requiere_oxigeno'] ?? false)
                || self::aBool($d['requiere_aislamiento'] ?? false)
                || self::aBool($d['requiere_camilla'] ?? false)
                || $noAutonomo) {
                return self::AMBULANCIA;
            }
            return null;
        }

        if (in_array($tipo, self::SOLO_AMBULANCIA, true)) {
            return self::AMBULANCIA;
        }

        return null; // Documento, insumo médico, equipamiento: elección libre
    }

    // Normaliza una fecha del formulario (YYYY-MM-DDTHH:MM) al formato de MySQL. Devuelve null si es inválida.
    public static function normalizarFecha(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'] as $formato) {
            $fecha = DateTime::createFromFormat($formato, trim($valor));
            if ($fecha !== false) {
                return $fecha->format('Y-m-d H:i:00');
            }
        }
        return null;
    }

    // Valida los datos de una petición. Devuelve la lista de errores (vacía si todo está bien).
    public static function validar(array $d): array
    {
        $errores = [];
        $tipo    = $d['tipo_elemento'] ?? '';

        if (!in_array($tipo, self::TIPOS_ELEMENTO, true)) {
            $errores[] = 'Elegí qué se traslada.';
        }

        // Datos comunes: siempre obligatorios
        $obligatorios = [
            'matricula' => 'el vehículo',
            'chofer'    => 'el chofer',
            'enfermero' => 'el enfermero',
            'razon'     => 'la razón del traslado',
            'destino'   => 'el destino',
        ];
        foreach ($obligatorios as $campo => $nombre) {
            if (trim((string) ($d[$campo] ?? '')) === '') {
                $errores[] = "Falta indicar $nombre.";
            }
        }

        $salida = self::normalizarFecha($d['hora_salida'] ?? null);
        if ($salida === null) {
            $errores[] = 'La hora de salida no es válida.';
        } elseif ($salida < date('Y-m-d H:i:00')) {
            // Las peticiones se hacen desde el minuto actual en adelante, nunca en el pasado
            $errores[] = 'La hora de salida no puede ser anterior al momento actual.';
        }

        // Largos máximos (coinciden con las columnas de la base)
        $maximos = ['chofer' => 60, 'enfermero' => 60, 'razon' => 150, 'destino' => 150, 'observaciones' => 255];
        foreach ($maximos as $campo => $max) {
            if (mb_strlen((string) ($d[$campo] ?? '')) > $max) {
                $errores[] = "El campo $campo admite hasta $max caracteres.";
            }
        }

        // Datos propios de cada tipo de elemento
        if ($tipo === 'Paciente') {
            if (!preg_match('/^\d{6,10}$/', trim((string) ($d['documento'] ?? '')))) {
                $errores[] = 'El documento del paciente debe tener entre 6 y 10 dígitos.';
            }
        } elseif (in_array($tipo, self::TIPOS_ELEMENTO, true)) {
            if (trim((string) ($d['item_nombre'] ?? '')) === '') {
                $errores[] = 'Indicá el nombre de lo que se traslada.';
            }
            if (mb_strlen((string) ($d['item_nombre'] ?? '')) > 80) {
                $errores[] = 'El nombre del elemento admite hasta 80 caracteres.';
            }
            if (mb_strlen((string) ($d['item_caracteristicas'] ?? '')) > 255) {
                $errores[] = 'Las características admiten hasta 255 caracteres.';
            }
        }

        return $errores;
    }

    public static function transicionValida(string $desde, string $hacia): bool
    {
        return in_array($hacia, self::TRANSICIONES[$desde] ?? [], true);
    }
}
