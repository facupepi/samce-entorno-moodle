# samce-moodle-plugin

`local_samce`, el complemento que conecta el aula virtual con SAMCE. Abre el
panel docente sin pedir una segunda contraseña, avisa cuando un alumno empieza,
entrega o abandona un examen, le presenta el aviso de privacidad antes de
comenzar, le muestra un indicador mientras rinde y registra señales técnicas de
cómo rinde (cambios de foco, cadencia de tecleo, mouse, portapapeles, ventana).

Es la única pieza que corre dentro de Moodle, y está pensada para que lo que se
le pide al Campus Virtual sea lo mínimo posible: no habilita servicios web, no
emite tokens de alcance amplio y no guarda nada.

## Abrir el panel sin una segunda contraseña

El docente ya tiene su sesión de Moodle abierta. En vez de pedirle que se
registre otra vez en otro sistema, el complemento usa esa sesión como prueba de
identidad y se la pasa firmada al panel.

Hay dos puertas:

**Desde un curso.** `lib.php` agrega *Panel de supervisión SAMCE* a la
navegación del curso, y sólo lo ve quien tenga la capacidad
`local/samce:viewpanel`, que por defecto tienen los dos roles de profesor. Al
pulsarlo, `launch.php` vuelve a comprobar la capacidad, arma el token con la
identidad del docente y del curso, y redirige al panel.

**Desde el campus.** El mismo archivo agrega *Panel SAMCE (todos mis cursos)* a
la navegación general, para quien tenga esa capacidad en al menos un curso.
`launch_global.php` resuelve con `get_user_capability_course()` en qué materias
la tiene y arma el token con la lista completa en lugar de un curso único.

Esa lista se resuelve de nuevo en cada lanzamiento, así que la relación entre
docente y curso no se guarda en ninguna parte: Moodle sigue siendo el único que
la sabe.

El token lo firma `classes/token_signer.php` con HMAC-SHA256 y dura sesenta
segundos. Viaja en la URL del redirect, y una URL queda en el historial y en los
registros del servidor, así que conviene que valga poco tiempo. El backend lo
canjea en `POST /auth/moodle/verify` por su propia sesión.

La contraseña del docente nunca sale de Moodle.

## Avisar cuando alguien rinde

`classes/observer.php` escucha dos eventos internos de Moodle:

| | |
|---|---|
| `attempt_started` | el alumno abrió el examen |
| `attempt_submitted` | lo entregó |
| `attempt_abandoned` | el intento venció sin entregarse |

En los tres casos arma un aviso con el intento, el alumno (id y nombre), el
curso y el examen, lo firma con el mismo mecanismo y el mismo secreto, y lo
manda por POST de servidor a servidor a `POST /sessions/moodle-event`. El
backend cifra tanto el id como el nombre del alumno antes de guardarlos
(ver ARCHITECTURE.md en samce-backend) — nunca quedan en texto plano.

El alumno no ve nada distinto ni tiene que permitir nada, y el docente no tiene
que activar el examen: alcanza con que exista. El identificador del examen sale
de la instancia del módulo y no del identificador del recurso en el curso, que
son números distintos y fácilmente confundibles.

## Registrar cómo rinde el alumno

Mientras el alumno rinde, un módulo JS del complemento junta señales técnicas
de su interacción y las entrega, cada pocos segundos, al servidor de Moodle.
El complemento las valida, las firma con el mismo mecanismo y el mismo secreto,
y las reenvía a `POST /sessions/moodle-events`. **El navegador del alumno nunca
habla con SAMCE**: solo habla con Moodle, y es Moodle, con la identidad del
alumno ya verificada, quien firma y reenvía.

```
página del intento (JS)  ──►  Moodle: lib/ajax/service.php  ──►  samce-backend
   junta y agrega               local_samce_send_events           /sessions/moodle-events
   cola que sobrevive           valida el intento y el usuario,
   a la recarga                 firma el lote y lo reenvía
```

**Qué se registra**

| Requisito | Señales |
|---|---|
| RF07 | pérdida y recuperación de foco de la ventana; pestaña oculta y visible |
| RF08 | cadencia de tecleo agregada por pregunta: inserciones, borrados, de dónde vino lo insertado (tipeo, pegado, arrastre, autocompletado), tiempos entre entradas y pausas |
| RF09 | movimiento del mouse muestreado, inactividad, salida y entrada del cursor en la ventana, y tiempo que cada pregunta estuvo a la vista (se acumula y se informa cuando la pregunta sale de la vista, al cambiar o cerrar la página, o como mucho cada minuto) |
| RF10 | copiar, cortar y pegar (con la longitud, nunca el texto), pantalla completa y tamaño de ventana |
| RF11 | conexión perdida y recuperada (la que informa el navegador y, además, la que se deduce de dos envíos fallidos seguidos, porque el navegador no siempre dispara `offline`); perfil del cliente (familia y versión mayor del navegador, si es táctil) una vez por intento |

**Privacidad.** No se guarda qué tecla se apretó ni el texto que hay en el
cuadro. Del tecleo se cuentan inserciones y borrados; del portapapeles, la
acción y la longitud. Los eventos no llevan ningún dato del alumno: solo el
intento, que el backend usa para ubicar la sesión que ya sabe quién es.

**Dónde corre.** Solo en la página de un intento propio y en curso
(`classes/hook_callbacks.php`, hook `before_standard_head_html_generation`).
No en las demás páginas del campus, ni en la revisión de un intento ya
entregado, ni en el intento de otro alumno, ni en la vista previa de un
docente. La función externa vuelve a comprobarlo del lado del servidor, así que
el JS no es la única barrera.

**Editores de texto.** Las preguntas de ensayo con editor enriquecido usan
TinyMCE, que dibuja lo que escribe el alumno dentro de un iframe; los eventos
de un iframe no llegan a un listener puesto en la página. `amd/src/frames.js`
engancha los detectores también dentro de esos iframes, y los vuelve a enganchar
si el editor reescribe su documento. Con ese mismo editor, hacer clic para
escribir hace que la ventana reciba un `blur` sin que el alumno haya salido
del examen, y al revés: con el foco dentro del editor, el `blur` del cambio de
aplicación le llega a la ventana del iframe y no a la principal. `sig_focus.js`
resuelve las dos cosas leyendo `document.hasFocus()` (que sigue siendo
verdadero mientras el foco está dentro de un iframe de la página, y falso
cuando la ventana pierde el foco de verdad), escuchando `blur` y `focus`
también dentro de los iframes de los editores, y sondeándolo una vez por
segundo por si algún evento no llega.

**Nada de esto puede afectar el examen.** Cada detector corre envuelto en un
`try/catch`; el envío tiene tiempo límite y no muestra mensajes; el hook ignora
cualquier excepción; la función externa devuelve un estado y no lanza errores
por fallas del monitoreo. Con el monitoreo caído, sin respuesta o mal
configurado, el alumno responde, navega y entrega igual. Antes de esperar al
backend, la función externa libera la sesión de Moodle: la llamada AJAX toma el
bloqueo de escritura de la sesión, y una demora de SAMCE trabaría la página
siguiente del alumno, que necesita esa misma sesión. Los avisos de inicio y
entrega del observer también tienen timeouts propios (2 s para conectar, 3 s en
total).

**Qué pasa con lo que no salió.** Los eventos esperan en una cola guardada en
`sessionStorage`, que sobrevive a la recarga de la página (cada pregunta de un
cuestionario paginado la recarga). Un evento sale de la cola solo cuando Moodle
confirmó que llegó a SAMCE; si falla, se reintenta con espera creciente (de 2 a
30 s). Al ocultarse la pestaña o cerrarse la página se vacía con `keepalive`.
Cada evento lleva un `seq` creciente que sirve de clave de idempotencia en el
backend: un lote reenviado no duplica nada.

Los estados que devuelve la función externa son `ok`, `disabled` (la captura
está apagada o mal configurada: el cliente deja de capturar), `rejected` (el
lote no se puede aceptar: se descarta) y `retry` (se conserva y se reintenta).

## Antes de empezar y mientras rinde

**El aviso de privacidad, antes de que exista el intento.** En la página del
cuestionario, «Comenzar intento» abre primero el aviso: qué se registra, adónde
van los datos, quién accede, el responsable, el plazo de conservación y los
derechos del alumno. Solo con «Leí el aviso y continúo» se crea el intento; con
«Volver» no se crea nada. La constancia queda en el servidor, con la hora, y la
captura no arranca sin ella.

**Una puerta del lado del servidor.** El aviso no es solo un cartel encima de la
página: en `startattempt.php`, `attempt.php` y `summary.php`, el complemento
frena y devuelve a la página del cuestionario a un alumno que no pasó por el
aviso, aunque haya borrado el cartel o desactivado JavaScript.

**Solo Chrome en una computadora.** Con `restrict_browser` encendido (es el valor
por defecto), un examen monitoreado no se puede rendir desde otro navegador ni
desde un teléfono o una tableta: el alumno ve un aviso y vuelve al cuestionario.

**El indicador de monitoreo.** Una franja fija al pie de cada página del intento
y del resumen previo a la entrega, fuera del formulario del examen. Desaparece al
cerrarse la sesión.

**Cuando la captura no pudo funcionar.** Si una página del examen se abre con
JavaScript desactivado, el complemento oculta el cuestionario y le explica el
motivo al alumno; si la página se entregó y la captura no arrancó, lo detecta.
En los dos casos el servidor de Moodle le avisa al backend (`capture_status`) y
el docente ve la sesión marcada.

## Configurar

En *Administración del sitio → Extensiones → Extensiones locales → SAMCE*:

| Ajuste | Qué es |
|---|---|
| `launchsecret` | el secreto compartido con el backend, que firma los dos tipos de aviso |
| `panelurl` | el callback del panel, `https://<panel>/auth/callback` |
| `backendurl` | el endpoint de eventos, `https://<backend>/sessions/moodle-event`; la URL de `/sessions/moodle-events` se deriva de ésta, así que tiene que terminar en `/sessions/moodle-event` |
| `capture_enabled` | enciende el registro de las señales de interacción. Viene **apagado** por defecto: con el ajuste apagado no se registra ni se envía ningún evento |
| `restrict_browser` | exige Google Chrome en una computadora para rendir un examen monitoreado. Viene **encendido** |
| `controllercontact` | el responsable de la base de datos y su contacto, que el aviso le muestra al alumno |
| `retentionnotice` | el plazo de conservación que el aviso informa; sin cargar, el aviso dice que todavía no está definido |

El secreto tiene que coincidir exactamente con `MOODLE_LAUNCH_SECRET` en el
backend. Se genera con `openssl rand -hex 32` y no se versiona en ningún lado.

## Instalar

1. Copiar el contenido de este repositorio en `local/samce/` dentro de Moodle y
   correr `admin/cli/upgrade.php`, o usar la pantalla de extensiones.
   **No copiar la carpeta `dev/`**: son herramientas de desarrollo, no forman
   parte del complemento (ni tampoco `.github/`).
2. Configurar los ajustes (ver arriba): `launchsecret` (el mismo que
   `MOODLE_LAUNCH_SECRET` del backend, de 32 bytes o más), `panelurl` y
   `backendurl`.
3. **Prender `capture_enabled`.** Viene apagado, y instalar el complemento no
   alcanza: queda instalado, la configuración se ve completa y no se captura un
   solo evento, sin ningún aviso. Sin `launchsecret` o sin `backendurl` el
   complemento tampoco muestra el aviso de monitoreo ni captura: deja un
   `debugging()` con el motivo.
4. Verificar con un intento de prueba: aparece el aviso de monitoreo, la barra
   al pie de la página y, en el panel, la sesión con sus eventos.

Cada cambio del complemento sube `version.php`; sin eso Moodle no recarga los
observers ni los textos.

**Despliegue en conjunto con el backend: el backend primero.** Un complemento
que manda un tipo de evento nuevo contra un backend que todavía no lo conoce
pierde el lote entero de hasta cien eventos que lo contiene, sin que nadie se
entere. Ver `docs/DEPLOYMENT.md` en `samce-backend`.

Requiere Moodle 4.5.

## Tests

**PHP.** Los que no dependen de Moodle:

```bash
vendor/bin/phpunit local/samce/tests/token_signer_test.php local/samce/tests/event_batch_test.php
```

`token_signer_test` cubre la firma: que el token tenga sus dos partes, que el
contenido vuelva entero al decodificarlo, que se verifique con el mismo secreto
y no con otro, y que no lleve caracteres que se rompan al viajar en una URL.
`event_batch_test` cubre la validación del lote que entrega el navegador (tipos,
tamaños, `seq`, `data`), y que un `data` vacío siga siendo un objeto al volver
a serializarse: como lista, el backend rechazaría el lote entero.

**JS.** Están en `dev/`, con Node 20:

```bash
cd dev
npm ci
npm test              # pruebas sobre amd/src
npm run build         # regenera amd/build
npm run check-build   # falla si amd/build no coincide con amd/src
npm run test:build    # las mismas pruebas sobre el JS compilado
```

Moodle 4.5 solo sirve los archivos de `amd/build/*.min.js` y nunca los de
`amd/src`, así que el JS compilado **se commitea junto con su fuente**. Se
compila con `terser`, sin clonar Moodle ni usar su grunt; las fuentes ya están
escritas como módulos AMD con nombre, que es lo que Moodle espera. La
integración continua (`.github/workflows/ci.yml`) falla si `amd/build` está
desactualizado.

## Estructura

```
lib.php               los dos enlaces, uno por curso y otro general
launch.php            el lanzamiento desde un curso
launch_global.php     el lanzamiento con todas las materias del docente
classes/
  token_signer.php    firma y verificación
  observer.php        los avisos de examen
  event_batch.php     validación del lote de eventos de interacción
  hook_callbacks.php  carga la captura solo en el intento propio y en curso
  external/
    send_events.php   recibe el lote, lo firma y lo reenvía a SAMCE
amd/
  src/                el JS de captura (una fuente por módulo)
  build/              el mismo JS compilado, que es el que sirve Moodle
db/access.php         la capacidad y qué roles la tienen
db/events.php         a qué eventos de Moodle se engancha el observer
db/hooks.php          el hook que carga la captura
db/services.php       la función externa, solo para AJAX
settings.php          los ajustes
lang/es, lang/en      los textos
dev/                  compilado y pruebas del JS (no se instala en Moodle)
```

## Lo que todavía no está

La conexión con el motor de análisis: el complemento registra lo que pasa en
el intento, pero no lo interpreta. Los exámenes monitoreados se rinden solo con
**Chrome en una computadora**; la Moodle App no pasa por la página del examen,
así que no muestra el aviso ni captura.

Tampoco escucha la eliminación de un intento. Si el docente borra uno desde el
aula virtual, el aviso no sale y la sesión queda registrada del lado del
sistema: los dos lados dejan de coincidir. El Sprint 2 sumó el abandono
(`attempt_abandoned`), no la eliminación. El evento existe (`attempt_deleted`),
así que es cuestión de engancharlo en `db/events.php` y decidir qué hacer con la
sesión ya abierta.
