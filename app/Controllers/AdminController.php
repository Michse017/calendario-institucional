<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Campos;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\Acceso;
use App\Models\Catalogo;
use App\Models\Evento;
use App\Models\Historial;
use Throwable;

final class AdminController extends Controller
{
    public function __construct()
    {
        Auth::exigirAdmin();
    }

    public function accesos(Request $req): void
    {
        $areasCatalogo = array_values(array_filter(Catalogo::areas(), static fn(array $a): bool => $a['valor_norm'] !== 'n/a'));
        $this->vista('admin/accesos', [
            'titulo' => 'Accesos', 'accesos' => Acceso::listar(), 'candidatos' => Acceso::candidatosSso(),
            'areasCatalogo' => $areasCatalogo, 'tab' => 'accesos',
        ]);
    }

    public function accesosGuardar(Request $req): void
    {
        Csrf::exigir();
        $id = $req->int('id');
        $accion = (string) $req->post('accion', '');
        try {
            if ($id === Auth::usuario()['id'] && in_array($accion, ['revocar', 'rol', 'otorgar'], true)) {
                throw new \RuntimeException('No puedes cambiar tu propio acceso.');
            }
            if ($accion === 'otorgar' && $id <= 0) {
                throw new \InvalidArgumentException('Selecciona un usuario del SSO.');
            }
            $areaId = $req->int('area_id');
            match ($accion) {
                'otorgar'   => $this->otorgar($req, $id, $areaId),
                'rol'       => Acceso::cambiarRol($id, (string) $req->post('rol', 'usuario')),
                'area'      => Acceso::cambiarArea($id, $areaId > 0 ? $areaId : null),
                'revocar'   => Acceso::revocar($id),
                'reactivar' => Acceso::reactivar($id),
                default     => throw new \RuntimeException('Acción desconocida.'),
            };
            flash('ok', 'Acceso actualizado.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        Response::redirigir(url('admin/accesos'));
    }

    /** Otorgar exige área cuando el rol es usuario (sin área solo podría consultar). */
    private function otorgar(Request $req, int $id, int $areaId): void
    {
        $rol = (string) $req->post('rol', 'usuario');
        if ($rol === 'usuario' && $areaId <= 0) {
            throw new \InvalidArgumentException('Elige el área del usuario: sin área solo podría consultar.');
        }
        Acceso::otorgar($id, (string) $req->post('nombre', ''), $rol, $areaId > 0 ? $areaId : null);
    }

    public function catalogos(Request $req): void
    {
        $campo = (string) $req->get('campo', 'tipo_accion');
        if (!in_array($campo, Campos::CATALOGOS, true)) {
            $campo = 'tipo_accion';
        }
        $this->vista('admin/catalogos', ['titulo' => 'Catálogos', 'campo' => $campo, 'valores' => Catalogo::listar($campo), 'tab' => 'catalogos']);
    }

    public function catalogosGuardar(Request $req): void
    {
        Csrf::exigir();
        $campo = (string) $req->post('campo', 'tipo_accion');
        $id = $req->int('id');
        try {
            match ((string) $req->post('accion', '')) {
                'renombrar'  => Catalogo::renombrar($id, (string) $req->post('valor', '')),
                'unir'       => Catalogo::unir($id, (int) $req->post('destino', '0')),
                'activar'    => Catalogo::activar($id, true),
                'desactivar' => Catalogo::activar($id, false),
                'color'      => Catalogo::color($id, (string) $req->post('color', '')),
                'recalcular' => Catalogo::recalcularUsos(),
                default      => throw new \RuntimeException('Acción desconocida.'),
            };
            flash('ok', 'Catálogo actualizado.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        Response::redirigir(url('admin/catalogos', ['campo' => $campo]));
    }

    public function historial(Request $req): void
    {
        $this->vista('admin/historial', ['titulo' => 'Historial', 'movimientos' => Historial::listar(200), 'tab' => 'historial']);
    }

    /** Papelera: eventos eliminados con quién, cuándo y por qué; desde aquí se restauran. */
    public function eliminados(Request $req): void
    {
        $this->vista('admin/eliminados', ['titulo' => 'Eliminados', 'eliminados' => Evento::eliminados(200), 'tab' => 'eliminados']);
    }

    public function restaurar(Request $req): void
    {
        Csrf::exigir();
        try {
            Evento::restaurar($req->int('id'), Auth::usuario()['id']);
            flash('ok', 'Evento restaurado.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        Response::redirigir(url($req->post('volver') === 'eliminados' ? 'admin/eliminados' : 'admin/historial'));
    }
}
