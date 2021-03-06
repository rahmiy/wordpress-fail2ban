<?php
/**
 * Plugin Name: WordPress-Fail2ban option table scan (MU)
 * Version: 0.1.6
 * Description: Find <code>&lt;script&gt;</code> and <code>&lt;iframe&gt;</code> tags in wp_options table and alert.
 * Plugin URI: https://github.com/szepeviktor/wordpress-fail2ban
 * License: The MIT License (MIT)
 * Author: Viktor Szépe
 * GitHub Plugin URI: https://github.com/szepeviktor/wordpress-fail2ban
 */


/* This is an IDEA for a new feature */

add_action( 'plugins_loaded', 'wpf2b_option_scan_schedule' );
add_action( 'wpf2b/daily', 'wpf2b_option_scan' );

function wpf2b_option_scan_schedule() {

    if ( false === wp_get_schedule( 'wpf2b/daily' ) ) {
        wp_schedule_event( time(), 'daily', 'wpf2b/daily' );
    }
}

function wpf2b_option_scan() {

    global $wpdb;

    define( 'MAIL_EOL', "\r\n" );

    $injection_exp = '<script|<iframe';
    $options       = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value REGEXP %s",
            $injection_exp
        )
    ); // WPCS: cache ok, db call ok.

    $to           = get_option( 'admin_email' );
    $subject      = '[admin] Malicious code found in WordPress options';
    $headers      = array( 'Content-Type: text/plain; charset=UTF-8' );
    $body         = sprintf(
        '%s has malicious code in `%s` database table:%s',
        get_option( 'blogname' ),
        $wpdb->options,
        MAIL_EOL
    );
    $option_names = array();
    foreach ( $options as $option ) {
        $body .= sprintf(
            'Option name: %s%s',
            $option->option_name,
            MAIL_EOL
        );
        // phpcs:set WordPress.PHP.DevelopmentFunctions exclude error_log
        error_log( 'Malicious code in wp_options: ' . $option->option_name );
        $option_names[] = $option->option_name;
    }

    $mail = wp_mail( $to, $subject, $body, $headers );
    if ( false === $mail ) {
        // phpcs:set WordPress.PHP.DevelopmentFunctions exclude error_log
        error_log(
            sprintf(
                'E-mail sending error: Malicious code in `%s`: %s',
                $wpdb->options,
                implode( ',', $option_names )
            )
        );
    }
}
