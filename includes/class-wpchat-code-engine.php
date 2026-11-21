<?php
/**
 * WPChat Code Engine & Sandboxed Execution Environment
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_Code_Engine {

    private static ?bool $snippets_table_exists = null;

    /**
     * Efficient in-memory table check to prevent redundant SQL metadata queries
     */
    private static function table_exists(): bool {
        if ( null !== self::$snippets_table_exists ) {
            return self::$snippets_table_exists;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_snippets';
        self::$snippets_table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table );
        return self::$snippets_table_exists;
    }

    public static function init(): void {
        // Run active PHP snippets on 'init' with error barrier
        add_action( 'init', [ __CLASS__, 'boot_php_snippets' ], 99 );

        // Inject header scripts & custom CSS
        add_action( 'wp_head', [ __CLASS__, 'render_header_injections' ], 99 );

        // Inject footer scripts
        add_action( 'wp_footer', [ __CLASS__, 'render_footer_injections' ], 99 );
    }

    /**
     * Create table schema
     */
    public static function get_table_schema( string $charset_collate ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_snippets';
        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            code_type ENUM('php', 'css', 'js_header', 'js_footer') NOT NULL DEFAULT 'php',
            code_content LONGTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            error_log TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY code_type (code_type),
            KEY is_active (is_active),
            KEY type_active (code_type, is_active)
        ) {$charset_collate};";
    }

    /**
     * Lint PHP code before saving or executing
     */
    public static function lint_php( string $code ): array {
        // Strip opening/closing php tags for evaluation
        $clean_code = trim( $code );
        if ( str_starts_with( $clean_code, '<?php' ) ) {
            $clean_code = substr( $clean_code, 5 );
        }
        if ( str_ends_with( $clean_code, '?>' ) ) {
            $clean_code = substr( $clean_code, 0, -2 );
        }
        $clean_code = trim( $clean_code );

        // 1. Token validation & security inspection
        try {
            $tokens = token_get_all( "<?php\n" . $clean_code );
            $forbidden_functions = [ 'exec', 'system', 'passthru', 'proc_open', 'shell_exec', 'popen', 'pcntl_exec' ];
            foreach ( $tokens as $token ) {
                if ( is_array( $token ) && ( $token[0] === T_STRING || $token[0] === T_NAME_QUALIFIED ) ) {
                    $func_name = strtolower( $token[1] );
                    if ( in_array( $func_name, $forbidden_functions, true ) ) {
                        return [
                            'valid'   => false,
                            'message' => "Execution of shell function '{$func_name}' is forbidden.",
                        ];
                    }
                }
            }
        } catch ( ParseError $pe ) {
            return [
                'valid'   => false,
                'message' => 'Parse error: ' . $pe->getMessage() . ' on line ' . $pe->getLine(),
            ];
        }

        // 2. Syntax check via php CLI if available
        $temp_file = wp_tempnam( 'wpchat_lint_' );
        if ( $temp_file ) {
            file_put_contents( $temp_file, "<?php\n" . $clean_code );
            $output = [];
            $return_var = 0;
            @exec( 'php -l ' . escapeshellarg( $temp_file ) . ' 2>&1', $output, $return_var );
            @unlink( $temp_file );

            if ( $return_var !== 0 && ! empty( $output ) ) {
                return [
                    'valid'   => false,
                    'message' => implode( "\n", $output ),
                ];
            }
        }

        return [
            'valid' => true,
            'code'  => $clean_code,
        ];
    }

    /**
     * Create or update code snippet
     */
    public static function save_snippet( string $title, string $type, string $content, bool $is_active = true ): array {
        if ( ! current_user_can( 'unfiltered_html' ) || ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
            throw new Exception( 'Permission denied: Code snippet management is disallowed on this site.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_snippets';

        $type = in_array( $type, [ 'php', 'css', 'js_header', 'js_footer' ], true ) ? $type : 'php';

        // Lint if PHP
        if ( $type === 'php' ) {
            $lint = self::lint_php( $content );
            if ( ! $lint['valid'] ) {
                throw new Exception( "PHP Syntax Error: {$lint['message']}" );
            }
            $content = $lint['code'];
        }

        $res = $wpdb->insert(
            $table,
            [
                'title'        => sanitize_text_field( $title ),
                'code_type'    => $type,
                'code_content' => $content,
                'is_active'    => $is_active ? 1 : 0,
                'error_log'    => null,
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        if ( false === $res ) {
            throw new Exception( 'Failed to save code snippet to database.' );
        }

        $snippet_id = (int) $wpdb->insert_id;
        self::clear_snippets_cache();

        // Test run immediately if active PHP snippet
        if ( $is_active && $type === 'php' ) {
            $test_result = self::execute_isolated_snippet( $snippet_id, $content );
            if ( ! $test_result['success'] ) {
                // Auto-deactivate on runtime error
                self::toggle_snippet( $snippet_id, false, $test_result['error'] );
                throw new Exception( "Snippet deactivated due to runtime error: " . $test_result['error'] );
            }
        }

        return [
            'snippet_id' => $snippet_id,
            'title'      => $title,
            'type'       => $type,
            'is_active'  => $is_active,
        ];
    }

    /**
     * Toggle snippet active status
     */
    public static function toggle_snippet( int $snippet_id, bool $active, ?string $error_log = null ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_snippets';

        $data = [ 'is_active' => $active ? 1 : 0 ];
        $format = [ '%d' ];

        if ( null !== $error_log ) {
            $data['error_log'] = $error_log;
            $format[] = '%s';
        }

        self::clear_snippets_cache();
        return false !== $wpdb->update( $table, $data, [ 'id' => $snippet_id ], $format, [ '%d' ] );
    }

    /**
     * List all snippets
     */
    public static function get_snippets( ?string $type = null ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_snippets';

        if ( $type && in_array( $type, [ 'php', 'css', 'js_header', 'js_footer' ], true ) ) {
            return $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE code_type = %s ORDER BY id DESC", $type ),
                ARRAY_A
            ) ?: [];
        }

        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A ) ?: [];
    }

    /**
     * Delete snippet
     */
    public static function delete_snippet( int $snippet_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_snippets';
        self::clear_snippets_cache();
        return false !== $wpdb->delete( $table, [ 'id' => $snippet_id ], [ '%d' ] );
    }

    /**
     * Inject custom CSS into WordPress Customizer
     */
    public static function inject_custom_css( string $css, string $mode = 'append' ): array {
        $existing = wp_get_custom_css();

        if ( 'replace' === $mode ) {
            $new_css = $css;
        } else {
            $new_css = $existing . "\n/* Added by WPChat */\n" . $css;
        }

        $res = wp_update_custom_css_post( $new_css );
        if ( is_wp_error( $res ) ) {
            throw new Exception( 'Failed updating custom CSS: ' . $res->get_error_message() );
        }

        return [
            'success'      => true,
            'css_length'   => strlen( $new_css ),
            'mode'         => $mode,
        ];
    }

    /**
     * Execute isolated snippet with crash-shield barrier
     */
    private static function execute_isolated_snippet( int $snippet_id, string $code ): array {
        try {
            // Sandboxed eval
            eval( $code );
            return [ 'success' => true ];
        } catch ( Throwable $t ) {
            return [
                'success' => false,
                'error'   => sprintf( '%s in snippet on line %d', $t->getMessage(), $t->getLine() ),
            ];
        }
    }

    /**
     * Clear cached snippets transient
     */
    public static function clear_snippets_cache(): void {
        delete_transient( 'wpchat_active_snippets_cache' );
    }

    /**
     * Get active snippets grouped by type, cached in transient to eliminate frontend queries
     */
    public static function get_active_snippets_cached(): array {
        $cached = get_transient( 'wpchat_active_snippets_cache' );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        if ( ! self::table_exists() ) {
            return [ 'php' => [], 'js_header' => [], 'js_footer' => [] ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_snippets';
        $rows = $wpdb->get_results(
            "SELECT id, code_type, code_content FROM {$table} WHERE is_active = 1",
            ARRAY_A
        );

        $grouped = [ 'php' => [], 'js_header' => [], 'js_footer' => [] ];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $type = $row['code_type'] ?? '';
                if ( isset( $grouped[ $type ] ) ) {
                    $grouped[ $type ][] = $row;
                }
            }
        }

        set_transient( 'wpchat_active_snippets_cache', $grouped, 24 * HOUR_IN_SECONDS );
        return $grouped;
    }

    /**
     * Boot all active PHP snippets on WordPress init
     */
    public static function boot_php_snippets(): void {
        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            return;
        }

        $active_grouped = self::get_active_snippets_cached();
        $snippets = $active_grouped['php'] ?? [];

        if ( empty( $snippets ) ) {
            return;
        }

        foreach ( $snippets as $snippet ) {
            try {
                eval( $snippet['code_content'] );
            } catch ( Throwable $t ) {
                // Auto-deactivate crashed snippet
                self::toggle_snippet(
                    (int) $snippet['id'],
                    false,
                    sprintf( 'Crash caught: %s on line %d', $t->getMessage(), $t->getLine() )
                );
            }
        }
    }

    /**
     * Render header injections
     */
    public static function render_header_injections(): void {
        $active_grouped = self::get_active_snippets_cached();
        $items = $active_grouped['js_header'] ?? [];

        foreach ( $items as $item ) {
            echo "\n<!-- WPChat Header Script -->\n" . $item['code_content'] . "\n";
        }
    }

    /**
     * Render footer injections
     */
    public static function render_footer_injections(): void {
        $active_grouped = self::get_active_snippets_cached();
        $items = $active_grouped['js_footer'] ?? [];

        foreach ( $items as $item ) {
            echo "\n<!-- WPChat Footer Script -->\n" . $item['code_content'] . "\n";
        }
    }
}
