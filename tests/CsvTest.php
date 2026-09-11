<?php
declare(strict_types=1);

use App\Controllers\EventoController;
use App\Core\Campos;

function test_fila_csv_orden_excel(): void
{
    $ev = [
        'id' => 12, 'nombre' => 'Top Resa', 'fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-09-17', 'estado' => 'en_ejecucion',
        'ciudad' => 'París', 'pais' => 'Francia', 'tipo_accion' => 'Participación en Ferias', 'segmento' => 'MICE', 'mercado' => 'Europa',
        'organizador' => 'IFTM', 'area' => 'Promoción y Mercadeo', 'objetivo' => 'Promover', 'linea_estrategica' => 'C2.', 'resultados' => 'N/A',
        'contactos_url' => 'N/A', 'reuniones' => '12', 'alianzas' => 'ProColombia', 'observaciones' => 'N/A', 'evidencia_url' => 'Pendiente',
    ];
    $fila = EventoController::filaCsv($ev);
    assertEq(count(Campos::CSV), count($fila));
    assertEq([12, 'Septiembre', '2026-09-15', '2026-09-17', 'París', 'Francia', 'Participación en Ferias', 'MICE', 'Top Resa', 'Europa', 'IFTM',
        'Promoción y Mercadeo', 'Promover', 'C2.', 'N/A', 'N/A', '12', 'ProColombia', 'En ejecución', 'N/A', 'Pendiente'], $fila);

    $ev['nombre'] = '=SUMA(1)';
    $filaFormula = EventoController::filaCsv($ev);
    assertEq("'=SUMA(1)", $filaFormula[8], 'una celda que empieza con = se blinda contra inyección de fórmulas');

    $ev['estado'] = 'cancelado';
    assertEq('Cancelado', EventoController::filaCsv($ev)[18], 'el estado cancelado sale con su etiqueta');
}
