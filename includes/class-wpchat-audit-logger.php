<?php
/**
 * Audit Logger & Undo / Rollback Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_Audit_Logger {

    /**
     * Capture pre-mutation state of a post
     */
    public static function capture_post_state( int $post_id ): ?array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return null;
        }

        return [
            'type'         => 'post',
            'post_id'      => $post->ID,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_status'  => $post->post_status,
            'post_excerpt' => $post->post_excerpt,
        ];
    }

    /**
     * Capture pre-mutation state of an option
     */
    public static function capture_option_state( string $option_name ): array {
        return [
            'type'         => 'option',
            'option_name'  => $option_name,
            'option_value' => get_option( $option_name ),
        ];
    }

    /**
     * Log executed action
     */
    public static function log( int $session_id, int $user_id, string $tool_name, array $tool_args, ?array $previous_state, string $status = 'executed' ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_audit_log';

        $wpdb->insert(
            $table,
            [
                'session_id'     => $session_id,
                'user_id'        => $user_id,
                'tool_name'      => $tool_name,
                'tool_args'      => wp_json_encode( $tool_args ),
                'previous_state' => $previous_state ? wp_json_encode( $previous_state ) : null,
                'status'         => $status,
                'created_at'     => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Rollback / Undo an action by audit ID
     */
    public static function undo( int $audit_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_audit_log';

        $entry = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $audit_id ),
            ARRAY_A
        );

        if ( ! $entry ) {
            return [ 'success' => false, 'message' => 'Audit log entry not found.' ];
        }

        if ( $entry['status'] === 'undone' ) {
            return [ 'success' => false, 'message' => 'This action was already undone.' ];
        }

        $tool_name = $entry['tool_name'];
        $args      = json_decode( $entry['tool_args'], true ) ?: [];
        $prev      = ! empty( $entry['previous_state'] ) ? json_decode( $entry['previous_state'], true ) : null;

        $undone = false;
        $details = '';

        switch ( $tool_name ) {
            case 'wp_create_post':
                // Undoing a creation means trashing it
                $created_id = (int) ( $args['created_post_id'] ?? 0 );
                if ( $created_id > 0 ) {
                    wp_trash_post( $created_id );
                    $undone = true;
                    $details = "Post #{$created_id} moved to trash.";
                }
                break;

            case 'wp_update_post':
                // Undoing an update means restoring prior values
                if ( $prev && isset( $prev['post_id'] ) ) {
                    wp_update_post( [
                        'ID'           => $prev['post_id'],
                        'post_title'   => $prev['post_title'],
                        'post_content' => $prev['post_content'],
                        'post_status'  => $prev['post_status'],
                        'post_excerpt' => $prev['post_excerpt'],
                    ] );
                    $undone = true;
                    $details = "Post #{$prev['post_id']} restored to previous state.";
                }
                break;

            case 'wp_delete_post':
                // Undoing a delete means untrashing
                if ( ! empty( $args['force_delete'] ) ) {
                    return [ 'success' => false, 'message' => 'Permanently deleted posts cannot be restored via undo.' ];
                }
                $deleted_id = (int) ( $args['post_id'] ?? 0 );
                if ( $deleted_id > 0 ) {
                    wp_untrash_post( $deleted_id );
                    $undone = true;
                    $details = "Post #{$deleted_id} restored from trash.";
                }
                break;

            case 'wp_update_option':
                // Restoring prior option
                if ( $prev && isset( $prev['option_name'] ) ) {
                    if ( false === $prev['option_value'] ) {
                        delete_option( $prev['option_name'] );
                    } else {
                        update_option( $prev['option_name'], $prev['option_value'] );
                    }
                    $undone = true;
                    $details = "Option '{$prev['option_name']}' restored to previous value.";
                }
                break;

            case 'wp_manage_plugins':
            case 'wp_toggle_plugin':
                // Revert plugin action
                $file   = $args['plugin_file'] ?? ( $args['slug'] ?? '' );
                $action = $args['action'] ?? '';
                if ( $file ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                    if ( 'activate' === $action ) {
                        deactivate_plugins( $file );
                        $details = "Deactivated plugin {$file}.";
                        $undone  = true;
                    } elseif ( 'deactivate' === $action ) {
                        activate_plugin( $file );
                        $details = "Re-activated plugin {$file}.";
                        $undone  = true;
                    } elseif ( 'install' === $action ) {
                        deactivate_plugins( $file );
                        $details = "Deactivated installed plugin {$file}.";
                        $undone  = true;
                    }
                }
                break;

            case 'wp_create_code_snippet':
            case 'wp_inject_header_footer':
                $snippet_id = (int) ( $args['snippet_id'] ?? 0 );
                if ( $snippet_id > 0 ) {
                    WPChat_Code_Engine::delete_snippet( $snippet_id );
                    $undone  = true;
                    $details = "Code snippet #{$snippet_id} removed.";
                }
                break;

            case 'wp_inject_custom_css':
                if ( $prev && isset( $prev['previous_css'] ) ) {
                    wp_update_custom_css_post( $prev['previous_css'] );
                    $undone  = true;
                    $details = 'Custom CSS reverted to previous state.';
                }
                break;

            case 'wp_toggle_code_snippet':
                $snippet_id = (int) ( $args['snippet_id'] ?? 0 );
                $action     = $args['action'] ?? '';
                if ( $snippet_id > 0 ) {
                    $new_state = ( $action === 'activate' ) ? false : true;
                    WPChat_Code_Engine::toggle_snippet( $snippet_id, $new_state );
                    $undone  = true;
                    $details = "Snippet #{$snippet_id} reverted to " . ( $new_state ? 'active' : 'inactive' );
                }
                break;
        }

        if ( $undone ) {
            $wpdb->update(
                $table,
                [ 'status' => 'undone' ],
                [ 'id' => $audit_id ],
                [ '%s' ],
                [ '%d' ]
            );
            return [ 'success' => true, 'message' => $details ];
        }

        return [ 'success' => false, 'message' => 'Unable to automatically revert this action.' ];
    }

    /**
     * Export all audit logs to CSV
     */
    public static function export_csv(): string {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_audit_log';
        $rows = $wpdb->get_results( "SELECT id, session_id, user_id, tool_name, tool_args, status, created_at FROM {$table} ORDER BY created_at DESC LIMIT 5000", ARRAY_A );

        $output = fopen( 'php://temp', 'r+' );
        fputcsv( $output, [ 'ID', 'Session ID', 'User ID', 'Tool Name', 'Arguments', 'Status', 'Date' ] );

        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                fputcsv( $output, [
                    $row['id'],
                    $row['session_id'],
                    $row['user_id'],
                    $row['tool_name'],
                    $row['tool_args'],
                    $row['status'],
                    $row['created_at'],
                ] );
            }
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv ?: '';
    }
}
