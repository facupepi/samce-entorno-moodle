# 5. Problemas conocidos

Cada punto de esta lista hizo fallar algo de verdad. Conviene leerla antes de tocar el entorno, y consultarla apenas algo no funcione.

## Del entorno

**El prefijo de las tablas es `m_`, no `mdl_`.** Es la configuración de `moodle-docker`. Toda consulta directa tiene que usar `m_user`, `m_quiz_attempts` y demás.

**Apache es el proceso principal del contenedor.** Cualquier `apache2ctl -k restart` o `-k stop` **mata el contenedor entero**. Para reiniciar Apache hay que usar `docker restart moodle-docker-webserver-1`. Esto tumbó el contenedor dos veces durante un diagnóstico.

**Los registros de Apache son enlaces a la salida estándar del contenedor.** Hacerles `tail` cuelga el proceso indefinidamente, porque son tuberías. Para ver los registros hay que usar `docker logs moodle-docker-webserver-1`.

**`/var/www/html/favicon.ico` no existe** y la configuración de Apache desvía todo lo inexistente a `r.php`. Consecuencia: pedir un archivo que no existe **ejecuta PHP**, no devuelve un error barato. Esto arruinó un diagnóstico entero, explicado en [02-rendimiento.md](02-rendimiento.md).

**El ajuste del acelerador de PHP vive dentro del contenedor** y se pierde si se recrea con `docker compose down`. Hay que volver a copiarlo.

## De Moodle

**La rama del tema Adaptable es `MOODLE_405`, no `MOODLE_405_STABLE`** (esta última no existe).

**Los scripts de línea de comandos que crean contenido necesitan sesión de administrador.** Sin establecerla explícitamente fallan por permisos.

**Para crear actividades por código hay que usar el generador de datos de prueba**, no las funciones de la API directamente: los módulos tienen campos obligatorios que no son evidentes.

**Para crear preguntas por código, el generador exige el marco de pruebas unitarias.** La vía que sí funciona fuera de él es importar un archivo XML, que es lo que hace `samce_preguntas.php`.

**El tema Adaptable emite avisos si dos ajustes de cuadros informativos no están definidos.** Se resuelve dejándolos como cadenas vacías.

**La función que borra archivos de un área acepta una ruta, no una condición.** Pasarle un filtro vacía el área completa sin dar error. Detalle en [04-identidad-visual.md](04-identidad-visual.md).

**Un bloque puede figurar en el código fuente y no verse**, si cayó en el panel lateral colapsable. Detalle en [04-identidad-visual.md](04-identidad-visual.md).

## Síntomas y qué mirar

| Síntoma | Causa probable |
|---|---|
| El sitio tarda 15 s o más por página | Modo de depuración activado. Ver [02-rendimiento.md](02-rendimiento.md) |
| La primera carga tras purgar cachés tarda 30 s | Normal: se regenera la hoja de estilos del tema |
| Una imagen no aparece | Comprobar que **responda**, no que su dirección esté en la página |
| Un bloque no se ve pero el verificador dice que está | Cayó en el panel lateral. Correr el paso 2 |
| Un complemento no se detecta | Nombre de carpeta incorrecto |
| Error de conexión al arrancar | Todavía está iniciando: tarda unos 11 segundos |
| El script de identidad aborta | Faltó copiar las imágenes al contenedor primero |

## Cuando algo no cuadra

Dos reglas que salieron de equivocarse:

**Verificar el dato antes que la hipótesis.** Medir un archivo "estático" que en realidad ejecutaba PHP llevó a descartar al verdadero culpable y perseguir a Apache durante horas. Antes de razonar sobre una medición, conviene confirmar que mide lo que uno cree.

**Que algo aparezca en el código fuente no prueba que funcione.** Vale para las imágenes, que pueden estar rotas, y para los bloques, que pueden estar en un contenedor invisible. La comprobación tiene que ser sobre el comportamiento, no sobre la presencia.
