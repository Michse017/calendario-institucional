<?php
declare(strict_types=1);

use App\Core\Validator;

function payloadValido(array $extra = []): array
{
    return $extra + [
        'nombre' => '  Top Resa   París ', 'fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-09-17', 'estado' => 'no_realizado',
        'tipo_accion' => 'Participación en Ferias', 'segmento' => 'MICE', 'area' => 'Promoción y Mercadeo',
        'linea_estrategica' => 'C2. Impulsar la promoción internacional', 'pais' => 'Francia', 'ciudad' => 'París',
        'mercado' => 'Europa', 'organizador' => 'IFTM',
        'objetivo' => 'Promover el destino', 'resultados' => 'n/a', 'alianzas' => 'ProColombia', 'observaciones' => 'N/A',
        'contactos_url' => 'https://drive.google.com/x', 'evidencia_url' => 'pendiente', 'reuniones' => ' 12 ',
    ];
}

function test_validator_acepta_payload_completo_y_limpia(): void
{
    $r = Validator::evento(payloadValido());
    assertEq(true, $r['ok'], json_encode($r['errores'], JSON_UNESCAPED_UNICODE));
    $d = $r['datos'];
    assertEq('Top Resa París', $d['nombre']);
    assertEq('N/A', $d['resultados']);
    assertEq('Pendiente', $d['evidencia_url']);
    assertEq('12', $d['reuniones']);
    assertEq('https://drive.google.com/x', $d['contactos_url']);
    assertEq('MICE', $d['segmento']);
}

function test_validator_todos_obligatorios(): void
{
    $r = Validator::evento([]);
    assertEq(false, $r['ok']);

    // Todos los campos son obligatorios menos los de Campos::AUTO_NA, que al
    // quedar vacíos se rellenan solos con N/A en lugar de dar error.
    $obligatorios = [
        'nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'tipo_accion', 'segmento', 'area',
        'linea_estrategica', 'pais', 'ciudad', 'mercado', 'organizador', 'objetivo',
        'alianzas', 'contactos_url', 'evidencia_url', 'reuniones',
    ];
    foreach ($obligatorios as $c) {
        assertTrue(isset($r['errores'][$c]), "falta error en $c");
    }
    foreach (App\Core\Campos::AUTO_NA as $c) {
        assertTrue(!isset($r['errores'][$c]), "$c no debe dar error: se rellena solo con N/A");
    }
}

function test_validator_auto_na_rellena_los_campos_opcionales(): void
{
    $datos = datosEvento();
    foreach (App\Core\Campos::AUTO_NA as $c) {
        $datos[$c] = '';
    }
    $r = Validator::evento($datos);
    assertEq(true, $r['ok'], 'dejar vacíos los campos de relleno automático no invalida el evento');
    foreach (App\Core\Campos::AUTO_NA as $c) {
        assertEq('N/A', $r['datos'][$c], "$c debe quedar en N/A");
    }
}

function test_validator_fechas(): void
{
    $r = Validator::evento(payloadValido(['fecha_fin' => '2026-09-14']));
    assertTrue(isset($r['errores']['fecha_fin']), 'fin antes de inicio');
    $r = Validator::evento(payloadValido(['fecha_inicio' => '15/09/2026']));
    assertTrue(isset($r['errores']['fecha_inicio']), 'formato inválido');
    $r = Validator::evento(payloadValido(['fecha_inicio' => '2026-02-30']));
    assertTrue(isset($r['errores']['fecha_inicio']), 'fecha inexistente');
}

function test_validator_enlaces_y_reuniones(): void
{
    // Evidencia sigue siendo SOLO un enlace (o N/A / Pendiente).
    $r = Validator::evento(payloadValido(['evidencia_url' => 'drive.google.com/x']));
    assertTrue(isset($r['errores']['evidencia_url']), 'sin esquema http');
    $r = Validator::evento(payloadValido(['evidencia_url' => 'javascript:alert(1)']));
    assertTrue(isset($r['errores']['evidencia_url']));
    $r = Validator::evento(payloadValido(['evidencia_url' => 'no aplica']));
    assertEq('N/A', $r['datos']['evidencia_url']);
    // Contactos admite texto libre: enlaces sin esquema, nombres, o en qué va.
    $r = Validator::evento(payloadValido(['contactos_url' => 'Ana Torres 300 123 4567; drive.google.com/x']));
    assertEq('Ana Torres 300 123 4567; drive.google.com/x', $r['datos']['contactos_url']);
    $r = Validator::evento(payloadValido(['contactos_url' => '  pendiente ']));
    assertEq('Pendiente', $r['datos']['contactos_url']);
    $r = Validator::evento(payloadValido(['contactos_url' => str_repeat('x', 501)]));
    assertTrue(isset($r['errores']['contactos_url']), 'máximo 500');
    // Aforo: número, N/A, Pendiente, o un texto corto que diga en qué va.
    $r = Validator::evento(payloadValido(['reuniones' => ' 12 ']));
    assertEq('12', $r['datos']['reuniones']);
    $r = Validator::evento(payloadValido(['reuniones' => 'N/A']));
    assertEq('N/A', $r['datos']['reuniones']);
    $r = Validator::evento(payloadValido(['reuniones' => 'Pendiente de consolidar al finalizar el evento']));
    assertEq('Pendiente de consolidar al finalizar el evento', $r['datos']['reuniones']);
    $r = Validator::evento(payloadValido(['reuniones' => str_repeat('a', 61)]));
    assertTrue(isset($r['errores']['reuniones']), 'máximo 60: la columna es varchar(60)');
}

function test_validator_estado_y_longitudes(): void
{
    $r = Validator::evento(payloadValido(['estado' => 'listo']));
    assertTrue(isset($r['errores']['estado']));
    $r = Validator::evento(payloadValido(['nombre' => str_repeat('a', 201)]));
    assertTrue(isset($r['errores']['nombre']));
    $r = Validator::evento(payloadValido(['ciudad' => str_repeat('b', 256)]));
    assertTrue(isset($r['errores']['ciudad']));
}

function test_validator_cancelado_solo_si_se_permite(): void
{
    $r = Validator::evento(payloadValido(['estado' => 'cancelado']));
    assertTrue(isset($r['errores']['estado']), 'el formulario no puede poner cancelado');
    $r = Validator::evento(payloadValido(['estado' => 'cancelado']), true);
    assertEq(true, $r['ok']);
    assertEq('cancelado', $r['datos']['estado']);
}

function test_validator_mercados_extra_sin_repetidos_ni_principal(): void
{
    $r = Validator::evento(payloadValido(['mercado' => 'Regional', 'mercados_extra' => [' Nacional ', 'Regional', 'Nacional', '', 'Internacional']]));
    assertEq('Regional', $r['datos']['mercado']);
    assertEq(['Nacional', 'Internacional'], $r['datos']['mercados_extra'], 'sin el principal, sin repetidos, sin vacíos');
    assertEq(['Regional', 'Nacional', 'Internacional'], Validator::mercadosDelEnvio(['mercado' => 'Regional', 'mercados_extra' => ['Nacional', 'Internacional', 'Regional']]));
    $r = Validator::evento(payloadValido(['mercado' => 'Regional']));
    assertEq([], $r['datos']['mercados_extra'], 'sin extras es una lista vacía, no falta la clave');
}
