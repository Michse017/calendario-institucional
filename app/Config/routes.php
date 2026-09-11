<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\CalendarioController;
use App\Controllers\DashboardController;
use App\Controllers\EventoController;
use App\Core\Request;
use App\Core\Response;
use App\Core\SsoBridge;

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
$router->get('panel', static function (Request $r): void {
    Response::redirigir(SsoBridge::panelUrl());
});
$router->get('admin/accesos', [AdminController::class, 'accesos']);
$router->post('admin/accesos', [AdminController::class, 'accesosGuardar']);
$router->get('admin/catalogos', [AdminController::class, 'catalogos']);
$router->post('admin/catalogos', [AdminController::class, 'catalogosGuardar']);
$router->get('admin/historial', [AdminController::class, 'historial']);
$router->get('admin/eliminados', [AdminController::class, 'eliminados']);
$router->post('admin/restaurar', [AdminController::class, 'restaurar']);
