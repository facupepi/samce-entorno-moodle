# samce-moodle-plugin

SAMCE - Plugin de Moodle (local_samce) para captura de eventos de interaccion

## Estado actual

Implementado hasta ahora: el lanzamiento firmado del panel docente, prerequisito
de HU01 (autenticación del docente) y de la HU02 reformulada. Todavía no hay
nada del módulo de captura de eventos del alumno (eso es Sprint 2, TT_S2).

## Cómo funciona el lanzamiento al panel

1. `lib.php` agrega un link "Panel de supervisión SAMCE" a la navegación de
   cada curso, visible solo para quien tenga la capability
   `local/samce:viewpanel` (docentes por defecto — ver `db/access.php`).
2. Al hacer clic, `launch.php` corre dentro de una sesión ya autenticada de
   Moodle (`require_login()`), vuelve a chequear la capability, arma un
   payload con la identidad del docente y lo firma con
   `classes/token_signer.php` (HMAC-SHA256, TTL de 60 segundos).
3. Redirige al panel docente (`samce-teacher-dashboard`) con el token en la
   query string. El backend (`samce-backend`) lo valida en
   `POST /auth/moodle/verify` y emite su propia sesión.

La contraseña del docente nunca sale de Moodle. Se descartó a propósito
depender de los servicios web *core* de Moodle (requeriría habilitarlos y
emitir un token de alcance amplio); en su lugar, el propio plugin expone
justo lo necesario, para que la instalación siga siendo un pedido acotado y
auditable ante el Campus Virtual real.

## Configuración

En Site administration → Plugins → Local plugins → SAMCE:

- **Launch shared secret**: tiene que coincidir exactamente con
  `MOODLE_LAUNCH_SECRET` en `samce-backend`. Generar con
  `openssl rand -hex 32` y no versionarlo en ningún lado.
- **Teacher panel callback URL**: URL del callback de autenticación del
  panel (`https://<dominio-del-panel>/auth/callback`).

## Instalación en la réplica local (Docker/Railway)

Copiar el contenido de este repo a `local/samce/` dentro de la instalación
de Moodle y correr `admin/cli/upgrade.php` (o la pantalla de administración
de plugins), después configurar los dos ajustes de arriba.
