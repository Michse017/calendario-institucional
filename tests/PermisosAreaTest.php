<?php
declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\EventoController;
use App\Core\Auth;
use App\Core\Request;

function test_area_permitida_nunca_sale_del_post_para_usuarios(): void
{
    $admin = ['id' => 1, 'nombre' => 'A', 'rol' => 'admin', 'area_id' => 0, 'area_nombre' => ''];
    $ana = ['id' => 7, 'nombre' => 'Ana', 'rol' => 'usuario', 'area_id' => 5, 'area_nombre' => 'Muelle'];
    $sinArea = ['id' => 9, 'nombre' => 'Sin', 'rol' => 'usuario', 'area_id' => 0, 'area_nombre' => ''];
    $evento = ['id' => 10, 'area_id' => 5, 'area' => 'Muelle'];
    assertEq('Planeación', EventoController::areaPermitida($admin, 'Planeación', null), 'el admin elige libremente');
    assertEq('Muelle', EventoController::areaPermitida($ana, 'Planeación', null), 'al crear, un usuario siempre usa su área');
    assertEq('Muelle', EventoController::areaPermitida($ana, 'Presidencia', $evento), 'al editar, un usuario siempre usa su área');
    assertEq('Muelle', EventoController::areaPermitida($sinArea, 'Presidencia', $evento), 'sin área se conserva la del evento');
    assertEq('', EventoController::areaPermitida($sinArea, 'Presidencia', null), 'sin área y sin evento no hay área');
}

function test_filtros_mios_se_traduce_al_area_del_usuario(): void
{
    Auth::fijar(['id' => 7, 'nombre' => 'Ana', 'rol' => 'usuario', 'area_id' => 5, 'area_nombre' => 'Muelle']);
    $_GET = ['r' => 'eventos', 'mios' => '1', 'anio' => '2026'];
    $f = ApiController::filtros(new Request());
    assertEq(5, $f['area_id']);
    assertTrue(!isset($f['mios']), 'mios no llega al modelo');
    assertEq('2026', $f['anio']);
    Auth::fijar(['id' => 9, 'nombre' => 'Sin', 'rol' => 'usuario']);
    $f = ApiController::filtros(new Request());
    assertEq(-1, $f['area_id'], 'sin área, el filtro no devuelve nada');
    $_GET = ['r' => 'eventos', 'area_id' => '3'];
    assertEq('3', ApiController::filtros(new Request())['area_id'], 'sin mios se respeta el área elegida');
    $_GET = [];
}
