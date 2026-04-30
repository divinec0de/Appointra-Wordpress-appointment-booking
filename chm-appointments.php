<?php
/**
 * Plugin Name: Appointra — Smart Booking System
 * Plugin URI:  https://github.com/divinec0de
 * Description: Beautiful appointment booking with date & time selection, per-service pricing, Stripe payments, and branded email notifications.
 * Version:     1.0.0
 * Author:      Nadir
 * Author URI:  https://github.com/divinec0de
 * License:     GPL-2.0+
 * Text Domain: appointra
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CHM_APPT_VERSION', '1.0.0' );
define( 'CHM_APPT_PATH',    plugin_dir_path( __FILE__ ) );
define( 'CHM_APPT_URL',     plugin_dir_url( __FILE__ ) );
define( 'CHM_APPT_TABLE',   'chm_appointments' );

/* ─── Includes ─── */
require_once CHM_APPT_PATH . 'includes/class-chm-db.php';
require_once CHM_APPT_PATH . 'includes/class-chm-admin.php';
require_once CHM_APPT_PATH . 'includes/class-chm-frontend.php';
require_once CHM_APPT_PATH . 'includes/class-chm-stripe.php';
require_once CHM_APPT_PATH . 'includes/class-chm-email.php';

/* ─── Activation ─── */
register_activation_hook( __FILE__, [ 'CHM_DB', 'activate' ] );

/* ─── Init ─── */
add_action( 'plugins_loaded', function () {
    CHM_Admin::init();
    CHM_Frontend::init();
});
