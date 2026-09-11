<?php
declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;

final class ControladorDePrueba
{
    public static string $ultimo = '';
    public function hola(Request $r): void
    {
        self::$ultimo = 'hola ' . ($r->get('n') ?? '');
    }
}

function test_router_despacha_closure_y_controlador(): void
{
    $router = new Router();
    $llamado = '';
    $router->get('ping', function (Request $r) use (&$llamado): void {
        $llamado = 'pong';
    });
    $router->get('saludo', [ControladorDePrueba::class, 'hola']);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['r' => 'ping'];
    $router->dispatch(new Request());
    assertEq('pong', $llamado);

    $_GET = ['r' => '/saludo/', 'n' => 'Ana'];
    $router->dispatch(new Request());
    assertEq('hola Ana', ControladorDePrueba::$ultimo);
}

function test_request_ruta_por_defecto_y_saneada(): void
{
    $_GET = [];
    assertEq('calendario', (new Request())->ruta());
    $_GET = ['r' => '../etc'];
    assertEq('__invalida__', (new Request())->ruta());
    $_GET = ['r' => 'eventos/nuevo', 'anio' => '2026', 'estado' => 'realizado', 'q' => ''];
    assertEq(['anio' => '2026', 'estado' => 'realizado'], (new Request())->filtros());
    $_GET = [];
}

function test_router_post_con_csrf_valido_despacha(): void
{
    $router = new Router();
    $llamado = false;
    $router->post('guardar', function (Request $r) use (&$llamado): void {
        $llamado = true;
    });
    $_SESSION = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_csrf' => \App\Core\Csrf::token()];
    $_GET = ['r' => 'guardar'];
    $router->dispatch(new Request());
    assertTrue($llamado, 'con token válido el POST llega al manejador');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];
    $_SESSION = [];
}

// No hay prueba del caso contrario (POST sin testigo) porque Response::error()
// termina el proceso en lugar de lanzar, y se llevaria por delante al ejecutor.
// Ese camino queda cubierto por las pruebas de extremo a extremo contra el servidor.
function test_router_ruta_con_token_se_salta_el_csrf(): void
{
    // Las rutas de máquina a máquina se autentican con una cabecera propia, que un
    // navegador no puede añadir desde otro origen: por eso el CSRF no aplica y el
    // manejador es quien tiene que comprobar el token.
    $router = new Router();
    $llamado = false;
    $router->postConToken('tarea/programada', function (Request $r) use (&$llamado): void {
        $llamado = true;
    });
    $_SESSION = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];   // sin testigo CSRF a propósito
    $_GET = ['r' => 'tarea/programada'];
    $router->dispatch(new Request());
    assertTrue($llamado, 'una ruta declarada con postConToken sí llega al manejador sin CSRF');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];
    $_SESSION = [];
}
