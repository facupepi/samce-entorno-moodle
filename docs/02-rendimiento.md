# 2. Rendimiento

El entorno responde entre 0,7 y 0,9 segundos por página. Si alguna vez vuelve a demorar quince segundos o más, la causa casi siempre es la misma.

## La causa

El `config.php` que trae `moodle-docker` activa el modo desarrollador:

```php
$CFG->debug = (E_ALL);   // DEBUG_DEVELOPER
$CFG->debugdisplay = 1;
$CFG->debugstringids = 1;
$CFG->perfdebug = 15;
$CFG->debugpageinfo = 1;
```

En ese modo Moodle recorre el árbol completo de complementos **en cada petición**. Como el código está montado desde el disco del anfitrión, cada operación de directorio cuesta cerca de dos milisegundos: recorrer tres mil archivos tarda 5,6 segundos medidos. Ese recorrido, multiplicado por el árbol entero, daba los quince segundos.

Con las cinco opciones en cero, el mismo entorno responde en menos de un segundo. La mejora es de unas veinticinco veces.

El script `0_instalar_entorno.ps1` ya deja esos valores en cero.

## Cómo distinguir un problema de aplicación de uno de infraestructura

```powershell
# Archivo estático real: debe dar centésimas de segundo
docker exec moodle-docker-webserver-1 curl -s -o /dev/null -w "%{time_total}\n" http://127.0.0.1/lib/editor/tiny/version.php

# Página de Moodle: debe dar menos de un segundo
curl.exe -s -L -o NUL -w "%{time_total}\n" http://localhost:8000/login/index.php
```

Si el estático es rápido y la página es lenta, el problema está en la aplicación, y lo primero que hay que mirar es `$CFG->debug`. Si el estático también es lento, el problema es de infraestructura.

## La trampa que costó horas

El diagnóstico original arrancó midiendo `favicon.ico`, con el razonamiento de que un archivo estático no ejecuta PHP y sirve para aislar la aplicación de la infraestructura. Tardaba veintiún segundos con el procesador al 0,01 %, lo que llevó a **descartar PHP como culpable** y perseguir a Apache durante varias rondas.

El razonamiento estaba bien; el dato estaba mal. **`/var/www/html/favicon.ico` no existe** en Moodle, y `conf-enabled/fallback.conf` desvía todo lo inexistente a `r.php`. Es decir que el supuesto archivo estático estaba ejecutando el arranque completo de Moodle.

Hipótesis falsas que se investigaron por culpa de eso: los registros escribiendo hacia la salida del contenedor, el mecanismo de exclusión mutua de Apache, el módulo de tiempo de espera de peticiones, el puerto 80 y su reenvío, y el directorio raíz sobre el disco montado. Todas inocentes.

**Antes de medir un archivo como "estático", verificar que exista.** Uno real de Moodle sirve, por ejemplo `/lib/editor/tiny/version.php`.

## Qué está descartado con mediciones

No hace falta volver a revisar nada de esto:

| Componente | Medición |
|---|---|
| MySQL (conexión y consulta) | 0,002 s |
| Directorio de datos | 0,001 s |
| Acelerador de código PHP | 1011 scripts en caché, 76 % de aciertos, cero reinicios |
| Archivo estático real | 0,01 a 0,10 s |
| PHP trivial | 0,003 s |
| Salidas a internet | 0,14 s |
| **Arranque de Moodle** | **15,2 s — todo el tiempo estaba acá** |

## Un detalle secundario

Windows resuelve `localhost` primero por IPv6, donde Docker no publica el puerto, así que cada conexión nueva paga el reintento hacia IPv4. Medido: 2,05 s contra `localhost` y 0,017 s contra `127.0.0.1` con clientes que no implementan el mecanismo de conexión rápida; unos 0,2 s en el navegador, que además reutiliza la conexión.

No amerita cambiar nada, porque la dirección del sitio está fijada en `localhost:8000` y entrar por IP solo genera una redirección. Pero explica por qué un script de línea de comandos puede parecer mucho más lento que el navegador contra el mismo sitio.

## Después de purgar cachés

La primera carga tarda entre veinte y treinta segundos mientras se regenera la hoja de estilos del tema, que ronda el megabyte. Es normal y se normaliza en la segunda. Conviene forzarla una vez después de cualquier cambio de configuración del tema:

```powershell
curl.exe -s -o NUL -w "%{time_total} s`n" -m 300 http://localhost:8000/
```
