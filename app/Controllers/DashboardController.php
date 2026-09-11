<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Dashboard;
use App\Models\Evento;

final class DashboardController extends Controller
{
    public function index(Request $req): void
    {
        $anio = $req->int('anio', (int) date('Y'));
        $this->vista('dashboard/index', [
            // La clave NO puede llamarse "datos": View::parcial() usa extract() con EXTR_SKIP y ya tiene una variable $datos
            'titulo' => 'Dashboard', 'anio' => $anio, 'anios' => Evento::anios(), 'ind' => Dashboard::indicadores($anio),
        ]);
    }
}
