<?php
declare(strict_types=1);

use App\Models\Catalogo;

function test_parecidos_pendientes_solo_para_valores_nuevos_no_confirmados(): void
{
    conDb(function (PDO $pdo): void {
        $pdo->exec("INSERT INTO catalogo_valores (campo, valor, valor_norm, usos) VALUES ('pais','Xqzmejico','xqzmejico',3), ('ciudad','Xmedellin','xmedellin',2)");
        $datos = datosEvento(['pais' => 'Xqzmexico', 'ciudad' => 'Xmedellin', 'mercado' => 'N/A', 'organizador' => 'Xorganizador totalmente nuevo']);
        $r = Catalogo::parecidosPendientes($datos, []);
        assertEq(['pais' => ['Xqzmejico']], $r, 'solo pais: ciudad existe exacta, mercado es N/A, organizador no se parece a nada');
        assertEq([], Catalogo::parecidosPendientes($datos, ['pais' => '1']), 'confirmado → no vuelve a preguntar');
    });
}
