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
use App\Models\Evento;
use App\Models\Historial;
use App\Models\Usuario;
use DateTimeImmutable;
use Throwable;

final class AdminController extends Controller
{
    public function __construct()
    {
        Auth::exigirAdmin();
    }

    public function usuarios(Request $req): void
    {
        $areasCatalogo = array_values(array_filter(Catalogo::areas(), static fn(array $a): bool => $a['valor_norm'] !== 'n/a'));
        $this->vista('admin/usuarios', [
            'titulo' => 'Usuarios', 'usuarios' => Usuario::listar(),
            'areasCatalogo' => $areasCatalogo, 'tab' => 'usuarios',
        ]);
    }

    /**
     * Informe de usuarios listo para imprimir o guardar como PDF.
     *
     * Se sirve SIN la plantilla normal (View::render con layout null): lo que
     * se ve en pantalla ya es la hoja. El PDF lo hace el navegador y no el
     * servidor, a propósito: sale con texto seleccionable y evita meter una
     * librería de PDF en el proyecto.
     */
    public function informe(Request $req): void
    {
        $anio = (int) date('Y');
        View::render('admin/informe_usuarios', [
            'usuarios'     => Usuario::listar(),
            'areasEventos' => Evento::conteos(['anio' => $anio])['areas'],
            'coloresArea'  => array_column(array_map(static fn(array $a): array => ['id' => (int) $a['id'], 'color' => $a['color'] ?: Campos::COLOR_NEUTRO], Catalogo::areas()), 'color', 'id'),
            'anio'         => $anio,
            'generado'     => new DateTimeImmutable(),
        ], null);
    }

    public function usuariosGuardar(Request $req): void
    {
        Csrf::exigir();
        $id = $req->int('id');
        $accion = (string) $req->post('accion', '');
        $yo = Auth::usuario()['id'];

        try {
            // Nadie puede degradarse ni darse de baja a sí mismo: así no se pierde
            // el acceso de administración por un clic descuidado.
            if ($id === $yo && in_array($accion, ['rol', 'baja'], true)) {
                throw new \RuntimeException('No puedes cambiar tu propio rol ni darte de baja.');
            }

            $areaId = $req->int('area_id');
            match ($accion) {
                'crear'       => $this->crearUsuario($req, $areaId),
                'editar'      => $this->editarUsuario($req, $id, $areaId),
                'contrasena'  => $this->restablecerContrasena($req, $id),
                'baja'        => $this->cambiarEstado($id, false),
                'alta'        => $this->cambiarEstado($id, true),
                default       => throw new \RuntimeException('Acción desconocida.'),
            };
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        Response::redirigir(url('admin/usuarios'));
    }

    private function crearUsuario(Request $req, int $areaId): void
    {
        $nombre = trim((string) $req->post('nombre', ''));
        $correo = trim((string) $req->post('correo', ''));
        $clave  = (string) $req->post('contrasena', '');
        $rol    = (string) $req->post('rol', 'usuario');

        if ($nombre === '') {
            throw new \InvalidArgumentException('Escribe el nombre.');
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo no es válido.');
        }
        if (Usuario::correoExiste($correo)) {
            throw new \InvalidArgumentException('Ya existe un usuario con ese correo.');
        }
        if (mb_strlen($clave) < 8) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }
        // Sin área solo podría consultar, así que para un usuario normal es obligatoria.
        if ($rol === 'usuario' && $areaId <= 0) {
            throw new \InvalidArgumentException('Elige el área: sin área la persona solo podría consultar.');
        }

        Usuario::crear($nombre, $correo, $clave, $rol, $areaId > 0 ? $areaId : null);
        flash('ok', 'Usuario creado.');
    }

    private function editarUsuario(Request $req, int $id, int $areaId): void
    {
        $nombre = trim((string) $req->post('nombre', ''));
        $rol    = (string) $req->post('rol', 'usuario');

        if ($id <= 0 || Usuario::porId($id) === null) {
            throw new \InvalidArgumentException('Ese usuario no existe.');
        }
        if ($nombre === '') {
            throw new \InvalidArgumentException('Escribe el nombre.');
        }
        if ($rol === 'usuario' && $areaId <= 0) {
            throw new \InvalidArgumentException('Elige el área: sin área la persona solo podría consultar.');
        }

        Usuario::actualizar($id, $nombre, $rol, $areaId > 0 ? $areaId : null);
        flash('ok', 'Usuario actualizado.');
    }

    private function restablecerContrasena(Request $req, int $id): void
    {
        $clave = (string) $req->post('contrasena', '');
        if ($id <= 0 || Usuario::porId($id) === null) {
            throw new \InvalidArgumentException('Ese usuario no existe.');
        }
        if (mb_strlen($clave) < 8) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }
        Usuario::cambiarContrasena($id, $clave);
        flash('ok', 'Contraseña restablecida.');
    }

    /** La baja es lógica: la fila permanece para que el historial siga teniendo autor. */
    private function cambiarEstado(int $id, bool $activo): void
    {
        $u = Usuario::porId($id);
        if ($u === null) {
            throw new \InvalidArgumentException('Ese usuario no existe.');
        }
        if (!$activo && $u['rol'] === 'admin' && Usuario::administradoresActivos() <= 1) {
            throw new \RuntimeException('Es el único administrador activo. Nombra otro antes de darlo de baja.');
        }
        Usuario::activar($id, $activo);
        flash('ok', $activo ? 'Usuario reactivado.' : 'Usuario dado de baja.');
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
