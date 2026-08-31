# samce-moodle-plugin

`local_samce`, el complemento que conecta el aula virtual con SAMCE. Hace dos
cosas: abre el panel docente sin pedir una segunda contraseña, y avisa cuando un
alumno empieza o entrega un examen.

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

En los dos casos arma un aviso con el intento, el alumno (id y nombre), el
curso y el examen, lo firma con el mismo mecanismo y el mismo secreto, y lo
manda por POST de servidor a servidor a `POST /sessions/moodle-event`. El
backend cifra tanto el id como el nombre del alumno antes de guardarlos
(ver ARCHITECTURE.md en samce-backend) — nunca quedan en texto plano.

El alumno no ve nada distinto ni tiene que permitir nada, y el docente no tiene
que activar el examen: alcanza con que exista. El identificador del examen sale
de la instancia del módulo y no del identificador del recurso en el curso, que
son números distintos y fácilmente confundibles.

## Configurar

En *Administración del sitio → Extensiones → Extensiones locales → SAMCE*:

| Ajuste | Qué es |
|---|---|
| `launchsecret` | el secreto compartido con el backend, que firma los dos tipos de aviso |
| `panelurl` | el callback del panel, `https://<panel>/auth/callback` |
| `backendurl` | el endpoint de eventos, `https://<backend>/sessions/moodle-event` |

El secreto tiene que coincidir exactamente con `MOODLE_LAUNCH_SECRET` en el
backend. Se genera con `openssl rand -hex 32` y no se versiona en ningún lado.

## Instalar

Copiar el contenido de este repositorio en `local/samce/` dentro de Moodle y
correr `admin/cli/upgrade.php`, o usar la pantalla de extensiones. Después,
configurar los tres ajustes.

Requiere Moodle 4.5.

## Tests

```bash
vendor/bin/phpunit local/samce/tests/token_signer_test.php
```

Cubren la firma: que el token tenga sus dos partes, que el contenido vuelva
entero al decodificarlo, que se verifique con el mismo secreto y no con otro, y
que no lleve caracteres que se rompan al viajar en una URL.

## Estructura

```
lib.php               los dos enlaces, uno por curso y otro general
launch.php            el lanzamiento desde un curso
launch_global.php     el lanzamiento con todas las materias del docente
classes/
  token_signer.php    firma y verificación
  observer.php        los avisos de examen
db/access.php         la capacidad y qué roles la tienen
db/events.php         a qué eventos de Moodle se engancha el observer
settings.php          los tres ajustes
lang/es, lang/en      los textos
```

## Lo que todavía no está

La captura de comportamiento del alumno durante el examen —foco, tecleo,
movimiento— y la conexión en vivo con el motor de análisis. El complemento hoy
sabe cuándo empieza y cuándo termina un examen, no lo que pasa en el medio.
