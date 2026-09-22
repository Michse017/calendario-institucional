<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Dashboard;
use App\Models\Evento;
use App\Models\Usuario;

final class DashboardController extends Controller
{
    public function index(Request $req): void
    {
        $anio = $req->int('anio', (int) date('Y'));
        $this->vista('dashboard/index', [
            // La clave NO puede llamarse "datos": View::parcial() usa extract() con EXTR_SKIP y ya tiene una variable $datos
            'titulo' => 'Dashboard', 'anio' => $anio, 'anios' => Evento::anios(), 'ind' => Dashboard::indicadores($anio),
            'cub' => Dashboard::cubrimiento($anio),
        ]);
    }

    /**
     * Quién está comprometido y quién disponible en un rango de fechas. Ojo con el nombre: esto
     * solo sabe de eventos del calendario, no de vacaciones ni de bajas; por eso se habla de
     * "comprometido en eventos" y no de disponibilidad del personal.
     */
    public function disponibilidad(Request $req): void
    {
        $fecha = static fn(string $v, string $d): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $d;
        // Sin filtros se ve TODO el año en curso: entrar y ver lo que hay.
        $inicio = $fecha((string) $req->get('inicio', ''), date('Y-01-01'));
        $fin = $fecha((string) $req->get('fin', ''), date('Y-12-31'));
        if ($fin < $inicio) {
            $fin = $inicio;
        }
        $persona = $req->int('persona');
        $this->vista('dashboard/disponibilidad', [
            'titulo'   => 'Disponibilidad',
            'inicio'   => $inicio,
            'fin'      => $fin,
            'gente'    => Dashboard::disponibilidad($inicio, $fin),
            'persona'  => $persona,
            'agenda'   => $persona > 0 ? Dashboard::agendaDe($persona, $inicio, $fin) : [],
            'usuarios' => Usuario::activos(),
        ]);
    }
}
