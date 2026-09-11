<?php
declare(strict_types=1);

/** Datos de un evento ya validados (forma de Validator::evento()['datos']); los valores "X…" no chocan con la semilla. */
function datosEvento(array $extra = []): array
{
    return $extra + [
        'nombre' => 'Xfit Argentina', 'fecha_inicio' => '2026-09-26', 'fecha_fin' => '2026-09-28', 'estado' => 'no_realizado',
        'tipo_accion' => 'Participación en Ferias', 'segmento' => 'MICE', 'area' => 'Promoción y Mercadeo',
        'linea_estrategica' => 'N/A', 'pais' => 'Xargentina', 'ciudad' => 'Xbuenos Aires', 'mercado' => 'Xcono Sur', 'organizador' => 'Xfaevyt',
        'objetivo' => 'Promover', 'resultados' => 'N/A', 'alianzas' => 'N/A', 'observaciones' => 'N/A',
        'contactos_url' => 'N/A', 'evidencia_url' => 'Pendiente', 'reuniones' => '0',
    ];
}
