# réplica del Campus Virtual: estado al cierre del Sprint 3

La réplica sigue siendo el lugar donde se rinde para ver el análisis: el complemento 0.3.28 corre en el Moodle de Railway (`plugins/local_samce`) y, en la PC donde se armó la réplica local, la cadena completa (MySQL, backend, motor, Moodle y panel).

- **Complemento 0.3.28.** El único arreglo del sprint en el complemento: cada cambio de página dejaba un `visibility_hidden` huérfano, porque el navegador dispara `pagehide` antes que `visibilitychange` (PR #33).
- **La cadena completa en local.** El README explica las cinco piezas con sus puertos (MySQL, backend en :8080, motor en :8001, Moodle en :8000 y panel en :5173), las tres causas mudas de que no se capture nada y el origen de cada copia del complemento (PR #34).
- **Cuestionario para el rendidor.** `scripts/crear_parcial_local.php` y `scripts/parcial_railway.json` crean el «Segundo Parcial (réplica del campus)» y los alumnos `rend01` a `rend06` (PR #34).
- **README del complemento al día.** `plugins/local_samce/README.md` era del 20/09 y ahora es el del original (PR #34).

**Lo que quedaba abierto en ese momento:** la comprobación del README por parte de quien levanta la réplica y los casos de prueba CP08 a CP26 sobre la réplica. Las dos cosas se completaron después, como se detalla en «Completado después de este cierre».

## Pull requests del sprint

- #33: complemento local_samce 0.3.28: el visibility_hidden de la página que se descarga ya no queda huérfano (2026-10-22)
- #34: TT_S4_03: la cadena completa en local en el README de la réplica
- #35: docs: réplica del Campus Virtual, estado al cierre del Sprint 3

## Completado después de este cierre

Después del #35 solo entró el #36, que deja este archivo con el estado final del sprint. Lo que siguió se hizo sobre la réplica, sin cambios en su código:

- **Los casos de prueba, ejecutados sobre la réplica local.** CP08 a CP20 como regresión de los sprints anteriores, CP21 a CP26 del análisis de cada sesión (HU06) y las cuatro pruebas de integración 6.06 (motor detenido, permisos, privacidad y recorrido completo), con el «Segundo Parcial (réplica del campus)» y los alumnos `rend01` a `rend06`. Todos quedaron aprobados.
- **La cadena completa, levantada con el README.** Las cinco piezas se pusieron en marcha en la PC donde se armó la réplica para ejecutar esos casos.
- **Los cuestionarios, comprobados.** Los cmid de la tabla del README coinciden con los de la réplica local: 3, 4, 7, 8, 10, 11 y 12.
- **El espejo del complemento, comparado.** El código de `plugins/local_samce` coincide con el `main` del complemento, en la versión 0.3.28 (`2026102201`), que es también la instalada en la réplica local. El `diff` del README solo muestra el panorama del sprint del propio complemento, que es documentación y no se instala.
- **El README, al día.** Suma lo que se probó sobre la réplica, la relación con el rendidor y los scripts del parcial local en «Estructura» (TT_S3_03 y TT_S4_03).

## Estado final

Moodle 4.5 sobre contenedores, en local y en Railway, con el complemento `local_samce` 0.3.28 (`2026102201`) igual al original. En local, la réplica es la pieza de Moodle de la cadena completa (MySQL, backend, motor, Moodle y panel) y el lugar donde se rinden las sesiones con `rend01` a `rend06`. En Railway, es donde el rendidor rindió las sesiones que sirven para entrenar y validar el modelo. El entorno no tiene tareas propias en el sprint: publica el complemento y es donde se rindieron las sesiones y los casos de prueba.
