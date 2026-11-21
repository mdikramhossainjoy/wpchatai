<?php
/**
 * WPChat Clean Uninstallation Handler
 *
 * Runs only when user clicks "Delete" in the WordPress Plugins administration interface.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Delete all custom database tables
$tables = [
    $wpdb->prefix . 'wpchat_messages',
    $wpdb->prefix . 'wpchat_sessions',
    $wpdb->prefix . 'wpchat_audit_log',
    $wpdb->prefix . 'wpchat_snippets',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// 2. Delete all options
$options = [
    'wpchat_api_key',
    'wpchat_gemini_api_key',
    'wpchat_openrouter_api_key',
    'wpchat_nvidia_api_key',
    'wpchat_custom_api_key',
    'wpchat_custom_base_url',
    'wpchat_ai_provider',
    'wpchat_approval_mode',
    'wpchat_gemini_model',
    'wpchat_openrouter_model',
    'wpchat_nvidia_model',
    'wpchat_custom_model',
    'wpchat_killswitch',
    'wpchat_blocked_tools',
    'wpchat_allowed_role',
    'wpchat_site_context_cache',
    'wpchat_snapshot_index',
    'wpchat_db_version',
];

foreach ( $options as $opt ) {
    delete_option( $opt );
    delete_site_option( $opt );
}

// 3. Delete transients
delete_transient( 'wpchat_site_context' );
delete_transient( 'wpchat_pending_tokens' );
delete_transient( 'wpchat_active_snippets_cache' );
