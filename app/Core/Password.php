<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cifrado de contraseñas.
 *
 * Regla única e innegociable: una contraseña nunca se guarda, se registra ni se
 * devuelve en texto plano. Solo entra aquí y sale convertida en hash.
 */
final class Password
{
    /**
     * PASSWORD_DEFAULT sigue el algoritmo que PHP considere mejor en cada versión
     * y ya incorpora una sal aleatoria por contraseña, así que no hay que añadirla.
     */
    public static function cifrar(string $plano): string
    {
        return password_hash($plano, PASSWORD_DEFAULT);
    }

    /**
     * password_verify compara en tiempo constante: tarda lo mismo acierte o falle,
     * de modo que no se puede deducir nada midiendo el tiempo de respuesta.
     */
    public static function verificar(string $plano, string $hash): bool
    {
        return password_verify($plano, $hash);
    }

    /**
     * Verdadero cuando el hash se generó con un algoritmo o un coste ya superados.
     * Permite recifrar la contraseña en el momento en que el usuario acierta,
     * que es la única ocasión en que se tiene el texto plano a mano.
     */
    public static function necesitaRecifrado(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
