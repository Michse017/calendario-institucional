<?php
declare(strict_types=1);

/** Datos de un evento ya validados (forma de Validator::evento()['datos']); los valores "X…" no chocan con la semilla. */
function datosEvento(array $extra = []): array
{
    return $extra + [
        'nombre' => 'Xciclo de Jazz', 'fecha_inicio' => '2026-09-26', 'fecha_fin' => '2026-09-28', 'estado' => 'no_realizado',
        'tipo_accion' => 'Concierto', 'segmento' => 'Público general', 'area' => 'Programación',
        'linea_estrategica' => 'C1. Ampliar el acceso de la ciudadanía a la programación cultural', 'pais' => 'Xandalia', 'ciudad' => 'Xpuerto Sereno', 'mercado' => 'Xregional', 'organizador' => 'Xmeridiano',
        'objetivo' => 'Acercar el jazz a nuevos públicos', 'resultados' => 'N/A', 'alianzas' => 'N/A', 'observaciones' => 'N/A',
        'contactos_url' => 'N/A', 'evidencia_url' => 'Pendiente', 'reuniones' => '0',
    ];
}
