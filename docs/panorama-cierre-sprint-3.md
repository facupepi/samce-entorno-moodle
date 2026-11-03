# réplica del Campus Virtual: estado al cierre del Sprint 3

La réplica sigue siendo el lugar donde se rinde para ver el análisis: el complemento 0.3.28 corre en el Moodle de Railway (`plugins/local_samce`) y, en la PC donde se armó la réplica local, la cadena completa (MySQL, backend, motor, Moodle y panel).

- **Complemento 0.3.28.** El único arreglo del sprint en el complemento: cada cambio de página dejaba un `visibility_hidden` huérfano, porque el navegador dispara `pagehide` antes que `visibilitychange` (PR #33).
- **La cadena completa en local.** El README explica las cinco piezas con sus puertos (MySQL, backend en :8080, motor en :8001, Moodle en :8000 y panel en :5173), las tres causas mudas de que no se capture nada y el origen de cada copia del complemento (PR #34).
- **Cuestionario para el rendidor.** `scripts/crear_parcial_local.php` y `scripts/parcial_railway.json` crean el «Segundo Parcial (réplica del campus)» y los alumnos `rend01` a `rend06` (PR #34).
- **README del complemento al día.** `plugins/local_samce/README.md` era del 20/09 y ahora es el del original (PR #34).

**Lo que queda abierto:** la comprobación del README por parte de quien levanta la réplica y los casos de prueba CP08 a CP26 sobre la réplica, que corre el equipo.

## Pull requests del sprint

- #33: complemento local_samce 0.3.28: el visibility_hidden de la página que se descarga ya no queda huérfano (2026-10-22)
- #34: TT_S4_03: la cadena completa en local en el README de la réplica
