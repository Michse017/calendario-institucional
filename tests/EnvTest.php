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

function test_env_prioriza_el_entorno_sobre_el_archivo(): void
{
    // Un contenedor no tiene .env: la plataforma inyecta la configuración como
    // variables de entorno. Si solo se mirara el archivo, la aplicación desplegada
    // caería a los valores por defecto sin avisar.
    App\Core\Env::set('XPRUEBA_ENTORNO', 'valor-del-archivo');
    assertEq('valor-del-archivo', App\Core\Env::get('XPRUEBA_ENTORNO'));

    putenv('XPRUEBA_ENTORNO=valor-del-entorno');
    assertEq('valor-del-entorno', App\Core\Env::get('XPRUEBA_ENTORNO'), 'el entorno manda sobre el archivo');

    putenv('XPRUEBA_ENTORNO');   // se retira
    assertEq('valor-del-archivo', App\Core\Env::get('XPRUEBA_ENTORNO'), 'sin variable vuelve el archivo');

    // Una variable definida y vacía es un valor legítimo, no un «no hay».
    putenv('XPRUEBA_VACIA=');
    assertEq('', App\Core\Env::get('XPRUEBA_VACIA', 'por-defecto'), 'vacía no debe caer al valor por defecto');
    putenv('XPRUEBA_VACIA');
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
    Env::set('APP_BASE_PATH', '/agenda');
    assertEq('/agenda/', url());
    assertEq('/agenda/?r=eventos%2Fnuevo&fecha=2026-09-16', url('eventos/nuevo', ['fecha' => '2026-09-16']));
    assertTrue(str_starts_with(asset('css/app.css'), '/agenda/assets/css/app.css?v='));
    Env::set('APP_BASE_PATH', '');
    assertEq('/?r=calendario', url('calendario'));
    assertEq('&lt;b&gt;&quot;', h('<b>"'));
    // Se restaura el valor por defecto: dejarlo puesto contaminaria las
    // pruebas siguientes, que dan por hecho que la aplicacion cuelga de la raiz.
    Env::set('APP_BASE_PATH', '');
}
