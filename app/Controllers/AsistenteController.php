<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\Asistente;
use App\Models\IntentoAcceso;
use RuntimeException;

/**
 * Asistente de ayuda.
 *
 * El historial vive en la sesión del servidor, nunca en el navegador ni en la
 * base. Eso resuelve de raíz que dos personas puedan cruzarse la conversación:
 * cada navegador tiene su sesión de PHP y no hay nada compartido entre ellas.
 * También significa que al cerrar sesión la conversación desaparece, que es lo
 * que cualquiera esperaría.
 */
final class AsistenteController extends Controller
{
    /** POST api/asistente  {pregunta} → {ok, respuesta} */
    public function preguntar(Request $req): void
    {
        Csrf::exigir();

        $j = $req->json();
        $pregunta = trim((string) ($j['pregunta'] ?? ''));
        $historial = self::historial();

        try {
            $respuesta = Asistente::responder($pregunta, $historial, Auth::usuario(), IntentoAcceso::ipCliente());
        } catch (RuntimeException $e) {
            // Los mensajes de Asistente están escritos para leerse en pantalla.
            Response::json(['ok' => false, 'error' => t($e->getMessage(), ['n' => 500])], 422);
        }

        $historial[] = ['rol' => 'persona',   'texto' => $pregunta];
        $historial[] = ['rol' => 'asistente', 'texto' => $respuesta];
        // Se recorta aquí y no al leer: así la sesión no crece sin tope aunque
        // alguien deje la pestaña abierta toda la tarde.
        $_SESSION['asistente_chat'] = array_slice($historial, -Asistente::TURNOS * 2);

        Response::json(['ok' => true, 'respuesta' => $respuesta]);
    }

    /** POST api/asistente/limpiar → vacía la conversación de ESTA sesión. */
    public function limpiar(Request $req): void
    {
        Csrf::exigir();
        unset($_SESSION['asistente_chat']);
        Response::json(['ok' => true]);
    }

    /** GET api/asistente → la conversación de esta sesión, para repintarla al recargar. */
    public function estado(Request $req): void
    {
        Response::json([
            'ok' => true,
            'disponible' => Asistente::configurado(),
            'historial' => self::historial(),
        ]);
    }

    /** @return array<int, array{rol:string, texto:string}> */
    private static function historial(): array
    {
        $h = $_SESSION['asistente_chat'] ?? [];
        return is_array($h) ? $h : [];
    }
}
