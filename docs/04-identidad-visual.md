# 4. Identidad visual

El entorno reproduce el aspecto del Campus Virtual. La configuración se obtuvo comparando la hoja de estilos y el HTML que genera cada sitio, de modo que cada valor está verificado contra el original.

## Lo que ya coincidía

La paleta del campus resultó ser la que Adaptable trae por defecto, así que no hubo nada que tocar en los colores principales: enlaces y botón primario en `#00796b`, botón secundario en `#009688`, botón de información en `#008196`, fondo blanco y texto en `#333`.

## Lo que hubo que ajustar

| Elemento | Valor | Ajuste que lo controla |
|---|---|---|
| Franja superior del encabezado | `#008cb2` | `headertoprowbkcolour` |
| Franja del logotipo | `#F2F2F0` | `headermainrowbkcolour` |
| Borde de la barra de navegación | `#008cb2` | `menubordercolor` |
| Contorno de los bloques | `#008cb2` | `blockbordercolor` |
| Fondo del pie | `#114C5E` | `footerbkcolor` |
| Cabecera de los bloques | `#e0f1f5` | `blockheaderbackgroundcolor` |
| Botón de acceso | `#ef5350` | `buttonlogincolor` |
| Tipografía general | Open Sans | `fontname` |
| Tipografía de títulos | Roboto | `fontheadername` |
| Título del encabezado | Roboto Condensed | `fonttitlename` |

## El encabezado tiene dos filas

Este punto se presta a confusión. La franja superior, donde están el selector de idioma y el botón de acceso, es celeste. La de abajo, donde va el logotipo, es gris muy claro y se controla con **otro ajuste**. Si se cambia solo la primera, la segunda queda en el verde por defecto del tema y el resultado no se parece al campus.

El texto de la segunda fila es blanco en el campus, lo cual deja el icono de búsqueda apenas visible sobre el fondo claro. Se replicó igual, porque el objetivo es la fidelidad.

## Dos logotipos distintos

| Dónde | Archivo | Área interna |
|---|---|---|
| Encabezado | `logo-utn-siglas.png.png` (a color) | `theme_adaptable/logo` |
| Pie de página | `Logo_Blanco.png` | `theme_adaptable/adaptablemarkettingimages` |

**No confundirlos**: sobre la franja gris del encabezado, el logotipo blanco resulta invisible.

## Cómo encontrar el ajuste que genera cada regla

Los nombres no son evidentes y adivinarlos no funciona: los dos primeros que se probaron por intuición (`headerbkcolor` y `headerbkcolor2`) **ni siquiera existen** en esta versión del tema, se aplicaron sin efecto alguno.

El método que sí sirve es buscar en el código del tema la regla que produce el color y leer el nombre del ajuste entre dobles corchetes:

```powershell
docker exec moodle-docker-webserver-1 sh -c "grep -rn 'background-color' /var/www/html/theme/adaptable/scss/settings/*.scss | head -20"
```

Para los colores que el tema expone como variables, el archivo a mirar es `theme/adaptable/classes/toolbox.php`.

## Las tipografías se cargan por HTML

El campus incorpora Montserrat y Roboto mediante una etiqueta en la cabecera del sitio, **no a través de los ajustes del tema**. Si se configuraran por el tema, se cargarían otras fuentes. Se replicó de la misma manera, agregando ese enlace en la configuración de contenido adicional de la cabecera.

## La portada

| Zona | Contenido |
|---|---|
| Carrusel | Tres imágenes institucionales |
| Región central de la primera fila | Bloque de acceso, centrado |
| Región de ancho completo | Franja de consultas por WhatsApp, centrada |
| Debajo | Listado de horarios por carrera |

**La lista de cursos está desactivada**, igual que en el campus. Al curso de pruebas se llega por el menú tras iniciar sesión, o por su dirección directa.

### Un bloque puede estar en el HTML y no verse

Por defecto Adaptable solo declara la región lateral en la portada, de modo que cualquier bloque que se agregue termina dentro del panel lateral colapsable y no aparece en la página, **aunque figure en el código fuente**. Las regiones del cuerpo se habilitan con el ajuste `frontpageblocksenabled`, y recién entonces existen las diez posiciones que el campus utiliza.

La lección general: comprobar que un elemento aparece en el código fuente no prueba que se vea. Hay que verificar además en qué contenedor cayó.

### Centrado

La primera fila se reparte en tres columnas, de modo que la del medio ocupa el tercio central y el bloque de acceso queda centrado. Con cuatro columnas, la posición central de esa disposición no cae en el medio y el bloque se ve corrido.

## Aplicación

```powershell
docker cp ..\imagenes moodle-docker-webserver-1:/tmp/imagenes_cvg
docker cp .\2_aplicar_identidad.php moodle-docker-webserver-1:/tmp/
docker exec moodle-docker-webserver-1 php /tmp/2_aplicar_identidad.php
```

Los tres comandos van en ese orden: el script necesita las imágenes ya copiadas.

## Cuidado al cargar imágenes por código

La función de Moodle que borra archivos de un área acepta como último parámetro **una ruta, no una condición**. Pasarle algo parecido a un filtro no da error: vacía el área completa. Eso dejó en su momento el logotipo del pie sin archivo y devolviendo un error de página no encontrada, pese a que el ajuste apuntaba correctamente.

La forma segura es obtener el archivo puntual y borrar solo ese. Y conviene comprobar después que cada imagen **responda**, no solo que su dirección aparezca en la página:

```powershell
curl.exe -s -o NUL -w "%{http_code} %{size_download} bytes`n" http://localhost:8000/pluginfile.php/1/theme_adaptable/adaptablemarkettingimages/0/Logo_Blanco.png
```

Debe dar `200` y un tamaño mayor que cero.
