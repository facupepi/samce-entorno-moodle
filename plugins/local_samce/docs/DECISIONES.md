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
