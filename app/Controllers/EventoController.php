<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Campos;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Catalogo;
use App\Models\Evento;
use App\Models\Usuario;
use RuntimeException;

final class EventoController extends Controller
{
    public function nuevo(Request $req): void
    {
        Auth::exigirCreacion();
        $fecha = (string) $req->get('fecha', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }
        $u = Auth::usuario();
        $this->formulario('crear', [
            'fecha_inicio' => $fecha, 'fecha_fin' => $fecha, 'estado' => 'no_realizado', 'pais' => 'Andalia', 'ciudad' => 'Puerto Sereno',
            'area' => $u['rol'] === 'admin' ? '' : $u['area_nombre'],
        ]);
    }

    public function crear(Request $req): void
    {
        Csrf::exigir();
        Auth::exigirCreacion();
        $this->guardar($req, null);
    }

    public function editar(Request $req): void
    {
        $ev = $this->cargar($req->int('id'));
        // Eventos anteriores a evento_mercados (o de una base sin rellenar) muestran al menos su principal.
        $ev['mercados'] = Evento::mercadosDe((int) $ev['id']) ?: [(string) $ev['mercado']];
        $this->formulario('editar', $ev);
    }

    public function actualizar(Request $req): void
    {
        Csrf::exigir();
        $this->guardar($req, $this->cargar($req->int('id')));
    }

    /** POST eventos/eliminar&id= con `motivo` obligatorio (misma área o admin): papelera restaurable por un admin. */
    public function eliminar(Request $req): void
    {
        Csrf::exigir();
        $ev = $this->cargar($req->int('id'));
        try {
            Evento::eliminar((int) $ev['id'], Auth::usuario()['id'], (string) $req->post('motivo', ''));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            Response::redirigir(url('calendario', ['evento' => (int) $ev['id'], 'fecha' => $ev['fecha_inicio']]));
        }
        flash('ok', 'Evento eliminado. Si hace falta, un administrador puede restaurarlo.');
        Response::redirigir(url('calendario', ['fecha' => $ev['fecha_inicio']]));
    }

    /** POST eventos/cancelar&id= con `motivo` (misma área o admin). */
    public function cancelar(Request $req): void
    {
        Csrf::exigir();
        $ev = $this->cargar($req->int('id'));
        try {
            Evento::cancelar((int) $ev['id'], (string) $req->post('motivo', ''), Auth::usuario()['id']);
            flash('ok', 'Evento cancelado. Sigue visible, tachado, y se puede reanudar desde su ficha.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        Response::redirigir(url('calendario', ['evento' => (int) $ev['id'], 'fecha' => $ev['fecha_inicio']]));
    }

    /** POST eventos/reanudar&id= (misma área o admin): vuelve al estado que tenía antes de cancelarse. */
    public function reanudar(Request $req): void
    {
        Csrf::exigir();
        $ev = $this->cargar($req->int('id'));
        try {
            Evento::reanudar((int) $ev['id'], Auth::usuario()['id']);
            flash('ok', 'Evento reanudado.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        Response::redirigir(url('calendario', ['evento' => (int) $ev['id'], 'fecha' => $ev['fecha_inicio']]));
    }

    /** POST eventos/estado&id= con `estado` (misma área o admin): cambio rápido entre los tres estados manuales. */
    public function estado(Request $req): void
    {
        Csrf::exigir();
        $ev = $this->cargar($req->int('id'));
        $estado = (string) $req->post('estado', '');
        try {
            Evento::cambiarEstado((int) $ev['id'], $estado, Auth::usuario()['id']);
            flash('ok', t('Estado actualizado: :estado.', ['estado' => t(estado_etiqueta($estado))]));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        Response::redirigir(url('calendario', ['evento' => (int) $ev['id'], 'fecha' => $ev['fecha_inicio']]));
    }

    /** POST eventos/cubrimiento&id= con `valor` 1|0 (misma área o admin): desde la ficha, sin pasar por el formulario. */
    public function cubrimiento(Request $req): void
    {
        Csrf::exigir();
        $ev = $this->cargar($req->int('id'));
        $pide = (string) $req->post('valor', '') === '1';
        try {
            Evento::fijarCubrimiento((int) $ev['id'], $pide, Auth::usuario()['id']);
            flash('ok', $pide ? t('El evento pide cubrimiento.') : t('El evento ya no pide cubrimiento.'));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        Response::redirigir(url('calendario', ['evento' => (int) $ev['id'], 'fecha' => $ev['fecha_inicio']]));
    }

    public function lista(Request $req): void
    {
        $filtros = ApiController::filtros($req);
        $mios = !empty($req->get('mios'));
        $filtrosVista = $mios ? array_diff_key($filtros, ['area_id' => 1]) : $filtros;
        $lista = Evento::listar($filtros, max(1, $req->int('pagina', 1)), 50);
        $this->vista('eventos/lista', [
            'titulo' => 'Eventos', 'ancho' => 'completo', 'filtros' => $filtrosVista, 'lista' => $lista,
            'mios' => $mios,
            'tieneArea' => Auth::usuario()['area_id'] > 0,
            'orden' => ($filtros['orden'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            'areas' => Catalogo::areas(), 'tipos' => Catalogo::listar('tipo_accion', true), 'segmentos' => Catalogo::listar('segmento', true), 'anios' => Evento::anios(),
            'usuarios' => Usuario::activos(),
        ]);
    }

    public function exportar(Request $req): void
    {
        $filas = [];
        foreach (Evento::todos(ApiController::filtros($req)) as $e) {
            $filas[] = self::filaCsv($e);
        }
        Response::csv('cronograma_' . date('Ymd_His') . '.csv', Campos::CSV, $filas);
    }

    /** Fila CSV en el orden exacto del Excel (Campos::CSV). N° = id; Mes derivado de la fecha de inicio. */
    public static function filaCsv(array $e): array
    {
        $s = static fn(string $v): string => self::celdaCsvSegura($v);
        return [
            (int) $e['id'], Campos::MESES[(int) date('n', (int) strtotime($e['fecha_inicio']))], $e['fecha_inicio'], $e['fecha_fin'],
            $s($e['ciudad']), $s($e['pais']), $s(catalogo_mostrar($e, 'tipo_accion')), $s(catalogo_mostrar($e, 'segmento')), $s($e['nombre']), $s($e['mercado']), $s($e['organizador']), $s($e['area']),
            $s($e['objetivo']), $s($e['linea_estrategica']), $s($e['resultados']), $s($e['contactos_url']), $s($e['reuniones']), $s($e['alianzas']),
            estado_etiqueta($e['estado']), $s($e['observaciones']), $s($e['evidencia_url']),
        ];
    }

    /**
     * Blinda una celda de texto contra inyección de fórmulas al abrir el CSV en Excel/Sheets: si el
     * valor (recortado) empieza con =, +, - o @, se antepone una comilla simple para que quede como
     * texto literal y no se interprete como fórmula.
     */
    private static function celdaCsvSegura(string $v): string
    {
        return preg_match('/^[=+\-@]/', ltrim($v)) === 1 ? "'" . $v : $v;
    }

    /** Evento vivo + permiso de edición por área (404/403 si no). */
    private function cargar(int $id): array
    {
        $ev = $id > 0 ? Evento::porId($id) : null;
        if (!$ev) {
            Response::error(404, 'El evento no existe o fue eliminado.');
        }
        Auth::exigirEdicion($ev);
        return $ev;
    }

    /** Pipeline único de alta/edición: valida, resuelve "¿Quisiste decir?", guarda y redirige. $evento === null crea. */
    private function guardar(Request $req, ?array $evento): void
    {
        $modo = $evento === null ? 'crear' : 'editar';
        $u = Auth::usuario();
        $in = $req->posts();
        $in['area'] = self::areaPermitida($u, (string) ($in['area'] ?? ''), $evento);   // el área nunca sale del POST para no-admins
        $cancelado = $evento !== null && $evento['estado'] === 'cancelado';
        if ($cancelado) {
            $in['estado'] = 'cancelado';   // guardar no cambia un evento cancelado; se vuelve a activar con "Reanudar"
        }
        $r = Validator::evento($in, $cancelado);
        if ($r['ok']) {
            // Segunda barrera: los campos de lista cerrada deben corresponder a un valor vivo del catálogo.
            $cerrados = Catalogo::erroresCerrados($r['datos']);
            if ($cerrados) {
                $r['ok'] = false;
                $r['errores'] = $cerrados;
            }
            // Y que el responsable pertenezca al área con la que de verdad se va a guardar. Importa
            // cuando un admin mueve el evento de área y el responsable se queda fuera.
            $malDueno = Usuario::errorDueno((int) ($r['datos']['dueno_id'] ?? 0), Catalogo::idArea((string) ($r['datos']['area'] ?? '')));
            if ($malDueno) {
                $r['ok'] = false;
                $r['errores'] += $malDueno;
            }
        }
        $sug = $r['ok'] ? Catalogo::parecidosPendientes($r['datos'], (array) ($in['confirmar'] ?? [])) : [];
        if (!$r['ok'] || $sug) {
            $valores = $evento === null ? $in : $in + ['id' => $evento['id'], 'cancelacion_motivo' => $evento['cancelacion_motivo']];
            // Lo que ya había elegido, para que el formulario no se lo borre al repintar.
            $valores['mercados'] = Validator::mercadosDelEnvio($in);
            $valores['requiere_cubrimiento'] = !empty($in['requiere_cubrimiento']);
            $this->formulario($modo, $valores, $r['errores'], $sug);
            return;
        }
        if ($evento === null) {
            $id = Evento::crear($r['datos'], $u['id']);
            flash('ok', 'Evento creado.');
        } else {
            $id = (int) $evento['id'];
            Evento::actualizar($id, $r['datos'], $u['id']);
            flash('ok', 'Evento actualizado.');
        }
        Response::redirigir(url('calendario', ['evento' => $id, 'fecha' => $r['datos']['fecha_inicio']]));
    }

    /**
     * Área con la que se guarda el evento: el admin elige libremente (lo que envió el formulario);
     * un usuario siempre la suya, sin importar lo que venga en el POST.
     */
    public static function areaPermitida(array $u, string $areaPost, ?array $evento): string
    {
        if ($u['rol'] === 'admin') {
            return $areaPost;
        }
        return $u['area_nombre'] !== '' ? $u['area_nombre'] : (string) ($evento['area'] ?? '');
    }

    private function formulario(string $modo, array $valores, array $errores = [], array $sugerencias = []): void
    {
        $u = Auth::usuario();
        $this->vista('eventos/form', [
            'titulo'       => $modo === 'crear' ? 'Nuevo evento' : 'Editar evento',
            'modo'         => $modo,
            'valores'      => $valores,
            'errores'      => $errores,
            'sugerencias'  => $sugerencias,
            'accion'       => $modo === 'crear' ? url('eventos/crear') : url('eventos/actualizar', ['id' => (int) ($valores['id'] ?? 0)]),
            'esAdmin'      => $u['rol'] === 'admin',
            'areaUsuario'  => $u['area_nombre'],
            // Quién puede ser responsable depende del área del evento, que para un usuario normal
            // es siempre la suya (areaPermitida() no deja que salga del POST).
            'duenos'       => Usuario::duenosPosibles(Catalogo::idArea((string) ($valores['area'] ?? $u['area_nombre']))),
            'duenoActual'  => (int) ($valores['dueno_id'] ?? $u['id']),
            'cubrimiento'  => !empty($valores['requiere_cubrimiento']),
            'opciones'     => Catalogo::opcionesCerradas(),
            'cancelado'    => ($valores['estado'] ?? '') === 'cancelado',
            'motivo'       => (string) ($valores['cancelacion_motivo'] ?? ''),
        ]);
    }
}
