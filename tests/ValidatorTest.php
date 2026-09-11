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
    foreach (['nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'tipo_accion', 'segmento', 'area', 'linea_estrategica', 'pais', 'ciudad', 'mercado', 'organizador', 'objetivo', 'resultados', 'alianzas', 'observaciones', 'contactos_url', 'evidencia_url', 'reuniones'] as $c) {
        assertTrue(isset($r['errores'][$c]), "falta error en $c");
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
    $r = Validator::evento(payloadValido(['contactos_url' => 'drive.google.com/x']));
    assertTrue(isset($r['errores']['contactos_url']), 'sin esquema http');
    $r = Validator::evento(payloadValido(['contactos_url' => 'javascript:alert(1)']));
    assertTrue(isset($r['errores']['contactos_url']));
    $r = Validator::evento(payloadValido(['evidencia_url' => 'no aplica']));
    assertEq('N/A', $r['datos']['evidencia_url']);
    $r = Validator::evento(payloadValido(['reuniones' => 'muchas']));
    assertTrue(isset($r['errores']['reuniones']));
    $r = Validator::evento(payloadValido(['reuniones' => 'N/A']));
    assertEq('N/A', $r['datos']['reuniones']);
    $r = Validator::evento(payloadValido(['reuniones' => '-3']));
    assertTrue(isset($r['errores']['reuniones']));
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
