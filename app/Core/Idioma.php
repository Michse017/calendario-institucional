<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Idioma de la interfaz.
 *
 * La clave de traducción es el propio texto en español. Es una decisión
 * deliberada: si una cadena se queda sin traducir, la pantalla la muestra en
 * español en lugar de enseñar una clave cruda tipo "calendario.nuevo_evento" o
 * quedarse en blanco. Degrada de forma elegante, y de paso las vistas siguen
 * leyéndose como texto y no como un mapa de identificadores.
 *
 * Solo se traduce la interfaz y los valores de catálogo que vienen sembrados.
 * Lo que escribe la gente (el nombre de un evento, un motivo de cancelación)
 * se queda tal cual: traducir contenido ajeno sería inventarlo.
 */
final class Idioma
{
    public const DISPONIBLES = ['es', 'en'];
    private const POR_DEFECTO = 'es';
    private const COOKIE = 'cro_idioma';

    private static ?string $actual = null;

    /** @var array<string,string> */
    private static array $diccionario = [];

    /**
     * Resuelve el idioma y lo recuerda.
     *
     * Orden de preferencia: lo que se pide en la dirección, lo que ya se eligió
     * antes, lo que anuncia el navegador, y por último el idioma del proyecto.
     */
    public static function iniciar(): void
    {
        $pedido = strtolower((string) ($_GET['lang'] ?? ''));
        if (in_array($pedido, self::DISPONIBLES, true)) {
            self::fijar($pedido);
            return;
        }

        $guardado = (string) ($_SESSION['idioma'] ?? $_COOKIE[self::COOKIE] ?? '');
        if (in_array($guardado, self::DISPONIBLES, true)) {
            self::fijar($guardado);
            return;
        }

        self::fijar(self::delNavegador());
    }

    /** Deja elegido un idioma y lo recuerda en la sesión y en una cookie. */
    public static function fijar(string $codigo): void
    {
        if (!in_array($codigo, self::DISPONIBLES, true)) {
            $codigo = self::POR_DEFECTO;
        }
        self::$actual = $codigo;
        self::$diccionario = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['idioma'] = $codigo;
        }
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            // Un año: la preferencia de idioma no es un dato sensible y molesta
            // tener que volver a elegirla en cada visita.
            setcookie(self::COOKIE, $codigo, [
                'expires'  => time() + 31536000,
                'path'     => base_path() !== '' ? base_path() : '/',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
                'httponly' => false,   // el navegador puede leerla; no protege nada
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function actual(): string
    {
        return self::$actual ??= self::POR_DEFECTO;
    }

    /**
     * Traduce un texto.
     *
     * Los parámetros se sustituyen por nombre (`:campo`) para que el orden de
     * las palabras pueda cambiar entre idiomas sin romper nada.
     *
     * @param array<string,string|int> $params
     */
    public static function t(string $texto, array $params = []): string
    {
        $salida = self::diccionario()[$texto] ?? $texto;

        foreach ($params as $clave => $valor) {
            $salida = str_replace(':' . $clave, (string) $valor, $salida);
        }

        return $salida;
    }

    /** @return array<string,string> */
    private static function diccionario(): array
    {
        if (self::$diccionario !== []) {
            return self::$diccionario;
        }
        // El español es el idioma de origen: sus claves ya son su traducción.
        if (self::actual() === self::POR_DEFECTO) {
            return self::$diccionario = ['' => ''];
        }
        $archivo = BASE_PATH . '/lang/' . self::actual() . '.php';
        $cargado = is_file($archivo) ? require $archivo : [];

        return self::$diccionario = is_array($cargado) ? $cargado : [];
    }

    /** Lee la cabecera que anuncia el navegador y se queda con el primero que sepamos hablar. */
    private static function delNavegador(): string
    {
        $cabecera = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (explode(',', $cabecera) as $trozo) {
            $codigo = strtolower(substr(trim(explode(';', $trozo)[0]), 0, 2));
            if (in_array($codigo, self::DISPONIBLES, true)) {
                return $codigo;
            }
        }

        return self::POR_DEFECTO;
    }
}
