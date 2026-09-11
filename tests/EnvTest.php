<?php
declare(strict_types=1);

use App\Core\Env;

function test_env_lee_claves_comillas_y_comentarios(): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($tmp, "# comentario\nA=1\nB=\"dos tres\"\nC='x'\nD=valor # nota\nE=\n");
    Env::cargar($tmp);
    unlink($tmp);
    assertEq('1', Env::get('A'));
    assertEq('dos tres', Env::get('B'));
    assertEq('x', Env::get('C'));
    assertEq('valor', Env::get('D'));
    assertEq('', Env::get('E'));
    assertEq(null, Env::get('NO_EXISTE'));
    assertEq('def', Env::get('NO_EXISTE', 'def'));
}

function test_env_bool(): void
{
    Env::set('F1', 'true');
    Env::set('F2', '0');
    assertTrue(Env::bool('F1'));
    assertTrue(!Env::bool('F2'));
    assertTrue(Env::bool('F_NO', true));
}

function test_helpers_url_y_asset(): void
{
    Env::set('APP_BASE_PATH', '/cal');
    assertEq('/cal/', url());
    assertEq('/cal/?r=eventos%2Fnuevo&fecha=2026-09-16', url('eventos/nuevo', ['fecha' => '2026-09-16']));
    assertTrue(str_starts_with(asset('css/app.css'), '/cal/assets/css/app.css?v='));
    Env::set('APP_BASE_PATH', '');
    assertEq('/?r=calendario', url('calendario'));
    assertEq('&lt;b&gt;&quot;', h('<b>"'));
    Env::set('APP_BASE_PATH', '/cal');
}
