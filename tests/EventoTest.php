<?php
declare(strict_types=1);

use App\Models\Catalogo;
use App\Models\Evento;
use App\Models\Historial;

function test_evento_crear_y_leer_con_catalogos(): void
{
    conDb(function (PDO $pdo): void {
        $id = Evento::crear(datosEvento(), 7);
        $ev = Evento::porId($id);
        assertEq('Xciclo de Jazz', $ev['nombre']);
        assertEq('Programación', $ev['area']);
        assertEq('#3A5BD9', $ev['area_color']);
        assertEq('Xpuerto Sereno', $ev['ciudad']);
        assertEq(7, (int) $ev['creado_por']);
        assertEq(null, $ev['eliminado_en']);
        $pais = Catalogo::buscarPorNorm('pais', 'xandalia');
        assertEq(1, (int) $pais['usos'], 'el país nuevo quedó con 1 uso');
        $h = Historial::listar(10, $id);
        assertEq('crear', $h[0]['accion']);
        assertEq('Xciclo de Jazz', $h[0]['cambios']['despues']['nombre']);
    });
}

function test_evento_actualizar_ajusta_usos_y_registra_diferencias(): void
{
    conDb(function (PDO $pdo): void {
        // Las áreas semilla pueden tener usos previos (eventos importados): se comparan valores relativos.
        $usosOrigen = (int) Catalogo::buscarPorNorm('area', 'programacion')['usos'];
        $usosDestino = (int) Catalogo::buscarPorNorm('area', 'educacion')['usos'];
        $id = Evento::crear(datosEvento(), 7);
        Evento::actualizar($id, datosEvento(['area' => 'Educación', 'estado' => 'realizado', 'nombre' => 'Xfit 2']), 7);
        $ev = Evento::porId($id);
        assertEq('Educación', $ev['area']);
        assertEq('realizado', $ev['estado']);
        assertEq($usosOrigen, (int) Catalogo::buscarPorNorm('area', 'programacion')['usos'], 'el área vieja devuelve su uso');
        assertEq($usosDestino + 1, (int) Catalogo::buscarPorNorm('area', 'educacion')['usos'], 'el área nueva suma un uso');
        $pais = Catalogo::buscarPorNorm('pais', 'xandalia');
        assertEq(1, (int) $pais['usos'], 'lo que no cambió queda igual');
        $h = Historial::listar(10, $id);
        assertEq('editar', $h[0]['accion']);
        assertEq(['antes' => 'Programación', 'despues' => 'Educación'], $h[0]['cambios']['area']);
        assertTrue(!isset($h[0]['cambios']['pais']));
    });
}

function test_evento_eliminar_es_logico_y_restaurar_vuelve(): void
{
    conDb(function (PDO $pdo): void {
        $id = Evento::crear(datosEvento(), 7);
        Evento::eliminar($id, 1, 'Se retira de la programación de la temporada.');
        assertEq(null, Evento::porId($id));
        $ev = Evento::porId($id, true);
        assertTrue($ev['eliminado_en'] !== null);
        assertEq(0, (int) Catalogo::buscarPorNorm('pais', 'xandalia')['usos']);
        Evento::restaurar($id, 1);
        assertTrue(Evento::porId($id) !== null);
        assertEq(1, (int) Catalogo::buscarPorNorm('pais', 'xandalia')['usos']);
        assertEq(['restaurar', 'eliminar', 'crear'], array_column(Historial::listar(10, $id), 'accion'));
    });
}

function test_evento_rango_listar_proximos_y_filtros(): void
{
    conDb(function (PDO $pdo): void {
        $a = Evento::crear(datosEvento(['nombre' => 'Xa', 'fecha_inicio' => '2030-09-26', 'fecha_fin' => '2030-09-28', 'ciudad' => 'XCapital']), 7);
        $b = Evento::crear(datosEvento(['nombre' => 'Xb', 'fecha_inicio' => '2030-10-01', 'fecha_fin' => '2030-10-01', 'estado' => 'realizado', 'area' => 'Muelle', 'ciudad' => 'XlaBoca']), 8);
        $r = Evento::enRango('2030-09-28', '2030-09-30');
        assertEq(['Xa'], array_column($r, 'nombre'), 'multi-día que solapa el rango');
        $r = Evento::enRango('2030-09-01', '2030-10-31', ['estado' => 'realizado']);
        assertEq(['Xb'], array_column($r, 'nombre'));
        $r = Evento::enRango('2030-09-01', '2030-10-31', ['creado_por' => 7]);
        assertEq(['Xa'], array_column($r, 'nombre'));
        $l = Evento::listar(['anio' => 2030, 'q' => 'Xb'], 1, 10);
        assertEq(1, $l['total']);
        assertEq('Xb', $l['filas'][0]['nombre']);
        $c = Evento::conteos(['anio' => 2030]);
        assertEq(['no_realizado' => 1, 'realizado' => 1], $c['estados'], 'en el orden del ENUM');
        assertEq(2, count($c['areas']));
        $anios = Evento::anios();
        assertTrue(in_array(2030, $anios, true) && in_array((int) date('Y'), $anios, true));
        assertEq(['Xb'], array_column(Evento::buscar('Xb'), 'nombre'));
        $hoy = date('Y-m-d');
        $p = Evento::crear(datosEvento(['nombre' => 'Xhoy', 'fecha_inicio' => $hoy, 'fecha_fin' => $hoy]), 7);
        assertTrue(in_array('Xhoy', array_column(Evento::proximos(30, 50), 'nombre'), true));
    });
}

function test_evento_buscar_escapa_comodines_like(): void
{
    conDb(function (PDO $pdo): void {
        Evento::crear(datosEvento(['nombre' => 'Xpct Meta 5%off Anual']), 7);
        Evento::crear(datosEvento(['nombre' => 'Xpct Meta 5andoff Anual']), 7);
        $r = array_column(Evento::buscar('5%off'), 'nombre');
        assertEq(['Xpct Meta 5%off Anual'], $r, 'el % de la búsqueda es literal, no comodín SQL');
    });
}

function test_evento_mover_fechas(): void
{
    conDb(function (PDO $pdo): void {
        $id = Evento::crear(datosEvento(), 7);
        Evento::moverFechas($id, '2026-10-01', '2026-10-03', 7);
        $ev = Evento::porId($id);
        assertEq('2026-10-01', $ev['fecha_inicio']);
        assertEq('2026-10-03', $ev['fecha_fin']);
        assertLanza(fn() => Evento::moverFechas($id, '2026-10-05', '2026-10-03', 7), 'fin < inicio');
        assertLanza(fn() => Evento::moverFechas($id, '2026-13-01', '2026-13-01', 7), 'fecha inválida');
        $h = Historial::listar(10, $id);
        assertEq('editar', $h[0]['accion']);
        assertEq('2026-10-01', $h[0]['cambios']['fecha_inicio']['despues']);
    });
}

function test_evento_cancelar_y_reanudar(): void
{
    conDb(function (PDO $pdo): void {
        $id = Evento::crear(datosEvento(['estado' => 'en_ejecucion']), 7);
        $usosPais = (int) Catalogo::buscarPorNorm('pais', 'xandalia')['usos'];

        assertLanza(fn() => Evento::cancelar($id, 'ab', 7), 'motivo demasiado corto');
        Evento::cancelar($id, '  Se cayó el patrocinio  ', 7);
        $ev = Evento::porId($id);
        assertEq('cancelado', $ev['estado']);
        assertEq('Se cayó el patrocinio', $ev['cancelacion_motivo']);
        assertEq($usosPais, (int) Catalogo::buscarPorNorm('pais', 'xandalia')['usos'], 'cancelar no toca usos');
        $h = Historial::listar(10, $id);
        assertEq('cancelar', $h[0]['accion']);
        assertEq(['estado_previo' => 'en_ejecucion', 'motivo' => 'Se cayó el patrocinio'], $h[0]['cambios']);
        assertLanza(fn() => Evento::cancelar($id, 'otra vez', 7), 'ya está cancelado');

        Evento::reanudar($id, 7);
        $ev = Evento::porId($id);
        assertEq('en_ejecucion', $ev['estado'], 'vuelve al estado previo');
        assertEq(null, $ev['cancelacion_motivo']);
        $h = Historial::listar(10, $id);
        assertEq('reanudar', $h[0]['accion']);
        assertEq(['estado' => 'en_ejecucion'], $h[0]['cambios']);
        assertLanza(fn() => Evento::reanudar($id, 7), 'no está cancelado');
        assertLanza(fn() => Evento::cancelar(999999999, 'motivo válido', 7), 'inexistente');
    });
}

function test_evento_reanudar_sin_historial_cae_a_no_realizado(): void
{
    conDb(function (PDO $pdo): void {
        $id = Evento::crear(datosEvento(['estado' => 'realizado']), 7);
        Evento::cancelar($id, 'Se cae por lluvia', 7);
        $pdo->exec("DELETE FROM eventos_historial WHERE evento_id = $id AND accion = 'cancelar'");
        Evento::reanudar($id, 7);
        $ev = Evento::porId($id);
        assertEq('no_realizado', $ev['estado'], 'sin el registro del historial, cae al valor por defecto');
        assertEq(null, $ev['cancelacion_motivo']);
    });
}

function test_evento_listar_orden(): void
{
    conDb(function (PDO $pdo): void {
        $a = Evento::crear(datosEvento(['nombre' => 'Xo1', 'fecha_inicio' => '2033-01-10', 'fecha_fin' => '2033-01-10', 'area' => 'Muelle']), 7);
        $b = Evento::crear(datosEvento(['nombre' => 'Xo2', 'fecha_inicio' => '2033-02-10', 'fecha_fin' => '2033-02-10', 'area' => 'Educación']), 7);
        assertEq(['Xo1', 'Xo2'], array_column(Evento::listar(['anio' => 2033])['filas'], 'nombre'), 'por defecto del más cercano al más lejano (asc)');
        assertEq(['Xo2', 'Xo1'], array_column(Evento::listar(['anio' => 2033, 'orden' => 'desc'])['filas'], 'nombre'));
        assertEq(['Xo1', 'Xo2'], array_column(Evento::listar(['anio' => 2033, 'orden' => 'raro'])['filas'], 'nombre'), 'valor inválido cae al orden por defecto (asc)');
        assertEq(['Xo2', 'Xo1'], array_column(Evento::todos(['anio' => 2033, 'orden' => 'desc']), 'nombre'));
        assertEq(['Xo1', 'Xo2'], array_column(Evento::todos(['anio' => 2033]), 'nombre'));

        $muelle = (int) Evento::porId($a)['area_id'];
        assertEq(['Xo1'], array_column(Evento::todos(['anio' => 2033, 'area_id' => $muelle]), 'nombre'), 'filtro por área (lo usa Solo mi área)');
        assertEq([], Evento::todos(['anio' => 2033, 'area_id' => -1]), 'un usuario sin área no ve nada con Solo mi área');
    });
}

function test_evento_mismo_nombre_avisa_sin_impedir_y_excluye_el_propio(): void
{
    conDb(function (PDO $pdo): void {
        $a = Evento::crear(datosEvento(['nombre' => 'Xcomité mensual']), 7);
        $b = Evento::crear(datosEvento(['nombre' => 'Xcomité mensual', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-05']), 7);
        $ids = static fn(array $r): array => array_map('intval', array_column($r, 'id'));
        // Espacios sobrantes y mayúsculas no cambian el resultado (limpiar + cotejamiento _ci).
        assertEq([$a, $b], $ids(Evento::mismoNombre('  xcomité   MENSUAL ')), 'los dos vivos, del más cercano al más lejano');
        assertEq([$b], $ids(Evento::mismoNombre('Xcomité mensual', $a)), 'al editar se excluye el propio evento');
        assertEq([], Evento::mismoNombre('   '), 'con nombre vacío no busca');
        assertEq([], Evento::mismoNombre('Xcomité'), 'es igualdad exacta, no "contiene"');
        Evento::eliminar($b, 7, 'prueba de duplicados');
        assertEq([$a], $ids(Evento::mismoNombre('Xcomité mensual')), 'los eliminados no cuentan');
    });
}
