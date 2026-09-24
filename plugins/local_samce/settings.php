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

    $settings->add(new admin_setting_configtext(
        'local_samce/backendurl',
        get_string('backendurl', 'local_samce'),
        get_string('backendurl_desc', 'local_samce'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_samce/capture_enabled',
        get_string('captureenabled', 'local_samce'),
        get_string('captureenabled_desc', 'local_samce'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_samce/restrict_browser',
        get_string('restrictbrowser', 'local_samce'),
        get_string('restrictbrowser_desc', 'local_samce'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_samce/controllercontact',
        get_string('controllercontact', 'local_samce'),
        get_string('controllercontact_desc', 'local_samce'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_samce/retentionnotice',
        get_string('retentionnotice', 'local_samce'),
        get_string('retentionnotice_desc', 'local_samce'),
        '',
        PARAM_TEXT
    ));
}
