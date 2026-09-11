<?php
declare(strict_types=1);

/**
 * Siembra los datos de demostración del Centro Cultural Meridiano.
 *
 * Todo lo que genera es ficticio. La generación es determinista: con la misma
 * semilla salen siempre los mismos eventos, de modo que la demo es reproducible
 * y las capturas del README no cambian entre reinicios.
 *
 * Uso:
 *   php bin/sembrar_demo.php                 solo si la base está vacía
 *   php bin/sembrar_demo.php --forzar        vacía el contenido y vuelve a sembrar
 *   php bin/sembrar_demo.php --con-usuarios  además recrea las cuentas de ejemplo
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Core\Normalizador;
use App\Models\Evento;
use App\Models\Usuario;

const SEMILLA_ALEATORIA = 20260911;

$forzar      = in_array('--forzar', $argv, true);
$conUsuarios = in_array('--con-usuarios', $argv, true);

$pdo = Database::pdo();

// ---------------------------------------------------------------------------
// Catálogos
// ---------------------------------------------------------------------------

/** Áreas del centro, cada una con el color con que se pinta en el calendario. */
const AREAS = [
    ['Programación',    '#3A5BD9'],
    ['Producción',      '#0E8F8B'],
    ['Comunicaciones',  '#C2780A'],
    ['Educación',       '#7A4BD6'],
    ['Administración',  '#3E8E3A'],
];

/**
 * Bolsa ponderada de áreas. Repetir un nombre aumenta su probabilidad, de modo
 * que la agenda se parezca a la de un centro real: casi la mitad de lo que pasa
 * es programación, y administración apenas asoma.
 */
const PESOS_AREA = [
    'Programación', 'Programación', 'Programación', 'Programación', 'Programación', 'Programación', 'Programación',
    'Educación', 'Educación', 'Educación', 'Educación',
    'Comunicaciones', 'Comunicaciones', 'Comunicaciones',
    'Producción', 'Producción', 'Producción',
    'Administración',
];

const CATALOGOS = [
    'tipo_accion' => [
        'Concierto', 'Exposición', 'Taller', 'Función de teatro', 'Conferencia',
        'Festival', 'Residencia artística', 'Visita guiada', 'Proyección', 'Reunión',
    ],
    'segmento' => [
        'Familiar', 'Infantil', 'Juvenil', 'Adulto', 'Adulto mayor',
        'Comunidad educativa', 'Público general', 'Profesional',
    ],
    'mercado'     => ['Barrio', 'Municipal', 'Regional', 'Nacional', 'Internacional'],
    'pais'        => ['Andalia'],
    'ciudad'      => ['Puerto Sereno', 'Valdecanto', 'Ribalta'],
    'organizador' => ['Centro Cultural Meridiano', 'Red de Bibliotecas', 'Asociación Vecinal Ribalta', 'Compañía La Veleta'],
    'linea_estrategica' => [
        'C1. Ampliar el acceso de la ciudadanía a la programación cultural, con especial atención a los barrios con menos oferta.',
        'C2. Acompañar la creación local ofreciendo espacios de ensayo, residencia y exhibición a artistas del territorio.',
        'C3. Convertir el centro en un lugar de encuentro cotidiano, más allá de la asistencia puntual a un espectáculo.',
        'P1. Construir públicos nuevos desde la educación, trabajando con centros escolares durante todo el curso.',
        'P2. Cuidar y difundir la memoria cultural del municipio mediante archivo, exposición y publicación.',
    ],
];

/**
 * Plantillas de evento por área. Cada una aporta nombre, tipo, público y objetivo,
 * de manera que los datos generados se lean como los de una institución real.
 *
 * @var array<string,array<int,array{0:string,1:string,2:string,3:string}>>
 */
const PLANTILLAS = [
    'Programación' => [
        ['Ciclo de jazz en el patio', 'Concierto', 'Público general', 'Ocupar el patio en las noches de verano con formaciones pequeñas.'],
        ['Temporada de teatro contemporáneo', 'Función de teatro', 'Adulto', 'Traer tres compañías de fuera del circuito habitual.'],
        ['Noche de cine mudo con música en directo', 'Proyección', 'Público general', 'Recuperar el repertorio silente con acompañamiento en vivo.'],
        ['Festival de músicas del mundo', 'Festival', 'Familiar', 'Reunir durante un fin de semana a grupos de cinco tradiciones distintas.'],
        ['Residencia de danza contemporánea', 'Residencia artística', 'Profesional', 'Ceder la sala grande a una compañía durante tres semanas.'],
        ['Recital de piano de invierno', 'Concierto', 'Adulto mayor', 'Programar repertorio clásico en horario de tarde.'],
        ['Muestra de teatro de calle', 'Función de teatro', 'Familiar', 'Sacar la programación a la plaza durante las fiestas.'],
        ['Ciclo de cine documental', 'Proyección', 'Juvenil', 'Proyectar seis documentales con coloquio posterior.'],
    ],
    'Producción' => [
        ['Montaje de la exposición de otoño', 'Exposición', 'Público general', 'Preparar sala, iluminación y seguros de las obras.'],
        ['Revisión técnica del equipo de sonido', 'Reunión', 'Profesional', 'Comprobar el estado del equipo antes de la temporada.'],
        ['Exposición de fotografía del archivo municipal', 'Exposición', 'Público general', 'Mostrar cien fotografías inéditas del archivo.'],
        ['Instalación sonora en la sala pequeña', 'Exposición', 'Juvenil', 'Acoger una pieza de arte sonoro durante un mes.'],
        ['Exposición colectiva de artistas locales', 'Exposición', 'Público general', 'Dar sala a doce creadores del municipio.'],
    ],
    'Comunicaciones' => [
        ['Presentación de la temporada', 'Conferencia', 'Profesional', 'Presentar la programación del año a prensa y colaboradores.'],
        ['Campaña de abonos de temporada', 'Conferencia', 'Adulto', 'Explicar las ventajas del abono y captar suscriptores.'],
        ['Jornada de puertas abiertas', 'Visita guiada', 'Familiar', 'Abrir bambalinas, camerinos y taller al público.'],
        ['Encuentro con medios locales', 'Reunión', 'Profesional', 'Mantener la relación con la prensa del municipio.'],
        ['Lanzamiento del boletín cultural', 'Conferencia', 'Público general', 'Estrenar el boletín mensual de actividades.'],
    ],
    'Educación' => [
        ['Taller de iniciación al teatro', 'Taller', 'Infantil', 'Acercar la escena a niñas y niños de ocho a doce años.'],
        ['Programa escolar de visitas', 'Visita guiada', 'Comunidad educativa', 'Recibir a los centros del municipio durante el curso.'],
        ['Taller de escritura creativa', 'Taller', 'Juvenil', 'Trabajar la escritura breve con adolescentes.'],
        ['Curso de introducción a la historia del arte', 'Conferencia', 'Adulto mayor', 'Ofrecer ocho sesiones abiertas los jueves por la tarde.'],
        ['Taller de radio para adolescentes', 'Taller', 'Juvenil', 'Producir un programa mensual hecho por el alumnado.'],
        ['Laboratorio de creación en familia', 'Taller', 'Familiar', 'Crear en común entre adultos y menores un sábado al mes.'],
    ],
    'Administración' => [
        ['Consejo de dirección', 'Reunión', 'Profesional', 'Revisar la marcha del centro y aprobar decisiones.'],
        ['Auditoría anual de cuentas', 'Reunión', 'Profesional', 'Someter las cuentas del ejercicio a revisión externa.'],
        ['Planificación presupuestaria', 'Reunión', 'Profesional', 'Preparar el presupuesto del ejercicio siguiente.'],
    ],
];

echo "Sembrando datos de demostración del Centro Cultural Meridiano\n";

// ---------------------------------------------------------------------------
// 1. Estado previo
// ---------------------------------------------------------------------------
$hayEventos = (int) $pdo->query('SELECT COUNT(*) FROM eventos')->fetchColumn();
if ($hayEventos > 0 && !$forzar) {
    echo "  Ya hay $hayEventos eventos. Usa --forzar para vaciar y volver a sembrar.\n";
    exit(0);
}

if ($forzar) {
    // El orden importa por las claves foráneas. Se desactivan un momento para
    // poder vaciar sin pelear con el grafo de dependencias.
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['eventos_historial', 'eventos', 'intentos_acceso'] as $t) {
        $pdo->exec("TRUNCATE TABLE `$t`");
    }
    if ($conUsuarios) {
        $pdo->exec('TRUNCATE TABLE `usuarios`');
        $pdo->exec('TRUNCATE TABLE `catalogo_valores`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "  Contenido anterior vaciado.\n";
}

// ---------------------------------------------------------------------------
// 2. Catálogos
// ---------------------------------------------------------------------------
$insCat = $pdo->prepare(
    'INSERT INTO catalogo_valores (campo, valor, valor_norm, usos, activo, color) VALUES (?, ?, ?, 0, 1, ?)
     ON DUPLICATE KEY UPDATE color = COALESCE(color, VALUES(color))'
);

$sembrado = 0;
foreach (AREAS as [$valor, $color]) {
    $insCat->execute(['area', $valor, Normalizador::normalizar($valor), $color]);
    $sembrado++;
}
$insCat->execute(['area', 'N/A', 'n/a', '#B3B7BF']);
$sembrado++;

foreach (CATALOGOS as $campo => $valores) {
    foreach ([...$valores, 'N/A'] as $v) {
        $insCat->execute([$campo, Normalizador::limpiar($v), Normalizador::normalizar($v), null]);
        $sembrado++;
    }
}
echo "  Catálogos: $sembrado valores.\n";

// ---------------------------------------------------------------------------
// 3. Usuarios de ejemplo
// ---------------------------------------------------------------------------
$areaId = static function (string $nombre) use ($pdo): int {
    $st = $pdo->prepare("SELECT id FROM catalogo_valores WHERE campo = 'area' AND valor_norm = ?");
    $st->execute([Normalizador::normalizar($nombre)]);
    return (int) $st->fetchColumn();
};

if ($conUsuarios || (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0) {
    $cuentas = [
        ['Ana Torres',   'ana.torres@meridiano.demo',   'admin',   null],
        ['Carlos Mena',  'carlos.mena@meridiano.demo',  'usuario', 'Programación'],
        ['Lucía Ferrer', 'lucia.ferrer@meridiano.demo', 'usuario', 'Comunicaciones'],
    ];
    foreach ($cuentas as [$nombre, $correo, $rol, $area]) {
        if (!Usuario::correoExiste($correo)) {
            Usuario::crear($nombre, $correo, 'demo1234', $rol, $area ? $areaId($area) : null);
        }
    }
    echo "  Usuarios: " . count($cuentas) . " cuentas de ejemplo (contraseña demo1234).\n";
}

$idAdmin = (int) $pdo->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
if ($idAdmin <= 0) {
    fwrite(STDERR, "  No hay ningún administrador: ejecuta con --con-usuarios.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// 4. Eventos repartidos por todo el año
// ---------------------------------------------------------------------------
mt_srand(SEMILLA_ALEATORIA);

$anio = (int) date('Y');
$hoy  = date('Y-m-d');
$creados = 0;
$cancelados = 0;

/** Estado coherente con la fecha: lo pasado está hecho, lo futuro no. */
$estadoSegunFecha = static function (string $inicio, string $fin) use ($hoy): string {
    if ($fin < $hoy)    { return 'realizado'; }
    if ($inicio <= $hoy) { return 'en_ejecucion'; }
    return 'no_realizado';
};

$ciudades = CATALOGOS['ciudad'];
$mercados = CATALOGOS['mercado'];
$organizadores = CATALOGOS['organizador'];
$lineas = CATALOGOS['linea_estrategica'];

// Se recorren los doce meses y en cada uno se programan entre 4 y 8 eventos,
// de modo que el mapa de calor tenga relieve en todo el año y no solo un pico.
for ($mes = 1; $mes <= 12; $mes++) {
    $cuantos = mt_rand(4, 8);
    $diasDelMes = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));

    for ($i = 0; $i < $cuantos; $i++) {
        // Una de cada cinco veces se repite un día ya usado del mes, para que
        // haya jornadas con dos o tres eventos y el mapa muestre intensidad.
        $dia = mt_rand(1, $diasDelMes);
        if ($i > 0 && mt_rand(1, 5) === 1) {
            $dia = min($diasDelMes, max(1, $dia - mt_rand(0, 2)));
        }

        $area = PESOS_AREA[array_rand(PESOS_AREA)];
        $plantilla = PLANTILLAS[$area][array_rand(PLANTILLAS[$area])];
        [$nombre, $tipo, $publico, $objetivo] = $plantilla;

        // Duración: casi todo dura un día, algunas cosas se alargan.
        $duracion = match (true) {
            $tipo === 'Exposición'           => mt_rand(8, 18),
            $tipo === 'Festival'             => mt_rand(2, 4),
            $tipo === 'Residencia artística' => mt_rand(6, 12),
            default                           => mt_rand(0, 1),
        };

        $inicio = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
        $fin = date('Y-m-d', strtotime($inicio . ' +' . $duracion . ' days'));
        // Un evento no se sale del año: la vista anual lo recorta y confunde.
        $finAnio = sprintf('%04d-12-31', $anio);
        if ($fin > $finAnio) {
            $fin = $finAnio;
        }

        $estado = $estadoSegunFecha($inicio, $fin);
        $motivo = null;

        // Tres eventos cancelados en todo el año, para que el filtro de estado
        // tenga algo que mostrar y se vea el motivo en la ficha.
        if ($cancelados < 3 && $estado === 'no_realizado' && mt_rand(1, 14) === 1) {
            $estado = 'cancelado';
            $motivo = ['Aplazado a la próxima temporada por agenda de la compañía.',
                       'La sala quedó ocupada por obras de mantenimiento.',
                       'No se alcanzó el mínimo de inscripciones.'][$cancelados];
            $cancelados++;
        }

        $datos = [
            'nombre'            => $nombre . ' · ' . ucfirst(strftime_mes($mes)),
            'fecha_inicio'      => $inicio,
            'fecha_fin'         => $fin,
            'estado'            => $estado === 'cancelado' ? 'no_realizado' : $estado,
            'tipo_accion'       => $tipo,
            'tipo_accion_otro'  => '',   // solo se usa cuando el tipo es "Otros"
            'segmento'          => $publico,
            'segmento_otro'     => '',
            'area'              => $area,
            'linea_estrategica' => $lineas[array_rand($lineas)],
            'pais'              => 'Andalia',
            'ciudad'            => $ciudades[array_rand($ciudades)],
            'mercado'           => $mercados[array_rand($mercados)],
            'organizador'       => $organizadores[array_rand($organizadores)],
            'objetivo'          => $objetivo,
            'resultados'        => $estado === 'realizado'
                ? 'Se cumplió el aforo previsto y se recogieron valoraciones del público.'
                : 'Pendiente',
            'alianzas'          => mt_rand(1, 3) === 1 ? 'Red de Bibliotecas del municipio' : 'N/A',
            'observaciones'     => 'N/A',
            'contactos_url'     => 'N/A',
            'evidencia_url'     => $estado === 'realizado' ? 'https://archivo.meridiano.demo/' . $mes . '-' . $i : 'Pendiente',
            'reuniones'         => (string) (mt_rand(1, 10) === 1 ? 'N/A' : mt_rand(20, 400)),
        ];

        $id = Evento::crear($datos, $idAdmin);
        $creados++;

        if ($motivo !== null) {
            Evento::cancelar($id, $motivo, $idAdmin);
        }
    }
}

echo "  Eventos: $creados repartidos por los doce meses de $anio ($cancelados cancelados).\n";
echo "Listo.\n";

/** Nombre del mes en español, sin depender de la configuración regional del sistema. */
function strftime_mes(int $mes): string
{
    return [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][$mes];
}
