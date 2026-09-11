<?php
declare(strict_types=1);

use App\Models\Usuario;
use App\Models\Catalogo;

function test_catalogo_resolver_crea_y_reutiliza(): void
{
    conDb(function (PDO $pdo): void {
        $id = Catalogo::resolver('pais', '  Xlandia  del Sur ', 1);
        $f = Catalogo::valor($id);
        assertEq('Xlandia del Sur', $f['valor']);
        assertEq('xlandia del sur', $f['valor_norm']);
        assertEq(1, (int) $f['usos']);
        $id2 = Catalogo::resolver('pais', 'XLANDIA DEL SUR', 2);
        assertEq($id, $id2, 'misma clave normalizada → mismo id');
        assertEq(2, (int) Catalogo::valor($id)['usos']);
        assertEq('Xlandia del Sur', Catalogo::valor($id)['valor'], 'conserva la forma original');
    });
}

function test_catalogo_resolver_na_va_al_na_semilla(): void
{
    conDb(function (PDO $pdo): void {
        $id = Catalogo::resolver('mercado', 'no aplica');
        assertEq('N/A', Catalogo::valor($id)['valor']);
        assertLanza(fn() => Catalogo::resolver('mercado', '   '), 'vacío');
        assertLanza(fn() => Catalogo::resolver('inexistente', 'x'), 'campo inválido');
    });
}

function test_catalogo_sugerencias_prefijo_primero_y_por_usos(): void
{
    conDb(function (PDO $pdo): void {
        $pdo->exec("INSERT INTO catalogo_valores (campo, valor, valor_norm, usos) VALUES
            ('ciudad','Xcaleta','xcaleta',5), ('ciudad','Xcanto','xcanto',9), ('ciudad','Buxca','buxca',1), ('ciudad','Xcinactiva','xcinactiva',99)");
        $pdo->exec("UPDATE catalogo_valores SET activo = 0 WHERE valor_norm = 'xcinactiva'");
        $r = Catalogo::sugerencias('ciudad', 'XCA');
        assertEq(['Xcanto', 'Xcaleta', 'Buxca'], array_column($r, 'valor'));
        // Con la caja vacía se ofrecen los valores más usados: así quien crea un evento
        // ve las opciones disponibles sin tener que empezar a escribir.
        assertTrue(count(Catalogo::sugerencias('ciudad', '')) > 0, 'la consulta vacía ofrece los más usados');
        assertEq([], Catalogo::sugerencias('ciudad', '%'), 'el % se escapa');
    });
}

function test_catalogo_similares_para_quisiste_decir(): void
{
    conDb(function (PDO $pdo): void {
        $pdo->exec("INSERT INTO catalogo_valores (campo, valor, valor_norm, usos) VALUES ('pais','Xqzmejico','xqzmejico',3)");
        $r = Catalogo::similares('pais', 'Xqzmexico');
        assertEq(['Xqzmejico'], array_column($r, 'valor'));
        assertEq([], Catalogo::similares('pais', 'Xqzmejico'), 'exacto no se sugiere');
        assertEq([], Catalogo::similares('pais', 'Xm'));
    });
}

function test_catalogo_area_nueva_recibe_color(): void
{
    conDb(function (PDO $pdo): void {
        $id = Catalogo::resolver('area', 'Innovación Xyz', 1);
        $c = (string) Catalogo::valor($id)['color'];
        assertTrue((bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $c), "color asignado: $c");
        assertEq(null, Catalogo::valor(Catalogo::resolver('pais', 'Xpais'))['color'], 'solo área lleva color');
    });
}

function test_catalogo_unir_renombrar_y_activar(): void
{
    conDb(function (PDO $pdo): void {
        $a = Catalogo::resolver('organizador', 'Xorg A');
        $b = Catalogo::resolver('organizador', 'Xorg B');
        Catalogo::resolver('organizador', 'Xorg B');
        Catalogo::unir($a, $b);
        assertEq(0, (int) Catalogo::valor($a)['activo']);
        assertEq(0, (int) Catalogo::valor($a)['usos']);
        assertEq(3, (int) Catalogo::valor($b)['usos']);
        assertLanza(fn() => Catalogo::unir($b, $b));
        $p = Catalogo::resolver('pais', 'Xpais Z');
        assertLanza(fn() => Catalogo::unir($p, $b), 'campos distintos');

        Catalogo::renombrar($b, '  Xorg  Bé ');
        assertEq('Xorg Bé', Catalogo::valor($b)['valor']);
        assertEq('xorg be', Catalogo::valor($b)['valor_norm']);
        $c = Catalogo::resolver('organizador', 'Xorg C');
        assertLanza(fn() => Catalogo::renombrar($c, 'xorg be'), 'colisión → usar Unir');

        Catalogo::activar($c, false);
        assertEq(0, (int) Catalogo::valor($c)['activo']);
        assertEq([], Catalogo::sugerencias('organizador', 'xorg c'), 'inactivo no se sugiere');
    });
}

function test_catalogo_decrementar_y_recalcular(): void
{
    conDb(function (PDO $pdo): void {
        $id = Catalogo::resolver('mercado', 'Xmercado');
        Catalogo::decrementar($id);
        Catalogo::decrementar($id);
        assertEq(0, (int) Catalogo::valor($id)['usos'], 'no baja de 0');
        Catalogo::incrementar($id);
        assertEq(1, (int) Catalogo::valor($id)['usos']);
        Catalogo::recalcularUsos();
        assertEq(0, (int) Catalogo::valor($id)['usos'], 'sin eventos vivos, usos = 0');
    });
}

function test_catalogo_resolver_reactiva_inactivo(): void
{
    conDb(function (PDO $pdo): void {
        $id = Catalogo::resolver('ciudad', 'Xqz Villa Inactiva');
        Catalogo::activar($id, false);
        assertEq(0, (int) Catalogo::valor($id)['activo']);
        $id2 = Catalogo::resolver('ciudad', 'xqz villa inactiva');
        assertEq($id, $id2);
        assertEq(1, (int) Catalogo::valor($id)['activo']);
        assertEq(2, (int) Catalogo::valor($id)['usos']);
    });
}

function test_catalogo_unir_areas_reasigna_usuarios(): void
{
    conDb(function (PDO $pdo): void {
        $origen = Catalogo::resolver('area', 'Xqz Área Origen');
        $destino = Catalogo::resolver('area', 'Xqz Área Destino');

        $uno = Usuario::crear('Xqz Uno', 'xqz.uno@prueba.local', 'clave12345', 'usuario', $origen);
        Catalogo::unir($origen, $destino);
        assertEq($destino, (int) Usuario::porId($uno)['area_id'], 'el usuario queda reasignado al área destino');
        assertEq(0, (int) Catalogo::valor($origen)['activo'], 'el área origen queda desactivada');

        $dos = Usuario::crear('Xqz Dos', 'xqz.dos@prueba.local', 'clave12345', 'usuario', $destino);
        assertLanza(fn() => Catalogo::activar($destino, false), 'no se desactiva un área con usuarios');

        // El primero llegó al área destino por el "unir" de arriba: hay que sacarlo también.
        Usuario::actualizar($uno, 'Xqz Uno', 'usuario', null);
        Usuario::actualizar($dos, 'Xqz Dos', 'usuario', null);
        Catalogo::activar($destino, false);
        assertEq(0, (int) Catalogo::valor($destino)['activo'], 'sin usuarios activos ya se puede desactivar');
    });
}

function test_catalogo_na_es_intocable(): void
{
    conDb(function (PDO $pdo): void {
        $na = Catalogo::buscarPorNorm('mercado', 'n/a');
        $id = (int) $na['id'];
        $otroId = Catalogo::resolver('mercado', 'Xqz mercado temporal');
        assertLanza(fn() => Catalogo::renombrar($id, 'Otra cosa'), 'renombrar N/A debe lanzar');
        assertLanza(fn() => Catalogo::activar($id, false), 'desactivar N/A debe lanzar');
        assertLanza(fn() => Catalogo::unir($id, $otroId), 'unir N/A como origen debe lanzar');
        assertLanza(fn() => Catalogo::unir($otroId, $id), 'unir N/A como destino debe lanzar');
    });
}
