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

Ese espejo es la única parte del entorno que puede quedar atrasada sin que nada
avise: el sitio levanta igual con una copia vieja, sólo que comportándose como
el complemento de la semana pasada. Para comprobar que está al día, con los dos
repositorios clonados al lado:

```bash
diff -r plugins/local_samce ../samce-moodle-plugin      --exclude=.git --exclude=README.md --exclude=dev --exclude=.github --exclude=node_modules
```

Sin salida quiere decir que la copia coincide con el original. Si aparece algo,
se copia de nuevo y se sube `$plugin->version` en `version.php`, que es lo que
hace que Moodle registre la actualización al arrancar.

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
- Página del intento, donde corre la captura: `/mod/quiz/attempt.php`

Con el complemento configurado, el docente además tiene dos accesos al panel:
*Panel de supervisión SAMCE* dentro de cada curso, y *Panel SAMCE (todos mis
cursos)* en el menú general. Y con la captura encendida, el alumno ve el aviso
de privacidad antes de comenzar y el indicador de monitoreo mientras rinde.

---

## La cadena completa en local

Para rendir un examen en la réplica y ver el análisis en el panel hacen falta cinco piezas, y cada una tiene su puerto. Los pasos parten de los contenedores que ya existen (no se recrea la base) y con Docker Desktop andando.

| Pieza | Puerto | Qué es |
|---|---|---|
| MySQL | 3306 | contenedor `samce_mysql`, con las migraciones hasta la 010 |
| Backend | 8080 | `samce-backend`, compilado con Go |
| Motor de análisis | 8001 | contenedor `samce-motor-local`, con el modelo 1.3.0 |
| Moodle | 8000 | la réplica (`moodle-docker-webserver-1`) |
| Panel docente | 5173 | Vite, `samce-teacher-dashboard` |

Las carpetas son las de la PC donde se armó la réplica (`C:\dev\...`); en otra máquina, las que correspondan.

**1. Contenedores.**

```powershell
docker start samce_mysql moodle-docker-db-1 moodle-docker-mailpit-1 moodle-docker-webserver-1 samce-motor-local
```

**2. Migraciones.** En `samce-backend` se aplican a mano y no hay tabla de control (`migrations/README.md`), así que se comprueba qué hay. La 009 está si existen las tablas `session_analyses` y `session_alerts`, y la 010 si esta consulta devuelve una fila:

```powershell
docker exec -i samce_mysql mysql -uroot -p samce -e "SHOW TABLES LIKE 'session_a%'; SHOW COLUMNS FROM session_alerts LIKE 'level_since';"
```

Para una que falte, desde la carpeta de `samce-backend` (la clave, sin escribir aquí):

```powershell
docker exec -i samce_mysql mysql -uroot -p<clave> samce < migrations/0xx_nombre.sql
```

**3. Motor en el puerto 8001.** La imagen se construye desde la raíz de `samce-ml-engine` y fija las versiones con que se entrenó el modelo (ver su README, «Con Docker, junto a la réplica local»):

```powershell
docker build -t samce-motor-local C:\dev\samce-ml-engine
docker run -d --name samce-motor-local -p 8001:8000 -e ENGINE_SERVICE_TOKEN=<token de al menos 32 caracteres> -e PORT=8000 samce-motor-local
```

Si el contenedor ya existe, alcanza con `docker start samce-motor-local`.

**4. Backend en el puerto 8080.** Lee su `.env` de la carpeta desde donde se corre (`C:\dev\samce-backend`). Además de los secretos de siempre, tiene que traer:

```
ENGINE_URL=http://localhost:8001
ENGINE_SERVICE_TOKEN=<el mismo token del motor>
ANALYSIS_MIN_INTERVAL=120s
```

Se compila y se corre con la salida a un log (así las líneas «sesión analizada» quedan a mano):

```powershell
cd C:\dev\samce-backend
go build -o C:\dev\samce-backend.exe ./src/api
C:\dev\samce-backend.exe *> backend.log
```

**5. Panel en el puerto 5173.**

```powershell
cd C:\dev\samce-teacher-dashboard
npm run dev
python scripts/enlace-de-prueba.py --env ../samce-backend/.env
```

Vite escucha en `[::1]:5173` (IPv6) y Moodle solo en `127.0.0.1:8000` (IPv4). Un Chrome normal llega a los dos, porque con `localhost` prueba las dos direcciones, y el enlace que imprime `enlace-de-prueba.py` funciona tal cual. El Chrome que lanza el rendidor fuerza `localhost` a IPv4 para llegar a Moodle, y por eso **ese navegador no sirve para abrir el panel**: el panel se abre en un Chrome aparte.

### El complemento en la réplica

Hay tres copias del complemento y conviene no confundirlas:

- `samce-moodle-plugin` es el original (la rama `main`).
- `plugins/local_samce` de este repositorio es la copia que instala el Moodle de Railway.
- La réplica local usa `C:\dev\samce\moodle\local\samce`, una carpeta de la PC donde se armó, que no es un repositorio. Se actualiza copiando encima la raíz de `samce-moodle-plugin` (en `main`, o `plugins/local_samce` de este repositorio) y corriendo la actualización:

```powershell
php admin/cli/upgrade.php --non-interactive
```

La versión instalada tiene que ser la 0.3.28 (`version` 2026102201).

### Si no se captura nada

Casi siempre es una de tres cosas, y ninguna avisa:

1. `capture_enabled = 0` en la configuración del complemento.
2. El complemento de la réplica está desactualizado (ver arriba).
3. El backend se desplegó después del complemento y no reconoce lo que este manda.

### Cuestionarios y alumnos de prueba

| Cuestionario | cmid |
|---|---|
| Primer Parcial (SI2-2026) | 3 |
| Segundo Parcial de la escena | 4 |
| Segundo Parcial (réplica del campus), el que usa el rendidor | 12 |
| Curso BD-2026 | 7, 8, 10 y 11 |

El «Segundo Parcial (réplica del campus)» (cmid 12) es copia del de Railway: curso SI2-2026, 5 preguntas, 60 minutos e intentos sin límite. Se crea con `scripts/crear_parcial_local.php`, que lee las preguntas de `scripts/parcial_railway.json`, crea también a los alumnos `rend01` a `rend06` (matriculados en SI2-2026) y corre dentro del contenedor de Moodle: lee `/var/www/html/config.php` y `/tmp/parcial_railway.json`, y toma la clave de los alumnos de la variable `REND_PASS`. Si se vuelve a correr no duplica el cuestionario ni los alumnos: solo les vuelve a poner la clave.

```powershell
docker cp scripts/parcial_railway.json moodle-docker-webserver-1:/tmp/parcial_railway.json
docker cp scripts/crear_parcial_local.php moodle-docker-webserver-1:/tmp/crear_parcial_local.php
docker exec -e REND_PASS=<clave> moodle-docker-webserver-1 php /tmp/crear_parcial_local.php
```

La clave de `rend01` a `rend06` no es la de Railway y no va escrita acá: la tiene quien administra la réplica. El Segundo Parcial de la escena (cmid 4) y el curso BD-2026 no los crea ningún script de este repositorio (`scripts/samce_setup.php` crea solo el Primer Parcial): ya están en la réplica.

### Para el rendidor

- Los cuestionarios tienen que permitir intentos sin límite.
- `localhost` tiene que resolver a IPv4 (el rendidor lo fuerza en `navegador.mjs`).
- Con la máquina cargada, Moodle tarda en mostrar la pantalla de ingreso y el rendidor se puede cortar por tiempo de espera: conviene esperar a que Moodle responda antes de lanzarlo.

El rendidor vive en su propio repositorio, `facupepi/samce-rendidor`, y su README explica cómo se lanza contra esta réplica. En la réplica de la PC donde se armó, `rend01` a `rend06` llevan nombres de persona (Lucía Fernández, Martín Ríos, Sofía Herrera, Tomás Molina, Valentina Castro y Joaquín Ledesma) para que las capturas de los casos de prueba se lean como un curso real. `crear_parcial_local.php` los crea como «Rendidor 01» a «Rendidor 06», y el nombre se cambia desde la administración de Moodle sin afectar nada más.

### Lo que se probó sobre esta réplica

Al cierre del Sprint 3, la cadena completa en local es donde se ejecutaron:

- **Los casos de prueba CP08 a CP20**, de la captura, el aviso de privacidad y el indicador de monitoreo, repetidos como regresión de los sprints anteriores.
- **Los casos CP21 a CP26**, del análisis de cada sesión (HU06): las variables, el índice, las alertas, su nivel de riesgo, su explicación y la validación del modelo.
- **Las cuatro pruebas de integración 6.06**: el motor detenido, los permisos del docente, la privacidad de los datos del alumno y el recorrido completo, desde que el alumno rinde hasta que el docente lee el análisis en el panel.

Todos se rindieron en el «Segundo Parcial (réplica del campus)» con `rend01` a `rend06`. Las sesiones que el rendidor rindió en el «Segundo Parcial» del Moodle de Railway, del que el local es copia, sirven para entrenar y ajustar el modelo junto con el conjunto sintético del motor, y también para validarlo.

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

**La captura de eventos de interacción del alumno** (`local_samce`) viene
apagada por defecto. En la versión publicada se enciende con la variable de
entorno `SAMCE_CAPTURE_ENABLED=1`, que `deploy/entrypoint.sh` aplica en cada
arranque; con `0` se apaga, y sin la variable no se toca lo que se haya
configurado desde la pantalla de administración del complemento.

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
| [panorama-cierre-sprint-3.md](docs/panorama-cierre-sprint-3.md) | Cómo quedó la réplica al cierre del Sprint 3 y los pull requests del sprint |

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
  crear_parcial_local.php    crea el Segundo Parcial (réplica del campus) y rend01 a rend06
  parcial_railway.json       las preguntas de ese parcial, copiadas del de Railway
  opcache-samce.ini          el ajuste de rendimiento de PHP (ver 01-instalacion.md)

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
