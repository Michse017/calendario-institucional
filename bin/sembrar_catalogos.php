<?php
declare(strict_types=1);

// Siembra los catálogos de la hoja "Listas" del Excel. Idempotente: se puede correr varias veces.
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Core\Normalizador;

$semilla = [
    'tipo_accion' => ['Press Trips', 'Fam Trips', 'Participación en Ferias', 'Workshops/Misión comercial', 'Campaña digital Pagada', 'Acción de promoción', 'Ruedas de negocio', 'Inteligencia de Mercados'],
    'segmento' => ['Deportivo', 'Romance', 'Religioso', 'Marino Costero', 'Cultural', 'Gastronómico', 'MICE'],
    'area' => [
        ['Gestión de Destino', '#3A5BD9'], ['Promoción y Mercadeo', '#0E8F8B'], ['Planeación', '#C2780A'],
        ['Presidencia', '#7A4BD6'], ['Muelle', '#C43D6B'], ['Administrativa', '#3E8E3A'],
    ],
    'linea_estrategica' => [
        'F1. Diversificar las fuentes de Ingreso con servicios que generan valor y que fortalezcan las finanzas de la corporación.',
        'F2. Consolidar la eficiencia y economía en costos y gastos apoyados en la tecnología e innovación.',
        'F3. Fortalecer sostenibilidad financiera de la Corporación con modelos de financiación y proyectos estratégicos de mediano y largo plazo.',
        'C1. Establecer propuestas de valor competitivas que mejoren la experiencia y la proyección del muelle, que fortalezcan la atracción y fidelización de clientes.',
        'C2. Impulsar la promoción internacional y nacional de Cartagena de Indias con canales, contenidos y eventos novedosos.',
        'C3. Incrementar la satisfacción y las experiencias innovadoras y diferenciales al visitante, aliados estratégicos y locales, frente a otros destinos internacionales.',
        'P1. Establecer esquemas de comunicación, información y de gestión de conocimiento eficaces.',
        'P2. Consolidar alianzas estratégicas con actores claves para potenciar la promoción del destino Cartagena de Indias, el Muelle de la Bodeguita y la experiencia en la ciudad.',
        'P3. Impulsar un Gobierno Corporativo estratégico que fomenta la alineación, la confianza y la trasparencia.',
        'P4. Modernizar la infraestructura, la estructura organizacional y los procesos para garantizar servicios seguros, sostenibles y de alta calidad.',
        'A1. Impulsar la transformación digital y tecnológica como eje de modernización, innovación y competitividad.',
        'A2. Fortalecer el capital humano mediante la formación y el desarrollo de capacidades alineadas con la estrategia de crecimiento institucional y retención del mismo.',
        'A3. Consolidar una cultura organizacional de alta motivación que promueva la integración, la sostenibilidad y el logro de los objetivos misionales.',
    ],
    'pais' => ['Colombia'],
    'ciudad' => ['Cartagena'],
    'mercado' => ['Local', 'Nacional', 'Internacional'],
    'organizador' => ['Corpoturismo'],
];

$pdo = Database::pdo();
// Si el valor ya existe, no se pisa el color que un administrador haya elegido desde el panel:
// se conserva el color actual y solo se usa el de la semilla si el registro aún no tiene ninguno.
$st = $pdo->prepare(
    'INSERT INTO catalogo_valores (campo, valor, valor_norm, usos, activo, color) VALUES (?, ?, ?, 0, 1, ?)
     ON DUPLICATE KEY UPDATE color = COALESCE(color, VALUES(color))'
);
$n = 0;
foreach ($semilla as $campo => $valores) {
    $valores[] = 'N/A';
    foreach ($valores as $v) {
        [$valor, $color] = is_array($v) ? $v : [$v, null];
        if ($campo === 'area' && $valor === 'N/A') {
            $color = '#B3B7BF';
        }
        $st->execute([$campo, Normalizador::limpiar($valor), Normalizador::normalizar($valor), $color]);
        $n++;
    }
}
echo "Catálogos sembrados: $n valores procesados.\n";
