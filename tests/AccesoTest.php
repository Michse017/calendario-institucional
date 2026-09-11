<?php
declare(strict_types=1);

use App\Models\Acceso;

function test_acceso_otorgar_rol_revocar_reactivar(): void
{
    conDb(function (PDO $pdo): void {
        Acceso::otorgar(900001, 'Prueba Uno', 'usuario');
        $a = Acceso::porId(900001);
        assertEq('usuario', $a['rol']);
        assertEq('activo', $a['estado']);
        Acceso::cambiarRol(900001, 'admin');
        assertEq('admin', Acceso::porId(900001)['rol']);
        assertLanza(fn() => Acceso::cambiarRol(900001, 'jefe'), 'rol inválido');
        Acceso::revocar(900001);
        assertEq('inactivo', Acceso::porId(900001)['estado'], 'baja lógica, la fila sigue');
        Acceso::reactivar(900001);
        assertEq('activo', Acceso::porId(900001)['estado']);
        Acceso::otorgar(900001, 'Prueba Uno B', 'usuario');
        assertEq('Prueba Uno B', Acceso::porId(900001)['nombre'], 'otorgar sobre existente actualiza');
        assertLanza(fn() => Acceso::revocar(1), 'el super-admin (id 1) no se revoca');
        assertLanza(fn() => Acceso::otorgar(0, 'x', 'usuario'), 'id inválido no se otorga');
    });
}

function test_acceso_area_unica(): void
{
    conDb(function (PDO $pdo): void {
        $promo = (int) App\Models\Catalogo::buscarPorNorm('area', 'promocion y mercadeo')['id'];
        $plan = (int) App\Models\Catalogo::buscarPorNorm('area', 'planeacion')['id'];
        $na = (int) App\Models\Catalogo::buscarPorNorm('area', 'n/a')['id'];
        $pais = (int) App\Models\Catalogo::buscarPorNorm('pais', 'colombia')['id'];

        Acceso::otorgar(900002, 'Prueba Dos', 'usuario', $promo);
        $a = Acceso::porId(900002);
        assertEq($promo, (int) $a['area_id']);
        assertEq('Promoción y Mercadeo', $a['area_nombre']);
        assertEq('#0E8F8B', $a['area_color']);

        Acceso::otorgar(900002, 'Prueba Dos', 'admin');
        assertEq($promo, (int) Acceso::porId(900002)['area_id'], 'otorgar sin área conserva la que tenía');

        Acceso::cambiarArea(900002, $plan);
        assertEq('Planeación', Acceso::porId(900002)['area_nombre']);
        assertLanza(fn() => Acceso::cambiarArea(900002, $pais), 'un país no es un área');
        assertLanza(fn() => Acceso::cambiarArea(900002, $na), 'N/A no se asigna');
        assertLanza(fn() => Acceso::cambiarArea(900004, $plan), 'acceso inexistente');
        assertLanza(fn() => Acceso::otorgar(900003, 'x', 'usuario', $pais), 'área inválida al otorgar');
        assertEq(null, Acceso::porId(900003), 'no se creó el acceso con área inválida');

        Acceso::cambiarArea(900002, null);
        assertEq(null, Acceso::porId(900002)['area_id'], 'sin área = solo consulta');

        $fila = null;
        foreach (Acceso::listar() as $x) {
            if ((int) $x['id'] === 900002) {
                $fila = $x;
            }
        }
        assertTrue($fila !== null, 'listar incluye el acceso de prueba');
        assertTrue(array_key_exists('area_nombre', $fila) && $fila['area_nombre'] === null, 'listar trae area_nombre (NULL sin área)');
    });
}
