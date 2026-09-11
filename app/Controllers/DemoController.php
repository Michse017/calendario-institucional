<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Demo\Sembrador;
use Throwable;

/**
 * Reinicio de los datos de demostración.
 *
 * Vive aparte de AdminController a propósito: aquel exige sesión de administrador
 * en su constructor, y la tarea programada nocturna llega sin sesión ninguna.
 *
 * Dos caminos de entrada, cada uno con su propia prueba de identidad:
 *
 *   1. Una persona administradora desde el panel, con testigo CSRF.
 *   2. Una tarea programada, con el token de `DEMO_RESET_TOKEN` en una cabecera.
 *
 * Fuera del modo demostración la ruta no existe: devuelve 404 y no toca nada.
 */
final class DemoController extends Controller
{
    private const CABECERA_TOKEN = 'HTTP_X_DEMO_TOKEN';

    public function reiniciar(Request $req): void
    {
        if (!Env::bool('APP_DEMO')) {
            Response::error(404, 'Esta instalación no está en modo demostración.');
        }

        $porToken = $this->tokenValido();

        if (!$porToken) {
            // Camino humano: hay que ser administrador y traer el testigo CSRF.
            if (!Auth::esAdmin()) {
                Response::error(403, 'Solo un administrador puede reiniciar la demostración.');
            }
            Csrf::exigir();
        }

        // Se llama a la clase directamente en lugar de lanzar un proceso: exec()
        // está desactivado en la mayoría de alojamientos compartidos, y depender
        // de él dejaría la demo sin poder reiniciarse justo donde suele vivir.
        try {
            $r = Sembrador::sembrar(true, false);
        } catch (Throwable $e) {
            error_log('calendario: falló el reinicio de la demo: ' . $e->getMessage());
            if ($porToken) {
                Response::json(['ok' => false, 'error' => 'El reinicio falló.'], 500);
            }
            flash('error', 'El reinicio falló. Revisa el registro del servidor.');
            Response::redirigir(url('admin/usuarios'));
        }

        if ($porToken) {
            Response::json([
                'ok' => true,
                'mensaje' => 'Demostración reiniciada.',
                'eventos' => $r['eventos'],
            ]);
        }

        flash('ok', 'La demostración volvió a su estado inicial.');
        Response::redirigir(url('calendario'));
    }

    /**
     * Comprueba el token de la tarea programada.
     *
     * hash_equals compara en tiempo constante: sin eso, alguien podría averiguar
     * el token carácter a carácter midiendo lo que tarda la respuesta.
     * Si la variable de entorno está vacía, no hay camino por token en absoluto.
     */
    private function tokenValido(): bool
    {
        $esperado = (string) Env::get('DEMO_RESET_TOKEN', '');
        if ($esperado === '') {
            return false;
        }
        $recibido = (string) ($_SERVER[self::CABECERA_TOKEN] ?? '');
        return $recibido !== '' && hash_equals($esperado, $recibido);
    }
}
