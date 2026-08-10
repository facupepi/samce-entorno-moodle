# 3. Complementos

El entorno replica los complementos de terceros que tiene instalados el Campus Virtual, para que el listado de actividades que ve un docente coincida con el del campus.

## Los siete

| Complemento | Nombre | Estado en el local |
|---|---|---|
| `mod_attendance` | Asistencia | funcional |
| `mod_customcert` | Certificado personalizado | funcional |
| `mod_hsuforum` | Open Forum | funcional |
| `mod_hotpot` | Hot Potatoes | funcional |
| `mod_zoom` | Reunión Zoom | instalado, **inerte** |
| `filter_wiris` | MathType por WIRIS | instalado, **sin activar** |
| `atto_wiris` | MathType para el editor Atto | instalado, **sin activar** |

Con sus subcomplementos suman treinta y ocho: el certificado aporta diecinueve tipos de elemento y Hot Potatoes once entre formatos, orígenes e informes.

## Dos quedan instalados pero sin funcionar

**Zoom** exige credenciales de una cuenta corporativa o educativa de pago. La actividad aparece en el listado, pero cualquier intento de crear una reunión falla. Además, como el entorno local no es alcanzable desde internet, tampoco recibiría los avisos que el servicio envía de vuelta.

**MathType** depende de un servicio externo de representación de fórmulas. Queda disponible y se activa desde la administración de filtros cuando se lo necesite.

Se instalaron igual porque el objetivo es que el entorno coincida con el campus. Si molestan, se desinstalan desde la administración de complementos.

## Una advertencia sobre Open Forum

**Quien lo mantiene ya anunció su discontinuación**, con el argumento de que sus funciones convergieron con las del foro que trae Moodle. Está porque el campus lo tiene, pero **no conviene construir nada nuevo encima**.

## Cómo se detectaron

Vale la pena documentarlo, porque el método sirve para volver a relevar el campus más adelante y porque dos técnicas obvias no funcionan.

**La fuente que sirvió** fue el paquete de módulos JavaScript que Moodle publica bajo `/lib/requirejs.php/`. Con la opción de argumentos por barra activada devuelve el paquete completo del sitio, y las declaraciones que contiene son un inventario de todo complemento que embarque JavaScript: ciento veintitrés componentes en un solo pedido.

**Primera trampa: el CSS del tema no prueba nada.** Adaptable trae reglas preventivas para complementos de terceros que pueden no estar instalados. De los candidatos que surgieron del CSS, diez resultaron inexistentes. Un tema aparece cincuenta y una veces en la hoja de estilos del campus y no está instalado.

**Segunda trampa: el paquete de módulos sirve como inventario pero jamás como sonda.** Cualquier ruta bajo `/lib/requirejs.php/` devuelve código 200 y exactamente el mismo cuerpo, incluso para complementos inventados. Usarlo para comprobar existencia da cien por ciento de falsos positivos.

**El método fiable** es pedir un archivo propio del complemento, como `version.php` o `styles.css`, y leer el código de respuesta, siempre acompañado de un control negativo con un nombre inventado que debe dar 404. Sin ese control no se sabe si el método discrimina.

**El mejor control de todos es el propio Moodle local.** Como es una instalación limpia de la misma versión, todo componente que ya exista ahí pertenece al núcleo y no hay que instalarlo. Ese cruce descartó de un saque seis candidatos que el barrido había marcado: la revisión de accesibilidad, BigBlueButton, el resaltador de código, el factor de autenticación por dispositivo, un tipo de pregunta y un complemento del editor. Todos vienen con Moodle 4.5.

## Volver a relevar el campus

Si alguna vez hace falta actualizar la lista, **alcanza con dos peticiones**: el paquete de módulos y la hoja de estilos del tema. El relevamiento original usó muchas más de las necesarias contra un sitio de producción de la Facultad, y no hay motivo para repetirlo así.

## Instalación

```powershell
.\1_instalar_plugins.ps1
```

Descarga los siete desde sus repositorios oficiales, los ubica con el nombre de carpeta correcto, ejecuta la actualización de Moodle y verifica. Antes de tocar nada crea una copia de seguridad de la base.

**El nombre de la carpeta es crítico.** Los paquetes de GitHub se descomprimen con nombres largos y Moodle deriva el nombre del componente del nombre del directorio: la carpeta tiene que llamarse `attendance`, no `moodle-mod_attendance-MOODLE_405_STABLE`. El script ya se ocupa de renombrarlas.

Las ramas que corresponden a Moodle 4.5 no siempre son las que parece. El certificado personalizado, por ejemplo, no tiene rama para 4.5: hay que usar la de 4.4, que declara compatibilidad con ambas. Las ramas correctas están en el script.
