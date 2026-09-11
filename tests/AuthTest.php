<?php
declare(strict_types=1);

use App\Core\Auth;

function test_auth_puede_editar_por_area(): void
{
    $admin = ['id' => 1, 'nombre' => 'Admin', 'rol' => 'admin', 'area_id' => 0];
    $ana = ['id' => 7, 'nombre' => 'Ana', 'rol' => 'usuario', 'area_id' => 5];
    $sinArea = ['id' => 9, 'nombre' => 'Sin', 'rol' => 'usuario', 'area_id' => 0];
    $evArea5 = ['id' => 10, 'area_id' => 5, 'creado_por' => 99];
    $evArea9 = ['id' => 11, 'area_id' => '9', 'creado_por' => 7];
    assertTrue(Auth::puedeEditar($admin, $evArea9), 'admin edita todo');
    assertTrue(Auth::puedeEditar($ana, $evArea5), 'misma área edita aunque no lo haya creado');
    assertTrue(!Auth::puedeEditar($ana, $evArea9), 'otra área no edita aunque lo haya creado');
    assertTrue(!Auth::puedeEditar($sinArea, $evArea5), 'sin área no edita');
    assertTrue(!Auth::puedeEditar($ana, []), 'sin area_id no se edita');
    assertTrue(Auth::puedeCrear($admin), 'admin crea');
    assertTrue(Auth::puedeCrear($ana), 'con área crea');
    assertTrue(!Auth::puedeCrear($sinArea), 'sin área no crea');
    Auth::fijar(['id' => 7, 'nombre' => 'Ana', 'rol' => 'usuario']);
    assertEq(0, Auth::usuario()['area_id'], 'fijar completa area_id');
    assertEq('', Auth::usuario()['area_nombre'], 'fijar completa area_nombre');
}

function test_auth_fijar_y_es_admin(): void
{
    Auth::fijar(['id' => 7, 'nombre' => 'Ana', 'rol' => 'usuario']);
    assertTrue(!Auth::esAdmin());
    assertEq('Ana', usuario_actual()['nombre']);
    Auth::fijar(['id' => 1, 'nombre' => 'Admin', 'rol' => 'admin']);
    assertTrue(es_admin());
}
