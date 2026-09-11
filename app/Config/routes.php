<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\CalendarioController;
use App\Controllers\DemoController;
use App\Controllers\DashboardController;
use App\Controllers\EventoController;

// Únicas rutas accesibles sin haber entrado (ver RUTAS_PUBLICAS en public/index.php).
$router->get('acceso', [AuthController::class, 'formulario']);
$router->post('acceso/entrar', [AuthController::class, 'entrar']);
$router->post('salir', [AuthController::class, 'salir']);
// Reinicio de la demo: publica de ruta, pero DemoController exige token de tarea
// programada o sesion de administrador con CSRF. Fuera del modo demo devuelve 404.
$router->postConToken('demo/reiniciar', [DemoController::class, 'reiniciar']);

$router->get('calendario', [CalendarioController::class, 'index']);
$router->get('dashboard', [DashboardController::class, 'index']);
$router->get('api/dashboard', [ApiController::class, 'dashboard']);
$router->get('api/sugerencias', [ApiController::class, 'sugerencias']);
$router->get('api/eventos', [ApiController::class, 'eventos']);
$router->get('api/proximos', [ApiController::class, 'proximos']);
$router->get('api/mapa', [ApiController::class, 'mapa']);
$router->get('api/evento', [ApiController::class, 'evento']);
$router->post('api/eventos/mover', [ApiController::class, 'mover']);
$router->get('api/buscar', [ApiController::class, 'buscar']);
$router->get('eventos', [EventoController::class, 'lista']);
$router->get('eventos/exportar', [EventoController::class, 'exportar']);
$router->get('eventos/nuevo', [EventoController::class, 'nuevo']);
$router->post('eventos/crear', [EventoController::class, 'crear']);
$router->get('eventos/editar', [EventoController::class, 'editar']);
$router->post('eventos/actualizar', [EventoController::class, 'actualizar']);
$router->post('eventos/eliminar', [EventoController::class, 'eliminar']);
$router->post('eventos/cancelar', [EventoController::class, 'cancelar']);
$router->post('eventos/reanudar', [EventoController::class, 'reanudar']);
$router->post('eventos/estado', [EventoController::class, 'estado']);
$router->get('admin/usuarios', [AdminController::class, 'usuarios']);
$router->post('admin/usuarios', [AdminController::class, 'usuariosGuardar']);
$router->get('admin/catalogos', [AdminController::class, 'catalogos']);
$router->post('admin/catalogos', [AdminController::class, 'catalogosGuardar']);
$router->get('admin/historial', [AdminController::class, 'historial']);
$router->get('admin/eliminados', [AdminController::class, 'eliminados']);
$router->post('admin/restaurar', [AdminController::class, 'restaurar']);
