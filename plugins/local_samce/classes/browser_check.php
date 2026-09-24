<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Decide si el navegador del alumno es uno con el que se puede rendir un examen
 * monitoreado: Google Chrome en una computadora (escritorio o notebook).
 *
 * Se decide con el User-Agent que llega al servidor. No depende de ninguna API
 * de Moodle, para poder probarla sin bootstrapear un Moodle (igual que
 * event_batch y token_signer).
 *
 * Límites, que conviene tener presentes: el User-Agent lo puede falsear quien
 * quiera, así que esto ordena el uso normal y no es un control a prueba de
 * trampas; Brave y otros navegadores basados en Chromium se identifican como
 * Chrome y no se pueden distinguir de forma fiable; y Chrome de escritorio en
 * una tablet con "sitio de escritorio" activado también pasa.
 *
 * @package local_samce
 */
class browser_check {

    /** Navegadores que dicen "Chrome" en el User-Agent pero no lo son. */
    private const NOT_CHROME = '~(?:Edg|EdgA|EdgiOS|Edge|OPR|OPiOS|Opera|Vivaldi|YaBrowser|SamsungBrowser|UCBrowser|CriOS|FxiOS|Firefox|DuckDuckGo)/~i';

    /** Marcas de teléfonos y tablets. */
    private const NOT_DESKTOP = '~Mobile|Android|iPhone|iPad|iPod|Tablet|Silk|Windows Phone~i';

    /**
     * @param string $useragent El User-Agent de la request.
     * @return bool true si es Chrome de escritorio.
     */
    public static function is_supported(string $useragent): bool {
        if ($useragent === '' || !preg_match('~\bChrome/\d+~', $useragent)) {
            return false;
        }
        if (preg_match(self::NOT_CHROME, $useragent)) {
            return false;
        }
        return !preg_match(self::NOT_DESKTOP, $useragent);
    }
}
