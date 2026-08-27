# Entorno Moodle — SAMCE

Una réplica del Campus Virtual de la UTN Facultad Regional San Francisco, para
desarrollar y probar el sistema de monitoreo de exámenes sin depender del
entorno institucional.

Existe en dos formas, y las dos salen de este repositorio:

- **En la máquina de cada uno**, con Docker, para trabajar sin conexión y sin
  esperar a nadie.
- **Publicada**, en Railway, para que el panel y el backend tengan contra qué
  hablar desde cualquier lado y para mostrar el sistema andando.

Levantarla desde cero en local son cuatro pasos y alrededor de una hora, casi
toda de descargas.

---

## Qué se obtiene

Un Moodle 4.5 igual al Campus Virtual real en tres aspectos: la versión y el
tema, los complementos instalados y la identidad visual.

| | Moodle recién instalado | Después de estos pasos |
|---|---|---|
| Complementos de terceros | 1 | 38 |
| Apariencia | tema por defecto | colores, tipografías, logotipos y carrusel del campus |
| Portada | lista de cursos genérica | acceso centrado, franja de consultas y horarios |
| Velocidad | ~15 s por página | 0,7 a 0,9 s |

Los valores no son estimados a ojo: se obtuvieron comparando la hoja de estilos
y el HTML que genera cada sitio, y cada uno está verificado contra el original.

Además trae **el complemento del proyecto ya instalado**, en `plugins/local_samce`.
Está acá para que sobreviva a un redespliegue: si dependiera de instalarlo a
mano, cada vez que Railway reconstruye la imagen habría que volver a hacerlo, y
el entorno publicado quedaría sin el complemento justo cuando alguien lo va a
mirar.

Esa copia es un espejo del repositorio del complemento, no su origen. Cuando el
complemento cambia, se vuelve a copiar acá y se sube la versión.

---

## Requisitos

- **Docker Desktop** en ejecución (en Windows, con WSL2)
- **Git**
- Alrededor de **2 GB** libres en disco
- Windows con PowerShell (los scripts están escritos para ese entorno)

---

## Los cuatro pasos

```powershell
git clone <URL-de-este-repositorio> C:\dev\samce-entorno-moodle
cd C:\dev\samce-entorno-moodle\scripts

.\0_instalar_entorno.ps1     # Docker, Moodle, tema y contenido de prueba
.\1_instalar_plugins.ps1     # los 7 complementos de terceros

docker cp ..\imagenes moodle-docker-webserver-1:/tmp/imagenes_cvg
docker cp .\2_aplicar_identidad.php moodle-docker-webserver-1:/tmp/
docker exec moodle-docker-webserver-1 php /tmp/2_aplicar_identidad.php

docker cp .\3_verificar.php moodle-docker-webserver-1:/tmp/
docker exec moodle-docker-webserver-1 php /tmp/3_verificar.php
```

El último tiene que terminar con **"RESULTADO: la réplica está completa y correcta"**.

Cada paso está explicado en detalle en [docs/](docs/), con lo que hace, lo que
puede fallar y cómo verificarlo.

**Advertencia sobre el paso 0:** a diferencia de los otros tres, no está probado
de punta a punta. Reproduce los pasos con los que se armó el entorno original,
pero depende de la red y del estado de los repositorios externos. Conviene
correrlo por tramos y verificar cada uno.

---

## El sitio, una vez levantado

**http://localhost:8000**

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin` | `Samce.2026` | Administrador |
| `docente.demo` | `Samce.2026` | Docente |
| `alumno1` a `alumno3` | `Samce.2026` | Alumnos (legajos 16001 a 16003) |

Son credenciales de un entorno de pruebas, sin datos reales de estudiantes.

**La portada no lista los cursos**, igual que el campus. Al curso de pruebas se
llega por el menú tras iniciar sesión, o directamente:

- Curso SI2-2026: http://localhost:8000/course/view.php?id=2
- Evaluación *Primer Parcial*: http://localhost:8000/mod/quiz/view.php?id=3
- Página del intento, donde después va la captura: `/mod/quiz/attempt.php`

Con el complemento configurado, el docente además tiene dos accesos al panel:
*Panel de supervisión SAMCE* dentro de cada curso, y *Panel SAMCE (todos mis
cursos)* en el menú general.

---

## Uso diario

```powershell
# Arrancar (tarda ~11 s en responder; no hacen falta variables de entorno)
docker start moodle-docker-db-1 moodle-docker-mailpit-1 moodle-docker-webserver-1

# Saber cuándo está listo: cuando devuelve 200
curl.exe -s -o NUL -w "%{http_code}`n" http://localhost:8000/login/index.php

# Apagar
docker stop moodle-docker-webserver-1 moodle-docker-db-1 moodle-docker-mailpit-1
```

Más operaciones en [docs/01-instalacion.md](docs/01-instalacion.md).

---

## La versión publicada

El `Dockerfile` arma la misma réplica como una imagen, para Railway. Clona
Moodle y el tema, instala los complementos de terceros, copia el complemento del
proyecto y deja los ajustes de PHP que hacen falta.

Lo que no puede hacer en el build lo hace `deploy/entrypoint.sh` en cada
arranque, porque depende de la base de datos: espera a MySQL, instala el esquema
si la base está vacía, y después aplica el idioma, el tema, el registro de
complementos, el contenido de prueba y la identidad visual.

**Todos esos pasos son idempotentes**, y eso es lo que hace que el entorno
publicado sea reproducible: cada arranque deja el sitio en el mismo estado sin
importar de dónde venga, así que un redespliegue no lo degrada ni exige tocar
nada a mano.

Las variables que espera son las que Railway inyecta al asociar un servicio
MySQL (`MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`),
más `MOODLE_DATAROOT` para el volumen de datos.

---

## Documentación

| Documento | Contenido |
|---|---|
| [01-instalacion.md](docs/01-instalacion.md) | Instalar desde cero y operar el entorno |
| [02-rendimiento.md](docs/02-rendimiento.md) | Por qué el sitio iba a 15 s por página y cómo se resolvió |
| [03-complementos.md](docs/03-complementos.md) | Los 7 complementos: cuáles, por qué y cómo se detectaron |
| [04-identidad-visual.md](docs/04-identidad-visual.md) | Colores, tipografías, logotipos y portada |
| [05-problemas-conocidos.md](docs/05-problemas-conocidos.md) | Las trampas que ya costaron horas |

Si algo no funciona, **empezá por
[05-problemas-conocidos.md](docs/05-problemas-conocidos.md)**: es probable que
ya esté ahí.

---

## Estructura

```
scripts/                     para levantarlo en la propia máquina
  0_instalar_entorno.ps1     Docker, Moodle, tema, contenido de prueba
  1_instalar_plugins.ps1     descarga e instala los 7 complementos
  2_aplicar_identidad.php    31 ajustes, 8 imágenes, pie y portada
  3_verificar.php            40 comprobaciones, no modifica nada
  samce_setup.php            crea curso, usuarios y evaluación
  samce_preguntas.php        importa las preguntas de la evaluación

Dockerfile                   la misma réplica, como imagen
deploy/                      lo que sólo aplica a la versión publicada
  entrypoint.sh              lo que corre en cada arranque
  config.php                 configuración de Moodle para ese entorno
  instalar_plugins.sh        los complementos, para el build
  instalar_idioma.php        el paquete de español, que no tiene CLI

plugins/local_samce/         el complemento del proyecto, para que sobreviva al redespliegue
imagenes/                    logotipos e imágenes del carrusel
docs/                        la documentación de arriba
```

Los scripts 1, 2 y 3 se pueden correr las veces que haga falta. El 2 rehace los
bloques de la portada en cada corrida, de modo que el resultado no depende del
estado anterior.

---

## Contexto

Forma parte del proyecto final **SAMCE** (Sistema de Monitoreo de Comportamiento
de Exámenes), tesis de grado de Ingeniería en Sistemas de Información en la UTN
FRSFCO.

El entorno existe porque el complemento se instala y se prueba sobre Moodle, y
el acceso al campus institucional depende de gestiones que no controla el
equipo. Tenerlo propio desacopla el avance del proyecto de esos tiempos.
