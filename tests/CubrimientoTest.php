<?php
declare(strict_types=1);

use App\Core\Validator;
use App\Models\Catalogo;
use App\Models\Dashboard;
use App\Models\Evento;

/**
 * Cubrimiento: un evento pide cubrimiento o no, y quien está comprometido es su RESPONSABLE.
 * Todo lo que cuenta gente (mapa en modo personas, Disponibilidad, línea de tiempo, informes)
 * sale de esa regla. Cuentas de la semilla: 1 Ana (admin), 2 Carlos (Programación),
 * 3 Lucía (Comunicaciones); el resto del personal se busca por correo.
 */

/** Id de una cuenta del personal sembrado, por su correo. */
function idPorCorreo(PDO $pdo, string $correo): int
{
    $st = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ?');
    $st->execute([$correo]);
    $id = (int) $st->fetchColumn();
    if ($id <= 0) {
        omitir("falta la cuenta $correo de la semilla");
    }
    return $id;
}

function test_mapa_en_modo_personas_cuenta_responsables_de_eventos_con_cubrimiento(): void
{
    conDb(function (): void {
        // Dos eventos el MISMO día con el mismo responsable (el 2): cuenta 1, no 2.
        Evento::crear(datosEvento(['nombre' => 'Xmapa uno', 'fecha_inicio' => '2030-05-10', 'fecha_fin' => '2030-05-10', 'requiere_cubrimiento' => '1']), 1);
        Evento::crear(datosEvento(['nombre' => 'Xmapa dos', 'fecha_inicio' => '2030-05-10', 'fecha_fin' => '2030-05-10', 'requiere_cubrimiento' => '1']), 1);
        // Otra responsable (la 1, admin) el mismo día: ya son dos personas.
        Evento::crear(datosEvento(['nombre' => 'Xmapa tres', 'fecha_inicio' => '2030-05-10', 'fecha_fin' => '2030-05-10', 'requiere_cubrimiento' => '1', 'dueno_id' => '1']), 1);
        // Sin cubrimiento: el responsable no cuenta como comprometido.
        Evento::crear(datosEvento(['nombre' => 'Xmapa libre', 'fecha_inicio' => '2030-05-11', 'fecha_fin' => '2030-05-11']), 1);

        $eventos = Evento::mapaCalor(2030, [], 'activos');
        $personas = Evento::mapaCalor(2030, [], 'personas');
        assertEq(4, (int) $eventos['resumen']['acciones'], 'en modo normal son 4 eventos');
        assertEq(2, (int) $personas['resumen']['acciones'], 'personas distintas: el 2 y la 1');
        assertEq(2, (int) $personas['max'], 'el día 10 coinciden las dos');
        assertEq(1, (int) $personas['resumen']['dias_con_algo'], 'el día 11 no compromete a nadie');
    });
}

function test_mapa_en_modo_piden_cubrimiento(): void
{
    conDb(function (): void {
        Evento::crear(datosEvento(['nombre' => 'Xpide', 'fecha_inicio' => '2031-03-05', 'fecha_fin' => '2031-03-06', 'requiere_cubrimiento' => '1']), 1);
        Evento::crear(datosEvento(['nombre' => 'Xno pide', 'fecha_inicio' => '2031-03-20', 'fecha_fin' => '2031-03-20']), 1);
        $m = Evento::mapaCalor(2031, [], 'sincubrir');
        assertEq(1, (int) $m['resumen']['acciones'], 'solo el que pide cubrimiento');
        assertEq(2, (int) $m['resumen']['dias_con_algo'], 'sus dos días');
        assertEq(['Xpide'], array_values(array_map(static fn(array $d): string => $d['nombre'], $m['detalles'])));
    });
}

function test_informe_de_cubrimiento(): void
{
    conDb(function (): void {
        // 2032: dos eventos del responsable 2, uno de tres días que pide cubrimiento y otro que no.
        Evento::crear(datosEvento(['nombre' => 'Xcarga', 'fecha_inicio' => '2032-04-01', 'fecha_fin' => '2032-04-03', 'requiere_cubrimiento' => '1']), 1);
        Evento::crear(datosEvento(['nombre' => 'Xlibre', 'fecha_inicio' => '2032-05-05', 'fecha_fin' => '2032-05-05']), 1);
        $c = Dashboard::cubrimiento(2032);

        assertEq(1, count($c['carga']), 'una sola persona con carga');
        assertEq(2, $c['carga'][0]['id']);
        assertEq(1, $c['carga'][0]['eventos']);
        assertEq(3, $c['carga'][0]['dias'], 'del 1 al 3 son 3 días');

        $r = array_column($c['responsables'], null, 'id');
        assertEq(2, $r[2]['eventos'], 'lidera dos eventos');
        assertEq(1, $r[2]['con_cubrimiento'], 'uno pide cubrimiento');

        $a = array_column($c['por_area'], null, 'area');
        assertEq(['total' => 2, 'piden' => 1], array_intersect_key($a['Programación'], array_flip(['total', 'piden'])));

        $sem = array_column($c['semanas'], 'personas_dia', 'semana');
        assertEq(3, $sem[(int) (new DateTime('2032-04-01'))->format('W')], '1 persona por 3 días');
        assertEq(0, $sem[(int) (new DateTime('2032-05-05'))->format('W')], 'el evento sin cubrimiento no compromete');
    });
}

function test_disponibilidad_separa_comprometidos_de_disponibles(): void
{
    conDb(function (): void {
        Evento::crear(datosEvento(['nombre' => 'Xocupa', 'fecha_inicio' => '2034-07-10', 'fecha_fin' => '2034-07-12', 'requiere_cubrimiento' => '1']), 1);

        $gente = Dashboard::disponibilidad('2034-07-11', '2034-07-11');
        $ocupados = array_values(array_filter($gente, static fn(array $p): bool => (int) $p['eventos'] > 0));
        assertEq(1, count($ocupados), 'solo el responsable está comprometido');
        assertEq(2, (int) $ocupados[0]['id']);
        assertEq('Xocupa', $ocupados[0]['lista'][0]['nombre'], 'trae la lista con fechas');
        assertTrue(count($gente) > 1, 'el resto aparece como disponible');
        assertEq(['Xocupa'], array_map(static fn(array $e): string => $e['nombre'], Dashboard::agendaDe(2, '2034-07-01', '2034-07-31')));

        // Sin cubrimiento, nadie queda comprometido.
        Evento::crear(datosEvento(['nombre' => 'Xsin cub', 'fecha_inicio' => '2034-08-10', 'fecha_fin' => '2034-08-10']), 1);
        assertEq(0, count(array_filter(Dashboard::disponibilidad('2034-08-10', '2034-08-10'), static fn(array $p): bool => (int) $p['eventos'] > 0)));
        // Fuera del rango, nadie.
        assertEq(0, count(array_filter(Dashboard::disponibilidad('2034-09-01', '2034-09-02'), static fn(array $p): bool => (int) $p['eventos'] > 0)));
    });
}

function test_filtrar_la_lista_por_responsable(): void
{
    conDb(function (): void {
        Evento::crear(datosEvento(['nombre' => 'Xde 2', 'fecha_inicio' => '2035-02-01', 'fecha_fin' => '2035-02-01']), 1);
        Evento::crear(datosEvento(['nombre' => 'Xde 1', 'fecha_inicio' => '2035-02-02', 'fecha_fin' => '2035-02-02', 'dueno_id' => '1']), 1);
        Evento::crear(datosEvento(['nombre' => 'Xde 3', 'fecha_inicio' => '2035-02-03', 'fecha_fin' => '2035-02-03', 'dueno_id' => '3', 'area' => 'Comunicaciones']), 1);

        assertEq(1, (int) Evento::listar(['anio' => '2035', 'persona' => '2'], 1, 50)['total'], 'una persona');
        assertEq('Xde 2', Evento::listar(['anio' => '2035', 'persona' => '2'], 1, 50)['filas'][0]['nombre']);
        assertEq(2, (int) Evento::listar(['anio' => '2035', 'persona' => '2,1'], 1, 50)['total'], 'dos personas: suma, no interseca');
        assertEq(3, (int) Evento::listar(['anio' => '2035', 'persona' => '2,2,1,3'], 1, 50)['total'], 'los repetidos no cuentan doble');
        assertEq(3, (int) Evento::listar(['anio' => '2035'], 1, 50)['total'], 'sin filtro salen los tres');
    });
}

function test_linea_de_tiempo_por_area_con_lo_que_lidera_cada_uno(): void
{
    conDb(function (PDO $pdo): void {
        $marta = idPorCorreo($pdo, 'marta.ibanez@meridiano.demo');   // Programación, como el 2
        Evento::crear(datosEvento(['nombre' => 'Xlt pide', 'fecha_inicio' => '2042-03-03', 'fecha_fin' => '2042-03-05', 'requiere_cubrimiento' => '1']), 1);
        Evento::crear(datosEvento(['nombre' => 'Xlt no', 'fecha_inicio' => '2042-03-20', 'fecha_fin' => '2042-03-21', 'dueno_id' => (string) $marta]), 1);

        $lt = Evento::lineaTiempo('2042-03-01', '2042-03-31');
        $prog = null;
        foreach ($lt['areas'] as $a) { if ($a['nombre'] === 'Programación') { $prog = $a; } }
        assertTrue($prog !== null, 'sale el área del fixture');
        $ev = array_column($prog['eventos'], null, 'nombre');
        assertEq(2, count($ev));
        assertTrue($ev['Xlt pide']['cubre'] && !$ev['Xlt no']['cubre']);
        assertEq(2, $ev['Xlt pide']['dueno_id']);
        assertEq($marta, $ev['Xlt no']['dueno_id']);
        $ids = array_map(static fn(array $p): int => $p['id'], $prog['personas']);
        assertTrue(in_array($marta, $ids, true) && in_array(2, $ids, true), 'la gente del área va en su bloque');

        $solo = Evento::lineaTiempo('2042-03-01', '2042-03-31', ['persona' => (string) $marta]);
        assertEq(1, count($solo['areas']), 'solo el área de esa persona');
        assertEq([$marta], array_map(static fn(array $p): int => $p['id'], $solo['areas'][0]['personas']));
        assertEq(2, count($solo['areas'][0]['eventos']), 'con todos los eventos del área');

        $otra = Evento::lineaTiempo('2042-03-01', '2042-03-31', ['area_id' => (string) Catalogo::idArea('Educación')]);
        assertTrue(!in_array('Programación', array_column($otra['areas'], 'nombre'), true), 'el filtro de área acota');
    });
}

function test_cambio_rapido_de_cubrimiento_desde_la_ficha(): void
{
    conDb(function (PDO $pdo): void {
        $id = Evento::crear(datosEvento(['nombre' => 'Xcr rapido']), 1);
        assertEq(0, (int) ((array) Evento::porId($id))['requiere_cubrimiento'], 'nace sin pedir cubrimiento');

        Evento::fijarCubrimiento($id, true, 1);
        assertEq(1, (int) ((array) Evento::porId($id))['requiere_cubrimiento'], 'ahora lo pide');
        $st = $pdo->prepare("SELECT cambios FROM eventos_historial WHERE evento_id = ? AND accion = 'editar' ORDER BY id DESC LIMIT 1");
        $st->execute([$id]);
        assertTrue(str_contains((string) $st->fetchColumn(), 'cubrimiento'), 'queda en el historial');

        $st2 = $pdo->prepare('SELECT COUNT(*) FROM eventos_historial WHERE evento_id = ?');
        $st2->execute([$id]);
        $antes = (int) $st2->fetchColumn();
        Evento::fijarCubrimiento($id, true, 1);
        $st2->execute([$id]);
        assertEq($antes, (int) $st2->fetchColumn(), 'repetir el mismo valor no escribe nada');

        Evento::fijarCubrimiento($id, false, 1);
        assertEq(0, (int) ((array) Evento::porId($id))['requiere_cubrimiento'], 'y se puede apagar');
        assertTrue(is_array(Evento::pidenCubrimiento('2026-01-01', '2026-12-31')), 'la lista del mapa existe');
    });
}

function test_el_validador_normaliza_el_cubrimiento_a_uno_o_cero(): void
{
    assertEq('1', Validator::evento(datosEvento(['requiere_cubrimiento' => '1']))['datos']['requiere_cubrimiento']);
    assertEq('0', Validator::evento(datosEvento(['requiere_cubrimiento' => '0']))['datos']['requiere_cubrimiento']);
    $sin = datosEvento();
    unset($sin['requiere_cubrimiento']);
    assertEq('0', Validator::evento($sin)['datos']['requiere_cubrimiento'], 'si no viene, es que no');
}
