<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Catalogo;
use App\Models\Evento;
use App\Models\Usuario;

final class CalendarioController extends Controller
{
    public function index(Request $req): void
    {
        $u = Auth::usuario();
        $filtros = ApiController::filtros($req);
        $mios = !empty($req->get('mios'));
        // Cuando "mios" está activo, la vista no debe mostrar el area_id inyectado como si se hubiera
        // elegido a mano (el chip de área quedaría "seleccionado" sin que el usuario lo tocara); las
        // consultas de abajo siguen usando $filtros completo, con el area_id sí aplicado.
        $filtrosVista = $mios ? array_diff_key($filtros, ['area_id' => 1]) : $filtros;
        $anio = (int) ($filtros['anio'] ?? date('Y'));
        $fechaPedida = (string) $req->get('fecha', '');
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPedida) ? $fechaPedida : date('Y-m-d');
        // Al entrar, lo primero que se ve es el mapa de calor del año. Solo si el enlace apunta a un
        // día o a un evento concreto (la matriz del Dashboard, un aviso, un enlace compartido) se abre
        // el mes: quien llega por ahí viene a ver ese día, no el año entero.
        $vistaInicial = ($fecha === $fechaPedida || $req->int('evento')) ? 'dayGridMonth' : 'anio';
        // Enlaces directos a la línea de tiempo (p. ej. desde el Dashboard).
        if ($req->get('vista') === 'linea') {
            $vistaInicial = 'linea';
        }
        // Los conteos laterales ignoran área/estado para que las cifras sigan visibles al filtrar
        $conteos = Evento::conteos(['anio' => $anio] + array_diff_key($filtros, ['area_id' => 1, 'estado' => 1, 'anio' => 1]));
        $this->vista('calendario/index', [
            'titulo'      => 'Calendario',
            'ancho'       => 'completo',
            'filtros'     => $filtrosVista,
            'mios'        => $mios,
            'puedeCrear'  => Auth::puedeCrear($u),
            'tieneArea'   => $u['area_id'] > 0,
            'anio'        => $anio,
            'anios'       => Evento::anios(),
            'fecha'       => $fecha,
            'vistaInicial' => $vistaInicial,
            'usuarios'    => Usuario::activos(),
            'agendas'     => Evento::eventosPorPersona($anio),
            'areas'       => Catalogo::areas(),
            'tipos'       => Catalogo::listar('tipo_accion', true),
            'segmentos'   => Catalogo::listar('segmento', true),
            'conteos'     => $conteos,
            'proximos'    => ApiController::proximosJson(Evento::proximos(30, 8, ApiController::filtrosProximos($filtros))),
            'eventoAbrir' => $req->int('evento'),
        ]);
    }
}
