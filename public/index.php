<?php
declare(strict_types=1);

/**
 * Punto de entrada único.
 *
 * Todas las peticiones pasan por aquí: se arranca la sesión, se aplican las
 * cabeceras de seguridad, se reconstruye el usuario y se exige haber entrado
 * salvo en las rutas públicas.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Idioma;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Seguridad;
use App\Core\Sesion;

/** Únicas rutas accesibles sin haber entrado. */
const RUTAS_PUBLICAS = ['acceso', 'acceso/entrar', 'demo/reiniciar'];

Sesion::iniciar();
// Antes que nada: las cabeceras y toda la interfaz dependen del idioma elegido.
Idioma::iniciar();
Seguridad::cabeceras();
Auth::iniciar();

$req = new Request();

if (!Auth::hayUsuario() && !in_array($req->ruta(), RUTAS_PUBLICAS, true)) {
    // Una petición de datos recibe un 401 con JSON; una de página, la pantalla de acceso.
    if (Response::quiereJson()) {
        Response::json(['ok' => false, 'error' => 'Tu sesión terminó. Vuelve a entrar.'], 401);
    }
    Response::redirigir(url('acceso'));
}

$router = new Router();
require APP_PATH . '/Config/routes.php';
$router->dispatch($req);
