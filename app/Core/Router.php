<?php
declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    /** @var array<string, array|Closure> */
    private array $rutas = [];

    public function get(string $ruta, array|Closure $handler): void
    {
        $this->rutas['GET ' . $ruta] = $handler;
    }

    public function post(string $ruta, array|Closure $handler): void
    {
        $this->rutas['POST ' . $ruta] = $handler;
    }

    public function dispatch(Request $req): void
    {
        // HEAD se sirve con las rutas GET (mismo handler; el servidor descarta el cuerpo de la respuesta).
        $metodoRuta = $req->metodo() === 'HEAD' ? 'GET' : $req->metodo();
        $h = $this->rutas[$metodoRuta . ' ' . $req->ruta()] ?? null;
        if ($h === null) {
            Response::error(404, 'Esta página no existe.');
        }
        if ($req->metodo() === 'POST') {
            Csrf::exigir();   // red de seguridad: ningún POST llega a un manejador sin token válido
        }
        if (is_array($h)) {
            [$clase, $metodo] = $h;
            $h = [new $clase(), $metodo];
        }
        $h($req);
    }
}
