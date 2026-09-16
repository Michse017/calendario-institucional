<?php
declare(strict_types=1);

use App\Models\AsistenteDatos;

function test_asistente_datos_valida_los_filtros_contra_el_catalogo(): void
{
    conDb(function (PDO $pdo): void {
        $todo = AsistenteDatos::ejecutar('contar_eventos', []);
        assertTrue($todo['eventos'] > 0, 'sin filtros cuenta todo el calendario');
        assertEq('ninguno, son las cifras de todo el calendario', $todo['filtros_aplicados']);

        // Un valor que no existe NO se busca a ciegas: se ignora y se avisa. Si no,
        // el asistente diría "hay 0 eventos" y dejaría creer que el área existe.
        $malo = AsistenteDatos::ejecutar('contar_eventos', ['area' => 'Marketing']);
        assertEq(['area' => 'Marketing'], $malo['filtros_ignorados_por_no_existir']);
        assertEq($todo['eventos'], $malo['eventos'], 'el filtro inválido no recorta el resultado');

        $real = AsistenteDatos::ejecutar('contar_eventos', ['area' => 'Educación']);
        assertEq(['area' => 'Educación'], $real['filtros_aplicados']);
        assertTrue($real['eventos'] < $todo['eventos'], 'el filtro válido sí recorta');
        assertTrue($real['tasa_cumplimiento_pct'] >= 0 && $real['tasa_cumplimiento_pct'] <= 100, 'la tasa es un porcentaje');
    });
}

function test_asistente_datos_no_ejecuta_consultas_que_no_estan_en_el_menu(): void
{
    conDb(function (PDO $pdo): void {
        assertEq('Esa consulta no existe.', AsistenteDatos::ejecutar('borrar_eventos', [])['error']);
        assertEq('Esa consulta no existe.', AsistenteDatos::ejecutar('SELECT * FROM usuarios', [])['error']);
        assertTrue(isset(AsistenteDatos::ejecutar('resumen_por', ['dimension' => 'contrasena'])['error']), 'dimensión fuera del menú');
    });
}

function test_asistente_datos_agrupa_y_suma_igual_que_el_total(): void
{
    conDb(function (PDO $pdo): void {
        $total = AsistenteDatos::ejecutar('contar_eventos', [])['eventos'];
        $r = AsistenteDatos::ejecutar('resumen_por', ['dimension' => 'area']);
        assertEq('area', $r['desglose_por']);
        assertEq($total, array_sum(array_column($r['grupos'], 'eventos')), 'el desglose suma el total');
        $n = array_column($r['grupos'], 'eventos');
        assertEq($n, array_reverse(array_reverse($n)), 'orden estable');
        assertTrue($n[0] >= end($n), 'viene de mayor a menor');
    });
}
