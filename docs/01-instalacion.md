# 1. Instalación y operación

## Qué se instala

| Componente | Versión | Por qué |
|---|---|---|
| Moodle | 4.5 (rama LTS) | El Campus Virtual corre 4.5.8 |
| Tema | Adaptable, rama `MOODLE_405` | Es el que usa el campus |
| PHP | 8.2 | Dentro del rango que soporta Moodle 4.5 |
| Base de datos | MySQL 8 | La que trae `moodle-docker` por defecto |
| Idioma | Español internacional | Igual que el campus |
| Zona horaria | America/Argentina/Cordoba | |

## Dónde queda cada cosa

| Ruta | Qué es |
|---|---|
| `C:\dev\samce\moodle` | Código de Moodle: 29.406 archivos, 303 MB |
| `C:\dev\samce\moodle-docker` | Definición de los contenedores |
| `C:\dev\samce\moodle\config.php` | Configuración, incluido el modo de depuración |

**El código va fuera de cualquier carpeta sincronizada en la nube.** Son casi treinta mil archivos: un servicio de sincronización los subiría uno por uno, y si usa descarga a demanda puede dejarlos sin contenido local, con lo cual Docker leería archivos vacíos. Además agrega latencia justo sobre el punto que ya es lento (ver [02-rendimiento.md](02-rendimiento.md)).

Dentro del contenedor `moodle-docker-webserver-1`:

- `/var/www/html` es el código montado desde el disco del anfitrión, y es el punto lento
- `/var/www/moodledata` **no** está montado: vive en el disco del contenedor, y es rápido
- `/usr/local/etc/php/conf.d/zz-samce.ini` es el ajuste del acelerador de PHP

## Instalación

```powershell
cd C:\dev\samce-entorno-moodle\scripts
.\0_instalar_entorno.ps1
```

Hace nueve cosas: comprueba que Docker y Git estén, descarga Moodle y `moodle-docker`, levanta los contenedores, instala la base, configura idioma y zona horaria, instala el tema, corrige el rendimiento, crea el contenido de prueba y verifica que el sitio responda.

Tarda entre treinta y cuarenta y cinco minutos, casi todo en descargar los archivos de Moodle.

**Este script no está probado de punta a punta**, a diferencia de los otros tres. Reproduce los pasos con los que se armó el entorno original, pero depende de la red y de que los repositorios externos no hayan cambiado. Conviene correrlo por tramos y verificar cada uno.

## Operación diaria

Los contenedores ya están creados, así que el uso cotidiano es un solo comando. **No hace falta definir variables de entorno**: las `MOODLE_DOCKER_*` solo intervienen al recrear los contenedores con `docker compose`, nunca para arrancarlos.

```powershell
docker start moodle-docker-db-1 moodle-docker-mailpit-1 moodle-docker-webserver-1
```

Conviene arrancar la base primero, como en esa línea. **Tarda unos 11 segundos** desde que el comando devuelve hasta que el sitio responde; antes de eso el navegador da error de conexión, lo cual es normal. Para saber cuándo está listo sin recargar a ciegas:

```powershell
curl.exe -s -o NUL -w "%{http_code}`n" http://localhost:8000/login/index.php
```

Cuando devuelve `200`, está operativo. Para apagarlo, el orden inverso evita que el servidor web quede atendiendo peticiones sin base de datos:

```powershell
docker stop moodle-docker-webserver-1 moodle-docker-db-1 moodle-docker-mailpit-1
```

### Comandos habituales

| Acción | Comando |
|---|---|
| Estado | `docker ps --filter "name=moodle-docker"` |
| Consola | `docker exec -it moodle-docker-webserver-1 bash` |
| Purgar cachés | `docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/purge_caches.php` |
| Registrar un complemento | `docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/upgrade.php --non-interactive` |
| Ejecutar el cron | `docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/cron.php` |
| Base de datos | `docker exec -it moodle-docker-db-1 mysql -umoodle -pm@0dl3ing moodle` |
| Registro del servidor | `docker logs --tail 50 moodle-docker-webserver-1` |

El `-w /var/www/html` fija el directorio de trabajo; sin él, los comandos de `admin/cli` fallan cuando el contenedor no arranca allí.

**El prefijo de las tablas es `m_`, no `mdl_`.** Es la configuración de `moodle-docker`. Toda consulta directa tiene que usar `m_user`, `m_quiz_attempts` y demás.

### Si hay que recrear los contenedores

Solo en ese caso hacen falta las variables, desde `C:\dev\samce\moodle-docker`:

```powershell
$env:MOODLE_DOCKER_WWWROOT = "C:\dev\samce\moodle"
$env:MOODLE_DOCKER_DB = "mysql"
$env:MOODLE_DOCKER_PHP_VERSION = "8.2"
$env:MOODLE_DOCKER_WEB_PORT = "8000"
```

Y después hay que volver a copiar el ajuste de rendimiento, porque vive dentro del contenedor y se pierde:

```powershell
docker cp C:\dev\samce-entorno-moodle\scripts\opcache-samce.ini moodle-docker-webserver-1:/usr/local/etc/php/conf.d/zz-samce.ini
docker restart moodle-docker-webserver-1
```

Luego se repiten los pasos 1 y 2 del README principal.

## Desarrollo del complemento

El complemento se desarrolla en su propio repositorio y se vincula al entorno con un enlace simbólico o una copia dentro de `C:\dev\samce\moodle\local\`. Después hay que registrarlo:

```powershell
docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/upgrade.php --non-interactive
```

Los cambios en los archivos se reflejan de inmediato, sin reiniciar nada.

**Para depurar conviene reactivar el modo de desarrollador** en `config.php`, asumiendo que el sitio se pondrá lento mientras dure, y volver a dejarlo en cero al terminar. El motivo está en [02-rendimiento.md](02-rendimiento.md).
