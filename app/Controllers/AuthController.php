<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sesion;
use App\Models\IntentoAcceso;
use App\Models\Usuario;

/**
 * Entrada y salida de la aplicación.
 *
 * Los mensajes de error son deliberadamente vagos: nunca se distingue entre
 * "ese correo no existe" y "la contraseña está mal", porque esa diferencia le
 * diría a quien prueba a ciegas qué cuentas son reales.
 */
final class AuthController extends Controller
{
    /**
     * Cuentas que la pantalla de acceso ofrece cuando la aplicación corre en
     * modo demostración. Fuera de ese modo no se muestra ninguna.
     * Los datos coinciden con los que siembra bin/sembrar_demo.php.
     */
    private const CUENTAS_DEMO = [
        ['correo' => 'ana.torres@meridiano.demo',   'rol' => 'Administradora', 'pista' => 'Ve y edita todo'],
        ['correo' => 'carlos.mena@meridiano.demo',  'rol' => 'Área de Programación', 'pista' => 'Solo edita lo suyo'],
        ['correo' => 'lucia.ferrer@meridiano.demo', 'rol' => 'Área de Comunicaciones', 'pista' => 'Solo edita lo suyo'],
    ];

    private const CONTRASENA_DEMO = 'demo1234';

    public function formulario(Request $req): void
    {
        if (Auth::hayUsuario()) {
            Response::redirigir(url('calendario'));
        }

        $this->vista('auth/login', [
            'titulo'      => 'Acceso',
            'sinNav'      => true,
            'esDemo'      => Env::bool('APP_DEMO'),
            'cuentas'     => self::CUENTAS_DEMO,
            'clave'       => self::CONTRASENA_DEMO,
            'correoPrevio' => (string) ($_SESSION['acceso_correo'] ?? ''),
        ]);
    }

    public function entrar(Request $req): void
    {
        Csrf::exigir();

        $correo = trim((string) $req->post('correo', ''));
        $clave  = (string) $req->post('contrasena', '');
        $ip     = IntentoAcceso::ipCliente();

        // Se recuerda el correo para no obligar a reescribirlo tras un fallo.
        $_SESSION['acceso_correo'] = $correo;

        if ($correo === '' || $clave === '') {
            flash('error', 'Escribe tu correo y tu contraseña.');
            Response::redirigir(url('acceso'));
        }

        if (IntentoAcceso::bloqueado($correo, $ip)) {
            flash('error', t('Demasiados intentos fallidos. Espera :minutos minutos y vuelve a probar.', ['minutos' => IntentoAcceso::VENTANA_MINUTOS]));
            Response::redirigir(url('acceso'));
        }

        $usuario = Usuario::autenticar($correo, $clave);
        IntentoAcceso::registrar($correo, $ip, $usuario !== null);

        if ($usuario === null) {
            flash('error', 'Correo o contraseña incorrectos.');
            Response::redirigir(url('acceso'));
        }

        // Identificador nuevo ANTES de marcar la sesión como autenticada: así, un
        // identificador que alguien hubiera fijado de antemano queda inservible.
        Sesion::regenerar();

        $_SESSION['usuario_id'] = (int) $usuario['id'];
        unset($_SESSION['acceso_correo']);

        Response::redirigir(url('calendario'));
    }

    public function salir(Request $req): void
    {
        Csrf::exigir();
        Sesion::cerrar();
        Sesion::iniciar();
        flash('ok', 'Cerraste la sesión.');
        Response::redirigir(url('acceso'));
    }
}
