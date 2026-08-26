# samce-moodle-plugin

SAMCE - Plugin de Moodle (local_samce) para captura de eventos de interaccion

## Estado actual

Implementado hasta ahora:

1. El lanzamiento firmado del panel docente (prerequisito de HU01 y HU02),
   tanto por curso (`launch.php`) como general (`launch_global.php`, todos
   los cursos donde el docente tiene el rol).
2. El registro automático de sesiones de examen (HU02, SAMCE-8): un observer
   escucha cuando un alumno arranca o entrega un intento de examen, y avisa
   a `samce-backend` — sin ninguna acción manual ni del docente ni del
   alumno.

Todavía no hay nada del módulo de captura de eventos de comportamiento del
alumno (foco, tecleo, mouse) ni de la conexión WebSocket — eso es HU10,
Sprint 2, sin empezar.

## Cómo funciona el lanzamiento al panel

1. `lib.php` agrega un link "Panel de supervisión SAMCE" a la navegación de
   cada curso, visible solo para quien tenga la capability
   `local/samce:viewpanel` (docentes por defecto — ver `db/access.php`).
2. Al hacer clic, `launch.php` corre dentro de una sesión ya autenticada de
   Moodle (`require_login()`), vuelve a chequear la capability, arma un
   payload con la identidad del docente (`$USER`) y del curso (`$course`,
   incluyendo `display_name` y `course_name` para que el panel muestre el
   nombre real y no solo identificadores internos de Moodle) y lo firma con
   `classes/token_signer.php` (HMAC-SHA256, TTL de 60 segundos).
3. Redirige al panel docente (`samce-teacher-dashboard`) con el token en la
   query string. El backend (`samce-backend`) lo valida en
   `POST /auth/moodle/verify` y emite su propia sesión.

### Panel general (todos los cursos)

Además del link por-curso, `lib.php` agrega un segundo link
("Panel SAMCE (todos mis cursos)") a la **navegación global** de Moodle
(`local_samce_extend_navigation()`), visible para cualquier usuario que
tenga `local/samce:viewpanel` en al menos un curso — sin depender de estar
parado en ninguno.

Al hacer clic, `launch_global.php` resuelve con
`get_user_capability_course()` en qué cursos el usuario tiene esa
capability, y arma el token con `course_ids` y `courses` (id + nombre de
cada uno) en vez del `course_id`/`course_name` único de `launch.php`. Esto
no requiere guardar en ningún lado la relación docente-curso: Moodle sigue
siendo la única fuente de verdad, resuelta de nuevo en cada lanzamiento.

La contraseña del docente nunca sale de Moodle. Se descartó a propósito
depender de los servicios web *core* de Moodle (requeriría habilitarlos y
emitir un token de alcance amplio); en su lugar, el propio plugin expone
justo lo necesario, para que la instalación siga siendo un pedido acotado y
auditable ante el Campus Virtual real.

## Cómo funciona el registro automático de sesiones (HU02)

`classes/observer.php` escucha dos eventos internos de Moodle:

1. `\mod_quiz\event\attempt_started` — el alumno arrancó el examen. Arma un
   payload (id de intento, id de alumno, curso, id de la instancia del
   examen en Moodle —no el cmid—, nombre) y
   lo firma con `token_signer` — mismo mecanismo que el lanzamiento del
   docente, mismo secreto (`launchsecret`).
2. `\mod_quiz\event\attempt_submitted` — el alumno entregó.

En los dos casos, hace un POST servidor-a-servidor a `samce-backend`
(`POST /sessions/moodle-event`), sin que el alumno vea ni haga nada
distinto de lo que hace hoy. El backend valida la firma y abre o cierra la
sesión.

## Configuración

En Site administration → Plugins → Local plugins → SAMCE:

- **Launch shared secret**: tiene que coincidir exactamente con
  `MOODLE_LAUNCH_SECRET` en `samce-backend`. Generar con
  `openssl rand -hex 32` y no versionarlo en ningún lado. La usan tanto el
  lanzamiento del docente como los eventos de examen.
- **Teacher panel callback URL**: URL del callback de autenticación del
  panel (`https://<dominio-del-panel>/auth/callback`).
- **Backend events URL**: URL del endpoint de eventos del backend
  (`https://<dominio-del-backend>/sessions/moodle-event`).

## Instalación en la réplica local (Docker/Railway)

Copiar el contenido de este repo a `local/samce/` dentro de la instalación
de Moodle y correr `admin/cli/upgrade.php` (o la pantalla de administración
de plugins), después configurar los dos ajustes de arriba.
