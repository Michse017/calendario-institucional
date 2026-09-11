<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cabeceras de seguridad de la respuesta.
 *
 * Son la capa que protege al navegador del visitante aunque el servidor esté
 * sano: limitan qué puede cargar la página y qué puede hacer con ella otro sitio.
 */
final class Seguridad
{
    /**
     * Orígenes desde los que la página puede cargar código y tipografías.
     * Todo lo demás queda restringido al propio origen.
     */
    private const CDN    = 'https://cdn.jsdelivr.net';
    private const FUENTES_CSS = 'https://fonts.googleapis.com';
    private const FUENTES_ARCHIVO = 'https://fonts.gstatic.com';

    /** Se calcula una vez por petición y vale tanto para la cabecera como para la vista. */
    private static ?string $nonce = null;

    /**
     * Número de un solo uso que autoriza a UN script en línea concreto.
     *
     * Es la alternativa correcta a abrir la política con 'unsafe-inline', que
     * autorizaría cualquier script en línea, incluido el que llegue a colarse
     * por una inyección. Con el nonce solo se ejecuta el que lleva la marca que
     * el servidor acaba de sortear, y que el atacante no puede adivinar.
     */
    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    public static function cabeceras(): void
    {
        if (headers_sent()) {
            return;
        }

        // Impide que el navegador adivine el tipo de un archivo y ejecute como
        // script algo que se sirvió como texto.
        header('X-Content-Type-Options: nosniff');

        // Nadie puede meter la aplicación dentro de un marco y engañar al usuario
        // para que pulse donde no cree estar pulsando.
        header('X-Frame-Options: DENY');

        // La dirección completa solo se comparte con el propio sitio.
        header('Referrer-Policy: same-origin');

        // Se renuncia a permisos del navegador que esta aplicación no necesita.
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

        header(
            "Content-Security-Policy: "
            . "default-src 'self'; "
            // Alpine.js evalúa las expresiones de sus atributos, por eso necesita
            // 'unsafe-eval'. Es una concesión consciente y acotada a los scripts.
            . "script-src 'self' " . self::CDN . " 'nonce-" . self::nonce() . "' 'unsafe-eval'; "
            . "style-src 'self' 'unsafe-inline' " . self::FUENTES_CSS . "; "
            . "font-src 'self' data: " . self::FUENTES_ARCHIVO . "; "
            . "img-src 'self' data:; "
            . "connect-src 'self'; "
            . "form-action 'self'; "
            . "base-uri 'self'; "
            . "frame-ancestors 'none'"
        );

        // Fuera de desarrollo se exige HTTPS durante un año.
        if (!Env::bool('APP_DEBUG')) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
