# Bitácora de decisiones del complemento

Qué se decidió en `local_samce`, por qué, y qué se descartó. La lista de tipos de
evento y los topes de tamaño se acuerdan con `samce-backend` (ver su
`docs/DEPLOYMENT.md`).

## El registro es autoinformado

Los eventos de interacción los arma el JavaScript que corre en el navegador del
alumno, y llegan a Moodle por una función AJAX que el alumno puede llamar con su
propia sesión. El servidor comprueba **quién** es y **de qué intento** se trata
(dueño, no vista previa, en curso, capacidad `mod/quiz:attempt`, aviso visto),
pero del **contenido** solo la forma. La firma que agrega el complemento garantiza
el origen del lote (salió de este Moodle), no la veracidad de lo que dice: nada
distingue un evento que generó la captura de uno escrito con dos líneas de
`fetch` en la consola. No tiene arreglo de fondo, porque el código corre en la
máquina del alumno.

Lo que se hace para acotar el daño: forma y tipos validados, tope de tamaño por
evento y por lote, tope de eventos por minuto y por intento (`send_policy`), y
el backend rechaza horas de evento absurdas. Cualquier análisis que se apoye en
estos datos tiene que tratarlos como lo que son.

## El aviso previo, y qué es (y qué no es) el "consentimiento"

El alumno ve un aviso antes de comenzar el intento y no puede rendir sin
haberlo leído. Eso es una **condición para rendir**, no un consentimiento libre
en el sentido del art. 5 de la Ley 25.326: negarse cuesta el examen. La base
sobre la que se propone sostener el tratamiento es la función evaluativa de la
institución, con el aviso como información previa obligatoria (art. 6). Los
nombres del proyecto (HU11, RF05, el evento `consent_accepted`) siguen diciendo
"consentimiento" por historia; el texto que ve el alumno ya no. La decisión
final sobre la base legal es de la institución (ver `docs/PRIVACIDAD.md` en
`samce-backend`).

La constancia de que el alumno vio el aviso se guarda **en el servidor**, como
una preferencia de usuario por intento (`local_samce_notice_<intento>`), y
`local_samce_send_events` la exige antes de aceptar el primer lote. Se exporta
con los datos del usuario y Moodle la borra cuando borra al usuario.

## Solo Chrome, en computadora

Los exámenes con captura se rinden con Google Chrome de escritorio. Se decide
con el User-Agent (falseable). `HeadlessChrome` pasa a propósito, para que los
casos de prueba automatizados puedan rendir; hay un test que lo fija. Brave y
otros basados en Chromium pasan: no se pueden distinguir de forma fiable.

## Qué se avisa al backend y cuándo

Con `capture_enabled` apagado no sale nada: ni la captura del navegador ni los
avisos de inicio, entrega y abandono (antes el observer no miraba el ajuste).
`backendurl` viene vacía: instalar el complemento no manda nada a ningún lado
hasta que alguien la configure a propósito.

## Reintentos

- El aviso de inicio desde el observer espera 800 ms; si falla, una tarea en
  segundo plano lo reintenta con esperas más largas.
- Un 404 al mandar eventos significa "el intento todavía no tiene sesión": durante
  los primeros 10 minutos del intento se conserva el lote y se reintenta.
- Lo que un lote rechazado se lleva puesto se acota partiéndolo en mitades para
  aislar el evento que falla.

## Descartado

- Un ajuste por cuestionario para elegir qué exámenes se monitorean: hoy el
  interruptor es global (`capture_enabled`).
- Emitir `mouse_activity` y `key_activity` como mucho una vez por minuto: se
  perdería granularidad para el motor de análisis. Queda como decisión abierta.

## Datos que se guardan y cómo se leen (revisión del 24/09/2026)

- `interval_ms` de `key_activity` **no se manda** cuando no hubo intervalos que
  medir (una tecla, o todos los huecos fueron pausas de 2 s o más). Antes salía
  `{0,0,0}` y un alumno lento quedaba igual que un pegado instantáneo. La pausa
  se lee de `pauses`, `max_pause_ms` y `gap_before_ms`.
- `window_ms` es, en teclado y mouse, el tiempo desde el vaciado anterior de esa
  señal. En `mouse_activity`, `idle_ms` es la pausa más larga dentro de la
  ventana (incluida la del final) y `gap_before_ms` es el hueco desde el
  último movimiento de una ventana anterior.
- `context_id` identifica la **pestaña** (sessionStorage), no cada carga de
  página. Duplicar una pestaña copia el almacenamiento y las dos comparten el id.
- El perfil, el tamaño inicial de la ventana (`resize` con `initial: true`) y la
  aceptación no se descartan al desbordarse la cola mientras haya otros eventos
  que sí.
- Un pegado de un archivo o una imagen sale con `non_text: true` y sin `length`.
- Lo que queda en la cola al entregar el examen y no entra en el último envío
  (40 eventos con `keepalive`, por el límite de 64 KB de Chrome) se pierde: la
  página siguiente ya no tiene captura. No se resuelve con más `keepalive` en
  paralelo porque el límite es del navegador para el total de envíos de cierre.
- Un `seq` repetido con el mismo intento no es un error: es un reenvío, y el
  backend lo ignora a propósito (idempotencia por sesión y `seq`).

## JavaScript apagado: se detecta y se marca, no se bloquea

Un alumno que acepta el aviso y después apaga o bloquea el JavaScript no se puede
frenar del lado del navegador: el JS corre en su máquina, y cualquier cosa que
calcule el JS la puede calcular él leyendo el código. Se descartó bloquear el avance
con un "latido" o una firma al navegar (riesgo para el alumno honesto si la captura
falla, y falsificable igual). En cambio se detecta y se le marca al docente:

- **`nojs.php`**: un bloque `<noscript>` en la página del examen enlaza una hoja de
  estilos que el navegador pide SOLO con JavaScript apagado. Oculta el examen con un
  cartel y le avisa al servidor. No es un bloqueo real (editar el CSS lo saltea).
- **La página se entregó y la captura no arrancó**: el servidor anota cuándo entrega
  cada página del intento y la captura anota cuándo arranca; si la entrega es anterior
  y no hubo arranque, esa página corrió sin captura (`capture_watch`).
- Ambos casos mandan al backend un `capture_status` (lo genera el servidor de Moodle),
  y el backend además marca las sesiones sin eventos o con un silencio de 10 minutos o
  más. El panel muestra "Sin captura". Es una señal y no una prueba.
- Sin ningún pedido periódico: no hay latido.

## El latido de "sigo acá" no depende de que haya preguntas visibles

En la página del intento, `question_time` funciona como latido: mientras haya una
pregunta a la vista, emite al menos un evento por minuto (`MAX_HOLD_MS`), y eso es
lo que evita que el backend marque "Sin señales" a un alumno que está pensando.
Pero `mod-quiz-summary` (la pantalla de repaso antes de entregar) no tiene
preguntas, y antes el único aviso posible ahí era el de mouse quieto, que salía
una sola vez y no se repetía. Un alumno que repasa diez minutos sin tocar el
mouse —el comportamiento más prudente que puede tener— quedaba con la misma
marca que un silencio real (revisión externa del 25/09/2026, punto 4.1).

Se corrigió repitiendo el aviso de mouse quieto cada minuto mientras siga sin
moverse, en vez de una sola vez. No depende de que haya preguntas y cubre las dos
páginas por igual, sin agregar ningún pedido de red nuevo: viaja en el próximo
vaciado que de todas formas ya iba a salir.

## `backendurl` rechaza una URL mal formada al guardarse

El ajuste solo exigía una URL sintácticamente válida (`PARAM_URL`), pero
`event_batch::events_url()` necesita que termine exactamente en
`/sessions/moodle-event`. Un typo, una barra de más o cualquier otro desvío
hacía que `events_url()` devolviera `''`, y eso apaga TODO el monitoreo —aviso,
captura, puerta del servidor, avisos de captura— en silencio: la casilla
`capture_enabled` sigue tildada y no queda ningún error visible (revisión
externa del 28/09/2026, punto 1). El ajuste ahora rechaza guardarse si no tiene
esa forma exacta (`classes/admin_setting_backendurl.php`), con un mensaje
explícito. Vacío sigue siendo válido: es el valor por defecto.

## Las preferencias del complemento se limpian al borrar un cuestionario

`mdl_user_preferences` no tiene columna de curso ni de cuestionario, así que
borrar un curso (o solo el cuestionario) no tocaba las marcas que el
complemento deja por intento (aviso visto, página entregada, captura vista,
alertas) ni la autorización de comienzo por cuestionario: quedaban huérfanas
para siempre (revisión externa del 28/09/2026, punto 8). Se limpian con
`local_samce_pre_course_module_delete` (`lib.php`), un callback legado de
Moodle que corre ANTES de que `quiz_delete_instance()` borre los intentos —
tiene que ser ése y no un observer de `course_module_deleted`, que se dispara
después, cuando ya no queda de dónde leer los intentos del cuestionario.

## "Vio el aviso" no es lo mismo que "la captura arrancó"

La constancia de que el alumno vio el aviso (`accept_notice`) y la marca de que
la captura realmente arrancó en una página (`capture_watch::SEEN_PREFIX`) son
dos hechos distintos. Antes el servidor anotaba el segundo en el mismo request
que registraba el primero, sin condición: si `Capture.init()` arrancaba después
y tiraba una excepción (una extensión que bloquea `sessionStorage` o
`MutationObserver` dentro de un iframe de editor, por ejemplo), el servidor ya
daba la sesión por "con captura" sin que llegara un solo evento (revisión
externa del 28/09/2026, punto 3).

Se corrigió moviendo esa marca a `send_events.php`, cuando efectivamente llega
un lote de eventos de ese intento: `capture.js` ya manda uno apenas arranca
(`flush({force: true})`), así que si la captura corrió de verdad, llega a los
pocos segundos por el mismo camino que ya existe. No agrega ningún pedido de
red nuevo.

## Una respuesta de red vieja no pisa el estado que dejó una más nueva

El vaciado periódico y el de "la página se está yendo de verdad" (keepalive)
pueden estar en vuelo al mismo tiempo a propósito, para no perder lo acumulado
al cerrar. Si el más nuevo respondía primero con éxito y el más viejo, que
venía colgado por una red lenta, respondía después con una falla, esa
respuesta tardía pisaba el estado de reintento (`failures`, `retryAt`,
`isLost`) que el más nuevo acababa de confirmar bueno (revisión externa del
28/09/2026, punto 6). Se corrigió guardando cuándo arrancó cada envío: importa
cuál arrancó último, no cuál termina último. Lo que confirma el servidor sobre
qué eventos recibió (`dropUpTo`) se sigue aplicando siempre, venga de la
respuesta que venga: es un hecho sobre ese envío puntual, no un estado
compartido.
