<?php
declare(strict_types=1);

function test_fecha_humana_y_rangos(): void
{
    assertEq('16 sep 2026', fecha_humana('2026-09-16'));
    assertEq('16 sep', fecha_humana('2026-09-16', false));
    assertEq('16 sep 2026', rango_fechas('2026-09-16', '2026-09-16'));
    assertEq('15–17 sep 2026', rango_fechas('2026-09-15', '2026-09-17'));
    assertEq('26 sep – 3 oct 2026', rango_fechas('2026-09-26', '2026-10-03'));
    assertEq('28 dic 2026 – 2 ene 2027', rango_fechas('2026-12-28', '2027-01-02'));
    assertEq('mié', dia_semana_corto('2026-09-16'));
    assertEq('En ejecución', estado_etiqueta('en_ejecucion'));
    assertEq('#2E9E5B', estado_color('realizado'));
    assertEq('#B3B7BF', estado_color('otro'));
}
