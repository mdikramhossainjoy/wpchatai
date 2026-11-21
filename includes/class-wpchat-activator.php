<?php
/**
 * Plugin Activator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_Activator {

    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // 1. Sessions table
        $table_sessions = $wpdb->prefix . 'wpchat_sessions';
        $sql_sessions = "CREATE TABLE {$table_sessions} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            title VARCHAR(255) DEFAULT 'New Chat',
            mode ENUM('always_ask', 'copilot', 'turbo') DEFAULT 'copilot',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY user_session (user_id, created_at)
        ) {$charset_collate};";
        dbDelta( $sql_sessions );

        // 2. Messages table
        $table_messages = $wpdb->prefix . 'wpchat_messages';
        $sql_messages = "CREATE TABLE {$table_messages} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT(20) UNSIGNED NOT NULL,
            role ENUM('user', 'model', 'tool') NOT NULL,
            content LONGTEXT NOT NULL,
            tool_call_data LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) {$charset_collate};";
        dbDelta( $sql_messages );

        // 3. Audit log table
        $table_audit = $wpdb->prefix . 'wpchat_audit_log';
        $sql_audit = "CREATE TABLE {$table_audit} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            tool_name VARCHAR(100) NOT NULL,
            tool_args LONGTEXT NOT NULL,
            previous_state LONGTEXT NULL,
            status ENUM('executed', 'undone', 'rejected') NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta( $sql_audit );

        // 4. Code snippets table
        require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-code-engine.php';
        $sql_snippets = WPChat_Code_Engine::get_table_schema( $charset_collate );
        dbDelta( $sql_snippets );

        // Set default options if not exists
        if ( false === get_option( 'wpchat_approval_mode' ) ) {
            add_option( 'wpchat_approval_mode', 'copilot' );
        }
        if ( false === get_option( 'wpchat_gemini_model' ) ) {
            add_option( 'wpchat_gemini_model', 'gemini-flash-latest' );
        }
        if ( false === get_option( 'wpchat_openrouter_model' ) || 'anthropic/claude-3.5-sonnet' === get_option( 'wpchat_openrouter_model' ) ) {
            update_option( 'wpchat_openrouter_model', 'openrouter/free' );
        }
        if ( false === get_option( 'wpchat_nvidia_model' ) || 'meta/llama-3.3-70b-instruct' === get_option( 'wpchat_nvidia_model' ) || 'meta/llama-3.1-405b-instruct' === get_option( 'wpchat_nvidia_model' ) ) {
            update_option( 'wpchat_nvidia_model', 'nvidia/nemotron-3.5-lightning-30b-a3b' );
        }
    }

    public static function deactivate(): void {
        // Clean up transient tokens if needed
    }
}
