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

    // ------------------------------------------- Etiquetas de los campos
    'Nombre del evento'            => 'Event name',
    'Fecha inicio'                 => 'Start date',
    'Fecha fin'                    => 'End date',
    'Tipo de evento'               => 'Event type',
    'Tipo de evento · cuál'        => 'Event type · which one',
    'Público'                      => 'Audience',
    'Público · cuál'               => 'Audience · which one',
    'Área responsable'             => 'Owning department',
    'Línea del plan cultural'      => 'Cultural plan objective',
    'País'                         => 'Country',
    'Ciudad'                       => 'City',
    'Procedencia del público'      => 'Where the audience comes from',
    'Organizador'                  => 'Organiser',
    'Objetivo'                     => 'Purpose',
    'Resultados'                   => 'Outcomes',
    'Contactos (enlace)'           => 'Contacts (link)',
    'Aforo estimado'               => 'Estimated attendance',
    'Alianzas'                     => 'Partnerships',
    'Observaciones'                => 'Notes',
    'Evidencia (enlace)'           => 'Evidence (link)',

    // --------------------------------------- Ayudas bajo cada etiqueta
    'Como se conoce el evento.'    => 'What the event is known as.',
    'Primer día del evento.'       => 'First day of the event.',
    'Último día. Si dura un solo día, repite la fecha de inicio.'
        => 'Last day. For a one-day event, repeat the start date.',
    'Cómo va hoy. Se puede cambiar después desde la ficha.'
        => 'How it stands today. It can be changed later from the record.',
    'Qué clase de evento es. Si no está en la lista, elige Otros.'
        => 'What kind of event it is. If it is not on the list, choose Other.',
    'Público al que se dirige. Si no está en la lista, elige Otros.'
        => 'Who it is aimed at. If it is not on the list, choose Other.',
    'Área responsable del evento. Solo esa área podrá editarlo.'
        => 'The department that owns the event. Only that department will be able to edit it.',
    'Línea del plan cultural a la que aporta este evento.'
        => 'The cultural plan objective this event contributes to.',
    'País donde ocurre el evento.' => 'Country where the event takes place.',
    'Ciudad donde ocurre el evento.' => 'City where the event takes place.',
    'De dónde viene principalmente el público del evento.'
        => 'Where the event audience mainly comes from.',
    'Quién organiza. Si no aparece en la lista, escríbelo y queda disponible.'
        => 'Who is organising it. If it is not on the list, type it and it becomes available to everyone.',
    'Para qué se hace y qué se busca lograr.'
        => 'Why it is being held and what it aims to achieve.',
    'Resultados esperados o los que ya se obtuvieron, según en qué va el evento. Si lo dejas vacío se guarda como N/A.'
        => 'Expected or actual outcomes, depending on how far along the event is. Left empty, it is stored as N/A.',
    'Enlace al listado de contactos conseguidos.' => 'Link to the list of contacts gathered.',
    'Número aproximado de asistentes.'           => 'Approximate number of attendees.',
    'Con quién se hizo alianza para este evento. Si no hubo ninguna, escribe N/A con el botón.'
        => 'Who you partnered with for this event. If there was nobody, use the button to write N/A.',
    'Cualquier nota útil sobre el evento. Si lo dejas vacío se guarda como N/A.'
        => 'Any useful note about the event. Left empty, it is stored as N/A.',
    'Enlace a fotos, informes o soportes.' => 'Link to photos, reports or supporting material.',

    // ------------------------------------------------ Resto de la interfaz
    '1 evento'                     => '1 event',
    ':n eventos'                   => ':n events',
    ':area · todo :anio: :total eventos en :meses meses, el más cargado con :pico'
        => ':area · all of :anio: :total events across :meses months, the busiest with :pico',
    ':n listas de contactos'       => ':n contact lists',
    ':n realizadas'                => ':n done',
    ':n realizados'                => ':n done',
    ':n realizados · :c cancelados' => ':n done · :c cancelled',
    ':n territorios'               => ':n territories',
    'Abrir dashboard'              => 'Open the dashboard',
    'Abrir enlace'                 => 'Open link',
    'Acciones'                     => 'Actions',
    'Acción'                       => 'Action',
    'Activar'                      => 'Activate',
    'Añadir usuario'               => 'Add a user',
    'Artes escénicas'              => 'Performing arts',
    'Buscar eventos o escribe una acción…' => 'Search events, or type a command…',
    'Buscar por nombre, ciudad, organizador u objetivo…'
        => 'Search by name, city, organiser or purpose…',
    'Cada casilla es un mes de un área. Pasa el mouse para ver el detalle; haz clic para abrir ese mes en el calendario.'
        => 'Each cell is one month of one department. Hover for the detail; click to open that month in the calendar.',
    'Cambiar estado'               => 'Change status',
    'Cambios'                      => 'Changes',
    'Cancelados'                   => 'Cancelled',
    'Cancelar evento…'             => 'Cancel event…',
    'Carga por área y mes'         => 'Workload by department and month',
    'Catálogos'                    => 'Catalogues',
    'Cerrar aviso'                 => 'Dismiss notice',
    'Color'                        => 'Colour',
    'Conciertos'                   => 'Concerts',
    'Confirmar cancelación'        => 'Confirm cancellation',
    'Confirmar eliminación'        => 'Confirm deletion',
    'Contactos'                    => 'Contacts',
    'Creado por'                   => 'Created by',
    'Crear usuario'                => 'Create user',
    'Cuándo y qué'                 => 'When and what',
    'Dar de baja'                  => 'Deactivate',
    'Del más cercano al más lejano' => 'Soonest first',
    'Del más lejano al más cercano' => 'Latest first',
    'Desactivar'                   => 'Deactivate',
    'Desactivar oculta el valor del autocompletado sin tocar los eventos que ya lo usan; si alguien lo vuelve a escribir tal cual, se reactiva solo (usa "Unir" si quieres redirigirlo para siempre). Unir mueve los eventos y desactiva el origen. "Recalcular usos" cuenta los eventos vivos reales.'
        => 'Deactivating hides the value from autocomplete without touching the events that already use it; if someone types it again exactly, it comes back on its own (use "Merge" if you want to redirect it for good). Merging moves the events and deactivates the source. "Recount uses" counts the live events for real.',
    'Dónde y quién'                => 'Where and who',
    'Editar'                       => 'Edit',
    'Editar evento'                => 'Edit event',
    'El evento seguirá visible, tachado, y se podrá reanudar. Si fue un error de carga, usa "Eliminar".'
        => 'The event stays visible, struck through, and can be resumed. If it was entered by mistake, use "Delete".',
    'El valor anterior «:valor» ya no está en la lista. Elige uno para poder guardar.'
        => 'The previous value «:valor» is no longer on the list. Pick one so you can save.',
    'Elige una línea…'             => 'Choose an objective…',
    'Eliminado'                    => 'Deleted',
    'Eliminados'                   => 'Deleted',
    'Eliminar es para errores de carga y siempre exige un motivo. Si un evento no se hará, lo correcto es cancelarlo (queda visible, tachado). Al restaurar, el evento vuelve al calendario con el estado y los datos que tenía; la eliminación y su motivo quedan en el historial.'
        => 'Deleting is for data-entry mistakes and always requires a reason. If an event will not go ahead, the right move is to cancel it (it stays visible, struck through). On restore, the event returns to the calendar with the status and data it had; the deletion and its reason stay in the history.',
    'Eliminar es para errores de carga; si el evento no se hará, usa "Cancelar evento". Si hace falta, un administrador podrá restaurarlo.'
        => 'Deleting is for data-entry mistakes; if the event will not go ahead, use "Cancel event". If needed, an administrator can restore it.',
    'Eliminar evento…'             => 'Delete event…',
    'Es la única sección donde vale' => 'This is the only section where',
    'Escribe cuál…'                => 'Type which one…',
    'Escribe para buscar en la lista…' => 'Type to search the list…',
    'Estado actual'                => 'Current status',
    'Estado previo'                => 'Previous status',
    'Esto es una demostración con datos ficticios. Se reinician solos cada noche; aquí puedes devolverlos a su estado inicial ahora mismo.'
        => 'This is a demo with fictional data. It resets itself every night; here you can return it to its initial state right now.',
    'Evento'                       => 'Event',
    'Evento cancelado'             => 'Event cancelled',
    'Eventos por mes y estado'     => 'Events by month and status',
    'Evidencia'                    => 'Evidence',
    'Exportar CSV'                 => 'Export CSV',
    'Exposiciones'                 => 'Exhibitions',
    'Fecha'                        => 'Date',
    'Fechas'                       => 'Dates',
    'Festivales'                   => 'Festivals',
    'Filtrar'                      => 'Filter',
    'Guardar'                      => 'Save',
    'Historial'                    => 'History',
    'Indicadores'                  => 'Indicators',
    'Ir al calendario'             => 'Go to the calendar',
    'La papelera está vacía.'      => 'The recycle bin is empty.',
    'Los eventos de «:valor» pasarán al valor elegido y este quedará inactivo. ¿Continuar?'
        => 'Events using «:valor» will move to the value you pick, and this one will be deactivated. Continue?',
    'Lugar'                        => 'Place',
    'Mantener «:valor» como valor nuevo' => 'Keep «:valor» as a new value',
    'Marcar como :estado'          => 'Mark as :estado',
    'Marcar este campo como «no aplica»' => 'Mark this field as «not applicable»',
    'Motivo'                       => 'Reason',
    'Motivo de la cancelación'     => 'Reason for cancelling',
    'Motivo de la eliminación'     => 'Reason for deleting',
    'Mínimo 8 caracteres'          => 'At least 8 characters',
    'Nada coincide. Solo se puede elegir un valor de la lista.'
        => 'Nothing matches. You can only pick a value from the list.',
    'No hay eventos con esos filtros.' => 'No events match those filters.',
    'Nombre'                       => 'Name',
    'Nueva contraseña'             => 'New password',
    'Número, N/A o Pendiente'      => 'A number, N/A or Pending',
    'Obligatoria: sin área la persona solo podría consultar.'
        => 'Required: with no department the person could only view.',
    'Orden'                        => 'Order',
    'Otros (especificar)'          => 'Other (specify)',
    'Pendiente'                    => 'Pending',
    'Persona'                      => 'Person',
    'Por'                          => 'By',
    'Por qué no se hará (queda en el historial)'
        => 'Why it will not go ahead (kept in the history)',
    'Por qué se elimina (p. ej. registro duplicado o error de carga). Queda en el historial.'
        => 'Why it is being deleted (a duplicate record, a data-entry mistake). Kept in the history.',
    'Por segmento'                 => 'By audience',
    'Por tipo de evento'           => 'By event type',
    'Por área'                     => 'By department',
    'Puedes corregir datos; para volver a activarlo usa "Reanudar evento".'
        => 'You can still fix the data; to bring it back use "Resume event".',
    'Página :n de :total'          => 'Page :n of :total',
    'Público escolar e infantil'   => 'Schools and children',
    'Queda registrado en el evento. Un administrador puede convertirlo después en una opción oficial.'
        => 'It is recorded on the event. An administrator can turn it into an official option later.',
    'Reactivar'                    => 'Reactivate',
    'Realizadas'                   => 'Done',
    'Reanudar'                     => 'Resume',
    'Reanudar evento'              => 'Resume event',
    'Recalcular usos'              => 'Recount uses',
    'Registrar un evento'          => 'Record an event',
    'Reiniciar la demostración'    => 'Reset the demo',
    'Renombrar'                    => 'Rename',
    'Restaurar'                    => 'Restore',
    'Revisa los campos marcados. Todos son obligatorios.'
        => 'Check the fields marked. All of them are required.',
    'Rol'                          => 'Role',
    'Se borrarán los eventos actuales y se volverán a sembrar los de ejemplo. ¿Seguir?'
        => 'The current events will be deleted and the sample ones seeded again. Go ahead?',
    'Se dará de baja a :nombre. Podrás reactivarlo después.'
        => ':nombre will be deactivated. You can reactivate them later.',
    'Se había eliminado por'       => 'It had been deleted because',
    'Se reanuda desde el botón de abajo.' => 'Resume it with the button below.',
    'Sedes'                        => 'Venues',
    'Seguimiento'                  => 'Follow-up',
    'Selecciona…'                  => 'Select…',
    'Sin eventos en :anio'         => 'No events in :anio',
    'Sin resultados.'              => 'No results.',
    'Sin área (solo consulta)'     => 'No department (view only)',
    'Sin área asignada'            => 'No department assigned',
    'Solo cambia el estado; queda en el historial del evento.'
        => 'This only changes the status; it is recorded in the event history.',
    'Solo el área :area (o un administrador) puede editar este evento.'
        => 'Only the :area department (or an administrator) can edit this event.',
    'Talleres'                     => 'Workshops',
    'Tipo'                         => 'Type',
    'Todas'                        => 'All',
    'Todas las áreas'              => 'All departments',
    'Todavía no se sabe'           => 'Not known yet',
    'Todos los años'               => 'All years',
    'Todos los estados'            => 'All statuses',
    'Total'                        => 'Total',
    'Un momento'                   => 'One moment',
    'Unir'                         => 'Merge',
    'Unir con…'                    => 'Merge with…',
    'Usos'                         => 'Uses',
    'Usuario'                      => 'User',
    'Usuarios'                     => 'Users',
    'Valor'                        => 'Value',
    'Volver'                       => 'Back',
    'Vuelve a'                     => 'Returns to',
    'acción'                       => 'command',
    'activo'                       => 'active',
    'algunos valores se parecen a otros que ya existen. Revisa las sugerencias marcadas; puedes usar el existente o mantener el tuyo y guardar de nuevo.'
        => 'some values look like others that already exist. Check the suggestions marked; you can use the existing one or keep yours and save again.',
    'creado por'                   => 'created by',
    'de baja'                      => 'deactivated',
    'editado'                      => 'edited',
    'eliminado'                    => 'deleted',
    'estaba'                       => 'was',
    'https://…, N/A o Pendiente'   => 'https://…, N/A or Pending',
    'inactivo'                     => 'inactive',
    'no cuentan en los indicadores' => 'not counted in the indicators',
    'registros'                    => 'records',
    'si algo no aplica, o'         => 'is valid when something does not apply, or',
    'si todavía no se sabe: pulsa los botones que hay junto al nombre de cada campo y se rellena solo.'
        => 'when it is not known yet: press the buttons next to each field name and it fills itself in.',
    'sin eventos'                  => 'no events',
    'sin usos'                     => 'unused',
    'solo vale en la sección de seguimiento; en el resto hay que elegir o escribir un valor real.'
        => 'is only valid in the follow-up section; everywhere else you must pick or type a real value.',
    'suma de los eventos con cifra' => 'sum of the events that have a figure',
    'todas las áreas'              => 'all departments',
    'tú'                           => 'you',
    'usos'                         => 'uses',
    '¿Cuál?'                       => 'Which one?',
    '¿Quisiste decir…?'            => 'Did you mean…?',
    '¿Reanudar este evento? Volverá al estado que tenía antes de cancelarse.'
        => 'Resume this event? It will go back to the status it had before being cancelled.',
    '¿Restaurar ":nombre"? Volverá al calendario con los datos y el estado que tenía.'
        => 'Restore ":nombre"? It will return to the calendar with the data and status it had.',
    'Área'                         => 'Department',

    // --------------------------------- Avisos y errores del servidor
    'Usuario creado.'              => 'User created.',
    'Usuario actualizado.'         => 'User updated.',
    'Contraseña restablecida.'     => 'Password reset.',
    'Catálogo actualizado.'        => 'Catalogue updated.',
    'Evento restaurado.'           => 'Event restored.',
    'Escribe tu correo y tu contraseña.' => 'Enter your email and password.',
    'Demasiados intentos fallidos. Espera :minutos minutos y vuelve a probar.'
        => 'Too many failed attempts. Wait :minutos minutes and try again.',
    'Correo o contraseña incorrectos.' => 'Wrong email or password.',
    'Cerraste la sesión.'          => 'You have signed out.',
    'El reinicio falló. Revisa el registro del servidor.'
        => 'The reset failed. Check the server log.',
    'La demostración volvió a su estado inicial.'
        => 'The demo is back to its initial state.',
    'Evento eliminado. Si hace falta, un administrador puede restaurarlo.'
        => 'Event deleted. If needed, an administrator can restore it.',
    'Evento cancelado. Sigue visible, tachado, y se puede reanudar desde su ficha.'
        => 'Event cancelled. It stays visible, struck through, and can be resumed from its record.',
    'Evento reanudado.'            => 'Event resumed.',
    'Estado actualizado: :estado.' => 'Status updated: :estado.',
    'Evento creado.'               => 'Event created.',
    'Evento actualizado.'          => 'Event updated.',
    'Esta instalación no está en modo demostración.'
        => 'This installation is not running in demo mode.',
    'Solo un administrador puede reiniciar la demostración.'
        => 'Only an administrator can reset the demo.',
    'El evento no existe o fue eliminado.' => 'The event does not exist or has been deleted.',
    'Solo los administradores pueden entrar aquí.' => 'Only administrators can go here.',
    'Solo el área responsable del evento (o un administrador) puede modificarlo.'
        => 'Only the department that owns the event (or an administrator) can change it.',
    'No tienes un área asignada. Pide al administrador que te asigne una para poder crear eventos.'
        => 'You have no department assigned. Ask an administrator to assign you one so you can create events.',
    'La sesión caducó o el formulario es inválido. Recarga la página e inténtalo de nuevo.'
        => 'Your session expired or the form is invalid. Reload the page and try again.',
    'Esta página no existe.'       => 'This page does not exist.',
    'Ocurrió un error.'            => 'Something went wrong.',

    // ------------------------------------- Mensajes de validación
    'Obligatorio: escribe un valor o N/A si no aplica.'
        => 'Required: type a value, or N/A if it does not apply.',
    'Obligatorio: elige una opción de la lista.' => 'Required: pick an option from the list.',
    'Obligatorio: escribe un valor.'            => 'Required: type a value.',
    'Este campo no admite N/A ni Pendiente: elige un valor real.'
        => 'This field does not accept N/A or Pending: choose a real value.',
    'Escribe el nombre del evento.' => 'Type the event name.',
    'Máximo 200 caracteres.'        => 'At most 200 characters.',
    'Máximo 255 caracteres.'        => 'At most 255 characters.',
    'Máximo 5000 caracteres.'       => 'At most 5000 characters.',
    'Fecha inválida (usa AAAA-MM-DD).' => 'Invalid date (use YYYY-MM-DD).',
    'La fecha fin debe ser igual o posterior a la fecha inicio.'
        => 'The end date must be the same as or later than the start date.',
    'Selecciona un estado.'         => 'Select a status.',
    'Escribe cuál: mínimo 3 caracteres.' => 'Type which one: at least 3 characters.',
    'Obligatorio: pega el enlace, o escribe N/A o Pendiente.'
        => 'Required: paste the link, or type N/A or Pending.',
    'Debe ser un enlace http(s) válido, N/A o Pendiente.'
        => 'It must be a valid http(s) link, N/A or Pending.',
    'Obligatorio: número de reuniones, N/A o Pendiente.'
        => 'Required: a number, N/A or Pending.',
    'Debe ser un número entero (0 o más), N/A o Pendiente.'
        => 'It must be a whole number (0 or more), N/A or Pending.',

    // ------------------------------------------------------------- Meses
    'Ene' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Abr' => 'Apr',
    'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Ago' => 'Aug',
    'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dic' => 'Dec',
    'enero' => 'January', 'febrero' => 'February', 'marzo' => 'March',
    'abril' => 'April', 'mayo' => 'May', 'junio' => 'June',
    'julio' => 'July', 'agosto' => 'August', 'septiembre' => 'September',
    'octubre' => 'October', 'noviembre' => 'November', 'diciembre' => 'December',
];
