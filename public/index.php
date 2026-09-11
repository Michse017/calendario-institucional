<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Request;
use App\Core\Router;
use App\Core\SsoBridge;

SsoBridge::boot();   // redirige al panel si no hay sesión/acceso
session_start();
Auth::iniciar();

$router = new Router();
require APP_PATH . '/Config/routes.php';
$router->dispatch(new Request());
