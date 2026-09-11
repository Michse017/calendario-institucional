<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Campos;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Catalogo;
use App\Models\Dashboard;
use App\Models\Evento;
use App\Models\Historial;
use RuntimeException;

final class ApiController extends Controller
{
    /** GET ?r=api/sugerencias&campo=pais&q=col → {ok, datos:[{id,valor,usos}]} */
    public function sugerencias(Request $req): void
    {
        $campo = (string) $req->get('campo', '');
        if (!in_array($campo, Campos::CATALOGOS, true)) {
            Response::json(['ok' => false, 'error' => 'Campo inválido.'], 400);
        }
        Response::json(['ok' => true, 'datos' => Catalogo::sugerencias($campo, (string) $req->get('q', ''))]);
    }

    /** Feed de FullCalendar: GET ?r=api/eventos&start=Y-m-d…&end=Y-m-d…&[filtros] → array de eventos */
    public function eventos(Request $req): void
    {
        $ini = substr((string) $req->get('start', ''), 0, 10);
        $fin = substr((string) $req->get('end', ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
            Response::json([], 400);
        }
        if ($fin < $ini) {
            Response::json(['ok' => false, 'error' => 'Rango de fechas inválido.'], 422);
        }
        if ((strtotime($fin) - strtotime($ini)) / 86400 > 400) {
            Response::json(['ok' => false, 'error' => 'Rango de fechas demasiado amplio.'], 422);
        }
        $u = Auth::usuario();
        $salida = [];
        foreach (Evento::enRango($ini, $fin, self::filtros($req)) as $e) {
            $salida[] = self::aCalendario($e, $u);
        }
        Response::json($salida);
    }

    /** Filtros de calendario/lista; "mios" (Solo mi área) se traduce al área del usuario actual (-1 = sin área ⇒ nada). */
    public static function filtros(Request $req): array
    {
        $f = $req->filtros();
        if (!empty($f['mios'])) {
            $f['area_id'] = Auth::usuario()['area_id'] ?: -1;
        }
        unset($f['mios']);
        return $f;
    }

    /** Fila de Evento → objeto de FullCalendar (fin exclusivo, color del área o gris si está cancelado). */
    public static function aCalendario(array $e, array $u): array
    {
        $cancelado = $e['estado'] === 'cancelado';
        $colorArea = $e['area_color'] ?: Campos::COLOR_NEUTRO;
        return [
            'id'              => (int) $e['id'],
            'title'           => $e['nombre'],
            'start'           => $e['fecha_inicio'],
            'end'             => date('Y-m-d', strtotime($e['fecha_fin'] . ' +1 day')),
            'allDay'          => true,
            'backgroundColor' => $cancelado ? Campos::ESTADO_COLOR['cancelado'] : $colorArea,
            'borderColor'     => 'transparent',
            'classNames'      => $cancelado ? ['cro-ev-cancelado'] : [],
            'extendedProps'   => [
                'estado' => $e['estado'], 'estadoColor' => estado_color($e['estado']), 'area' => $e['area'], 'areaColor' => $colorArea,
                'cancelado' => $cancelado, 'tipo' => $e['tipo_accion'], 'ciudad' => $e['ciudad'], 'pais' => $e['pais'],
                'puedeEditar' => Auth::puedeEditar($u, $e),
            ],
        ];
    }

    /** GET ?r=api/evento&id=N → HTML del panel lateral */
    public function evento(Request $req): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $ev = Evento::porId($req->int('id'));
        if (!$ev) {
            http_response_code(404);
            echo '<p class="p-6 text-sm text-gris">El evento no existe o fue eliminado.</p>';
            exit;
        }
        echo View::parcial('calendario/_detalle', [
            'ev' => $ev,
            'puedeEditar' => Auth::puedeEditar(Auth::usuario(), $ev),
            'historial' => Historial::listar(5, (int) $ev['id']),
        ]);
        exit;
    }

    /** POST JSON {id, fecha_inicio, fecha_fin} (arrastrar/estirar). */
    public function mover(Request $req): void
    {
        Csrf::exigir();
        $j = $req->json();
        $ev = Evento::porId((int) ($j['id'] ?? 0));
        if (!$ev) {
            Response::json(['ok' => false, 'error' => 'El evento no existe.'], 404);
        }
        if (!Auth::puedeEditar(Auth::usuario(), $ev)) {
            Response::json(['ok' => false, 'error' => 'Solo el área responsable del evento (o un administrador) puede moverlo.'], 403);
        }
        try {
            Evento::moverFechas((int) $ev['id'], (string) ($j['fecha_inicio'] ?? ''), (string) ($j['fecha_fin'] ?? ''), Auth::usuario()['id']);
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true]);
    }

    /** GET ?r=api/buscar&q= → {ok, datos:[{id,nombre,fecha_inicio,fecha_fin,estado,area,area_color,ciudad}]} */
    public function buscar(Request $req): void
    {
        Response::json(['ok' => true, 'datos' => Evento::buscar((string) $req->get('q', ''))]);
    }

    /** GET ?r=api/mapa&anio=2026&modo=activos|inicio&cancelados=1&[filtros] → mapa de calor por día */
    public function mapa(Request $req): void
    {
        $anio = $req->int('anio', (int) date('Y'));
        if ($anio < 2000 || $anio > 2100) {
            Response::json(['ok' => false, 'error' => 'Año fuera de rango.'], 422);
        }
        $modo = $req->get('modo') === 'inicio' ? 'inicio' : 'activos';
        Response::json(['ok' => true] + Evento::mapaCalor($anio, self::filtros($req), $modo, (bool) $req->get('cancelados')));
    }

    /** GET ?r=api/dashboard&anio=2026 */
    public function dashboard(Request $req): void
    {
        Response::json(['ok' => true] + Dashboard::indicadores($req->int('anio', (int) date('Y'))));
    }

    /** GET ?r=api/proximos&[filtros] → {ok, datos} lista "Próximos 30 días" respetando los filtros activos. */
    public function proximos(Request $req): void
    {
        $f = self::filtrosProximos(self::filtros($req));
        Response::json(['ok' => true, 'datos' => self::proximosJson(Evento::proximos(30, 8, $f))]);
    }

    /** "Próximos 30 días" es una ventana de fecha fija: año/desde/hasta no aplican (solo área/tipo/segmento/estado). */
    public static function filtrosProximos(array $filtros): array
    {
        return array_diff_key($filtros, ['anio' => 1, 'desde' => 1, 'hasta' => 1]);
    }

    /** Filas de Evento::proximos()/enRango() → forma JSON de la lista "Próximos 30 días" (compartida API/vista). */
    public static function proximosJson(array $filas): array
    {
        return array_map(static fn(array $p): array => [
            'id'              => (int) $p['id'],
            'nombre'          => $p['nombre'],
            'rango'           => rango_fechas($p['fecha_inicio'], $p['fecha_fin']),
            'area'            => $p['area'],
            'ciudad'          => (string) ($p['ciudad'] ?? ''),
            'area_color'      => $p['area_color'] ?: Campos::COLOR_NEUTRO,
            'estado'          => $p['estado'],
            'estado_etiqueta' => estado_etiqueta($p['estado']),
        ], $filas);
    }
}
