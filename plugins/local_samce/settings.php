<?php
/**
 * Configuración de local_samce en Site Administration > Plugins > Local plugins.
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_samce', get_string('pluginname', 'local_samce'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configpasswordunmask(
        'local_samce/launchsecret',
        get_string('launchsecret', 'local_samce'),
        get_string('launchsecret_desc', 'local_samce'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_samce/panelurl',
        get_string('panelurl', 'local_samce'),
        get_string('panelurl_desc', 'local_samce'),
        'https://samce-teacher-dashboard.vercel.app/auth/callback',
        PARAM_URL
    ));
}
