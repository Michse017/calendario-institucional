<?php
declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    /** @var array<string, array|Closure> */
    private array $rutas = [];

    /** Rutas que se autentican con token de cabecera y por eso no llevan CSRF. */
    private array $sinCsrf = [];

    public function get(string $ruta, array|Closure $handler): void
    {
        $this->rutas['GET ' . $ruta] = $handler;
    }

    public function post(string $ruta, array|Closure $handler): void
    {
        $this->rutas['POST ' . $ruta] = $handler;
    }

    /**
     * POST que NO pasa por la comprobación de CSRF.
     *
     * Reservado a rutas que se autentican con un token en una cabecera propia en
     * lugar de con la sesión. El CSRF protege de que un sitio ajeno haga que TU
     * navegador envíe una petición usando tu sesión; si la identidad no viene de
     * la sesión sino de una cabecera que el navegador no puede añadir desde otro
     * origen, no hay nada que proteger.
     *
     * El manejador queda obligado a comprobar ese token él mismo.
     */
    public function postConToken(string $ruta, array|Closure $handler): void
    {
        $this->rutas['POST ' . $ruta] = $handler;
        $this->sinCsrf['POST ' . $ruta] = true;
    }

    public function dispatch(Request $req): void
    {
        // HEAD se sirve con las rutas GET (mismo handler; el servidor descarta el cuerpo de la respuesta).
        $metodoRuta = $req->metodo() === 'HEAD' ? 'GET' : $req->metodo();
        $h = $this->rutas[$metodoRuta . ' ' . $req->ruta()] ?? null;
        if ($h === null) {
            Response::error(404, 'Esta página no existe.');
        }
        $clave = $metodoRuta . ' ' . $req->ruta();
        if ($req->metodo() === 'POST' && !isset($this->sinCsrf[$clave])) {
            Csrf::exigir();   // red de seguridad: ningún POST llega a un manejador sin token válido
        }
        if (is_array($h)) {
            [$clase, $metodo] = $h;
            $h = [new $clase(), $metodo];
        }
        $h($req);
    }
}
