<?php
declare(strict_types=1);

/**
 * Español → English.
 *
 * La clave es el texto original en español, tal cual aparece en la vista. Si
 * una cadena no está aquí, se muestra en español: nunca una clave cruda ni un
 * hueco en blanco.
 *
 * Incluye también los valores de catálogo que vienen sembrados (áreas, tipos,
 * públicos, líneas). Lo que añada la gente desde la aplicación no está y se
 * muestra como lo escribieron, que es lo correcto: traducir contenido ajeno
 * sería inventarlo.
 */

return [
    // ---------------------------------------------------------------- Barra
    'Calendario'                                   => 'Calendar',
    'Eventos'                                      => 'Events',
    'Dashboard'                                    => 'Dashboard',
    'Admin'                                        => 'Admin',
    'Nuevo evento'                                 => 'New event',
    'Pide al administrador que te asigne un área'  => 'Ask an administrator to assign you a department',
    'Idioma'                                       => 'Language',
    'Tema claro'                                   => 'Light theme',
    'Tema oscuro'                                  => 'Dark theme',
    'Menú de usuario'                              => 'User menu',
    'Administrador'                                => 'Administrator',
    'Usuario · sin área'                           => 'User · no department',
    'Mi área'                                      => 'My department',
    'Cerrar sesión'                                => 'Sign out',

    // ------------------------------------------------ Pantalla de acceso
    'Calendario institucional de eventos'          => 'Institutional events calendar',
    'Correo'                                       => 'Email',
    'Contraseña'                                   => 'Password',
    'Tu contraseña'                                => 'Your password',
    'Entrar'                                       => 'Sign in',
    'Cuentas de prueba'                            => 'Sample accounts',
    'Esto es una demostración con datos ficticios. Pulsa una cuenta para rellenar el formulario y comprueba cómo cambia lo que cada persona puede editar.'
        => 'This is a demo with fictional data. Click an account to fill in the form and see how what each person may edit changes.',
    'Todas usan la contraseña :clave.'             => 'They all use the password :clave.',
    'Los datos se reinician cada noche, así que puedes crear, editar y borrar sin miedo.'
        => 'The data resets every night, so you can create, edit and delete without worrying.',
    'Administradora'                               => 'Administrator',
    'Área de Programación'                         => 'Programming department',
    'Área de Comunicaciones'                       => 'Communications department',
    'Ve y edita todo'                              => 'Sees and edits everything',
    'Solo edita lo suyo'                           => 'Only edits their own',

    // ----------------------------------------------------------- Calendario
    'Año'                                          => 'Year',
    'Mes'                                          => 'Month',
    'Semana'                                       => 'Week',
    'Lista'                                        => 'List',
    'eventos en :anio'                             => 'events in :anio',
    'Áreas'                                        => 'Departments',
    'Sin áreas activas.'                           => 'No active departments.',
    'Puedes marcar varias a la vez.'               => 'You can select more than one.',
    'Quitar el área'                               => 'Clear the department',
    'Quitar las :n áreas'                          => 'Clear the :n departments',
    'Quitar'                                       => 'Remove',
    'Estado'                                       => 'Status',
    'Todos los tipos'                              => 'All types',
    'Añadir otro tipo…'                            => 'Add another type…',
    'Todos los segmentos'                          => 'All audiences',
    'Añadir otro segmento…'                        => 'Add another audience…',
    'Solo mi área'                                 => 'My department only',
    'No tienes un área asignada'                   => 'You have no department assigned',
    'Limpiar filtros'                              => 'Clear filters',
    'Próximos 30 días'                             => 'Next 30 days',
    'Nada programado en los próximos 30 días.'     => 'Nothing scheduled in the next 30 days.',
    'Hoy'                                          => 'Today',
    'Anterior'                                     => 'Previous',
    'Siguiente'                                    => 'Next',
    'Mapa de calor'                                => 'Heat map',
    'Resaltar en el mapa todos los días que tienen algún evento'
        => 'Highlight every day on the map that has an event',
    'Días con eventos'                             => 'Days with events',
    'Llevarme a ese día en el mapa'                => 'Take me to that day on the map',
    'Día más cargado'                              => 'Busiest day',
    'Llevarme a esa semana en el mapa'             => 'Take me to that week on the map',
    'Semana más cargada'                           => 'Busiest week',
    'con'                                          => 'with',
    'Quitar resaltado'                             => 'Clear highlight',
    'Contar solo el día de inicio'                 => 'Count the start day only',
    'Cargando…'                                    => 'Loading…',
    'Menos'                                        => 'Less',
    'Más'                                          => 'More',
    'Hasta'                                        => 'Up to',
    'por día. Haz clic en un día para abrirlo en el calendario.'
        => 'per day. Click a day to open it in the calendar.',
    'Detalle'                                      => 'Details',
    'Cerrar'                                       => 'Close',
    'Selecciona un evento'                         => 'Select an event',
    'Haz clic en un chip del calendario para ver toda la ficha.'
        => 'Click an entry in the calendar to see the full record.',
    'Clic en un día vacío crea un evento en esa fecha.'
        => 'Clicking an empty day creates an event on that date.',
    'Para crear eventos pide al administrador que te asigne un área.'
        => 'To create events, ask an administrator to assign you a department.',
    // Iniciales de los días, de lunes a domingo. En inglés la semana se
    // escribe igual empezando en lunes porque la rejilla usa firstDay: 1.
    'L M X J V S D'                                => 'M T W T F S S',

    // Textos que pinta el JavaScript
    'más'                                          => 'more',
    'Sin eventos en este periodo'                  => 'No events in this period',
    'sin nada'                                     => 'nothing',
    'evento'                                       => 'event',
    'eventos'                                      => 'events',
    'semana'                                       => 'week',
    'No se pudo cargar el evento.'                 => 'The event could not be loaded.',
    'No se pudo mover el evento.'                  => 'The event could not be moved.',
    'Ver lista de eventos'                         => 'See the event list',

    // ------------------------------------------------- Áreas de la demo
    'Programación'                                 => 'Programming',
    'Educación'                                    => 'Education',
    'Comunicaciones'                               => 'Communications',
    'Producción'                                   => 'Production',
    'Administración'                               => 'Administration',

    // -------------------------------------------------------- Estados
    'No realizado'                                 => 'Not started',
    'En ejecución'                                 => 'In progress',
    'Realizado'                                    => 'Done',
    'Cancelado'                                    => 'Cancelled',

    // ---------------------------------------------------- Tipos de evento
    'Concierto'                                    => 'Concert',
    'Exposición'                                   => 'Exhibition',
    'Taller'                                       => 'Workshop',
    'Función de teatro'                            => 'Theatre performance',
    'Conferencia'                                  => 'Talk',
    'Festival'                                     => 'Festival',
    'Residencia artística'                         => 'Artist residency',
    'Visita guiada'                                => 'Guided tour',
    'Proyección'                                   => 'Screening',
    'Reunión'                                      => 'Meeting',

    // ---------------------------------------------------------- Públicos
    'Familiar'                                     => 'Families',
    'Infantil'                                     => 'Children',
    'Juvenil'                                      => 'Teenagers',
    'Adulto'                                       => 'Adults',
    'Adulto mayor'                                 => 'Older adults',
    'Comunidad educativa'                          => 'Schools',
    'Público general'                              => 'General public',
    'Profesional'                                  => 'Professionals',

    // ---------------------------------------------------------- Mercados
    'Barrio'                                       => 'Neighbourhood',
    'Municipal'                                    => 'Municipal',
    'Regional'                                     => 'Regional',
    'Nacional'                                     => 'National',
    'Internacional'                                => 'International',

    // ------------------------------------------------ Líneas estratégicas
    'C1. Ampliar el acceso de la ciudadanía a la programación cultural, con especial atención a los barrios con menos oferta.'
        => 'C1. Widen public access to the cultural programme, with particular attention to the neighbourhoods with the least on offer.',
    'C2. Acompañar la creación local ofreciendo espacios de ensayo, residencia y exhibición a artistas del territorio.'
        => 'C2. Support local creation by offering rehearsal, residency and exhibition space to artists from the area.',
    'C3. Convertir el centro en un lugar de encuentro cotidiano, más allá de la asistencia puntual a un espectáculo.'
        => 'C3. Turn the centre into an everyday meeting place, beyond the occasional visit to a show.',
    'P1. Construir públicos nuevos desde la educación, trabajando con centros escolares durante todo el curso.'
        => 'P1. Build new audiences through education, working with schools throughout the academic year.',
    'P2. Cuidar y difundir la memoria cultural del municipio mediante archivo, exposición y publicación.'
        => 'P2. Care for and share the town\'s cultural memory through archiving, exhibition and publishing.',
];
