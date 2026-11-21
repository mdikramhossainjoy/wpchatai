<?php
/**
 * Real WordPress Execution Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_Tool_Executor {

    /**
     * Dispatch tool execution
     */
    public static function execute( string $tool_name, array $args, int $session_id, int $user_id, bool $is_turbo = false ): array {
        // 1. Tool Name Prefix & Alias Normalization (Normalize FIRST before security checks)
        $clean_tool = sanitize_key( $tool_name );
        if ( ! str_starts_with( $clean_tool, 'wp_' ) ) {
            $clean_tool = 'wp_' . $clean_tool;
        }
        $tool_name = $clean_tool;

        // Alias Normalization
        switch ( $tool_name ) {
            case 'wp_toggle_plugin':
            case 'wp_install_plugin':
            case 'wp_activate_plugin':
            case 'wp_deactivate_plugin':
                if ( 'wp_install_plugin' === $tool_name && ! isset( $args['action'] ) ) {
                    $args['action'] = 'install';
                }
                $tool_name = 'wp_manage_plugins';
                break;

            case 'wp_run_php':
            case 'wp_execute_code':
            case 'wp_run_code':
                $tool_name = 'wp_execute_php';
                break;

            case 'wp_switch_theme':
            case 'wp_set_theme':
                if ( ! isset( $args['action'] ) ) {
                    $args['action'] = 'switch_theme';
                }
                $tool_name = 'wp_manage_themes';
                break;

            case 'wp_manage_menu':
                $tool_name = 'wp_manage_menus';
                break;

            case 'wp_manage_comment':
                $tool_name = 'wp_manage_comments';
                break;
        }

        // 2. Emergency Killswitch Check (evaluated against normalized tool name)
        $killswitch = get_option( 'wpchat_killswitch', 'off' );
        $read_only_tools = [ 'wp_get_site_meta', 'wp_get_content', 'wp_list_code_snippets' ];
        if ( 'on' === $killswitch && ! in_array( $tool_name, $read_only_tools, true ) ) {
            throw new Exception( 'All write actions paused by Emergency Kill-Switch in Settings.' );
        }

        // 3. Blocked Tools Blacklist Check (evaluated against normalized tool name)
        $blocked_tools = (array) get_option( 'wpchat_blocked_tools', [] );
        if ( in_array( $tool_name, $blocked_tools, true ) ) {
            throw new Exception( "Tool '{$tool_name}' has been disabled by site administrator in Settings." );
        }

        switch ( $tool_name ) {
            case 'wp_get_site_meta':
                return self::get_site_meta();

            case 'wp_get_content':
                return self::get_content( $args );

            case 'wp_create_post':
                return self::create_post( $args, $session_id, $user_id, $is_turbo );

            case 'wp_update_post':
                return self::update_post( $args, $session_id, $user_id, $is_turbo );

            case 'wp_delete_post':
                return self::delete_post( $args, $session_id, $user_id, $is_turbo );

            case 'wp_update_option':
                return self::update_option( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_plugins':
                return self::manage_plugins( $args, $session_id, $user_id, $is_turbo );

            case 'wp_execute_php':
                return self::execute_php( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_themes':
                return self::manage_themes( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_menus':
                return self::manage_menus( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_comments':
                return self::manage_comments( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_taxonomies':
                return self::manage_taxonomies( $args );

            case 'wp_manage_media':
                return self::manage_media( $args );

            case 'wp_inject_custom_css':
                return self::inject_custom_css( $args, $session_id, $user_id, $is_turbo );

            case 'wp_inject_header_footer':
                return self::inject_header_footer( $args, $session_id, $user_id, $is_turbo );

            case 'wp_create_code_snippet':
                return self::create_code_snippet( $args, $session_id, $user_id, $is_turbo );

            case 'wp_toggle_code_snippet':
                return self::toggle_code_snippet( $args, $session_id, $user_id, $is_turbo );

            case 'wp_list_code_snippets':
                return self::list_code_snippets( $args );

            case 'wp_manage_woocommerce':
                return self::manage_woocommerce( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_seo':
                return self::manage_seo( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_performance':
                return self::manage_performance( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_database':
                return self::manage_database( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_security':
                return self::manage_security( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_users':
                return self::manage_users( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_cron':
                return self::manage_cron( $args, $session_id, $user_id, $is_turbo );

            case 'wp_manage_backup':
                return self::manage_backup( $args, $session_id, $user_id, $is_turbo );

            default:
                throw new Exception( "Unknown tool execution requested: {$tool_name}" );
        }
    }

    private static function get_site_meta(): array {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $active_plugins = get_option( 'active_plugins', [] );
        $all_plugins    = get_plugins();
        $plugin_summary = [];

        foreach ( $active_plugins as $plugin_file ) {
            if ( isset( $all_plugins[ $plugin_file ] ) ) {
                $plugin_summary[] = [
                    'name'    => $all_plugins[ $plugin_file ]['Name'],
                    'file'    => $plugin_file,
                    'version' => $all_plugins[ $plugin_file ]['Version'],
                    'status'  => 'active',
                ];
            }
        }

        $theme = wp_get_theme();

        return [
            'site_title'   => get_bloginfo( 'name' ),
            'tagline'      => get_bloginfo( 'description' ),
            'site_url'     => home_url(),
            'wp_version'   => get_bloginfo( 'version' ),
            'active_theme' => [
                'name'    => $theme->get( 'Name' ),
                'version' => $theme->get( 'Version' ),
            ],
            'active_plugins' => $plugin_summary,
            'total_posts'  => (int) wp_count_posts( 'post' )->publish,
            'total_pages'  => (int) wp_count_posts( 'page' )->publish,
        ];
    }

    private static function get_content( array $args ): array {
        $post_id = (int) ( $args['post_id'] ?? 0 );
        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                return [ 'error' => "Post ID {$post_id} not found." ];
            }
            return [
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'status'       => $post->post_status,
                'type'         => $post->post_type,
                'content'      => $post->post_content,
                'excerpt'      => $post->post_excerpt,
                'date'         => $post->post_date,
                'view_url'     => get_permalink( $post->ID ),
                'edit_url'     => get_edit_post_link( $post->ID, 'raw' ),
            ];
        }

        $query_args = [
            'post_type'      => ! empty( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post',
            'post_status'    => ! empty( $args['post_status'] ) ? sanitize_text_field( $args['post_status'] ) : 'any',
            'posts_per_page' => isset( $args['number'] ) ? min( 20, max( 1, (int) $args['number'] ) ) : 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = sanitize_text_field( $args['search'] );
        }

        $posts = get_posts( $query_args );
        $list  = [];

        foreach ( $posts as $p ) {
            $list[] = [
                'id'       => $p->ID,
                'title'    => $p->post_title,
                'status'   => $p->post_status,
                'type'     => $p->post_type,
                'date'     => $p->post_date,
                'excerpt'  => wp_trim_words( $p->post_content, 20 ),
                'view_url' => get_permalink( $p->ID ),
            ];
        }

        return [
            'count' => count( $list ),
            'items' => $list,
        ];
    }

    private static function create_post( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'edit_posts' ) ) {
            throw new Exception( 'Permission denied: Insufficient capabilities to create posts.' );
        }

        $post_type = sanitize_key( $args['post_type'] ?? 'post' );
        if ( 'page' === $post_type && ! current_user_can( 'edit_pages' ) ) {
            throw new Exception( 'Permission denied: Insufficient capabilities to create pages.' );
        }

        $status = in_array( $args['post_status'] ?? '', [ 'draft', 'publish', 'pending' ], true ) ? $args['post_status'] : 'draft';
        if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
            $status = 'pending';
        }

        $post_data = [
            'post_title'   => sanitize_text_field( $args['post_title'] ?? 'Untitled' ),
            'post_content' => wp_kses_post( $args['post_content'] ?? '' ),
            'post_status'  => $status,
            'post_type'    => $post_type,
            'post_excerpt' => sanitize_textarea_field( $args['post_excerpt'] ?? '' ),
            'post_author'  => $user_id,
        ];

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            throw new Exception( 'Failed creating post: ' . $post_id->get_error_message() );
        }

        // Log for undo
        $args['created_post_id'] = $post_id;
        $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_create_post', $args, null, 'executed' );

        return [
            'success'   => true,
            'post_id'   => $post_id,
            'status'    => $post_data['post_status'],
            'title'     => $post_data['post_title'],
            'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
            'view_url'  => get_permalink( $post_id ),
            'audit_id'  => $audit_id,
            'is_turbo'  => $is_turbo,
        ];
    }

    private static function update_post( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        $post_id = (int) ( $args['post_id'] ?? 0 );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            throw new Exception( "Post ID {$post_id} does not exist." );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new Exception( "Permission denied: Insufficient capabilities to edit post ID {$post_id}." );
        }

        // Capture previous state
        $prev_state = WPChat_Audit_Logger::capture_post_state( $post_id );

        // If trashing, route to native wp_trash_post to preserve restore metadata
        if ( isset( $args['post_status'] ) && 'trash' === $args['post_status'] ) {
            $trash_res = wp_trash_post( $post_id );
            if ( ! $trash_res ) {
                throw new Exception( "Failed to move post ID {$post_id} to trash." );
            }
            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_update_post', $args, $prev_state, 'executed' );
            return [
                'success'  => true,
                'post_id'  => $post_id,
                'title'    => get_the_title( $post_id ),
                'status'   => 'trash',
                'edit_url' => get_edit_post_link( $post_id, 'raw' ),
                'audit_id' => $audit_id,
                'is_turbo' => $is_turbo,
            ];
        }

        $post_data = [ 'ID' => $post_id ];
        if ( isset( $args['post_title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $args['post_title'] );
        }
        if ( isset( $args['post_content'] ) ) {
            $post_data['post_content'] = wp_kses_post( $args['post_content'] );
        }
        if ( isset( $args['post_status'] ) && in_array( $args['post_status'], [ 'draft', 'publish', 'pending' ], true ) ) {
            $post_data['post_status'] = $args['post_status'];
        }
        if ( isset( $args['post_excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_textarea_field( $args['post_excerpt'] );
        }

        $res = wp_update_post( $post_data, true );
        if ( is_wp_error( $res ) ) {
            throw new Exception( 'Failed updating post: ' . $res->get_error_message() );
        }

        $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_update_post', $args, $prev_state, 'executed' );

        return [
            'success'  => true,
            'post_id'  => $post_id,
            'title'    => get_the_title( $post_id ),
            'status'   => get_post_status( $post_id ),
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            'audit_id' => $audit_id,
            'is_turbo' => $is_turbo,
        ];
    }

    private static function delete_post( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        $post_id = (int) ( $args['post_id'] ?? 0 );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            throw new Exception( "Post ID {$post_id} does not exist." );
        }

        if ( ! current_user_can( 'delete_post', $post_id ) ) {
            throw new Exception( "Permission denied: Insufficient capabilities to delete post ID {$post_id}." );
        }

        $prev_state = WPChat_Audit_Logger::capture_post_state( $post_id );
        $force = ! empty( $args['force_delete'] );

        $res = wp_delete_post( $post_id, $force );
        if ( ! $res ) {
            throw new Exception( "Failed to delete post ID {$post_id}." );
        }

        $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_delete_post', $args, $prev_state, 'executed' );

        return [
            'success'   => true,
            'post_id'   => $post_id,
            'action'    => $force ? 'permanently_deleted' : 'moved_to_trash',
            'audit_id'  => $audit_id,
            'is_turbo'  => $is_turbo,
        ];
    }

    private static function update_option( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator capabilities required to update site options.' );
        }

        $option_name  = sanitize_text_field( $args['option_name'] ?? '' );
        $option_value = $args['option_value'] ?? '';

        if ( empty( $option_name ) ) {
            throw new Exception( 'Option name cannot be empty.' );
        }

        $lower_name = strtolower( trim( $option_name ) );
        if ( in_array( $lower_name, WPChat_Tool_Registry::FORBIDDEN_OPTIONS, true ) || str_starts_with( $lower_name, 'wpchat_' ) ) {
            throw new Exception( "Option '{$option_name}' cannot be modified by AI for security reasons." );
        }

        // If JSON string representing object/array, decode to store natively
        if ( is_string( $option_value ) && ( str_starts_with( trim( $option_value ), '{' ) || str_starts_with( trim( $option_value ), '[' ) ) ) {
            $decoded = json_decode( $option_value, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $option_value = $decoded;
            }
        }

        $prev_state = WPChat_Audit_Logger::capture_option_state( $option_name );
        update_option( $option_name, $option_value );

        $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_update_option', $args, $prev_state, 'executed' );

        return [
            'success'      => true,
            'option_name'  => $option_name,
            'option_value' => is_scalar( $option_value ) ? $option_value : wp_json_encode( $option_value ),
            'audit_id'     => $audit_id,
            'is_turbo'     => $is_turbo,
        ];
    }

    private static function manage_plugins( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $action = sanitize_key( $args['action'] ?? '' );
        $slug   = sanitize_key( $args['slug'] ?? ( $args['plugin'] ?? ( $args['plugin_slug'] ?? ( $args['name'] ?? ( $args['plugin_name'] ?? '' ) ) ) ) );

        if ( empty( $action ) || 'add' === $action || 'download' === $action ) {
            if ( ! empty( $slug ) ) {
                $action = 'install';
            } else {
                $action = 'list';
            }
        }

        // 1. List installed plugins
        if ( 'list' === $action ) {
            $all_plugins    = get_plugins();
            $active_plugins = get_option( 'active_plugins', [] );
            $list = [];

            foreach ( $all_plugins as $file => $info ) {
                $slug = dirname( $file );
                if ( empty( $slug ) || $slug === '.' ) {
                    $slug = basename( $file, '.php' );
                }
                $is_active = in_array( $file, $active_plugins, true );

                $list[] = [
                    'type'         => 'plugin',
                    'file'         => $file,
                    'plugin_file'  => $file,
                    'slug'         => $slug,
                    'name'         => $info['Name'] ?? '',
                    'version'      => $info['Version'] ?? '',
                    'is_active'    => $is_active,
                    'is_installed' => true,
                    'author'       => wp_strip_all_tags( $info['Author'] ?? '' ),
                    'description'  => mb_strimwidth( wp_strip_all_tags( $info['Description'] ?? '' ), 0, 140, '...' ),
                    'banner'       => "https://ps.w.org/{$slug}/assets/banner-772x250.jpg",
                    'icon'         => "https://ps.w.org/{$slug}/assets/icon-128x128.png",
                ];
            }

            return [
                'plugins' => $list,
                'total'   => count( $list ),
            ];
        }

        // 2. Search plugins on WordPress.org
        if ( 'search' === $action ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $query_term = sanitize_text_field( $slug ?: ( $args['search'] ?? ( $args['query'] ?? ( $args['keyword'] ?? '' ) ) ) );
            if ( empty( $query_term ) ) {
                throw new Exception( 'Search keyword or slug is required.' );
            }

            $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
            $per_page = min( max( (int) ( $args['limit'] ?? ( $args['per_page'] ?? 8 ) ), 1 ), 24 );

            $api = plugins_api( 'query_plugins', [
                'search'   => $query_term,
                'page'     => $page,
                'per_page' => $per_page,
                'fields'   => [
                    'banners'           => true,
                    'icons'             => true,
                    'short_description' => true,
                    'active_installs'   => true,
                    'rating'            => true,
                    'num_ratings'       => true,
                    'version'           => true,
                    'author'            => true,
                ],
            ] );

            if ( is_wp_error( $api ) ) {
                throw new Exception( 'Plugin repository search failed: ' . $api->get_error_message() );
            }

            $all_plugins = get_plugins();
            $results     = [];
            if ( ! empty( $api->plugins ) && is_array( $api->plugins ) ) {
                foreach ( $api->plugins as $p ) {
                    $banner = null;
                    if ( ! empty( $p->banners ) ) {
                        $banners = (array) $p->banners;
                        $banner  = $banners['high'] ?? ( $banners['low'] ?? ( $banners['default'] ?? null ) );
                    }
                    if ( empty( $banner ) && ! empty( $p->slug ) ) {
                        $banner = "https://ps.w.org/{$p->slug}/assets/banner-772x250.jpg";
                    }

                    $icon = null;
                    if ( ! empty( $p->icons ) ) {
                        $icons = (array) $p->icons;
                        $icon  = $icons['2x'] ?? ( $icons['1x'] ?? ( $icons['svg'] ?? ( $icons['default'] ?? null ) ) );
                    }
                    if ( empty( $icon ) && ! empty( $p->slug ) ) {
                        $icon = "https://ps.w.org/{$p->slug}/assets/icon-128x128.png";
                    }

                    $is_installed   = false;
                    $is_active      = false;
                    $installed_file = null;
                    foreach ( $all_plugins as $pfile => $pinfo ) {
                        if ( str_starts_with( $pfile, $p->slug . '/' ) || basename( dirname( $pfile ) ) === $p->slug || basename( $pfile, '.php' ) === $p->slug ) {
                            $is_installed   = true;
                            $is_active      = is_plugin_active( $pfile );
                            $installed_file = $pfile;
                            break;
                        }
                    }

                    $results[] = [
                        'type'            => 'plugin',
                        'name'            => $p->name,
                        'slug'            => $p->slug,
                        'version'         => $p->version ?? '',
                        'author'          => wp_strip_all_tags( $p->author ?? '' ),
                        'active_installs' => number_format( (int) ( $p->active_installs ?? 0 ) ),
                        'rating'          => round( (float) ( $p->rating ?? 0 ) / 20, 1 ),
                        'num_ratings'     => (int) ( $p->num_ratings ?? 0 ),
                        'description'     => mb_strimwidth( wp_strip_all_tags( $p->short_description ?? '' ), 0, 140, '...' ),
                        'banner'          => $banner,
                        'icon'            => $icon,
                        'is_installed'    => $is_installed,
                        'is_active'       => $is_active,
                        'plugin_file'     => $installed_file,
                    ];
                }
            }

            return [
                'results' => $results,
                'count'   => count( $results ),
            ];
        }

        // 3. Install plugin from WordPress.org
        if ( 'install' === $action ) {
            if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'manage_options' ) ) {
                throw new Exception( 'Permission denied: Administrator capabilities required to install plugins.' );
            }

            @set_time_limit( 300 );

            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            if ( file_exists( ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
            }

            if ( empty( $slug ) && ! empty( $args['plugin_file'] ) ) {
                $slug = dirname( sanitize_text_field( $args['plugin_file'] ) );
            }

            $slug_map = [
                'elementor'   => 'elementor',
                'woocommerce' => 'woocommerce',
                'yoast'       => 'wordpress-seo',
                'yoast-seo'   => 'wordpress-seo',
                'wordfence'   => 'wordfence',
                'cf7'         => 'contact-form-7',
                'litespeed'   => 'litespeed-cache',
            ];
            if ( isset( $slug_map[ $slug ] ) ) {
                $slug = $slug_map[ $slug ];
            }

            if ( empty( $slug ) || $slug === '.' ) {
                throw new Exception( 'Valid plugin slug required for installation (e.g. elementor, woocommerce).' );
            }

            $auto_activate = ! isset( $args['auto_activate'] ) || ! empty( $args['auto_activate'] );

            // Check if already installed
            wp_clean_plugins_cache( false );
            $all_plugins = get_plugins();
            $existing_file = null;
            foreach ( $all_plugins as $pfile => $pinfo ) {
                if ( str_starts_with( $pfile, $slug . '/' ) || basename( dirname( $pfile ) ) === $slug || basename( $pfile, '.php' ) === $slug ) {
                    $existing_file = $pfile;
                    break;
                }
            }

            // If already installed on disk, activate if requested and return success
            if ( ! empty( $existing_file ) ) {
                $activated = is_plugin_active( $existing_file );
                if ( ! $activated && $auto_activate ) {
                    ob_start();
                    $act_res   = activate_plugin( $existing_file );
                    ob_end_clean();
                    $activated = ! is_wp_error( $act_res );
                }
                $audit_args = array_merge( $args, [
                'action'      => 'install',
                'plugin_file' => $plugin_file,
            ] );
            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_plugins', $audit_args, null, 'executed' );
                return [
                    'success'     => true,
                    'action'      => 'install',
                    'slug'        => $slug,
                    'plugin_file' => $existing_file,
                    'activated'   => $activated,
                    'message'     => "Plugin '{$slug}' is already installed." . ( $activated ? " Activated successfully." : "" ),
                    'audit_id'    => $audit_id,
                    'is_turbo'    => $is_turbo,
                ];
            }

            // Fetch plugin information from WordPress.org
            $api = plugins_api( 'plugin_information', [
                'slug'   => $slug,
                'fields' => [ 'sections' => false ],
            ] );

            if ( is_wp_error( $api ) ) {
                throw new Exception( "Could not retrieve info for plugin '{$slug}' from WordPress.org: " . $api->get_error_message() );
            }

            // Initialize WordPress Filesystem
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();

            // Instantiate silent skin
            if ( class_exists( 'Automatic_Upgrader_Skin' ) ) {
                $skin = new Automatic_Upgrader_Skin();
            } else {
                $skin = new class extends WP_Upgrader_Skin {
                    public function feedback( $string, ...$args ) {}
                    public function header() {}
                    public function footer() {}
                };
            }

            $upgrader = new Plugin_Upgrader( $skin );

            ob_start();
            $install = $upgrader->install( $api->download_link );
            $raw_output = ob_get_clean();

            // Handle folder_exists
            if ( is_wp_error( $install ) && 'folder_exists' === $install->get_error_code() ) {
                wp_clean_plugins_cache( false );
                $all_plugins = get_plugins();
                foreach ( $all_plugins as $pfile => $pinfo ) {
                    if ( str_starts_with( $pfile, $slug . '/' ) || basename( dirname( $pfile ) ) === $slug ) {
                        $existing_file = $pfile;
                        break;
                    }
                }
                if ( empty( $existing_file ) ) {
                    $existing_file = $slug . '/' . $slug . '.php';
                }
                $activated = false;
                if ( $auto_activate ) {
                    ob_start();
                    $act_res = activate_plugin( $existing_file );
                    ob_end_clean();
                    $activated = ! is_wp_error( $act_res );
                }
                $audit_args = array_merge( $args, [
                'action'      => 'install',
                'plugin_file' => $plugin_file,
            ] );
            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_plugins', $audit_args, null, 'executed' );
                return [
                    'success'     => true,
                    'action'      => 'install',
                    'slug'        => $slug,
                    'plugin_file' => $existing_file,
                    'activated'   => $activated,
                    'message'     => "Plugin '{$slug}' already existed on disk. Activated successfully.",
                    'audit_id'    => $audit_id,
                    'is_turbo'    => $is_turbo,
                ];
            }

            if ( is_wp_error( $install ) ) {
                throw new Exception( 'Plugin installation failed: ' . $install->get_error_message() );
            }

            if ( false === $install ) {
                $err = ( method_exists( $skin, 'get_errors' ) && $skin->get_errors()->has_errors() )
                    ? implode( ', ', $skin->get_error_messages() )
                    : ( ! empty( $raw_output ) ? wp_strip_all_tags( $raw_output ) : 'Filesystem write permission denied or installation aborted.' );
                throw new Exception( 'Plugin installation failed: ' . $err );
            }

            wp_clean_plugins_cache( false );
            $plugin_file = $upgrader->plugin_info();
            if ( empty( $plugin_file ) ) {
                $all_plugins = get_plugins();
                foreach ( $all_plugins as $pfile => $pinfo ) {
                    if ( str_starts_with( $pfile, $slug . '/' ) || basename( dirname( $pfile ) ) === $slug ) {
                        $plugin_file = $pfile;
                        break;
                    }
                }
            }
            if ( empty( $plugin_file ) ) {
                $candidate = $slug . '/' . $slug . '.php';
                if ( file_exists( WP_PLUGIN_DIR . '/' . $candidate ) ) {
                    $plugin_file = $candidate;
                }
            }

            $activated = false;
            $activate_error = null;
            if ( $auto_activate && ! empty( $plugin_file ) ) {
                ob_start();
                $act_res = activate_plugin( $plugin_file );
                ob_end_clean();
                $activated = ! is_wp_error( $act_res );
                if ( is_wp_error( $act_res ) ) {
                    $activate_error = $act_res->get_error_message();
                }
            }

            $audit_args = array_merge( $args, [
                'action'      => 'install',
                'plugin_file' => $plugin_file,
            ] );
            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_plugins', $audit_args, null, 'executed' );

            return [
                'success'        => true,
                'action'         => 'install',
                'slug'           => $slug,
                'plugin_file'    => $plugin_file,
                'activated'      => $activated,
                'activate_error' => $activate_error,
                'message'        => "Plugin '{$slug}' installed successfully" . ( $activated ? " and activated." : "." ),
                'audit_id'       => $audit_id,
                'is_turbo'       => $is_turbo,
            ];
        }

        // 4. Activate installed plugin
        if ( 'activate' === $action ) {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                throw new Exception( 'Permission denied: Cannot activate plugins.' );
            }

            $file = sanitize_text_field( $args['plugin_file'] ?? '' );
            if ( empty( $file ) && ! empty( $args['slug'] ) ) {
                $installed = get_plugins( '/' . sanitize_key( $args['slug'] ) );
                if ( ! empty( $installed ) ) {
                    $file = sanitize_key( $args['slug'] ) . '/' . key( $installed );
                }
            }

            if ( empty( $file ) ) {
                throw new Exception( 'Plugin file path or slug required for activation.' );
            }

            $res = activate_plugin( $file );
            if ( is_wp_error( $res ) ) {
                throw new Exception( 'Activation error: ' . $res->get_error_message() );
            }

            $all_plugins = get_plugins();
            $plugin_name = $all_plugins[ $file ]['Name'] ?? $file;
            $audit_id    = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_plugins', $args, null, 'executed' );

            return [
                'success'     => true,
                'action'      => 'activate',
                'plugin_file' => $file,
                'plugin_name' => $plugin_name,
                'audit_id'    => $audit_id,
                'is_turbo'    => $is_turbo,
            ];
        }

        // 5. Deactivate plugin
        if ( 'deactivate' === $action ) {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                throw new Exception( 'Permission denied: Cannot deactivate plugins.' );
            }

            $file = sanitize_text_field( $args['plugin_file'] ?? '' );
            if ( empty( $file ) && ! empty( $args['slug'] ) ) {
                $installed = get_plugins( '/' . sanitize_key( $args['slug'] ) );
                if ( ! empty( $installed ) ) {
                    $file = sanitize_key( $args['slug'] ) . '/' . key( $installed );
                }
            }

            if ( empty( $file ) ) {
                throw new Exception( 'Plugin file path required for deactivation.' );
            }

            deactivate_plugins( $file );
            $audit_args = array_merge( $args, [
                'action'      => 'install',
                'plugin_file' => $plugin_file,
            ] );
            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_plugins', $audit_args, null, 'executed' );

            return [
                'success'     => true,
                'action'      => 'deactivate',
                'plugin_file' => $file,
                'audit_id'    => $audit_id,
                'is_turbo'    => $is_turbo,
            ];
        }

        // 6. Delete plugin
        if ( 'delete' === $action ) {
            if ( ! current_user_can( 'delete_plugins' ) ) {
                throw new Exception( 'Permission denied: Cannot delete plugins.' );
            }

            $file = sanitize_text_field( $args['plugin_file'] ?? '' );
            if ( empty( $file ) && ! empty( $args['slug'] ) ) {
                $installed = get_plugins( '/' . sanitize_key( $args['slug'] ) );
                if ( ! empty( $installed ) ) {
                    $file = sanitize_key( $args['slug'] ) . '/' . key( $installed );
                }
            }

            if ( empty( $file ) ) {
                throw new Exception( 'Plugin file path required for deletion.' );
            }

            deactivate_plugins( $file );
            $res = delete_plugins( [ $file ] );
            if ( is_wp_error( $res ) ) {
                throw new Exception( 'Deletion error: ' . $res->get_error_message() );
            }

            $audit_args = array_merge( $args, [
                'action'      => 'install',
                'plugin_file' => $plugin_file,
            ] );
            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_plugins', $audit_args, null, 'executed' );

            return [
                'success'     => true,
                'action'      => 'delete',
                'plugin_file' => $file,
                'audit_id'    => $audit_id,
                'is_turbo'    => $is_turbo,
            ];
        }

        throw new Exception( "Unknown plugin action: {$action}" );
    }

    private static function execute_php( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator capabilities required to execute code.' );
        }

        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            throw new Exception( 'Execution blocked: DISALLOW_FILE_MODS is active on this WordPress installation.' );
        }

        $code        = trim( (string) ( $args['code'] ?? '' ) );
        $description = sanitize_text_field( (string) ( $args['description'] ?? 'Administrative PHP Execution' ) );

        if ( empty( $code ) ) {
            throw new Exception( 'No PHP code provided to execute.' );
        }

        // Strip leading opening tags and trailing closing tags
        $clean_code = preg_replace( '/^<\?php\s*/i', '', $code );
        $clean_code = preg_replace( '/^<\?\s*/i', '', $clean_code );
        $clean_code = preg_replace( '/\s*\?>\s*$/', '', $clean_code );

        // Syntax token validation & security inspection
        try {
            $tag = '<' . '?' . 'php ';
            $tokens = @token_get_all( $tag . $clean_code );
            $forbidden_functions = [ 'exec', 'system', 'passthru', 'proc_open', 'shell_exec', 'popen', 'pcntl_exec' ];
            foreach ( $tokens as $token ) {
                if ( is_array( $token ) && ( $token[0] === T_STRING || $token[0] === T_NAME_QUALIFIED ) ) {
                    $func_name = strtolower( $token[1] );
                    if ( in_array( $func_name, $forbidden_functions, true ) ) {
                        throw new Exception( "Execution of shell function '{$func_name}' is forbidden for security." );
                    }
                }
            }
        } catch ( Throwable $t ) {
            throw new Exception( 'Syntax or security check failed: ' . $t->getMessage() );
        }

        // Execute in isolated buffer
        ob_start();
        $start_time = microtime( true );
        $return_val = null;
        $error_msg  = null;

        try {
            global $wpdb, $wp_rewrite, $wp_filesystem, $wp_query;
            $return_val = eval( $clean_code );
        } catch ( Throwable $e ) {
            $error_msg = $e->getMessage() . ' on line ' . $e->getLine();
        }

        $output      = ob_get_clean();
        $duration_ms = round( ( microtime( true ) - $start_time ) * 1000, 2 );

        if ( $error_msg !== null ) {
            throw new Exception( 'PHP execution error: ' . $error_msg . '. Output: ' . $output );
        }

        $audit_id = WPChat_Audit_Logger::log(
            $session_id,
            $user_id,
            'wp_execute_php',
            [ 'description' => $description, 'code_preview' => mb_substr( $clean_code, 0, 300 ) ],
            null,
            'executed'
        );

        return [
            'success'      => true,
            'description'  => $description,
            'output'       => ! empty( $output ) ? $output : '(Code executed without stdout)',
            'return_value' => is_scalar( $return_val ) || is_null( $return_val ) ? $return_val : ( is_array( $return_val ) || is_object( $return_val ) ? wp_json_encode( $return_val ) : '(resource)' ),
            'duration_ms'  => $duration_ms,
            'audit_id'     => $audit_id,
            'is_turbo'     => $is_turbo,
        ];
    }

    private static function manage_themes( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        $action = sanitize_key( $args['action'] ?? 'list_themes' );

        // 1. List themes
        if ( 'list_themes' === $action ) {
            $themes = wp_get_themes();
            $active = get_stylesheet();
            $list   = [];

            foreach ( $themes as $stylesheet => $t ) {
                $screenshot = $t->get_screenshot();
                $list[] = [
                    'type'         => 'theme',
                    'stylesheet'   => $stylesheet,
                    'slug'         => $stylesheet,
                    'name'         => $t->get( 'Name' ),
                    'version'      => $t->get( 'Version' ),
                    'author'       => wp_strip_all_tags( $t->get( 'Author' ) ),
                    'description'  => mb_strimwidth( wp_strip_all_tags( $t->get( 'Description' ) ), 0, 140, '...' ),
                    'banner'       => $screenshot ? $screenshot : null,
                    'is_active'    => ( $stylesheet === $active ),
                    'is_installed' => true,
                ];
            }

            return [
                'active_theme' => $active,
                'themes'       => $list,
                'total'        => count( $list ),
            ];
        }

        // Search themes on WordPress.org
        if ( 'search' === $action ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
            require_once ABSPATH . 'wp-admin/includes/theme-install.php';
            $query_term = sanitize_text_field( $args['slug'] ?? ( $args['query'] ?? ( $args['search'] ?? ( $args['keyword'] ?? '' ) ) ) );
            if ( empty( $query_term ) ) {
                throw new Exception( 'Search keyword or query is required.' );
            }

            $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
            $per_page = min( max( (int) ( $args['limit'] ?? ( $args['per_page'] ?? 8 ) ), 1 ), 24 );

            $api = themes_api( 'query_themes', [
                'search'   => $query_term,
                'page'     => $page,
                'per_page' => $per_page,
                'fields'   => [
                    'description'    => true,
                    'rating'         => true,
                    'num_ratings'    => true,
                    'screenshot_url' => true,
                    'author'         => true,
                    'version'        => true,
                ],
            ] );

            if ( is_wp_error( $api ) ) {
                throw new Exception( 'Theme repository search failed: ' . $api->get_error_message() );
            }

            $installed_themes = wp_get_themes();
            $active_theme     = get_stylesheet();
            $results          = [];

            if ( ! empty( $api->themes ) && is_array( $api->themes ) ) {
                foreach ( $api->themes as $t ) {
                    $results[] = [
                        'type'            => 'theme',
                        'name'            => $t->name,
                        'slug'            => $t->slug,
                        'stylesheet'      => $t->slug,
                        'version'         => $t->version ?? '',
                        'author'          => wp_strip_all_tags( $t->author ?? '' ),
                        'rating'          => round( (float) ( $t->rating ?? 0 ) / 20, 1 ),
                        'num_ratings'     => (int) ( $t->num_ratings ?? 0 ),
                        'description'     => mb_strimwidth( wp_strip_all_tags( $t->description ?? '' ), 0, 140, '...' ),
                        'banner'          => $t->screenshot_url ?? null,
                        'is_installed'    => isset( $installed_themes[ $t->slug ] ),
                        'is_active'       => ( $active_theme === $t->slug ),
                    ];
                }
            }

            return [
                'results' => $results,
                'count'   => count( $results ),
            ];
        }

        // Install theme from WordPress.org
        if ( 'install' === $action ) {
            if ( ! current_user_can( 'install_themes' ) && ! current_user_can( 'manage_options' ) ) {
                throw new Exception( 'Permission denied: Administrator capabilities required to install themes.' );
            }

            $theme_slug = sanitize_key( $args['slug'] ?? ( $args['stylesheet'] ?? '' ) );
            if ( empty( $theme_slug ) ) {
                throw new Exception( 'Theme slug required for installation.' );
            }

            require_once ABSPATH . 'wp-admin/includes/theme.php';
            require_once ABSPATH . 'wp-admin/includes/theme-install.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            if ( file_exists( ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
            }

            $api = themes_api( 'theme_information', [ 'slug' => $theme_slug ] );
            if ( is_wp_error( $api ) ) {
                throw new Exception( "Could not retrieve theme info for '{$theme_slug}': " . $api->get_error_message() );
            }

            $skin     = class_exists( 'Automatic_Upgrader_Skin' ) ? new Automatic_Upgrader_Skin() : null;
            $upgrader = new Theme_Upgrader( $skin );
            $result   = $upgrader->install( $api->download_link );

            if ( is_wp_error( $result ) || false === $result ) {
                throw new Exception( 'Theme installation failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'Filesystem error occurred during installation.' ) );
            }

            $auto_activate = ! empty( $args['auto_activate'] ) || ! empty( $args['switch_theme'] );
            if ( $auto_activate ) {
                switch_theme( $theme_slug );
            }

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_themes', $args, null, 'executed' );

            return [
                'success'      => true,
                'action'       => 'install',
                'slug'         => $theme_slug,
                'activated'    => $auto_activate,
                'active_theme' => get_stylesheet(),
                'message'      => "Theme '{$theme_slug}' installed successfully" . ( $auto_activate ? " and switched to active." : "." ),
                'audit_id'     => $audit_id,
                'is_turbo'     => $is_turbo,
            ];
        }

        // 2. Switch theme
        if ( 'switch_theme' === $action ) {
            if ( ! current_user_can( 'switch_themes' ) ) {
                throw new Exception( 'Permission denied: Cannot switch themes.' );
            }

            $stylesheet = sanitize_text_field( $args['stylesheet'] ?? '' );
            $theme      = wp_get_theme( $stylesheet );

            if ( ! $theme->exists() ) {
                throw new Exception( "Theme '{$stylesheet}' is not installed." );
            }

            $prev_theme = get_stylesheet();
            switch_theme( $stylesheet );

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_themes', $args, [ 'theme' => $prev_theme ], 'executed' );

            return [
                'success'      => true,
                'action'       => 'switch_theme',
                'active_theme' => $stylesheet,
                'theme_name'   => $theme->get( 'Name' ),
                'audit_id'     => $audit_id,
                'is_turbo'     => $is_turbo,
            ];
        }

        // 3. Set Site Identity
        if ( 'set_identity' === $action ) {
            if ( ! current_user_can( 'edit_theme_options' ) && ! current_user_can( 'manage_options' ) ) {
                throw new Exception( 'Permission denied: Cannot edit site identity.' );
            }

            $updates = [];
            if ( isset( $args['site_title'] ) ) {
                update_option( 'blogname', sanitize_text_field( $args['site_title'] ) );
                $updates['site_title'] = $args['site_title'];
            }
            if ( isset( $args['tagline'] ) ) {
                update_option( 'blogdescription', sanitize_text_field( $args['tagline'] ) );
                $updates['tagline'] = $args['tagline'];
            }
            if ( isset( $args['site_icon_id'] ) ) {
                update_option( 'site_icon', (int) $args['site_icon_id'] );
                $updates['site_icon'] = (int) $args['site_icon_id'];
            }
            if ( isset( $args['logo_id'] ) ) {
                set_theme_mod( 'custom_logo', (int) $args['logo_id'] );
                $updates['custom_logo'] = (int) $args['logo_id'];
            }

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_themes', $args, null, 'executed' );

            return [
                'success'  => true,
                'action'   => 'set_identity',
                'updates'  => $updates,
                'audit_id' => $audit_id,
                'is_turbo' => $is_turbo,
            ];
        }

        // 4. Set Static Front Page
        if ( 'set_front_page' === $action ) {
            if ( ! current_user_can( 'edit_theme_options' ) && ! current_user_can( 'manage_options' ) ) {
                throw new Exception( 'Permission denied: Cannot configure front page.' );
            }

            $page_on_front = (int) ( $args['page_on_front'] ?? 0 );
            if ( ! $page_on_front || ! get_post( $page_on_front ) ) {
                throw new Exception( "Page ID {$page_on_front} does not exist." );
            }

            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $page_on_front );

            if ( isset( $args['page_for_posts'] ) ) {
                update_option( 'page_for_posts', (int) $args['page_for_posts'] );
            }

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_themes', $args, null, 'executed' );

            return [
                'success'       => true,
                'action'        => 'set_front_page',
                'show_on_front' => 'page',
                'page_on_front' => $page_on_front,
                'audit_id'      => $audit_id,
                'is_turbo'      => $is_turbo,
            ];
        }

        // 5. Set Permalinks
        if ( 'set_permalinks' === $action ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                throw new Exception( 'Permission denied: Administrator capabilities required to update permalinks.' );
            }
            global $wp_rewrite;
            $structure = sanitize_text_field( $args['permalink_structure'] ?? '/%postname%/' );

            $wp_rewrite->set_permalink_structure( $structure );
            $wp_rewrite->flush_rules();
            update_option( 'permalink_structure', $structure );

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_themes', $args, null, 'executed' );

            return [
                'success'   => true,
                'action'    => 'set_permalinks',
                'structure' => $structure,
                'audit_id'  => $audit_id,
                'is_turbo'  => $is_turbo,
            ];
        }

        throw new Exception( "Unknown theme action: {$action}" );
    }

    private static function manage_menus( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        $action = sanitize_key( $args['action'] ?? 'list_menus' );

        // 1. List menus
        if ( 'list_menus' === $action ) {
            $menus     = wp_get_nav_menus();
            $locations = get_nav_menu_locations();
            $registered_locations = get_registered_nav_menus();
            $summary   = [];

            foreach ( $menus as $m ) {
                $items = wp_get_nav_menu_items( $m->term_id );
                $item_list = [];
                if ( ! empty( $items ) ) {
                    foreach ( $items as $it ) {
                        $item_list[] = [
                            'id'    => $it->ID,
                            'title' => $it->title,
                            'url'   => $it->url,
                        ];
                    }
                }

                $assigned_to = [];
                foreach ( $locations as $loc => $tid ) {
                    if ( $tid === $m->term_id ) {
                        $assigned_to[] = $loc;
                    }
                }

                $summary[] = [
                    'menu_id'     => $m->term_id,
                    'name'        => $m->name,
                    'count'       => $m->count,
                    'assigned_to' => $assigned_to,
                    'items'       => $item_list,
                ];
            }

            return [
                'menus'                => $summary,
                'registered_locations' => $registered_locations,
            ];
        }

        // 2. Create menu
        if ( 'create_menu' === $action ) {
            if ( ! current_user_can( 'edit_theme_options' ) && ! current_user_can( 'manage_options' ) ) {
                throw new Exception( 'Permission denied: Cannot create navigation menus.' );
            }

            $name = sanitize_text_field( $args['menu_name'] ?? 'Main Menu' );
            $res  = wp_create_nav_menu( $name );

            if ( is_wp_error( $res ) ) {
                throw new Exception( 'Failed creating menu: ' . $res->get_error_message() );
            }

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_menus', $args, null, 'executed' );

            return [
                'success'   => true,
                'action'    => 'create_menu',
                'menu_id'   => $res,
                'menu_name' => $name,
                'audit_id'  => $audit_id,
                'is_turbo'  => $is_turbo,
            ];
        }

        // 3. Add item to menu
        if ( 'add_item' === $action ) {
            if ( ! current_user_can( 'edit_theme_options' ) && ! current_user_can( 'manage_options' ) ) {
                throw new Exception( 'Permission denied: Cannot modify navigation menus.' );
            }

            $menu_id = (int) ( $args['menu_id'] ?? 0 );
            if ( ! $menu_id || ! is_nav_menu( $menu_id ) ) {
                throw new Exception( "Navigation menu ID {$menu_id} does not exist." );
            }

            $title     = sanitize_text_field( $args['title'] ?? 'Menu Item' );
            $url       = esc_url_raw( $args['url'] ?? home_url( '/' ) );
            $type      = sanitize_key( $args['type'] ?? 'custom' );
            $object_id = (int) ( $args['object_id'] ?? 0 );

            $item_data = [
                'menu-item-title'  => $title,
                'menu-item-url'    => $url,
                'menu-item-type'   => $type,
                'menu-item-status' => 'publish',
            ];

            if ( $object_id > 0 && in_array( $type, [ 'post_type', 'taxonomy' ], true ) ) {
                $item_data['menu-item-object-id'] = $object_id;
                if ( 'post_type' === $type ) {
                    $p = get_post( $object_id );
                    $item_data['menu-item-object'] = $p ? $p->post_type : 'page';
                } else {
                    $t = get_term( $object_id );
                    $item_data['menu-item-object'] = ( $t && ! is_wp_error( $t ) ) ? $t->taxonomy : 'category';
                }
            }

            $item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

            if ( is_wp_error( $item_id ) ) {
                throw new Exception( 'Failed adding menu item: ' . $item_id->get_error_message() );
            }

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_menus', $args, null, 'executed' );

            return [
                'success'      => true,
                'action'       => 'add_item',
                'menu_id'      => $menu_id,
                'menu_item_id' => $item_id,
                'title'        => $title,
                'audit_id'     => $audit_id,
                'is_turbo'     => $is_turbo,
            ];
        }

        // 4. Assign location
        if ( 'assign_location' === $action ) {
            if ( ! current_user_can( 'edit_theme_options' ) && ! current_user_can( 'manage_options' ) ) {
                throw new Exception( 'Permission denied: Cannot assign menu locations.' );
            }
            $menu_id  = (int) ( $args['menu_id'] ?? 0 );
            $location = sanitize_key( $args['location'] ?? 'primary' );

            $locations = get_theme_mod( 'nav_menu_locations', [] );
            $locations[ $location ] = $menu_id;
            set_theme_mod( 'nav_menu_locations', $locations );

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_menus', $args, null, 'executed' );

            return [
                'success'   => true,
                'action'    => 'assign_location',
                'menu_id'   => $menu_id,
                'location'  => $location,
                'audit_id'  => $audit_id,
                'is_turbo'  => $is_turbo,
            ];
        }

        throw new Exception( "Unknown menu action: {$action}" );
    }

    private static function manage_comments( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        $action = sanitize_key( $args['action'] ?? 'list_comments' );

        // 1. List comments
        if ( 'list_comments' === $action ) {
            $limit   = min( max( (int) ( $args['limit'] ?? 10 ), 1 ), 50 );
            $status  = sanitize_key( $args['status'] ?? 'all' );
            $post_id = (int) ( $args['post_id'] ?? 0 );

            $query = [
                'number' => $limit,
                'status' => ( 'all' === $status ) ? '' : $status,
            ];
            if ( $post_id > 0 ) {
                $query['post_id'] = $post_id;
            }

            $comments = get_comments( $query );
            $list     = [];

            foreach ( $comments as $c ) {
                $list[] = [
                    'comment_id'   => $c->comment_ID,
                    'post_id'      => $c->comment_post_ID,
                    'post_title'   => get_the_title( $c->comment_post_ID ),
                    'author'       => $c->comment_author,
                    'author_email' => $c->comment_author_email,
                    'content'      => wp_strip_all_tags( $c->comment_content ),
                    'date'         => $c->comment_date,
                    'status'       => wp_get_comment_status( $c->comment_ID ),
                ];
            }

            return [
                'comments' => $list,
                'count'    => count( $list ),
            ];
        }

        // 2. Update comment status (moderate)
        if ( 'update_status' === $action ) {
            if ( ! current_user_can( 'moderate_comments' ) ) {
                throw new Exception( 'Permission denied: Cannot moderate comments.' );
            }

            $cid    = (int) ( $args['comment_id'] ?? 0 );
            $status = sanitize_key( $args['status'] ?? 'approve' );

            if ( ! $cid || ! get_comment( $cid ) ) {
                throw new Exception( "Comment ID {$cid} not found." );
            }

            if ( 'delete' === $status ) {
                $res = wp_delete_comment( $cid, true );
            } else {
                $res = wp_set_comment_status( $cid, $status );
            }

            if ( ! $res ) {
                throw new Exception( "Failed to set comment status to '{$status}'." );
            }

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_comments', $args, null, 'executed' );

            return [
                'success'    => true,
                'action'     => 'update_status',
                'comment_id' => $cid,
                'status'     => $status,
                'audit_id'   => $audit_id,
                'is_turbo'   => $is_turbo,
            ];
        }

        // 3. Reply to comment
        if ( 'reply' === $action ) {
            $cid     = (int) ( $args['comment_id'] ?? 0 );
            $content = sanitize_textarea_field( $args['content'] ?? '' );

            if ( empty( $content ) ) {
                throw new Exception( 'Reply content cannot be empty.' );
            }

            $parent_comment = get_comment( $cid );
            if ( ! $parent_comment ) {
                throw new Exception( "Parent comment ID {$cid} not found." );
            }

            $current_user = wp_get_current_user();
            $new_comment_id = wp_insert_comment( [
                'comment_post_ID'      => $parent_comment->comment_post_ID,
                'comment_parent'       => $cid,
                'comment_content'      => $content,
                'user_id'              => $user_id,
                'comment_author'       => $current_user->display_name ?: 'Administrator',
                'comment_author_email' => $current_user->user_email ?: get_option( 'admin_email' ),
                'comment_approved'     => 1,
            ] );

            if ( ! $new_comment_id ) {
                throw new Exception( 'Failed creating comment reply.' );
            }

            $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_comments', $args, null, 'executed' );

            return [
                'success'        => true,
                'action'         => 'reply',
                'comment_id'     => $new_comment_id,
                'parent_comment' => $cid,
                'audit_id'       => $audit_id,
                'is_turbo'       => $is_turbo,
            ];
        }

        throw new Exception( "Unknown comment action: {$action}" );
    }

    private static function manage_taxonomies( array $args ): array {
        $action   = $args['action'] ?? '';
        $taxonomy = sanitize_key( $args['taxonomy'] ?? 'category' );

        if ( $action === 'create_term' ) {
            if ( ! current_user_can( 'manage_categories' ) ) {
                throw new Exception( 'Permission denied: Cannot manage categories or taxonomies.' );
            }

            $name = sanitize_text_field( $args['term_name'] ?? '' );
            if ( empty( $name ) ) {
                throw new Exception( 'Term name is required.' );
            }
            $term = wp_insert_term( $name, $taxonomy );
            if ( is_wp_error( $term ) ) {
                throw new Exception( 'Failed creating term: ' . $term->get_error_message() );
            }
            return [
                'success'  => true,
                'term_id'  => $term['term_id'],
                'name'     => $name,
                'taxonomy' => $taxonomy,
            ];
        }

        if ( $action === 'assign_terms' ) {
            $post_id = (int) ( $args['post_id'] ?? 0 );
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                throw new Exception( "Permission denied: Cannot edit post #{$post_id}." );
            }

            $raw     = $args['terms'] ?? '';
            $terms   = is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) );
            $res     = wp_set_object_terms( $post_id, $terms, $taxonomy, true );
            if ( is_wp_error( $res ) ) {
                throw new Exception( 'Failed assigning terms: ' . $res->get_error_message() );
            }
            return [
                'success'  => true,
                'post_id'  => $post_id,
                'taxonomy' => $taxonomy,
                'terms'    => $terms,
            ];
        }

        throw new Exception( "Invalid taxonomy action '{$action}'." );
    }

    private static function manage_media( array $args ): array {
        $action = $args['action'] ?? 'search';

        if ( $action === 'search' ) {
            $search = sanitize_text_field( $args['search'] ?? '' );
            $query = [
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => 10,
                's'              => $search,
            ];
            $attachments = get_posts( $query );
            $items = [];
            foreach ( $attachments as $att ) {
                $items[] = [
                    'id'       => $att->ID,
                    'title'    => $att->post_title,
                    'mime'     => $att->post_mime_type,
                    'url'      => wp_get_attachment_url( $att->ID ),
                    'alt_text' => get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
                ];
            }
            return [ 'items' => $items, 'count' => count( $items ) ];
        }

        if ( $action === 'update_meta' ) {
            $media_id = (int) ( $args['media_id'] ?? 0 );
            if ( ! $media_id || get_post_type( $media_id ) !== 'attachment' ) {
                throw new Exception( "Attachment ID {$media_id} not found." );
            }
            if ( ! current_user_can( 'edit_post', $media_id ) ) {
                throw new Exception( "Permission denied: Cannot edit attachment #{$media_id}." );
            }
            if ( isset( $args['alt_text'] ) ) {
                update_post_meta( $media_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt_text'] ) );
            }
            return [ 'success' => true, 'media_id' => $media_id ];
        }

        throw new Exception( "Unknown media action: {$action}" );
    }

    private static function inject_custom_css( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'edit_theme_options' ) && ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator or theme editor capabilities required to inject custom CSS.' );
        }

        $css  = $args['css'] ?? '';
        $mode = $args['mode'] ?? 'append';

        if ( empty( trim( $css ) ) ) {
            throw new Exception( 'CSS content cannot be empty.' );
        }

        $previous_css = wp_get_custom_css();
        $res          = WPChat_Code_Engine::inject_custom_css( $css, $mode );
        $audit_id     = WPChat_Audit_Logger::log(
            $session_id,
            $user_id,
            'wp_inject_custom_css',
            $args,
            [ 'previous_css' => $previous_css ],
            'executed'
        );

        return [
            'success'    => true,
            'summary'    => 'Custom CSS injected into Theme Customizer.',
            'css_length' => $res['css_length'],
            'audit_id'   => $audit_id,
            'is_turbo'   => $is_turbo,
        ];
    }

    private static function inject_header_footer( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'unfiltered_html' ) ) {
            throw new Exception( 'Permission denied: unfiltered_html capability required to inject header/footer scripts.' );
        }

        $title    = $args['title'] ?? 'Custom Script';
        $location = $args['location'] ?? 'js_header';
        $code     = $args['code'] ?? '';

        if ( empty( trim( $code ) ) ) {
            throw new Exception( 'Script code cannot be empty.' );
        }

        $snippet = WPChat_Code_Engine::save_snippet( $title, $location, $code, true );
        $args['snippet_id'] = $snippet['snippet_id'];
        $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_inject_header_footer', $args, null, 'executed' );

        return [
            'success'    => true,
            'snippet_id' => $snippet['snippet_id'],
            'title'      => $title,
            'location'   => $location,
            'audit_id'   => $audit_id,
            'is_turbo'   => $is_turbo,
        ];
    }

    private static function create_code_snippet( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'unfiltered_html' ) || ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
            throw new Exception( 'Permission denied: unfiltered_html capability required and DISALLOW_FILE_MODS must not be active.' );
        }

        $title  = $args['title'] ?? 'Custom Snippet';
        $code   = $args['code'] ?? '';
        $active = ! isset( $args['active'] ) || (bool) $args['active'];

        if ( empty( trim( $code ) ) ) {
            throw new Exception( 'PHP code content cannot be empty.' );
        }

        $snippet = WPChat_Code_Engine::save_snippet( $title, 'php', $code, $active );
        $args['snippet_id'] = $snippet['snippet_id'];
        $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_create_code_snippet', $args, null, 'executed' );

        return [
            'success'    => true,
            'snippet_id' => $snippet['snippet_id'],
            'title'      => $title,
            'type'       => 'php',
            'is_active'  => $active,
            'audit_id'   => $audit_id,
            'is_turbo'   => $is_turbo,
            'message'    => 'PHP snippet syntax verified and safely activated.',
        ];
    }

    private static function toggle_code_snippet( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator capabilities required to manage PHP code snippets.' );
        }

        $snippet_id = (int) ( $args['snippet_id'] ?? 0 );
        $action     = $args['action'] ?? '';

        if ( ! $snippet_id ) {
            throw new Exception( 'Valid snippet ID is required.' );
        }

        if ( $action === 'delete' ) {
            WPChat_Code_Engine::delete_snippet( $snippet_id );
            $msg = "Snippet #{$snippet_id} deleted.";
        } elseif ( $action === 'activate' ) {
            WPChat_Code_Engine::toggle_snippet( $snippet_id, true );
            $msg = "Snippet #{$snippet_id} activated.";
        } elseif ( $action === 'deactivate' ) {
            WPChat_Code_Engine::toggle_snippet( $snippet_id, false );
            $msg = "Snippet #{$snippet_id} deactivated.";
        } else {
            throw new Exception( "Invalid snippet action: {$action}" );
        }

        $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_toggle_code_snippet', $args, null, 'executed' );

        return [
            'success'    => true,
            'snippet_id' => $snippet_id,
            'action'     => $action,
            'message'    => $msg,
            'audit_id'   => $audit_id,
            'is_turbo'   => $is_turbo,
        ];
    }

    private static function list_code_snippets( array $args ): array {
        $type = ( ! empty( $args['type'] ) && $args['type'] !== 'all' ) ? $args['type'] : null;
        $snippets = WPChat_Code_Engine::get_snippets( $type );

        $formatted = [];
        foreach ( $snippets as $s ) {
            $formatted[] = [
                'id'         => (int) $s['id'],
                'title'      => $s['title'],
                'type'       => $s['code_type'],
                'is_active'  => (bool) $s['is_active'],
                'error_log'  => $s['error_log'],
                'created_at' => $s['created_at'],
                'code_preview' => mb_substr( $s['code_content'], 0, 80 ) . '...',
            ];
        }

        return [
            'count'    => count( $formatted ),
            'snippets' => $formatted,
        ];
    }

    /**
     * Module 1: WooCommerce Autonomous Store Manager
     */
    private static function manage_woocommerce( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return [
                'success' => false,
                'error'   => 'WooCommerce is not currently active on this WordPress site.',
            ];
        }

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: WooCommerce store management capabilities required.' );
        }

        $action = $args['action'] ?? 'store_analytics';

        switch ( $action ) {
            case 'list_products':
                $page  = max( 1, (int) ( $args['page'] ?? 1 ) );
                $limit = min( max( (int) ( $args['limit'] ?? 10 ), 1 ), 50 );
                $query_args = [
                    'limit'   => $limit,
                    'page'    => $page,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                ];
                if ( ! empty( $args['search'] ) ) {
                    $query_args['s'] = sanitize_text_field( $args['search'] );
                }
                $products = function_exists( 'wc_get_products' ) ? wc_get_products( $query_args ) : [];
                $items = [];
                foreach ( $products as $p ) {
                    $items[] = [
                        'id'             => $p->get_id(),
                        'name'           => $p->get_name(),
                        'sku'            => $p->get_sku() ?: '—',
                        'price'          => $p->get_price(),
                        'regular_price'  => $p->get_regular_price(),
                        'sale_price'     => $p->get_sale_price() ?: '—',
                        'stock_status'   => $p->get_stock_status(),
                        'stock_quantity' => $p->get_stock_quantity() ?? '—',
                    ];
                }
                return [
                    'success'  => true,
                    'page'     => $page,
                    'limit'    => $limit,
                    'count'    => count( $items ),
                    'products' => $items,
                ];

            case 'update_product':
                $product_id = (int) ( $args['product_id'] ?? 0 );
                $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
                if ( ! $product ) {
                    throw new Exception( "Product #{$product_id} not found." );
                }

                $before_state = [
                    'regular_price'  => $product->get_regular_price(),
                    'sale_price'     => $product->get_sale_price(),
                    'stock_status'   => $product->get_stock_status(),
                    'stock_quantity' => $product->get_stock_quantity(),
                ];

                if ( isset( $args['regular_price'] ) ) {
                    $product->set_regular_price( wc_format_decimal( $args['regular_price'] ) );
                }
                if ( isset( $args['sale_price'] ) ) {
                    $product->set_sale_price( wc_format_decimal( $args['sale_price'] ) );
                }
                if ( ! empty( $args['stock_status'] ) ) {
                    $product->set_stock_status( $args['stock_status'] );
                }
                if ( isset( $args['stock_quantity'] ) ) {
                    $product->set_manage_stock( true );
                    $product->set_stock_quantity( (int) $args['stock_quantity'] );
                }

                $product->save();

                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_woocommerce', $args, $before_state, 'executed' );

                return [
                    'success'    => true,
                    'product_id' => $product_id,
                    'name'       => $product->get_name(),
                    'price'      => $product->get_price(),
                    'audit_id'   => $audit_id,
                    'is_turbo'   => $is_turbo,
                    'message'    => "Product #{$product_id} ({$product->get_name()}) updated successfully.",
                ];

            case 'create_coupon':
                $code = sanitize_text_field( strtolower( trim( $args['coupon_code'] ?? '' ) ) );
                if ( empty( $code ) ) {
                    throw new Exception( 'Coupon code is required.' );
                }

                $discount_type = $args['discount_type'] ?? 'percent';
                $amount        = floatval( $args['amount'] ?? 10 );
                $free_shipping = ! empty( $args['free_shipping'] );
                $expiry_date   = ! empty( $args['expiry_date'] ) ? sanitize_text_field( $args['expiry_date'] ) : '';

                if ( class_exists( 'WC_Coupon' ) ) {
                    $coupon = new WC_Coupon();
                    $coupon->set_code( $code );
                    $coupon->set_discount_type( $discount_type );
                    $coupon->set_amount( $amount );
                    $coupon->set_individual_use( false );
                    $coupon->set_free_shipping( $free_shipping );
                    if ( ! empty( $expiry_date ) ) {
                        $coupon->set_date_expires( $expiry_date );
                    }
                    $coupon_id = $coupon->save();
                } else {
                    throw new Exception( 'WooCommerce Coupon class unavailable.' );
                }

                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_woocommerce', $args, null, 'executed' );

                return [
                    'success'     => true,
                    'coupon_id'   => $coupon_id,
                    'coupon_code' => strtoupper( $code ),
                    'type'        => $discount_type,
                    'amount'      => $amount,
                    'expires'     => $expiry_date ?: 'Never',
                    'audit_id'    => $audit_id,
                    'is_turbo'    => $is_turbo,
                    'message'     => "Coupon `{$code}` created successfully.",
                ];

            case 'list_orders':
                $page  = max( 1, (int) ( $args['page'] ?? 1 ) );
                $limit = min( max( (int) ( $args['limit'] ?? 10 ), 1 ), 50 );
                $order_args = [
                    'limit'   => $limit,
                    'page'    => $page,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                ];
                if ( ! empty( $args['order_status'] ) ) {
                    $order_args['status'] = sanitize_text_field( $args['order_status'] );
                }
                if ( ! empty( $args['search'] ) ) {
                    $order_args['s'] = sanitize_text_field( $args['search'] );
                }
                $orders = function_exists( 'wc_get_orders' ) ? wc_get_orders( $order_args ) : [];
                $items = [];
                foreach ( $orders as $o ) {
                    $items[] = [
                        'id'            => $o->get_id(),
                        'order_number'  => $o->get_order_number(),
                        'status'        => $o->get_status(),
                        'total'         => $o->get_total(),
                        'currency'      => $o->get_currency(),
                        'date'          => $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i' ) : '',
                        'customer_name' => $o->get_billing_first_name() . ' ' . $o->get_billing_last_name(),
                        'items_count'   => $o->get_item_count(),
                    ];
                }
                return [
                    'success' => true,
                    'page'    => $page,
                    'limit'   => $limit,
                    'count'   => count( $items ),
                    'orders'  => $items,
                ];

            case 'store_analytics':
            default:
                global $wpdb;
                $products_count = (int) wp_count_posts( 'product' )->publish;

                $processing = 0;
                $completed  = 0;
                $on_hold    = 0;
                $pending    = 0;

                if ( function_exists( 'wc_orders_count' ) ) {
                    $processing = (int) wc_orders_count( 'processing' );
                    $completed  = (int) wc_orders_count( 'completed' );
                    $on_hold    = (int) wc_orders_count( 'on-hold' );
                    $pending    = (int) wc_orders_count( 'pending' );
                } else {
                    $orders_counts = (array) wp_count_posts( 'shop_order' );
                    $processing    = (int) ( $orders_counts['wc-processing'] ?? 0 );
                    $completed     = (int) ( $orders_counts['wc-completed'] ?? 0 );
                    $on_hold       = (int) ( $orders_counts['wc-on-hold'] ?? 0 );
                    $pending       = (int) ( $orders_counts['wc-pending'] ?? 0 );
                }

                $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
                $symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : '$';

                return [
                    'success'        => true,
                    'currency'       => $currency,
                    'currency_symbol'=> $symbol,
                    'total_products' => $products_count,
                    'orders_summary' => [
                        'processing' => $processing,
                        'completed'  => $completed,
                        'on_hold'    => $on_hold,
                        'pending'    => $pending,
                    ],
                ];
        }
    }

    /**
     * Module 2: Auto-SEO & Content Optimizer
     */
    private static function manage_seo( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        $action = $args['action'] ?? 'audit_post_seo';

        switch ( $action ) {
            case 'audit_post_seo':
                $post_id = (int) ( $args['post_id'] ?? 0 );
                $post = get_post( $post_id );
                if ( ! $post ) {
                    throw new Exception( "Post #{$post_id} not found." );
                }

                $title_len = mb_strlen( $post->post_title );
                $words     = str_word_count( wp_strip_all_tags( $post->post_content ) );
                $has_h1    = preg_match( '/<h1[^>]*>/i', $post->post_content );
                $h2_count  = preg_match_all( '/<h2[^>]*>/i', $post->post_content );
                $img_count = preg_match_all( '/<img[^>]*>/i', $post->post_content );

                $meta_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
                    ?: get_post_meta( $post_id, 'rank_math_description', true )
                    ?: $post->post_excerpt;

                $issues = [];
                if ( $title_len < 30 || $title_len > 60 ) {
                    $issues[] = "Title length ({$title_len} chars) is outside optimal 40-60 characters.";
                }
                if ( $words < 300 ) {
                    $issues[] = "Content is brief ({$words} words). Consider expanding to at least 300 words.";
                }
                if ( empty( $meta_desc ) ) {
                    $issues[] = "Missing meta description for search engine snippets.";
                }
                if ( $img_count === 0 ) {
                    $issues[] = "No images found in content.";
                }

                $score = max( 20, 100 - ( count( $issues ) * 20 ) );

                return [
                    'success'     => true,
                    'post_id'     => $post_id,
                    'title'       => $post->post_title,
                    'word_count'  => $words,
                    'seo_score'   => "{$score}/100",
                    'issues'      => $issues,
                    'meta_desc'   => $meta_desc ?: 'None set',
                    'suggestions' => empty( $issues ) ? 'Content meets primary SEO best practices!' : 'Use `wp_manage_seo` with `update_meta` to optimize tags.',
                ];

            case 'update_meta':
                $post_id = (int) ( $args['post_id'] ?? 0 );
                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                    throw new Exception( "Permission denied: Cannot edit post #{$post_id}." );
                }

                $post = get_post( $post_id );
                if ( ! $post ) {
                    throw new Exception( "Post #{$post_id} not found." );
                }

                $meta_title = sanitize_text_field( $args['meta_title'] ?? '' );
                $meta_desc  = sanitize_textarea_field( $args['meta_description'] ?? '' );
                $focus_kw   = sanitize_text_field( $args['focus_keyword'] ?? '' );

                if ( ! empty( $meta_title ) ) {
                    update_post_meta( $post_id, '_yoast_wpseo_title', $meta_title );
                    update_post_meta( $post_id, 'rank_math_title', $meta_title );
                }
                if ( ! empty( $meta_desc ) ) {
                    update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
                    update_post_meta( $post_id, 'rank_math_description', $meta_desc );
                    wp_update_post( [ 'ID' => $post_id, 'post_excerpt' => $meta_desc ] );
                }
                if ( ! empty( $focus_kw ) ) {
                    update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_kw );
                    update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_kw );
                }

                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_seo', $args, null, 'executed' );

                return [
                    'success'    => true,
                    'post_id'    => $post_id,
                    'meta_title' => $meta_title,
                    'meta_desc'  => $meta_desc,
                    'audit_id'   => $audit_id,
                    'is_turbo'   => $is_turbo,
                    'message'    => "SEO metadata updated for post #{$post_id}.",
                ];

            case 'fix_missing_alt_tags':
                $query = new WP_Query( [
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'post_status'    => 'inherit',
                    'posts_per_page' => 20,
                ] );

                $fixed = [];
                foreach ( $query->posts as $attachment ) {
                    $current_alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
                    if ( empty( $current_alt ) ) {
                        $clean_name = ucwords( str_replace( [ '-', '_', '.' ], ' ', pathinfo( $attachment->post_title ?: 'Image', PATHINFO_FILENAME ) ) );
                        update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $clean_name );
                        $fixed[] = [
                            'id'      => $attachment->ID,
                            'title'   => $attachment->post_title,
                            'new_alt' => $clean_name,
                        ];
                    }
                }

                return [
                    'success'     => true,
                    'fixed_count' => count( $fixed ),
                    'images'      => $fixed,
                    'message'     => count( $fixed ) > 0 ? "Auto-generated alt text for " . count( $fixed ) . " images." : "All checked images already have alt text configured.",
                ];

            case 'check_broken_links':
                $recent = get_posts( [ 'numberposts' => 5, 'post_status' => 'publish' ] );
                $urls_found = [];
                foreach ( $recent as $p ) {
                    preg_match_all( '/href=[\'"]([^\'"]+)[\'"]/i', $p->post_content, $matches );
                    if ( ! empty( $matches[1] ) ) {
                        foreach ( $matches[1] as $url ) {
                            if ( str_starts_with( $url, 'http' ) ) {
                                $urls_found[] = [ 'post_id' => $p->ID, 'url' => $url ];
                            }
                        }
                    }
                }
                return [
                    'success'      => true,
                    'scanned_posts'=> count( $recent ),
                    'links_found'  => count( $urls_found ),
                    'sample_links' => array_slice( $urls_found, 0, 10 ),
                ];

            default:
                throw new Exception( "Unknown SEO action: {$action}" );
        }
    }

    /**
     * Module 3: Performance & Cache Doctor
     */
    private static function manage_performance( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator capabilities required for performance management.' );
        }

        $action = $args['action'] ?? 'health_check';

        switch ( $action ) {
            case 'flush_cache':
                $flushed = [];

                // LiteSpeed Cache
                if ( has_action( 'litespeed_purge_all' ) ) {
                    do_action( 'litespeed_purge_all' );
                    $flushed[] = 'LiteSpeed Cache';
                }

                // WP Rocket
                if ( function_exists( 'rocket_clean_domain' ) ) {
                    rocket_clean_domain();
                    $flushed[] = 'WP Rocket';
                }

                // WP Super Cache
                if ( function_exists( 'wp_cache_clear_cache' ) ) {
                    wp_cache_clear_cache();
                    $flushed[] = 'WP Super Cache';
                }

                // Core cache flush
                wp_cache_flush();
                $flushed[] = 'WordPress Object Cache';

                return [
                    'success' => true,
                    'flushed' => $flushed,
                    'message' => 'Cache purged across: ' . implode( ', ', $flushed ),
                ];

            case 'clean_transients':
                global $wpdb;
                $expired_timeouts = $wpdb->get_col(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()"
                );
                $deleted = 0;
                if ( ! empty( $expired_timeouts ) ) {
                    $keys_to_delete = [];
                    foreach ( $expired_timeouts as $timeout_key ) {
                        $transient_key    = substr( $timeout_key, strlen( '_transient_timeout_' ) );
                        $keys_to_delete[] = $timeout_key;
                        $keys_to_delete[] = '_transient_' . $transient_key;
                    }
                    $chunks = array_chunk( $keys_to_delete, 500 );
                    foreach ( $chunks as $chunk ) {
                        $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
                        $res = $wpdb->query(
                            $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name IN ($placeholders)", ...$chunk )
                        );
                        if ( is_numeric( $res ) ) {
                            $deleted += (int) $res;
                        }
                    }
                }
                return [
                    'success'       => true,
                    'cleaned_count' => (int) $deleted,
                    'message'       => "Cleaned {$deleted} expired transient and timeout records from options table.",
                ];

            case 'clean_revisions':
                global $wpdb;
                $deleted = $wpdb->query(
                    "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_status = 'inherit' AND post_modified < DATE_SUB(NOW(), INTERVAL 30 DAY)"
                );
                return [
                    'success' => true,
                    'deleted_revisions' => (int) $deleted,
                    'message' => "Removed {$deleted} legacy post revisions older than 30 days.",
                ];

            case 'health_check':
            default:
                global $wpdb;
                $db_size_res = $wpdb->get_row(
                    "SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = DATABASE()"
                );
                $db_size_mb = $db_size_res ? round( $db_size_res->size / ( 1024 * 1024 ), 2 ) : 0;

                return [
                    'success'          => true,
                    'php_version'      => PHP_VERSION,
                    'memory_limit'     => ini_get( 'memory_limit' ),
                    'max_upload'       => ini_get( 'upload_max_filesize' ),
                    'max_execution'    => ini_get( 'max_execution_time' ) . 's',
                    'object_cache'     => wp_using_ext_object_cache() ? 'External Persistent Cache Active' : 'Default In-Memory',
                    'opcache_active'   => extension_loaded( 'Zend OPcache' ) && ini_get( 'opcache.enable' ),
                    'database_size_mb' => "{$db_size_mb} MB",
                ];
        }
    }

    /**
     * Module 4: Database Ops & Safe Optimization
     */
    private static function manage_database( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator capabilities required for database operations.' );
        }

        global $wpdb;
        $action = $args['action'] ?? 'query_summary';

        switch ( $action ) {
            case 'query_summary':
                $query = trim( $args['query'] ?? '' );
                if ( empty( $query ) ) {
                    throw new Exception( 'SQL query is required.' );
                }

                // Strict read-only guard
                $upper = strtoupper( $query );
                if ( ! str_starts_with( $upper, 'SELECT' ) && ! str_starts_with( $upper, 'SHOW' ) && ! str_starts_with( $upper, 'DESCRIBE' ) ) {
                    throw new Exception( 'Security Policy: Only read-only SELECT or SHOW queries are permitted in query_summary.' );
                }

                $users_tbl     = preg_quote( $wpdb->users, '/' );
                $usermeta_tbl  = preg_quote( $wpdb->usermeta, '/' );
                if ( preg_match( "/\b(?:{$users_tbl}|{$usermeta_tbl}|users|usermeta)\b/i", $query ) ) {
                    throw new Exception( 'Security Policy: Access to user credentials and metadata tables is forbidden via query_summary.' );
                }

                foreach ( [ 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE', 'UNION', 'OUTFILE', 'DUMPFILE', 'LOAD_FILE', 'USER_PASS', 'USER_ACTIVATION_KEY' ] as $forbidden ) {
                    if ( preg_match( "/\b{$forbidden}\b/i", $query ) ) {
                        throw new Exception( "Security Policy: {$forbidden} statement blocked." );
                    }
                }

                $page   = max( 1, (int) ( $args['page'] ?? 1 ) );
                $limit  = min( max( (int) ( $args['limit'] ?? 25 ), 1 ), 100 );
                $offset = ( $page - 1 ) * $limit;

                if ( ! preg_match( '/\bLIMIT\b/i', $query ) ) {
                    $query .= sprintf( ' LIMIT %d OFFSET %d', $limit, $offset );
                }

                $results = $wpdb->get_results( $query, ARRAY_A );

                // Redact sensitive credential keys if returned
                if ( is_array( $results ) ) {
                    foreach ( $results as &$row ) {
                        if ( is_array( $row ) ) {
                            unset( $row['user_pass'], $row['user_activation_key'], $row['session_tokens'] );
                        }
                    }
                    unset( $row );
                }

                return [
                    'success'    => true,
                    'rows_count' => count( $results ),
                    'rows'       => $results,
                ];

            case 'optimize_tables':
                $tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
                $optimized = [];
                foreach ( $tables as $table ) {
                    $wpdb->query( "OPTIMIZE TABLE `{$table}`" );
                    $optimized[] = $table;
                }
                return [
                    'success'   => true,
                    'count'     => count( $optimized ),
                    'tables'    => $optimized,
                    'message'   => 'Successfully optimized ' . count( $optimized ) . ' database tables.',
                ];

            case 'search_replace_preview':
                $search = $args['search_str'] ?? '';
                if ( empty( $search ) ) {
                    throw new Exception( 'search_str is required.' );
                }
                $count = (int) $wpdb->get_var(
                    $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_title LIKE %s", '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' )
                );
                return [
                    'success'       => true,
                    'search_string' => $search,
                    'matches_found' => $count,
                    'message'       => "Found {$count} posts containing `{$search}`.",
                ];

            case 'search_replace':
                $search  = $args['search_str'] ?? '';
                $replace = $args['replace_str'] ?? '';
                if ( empty( $search ) ) {
                    throw new Exception( 'search_str is required.' );
                }

                $updated = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s), post_title = REPLACE(post_title, %s, %s)",
                        $search, $replace, $search, $replace
                    )
                );

                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_database', $args, null, 'executed' );

                return [
                    'success'       => true,
                    'updated_posts' => (int) $updated,
                    'search'        => $search,
                    'replace'       => $replace,
                    'audit_id'      => $audit_id,
                    'is_turbo'      => $is_turbo,
                    'message'       => "Replaced `{$search}` with `{$replace}` across {$updated} post records.",
                ];

            default:
                throw new Exception( "Unknown database action: {$action}" );
        }
    }

    /**
     * Module 5: Security Sentinel & File Permissions
     */
    private static function manage_security( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator capabilities required for security inspection.' );
        }

        $action = $args['action'] ?? 'audit_admins';

        switch ( $action ) {
            case 'audit_admins':
                $admins = get_users( [ 'role' => 'administrator' ] );
                $list = [];
                $recommendations = [];

                foreach ( $admins as $u ) {
                    $is_default_admin = ( 'admin' === strtolower( $u->user_login ) );
                    if ( $is_default_admin ) {
                        $recommendations[] = "User `admin` is using default username. Rename for enhanced brute-force defense.";
                    }
                    $list[] = [
                        'id'           => $u->ID,
                        'user_login'   => $u->user_login,
                        'display_name' => $u->display_name,
                        'email'        => $u->user_email,
                        'registered'   => $u->user_registered,
                    ];
                }

                return [
                    'success'         => true,
                    'total_admins'    => count( $list ),
                    'administrators'  => $list,
                    'recommendations' => $recommendations,
                ];

            case 'check_file_permissions':
                $checks = [
                    'wp-config.php' => ABSPATH . 'wp-config.php',
                    '.htaccess'     => ABSPATH . '.htaccess',
                    'uploads_dir'   => wp_upload_dir()['basedir'],
                ];

                $results = [];
                foreach ( $checks as $label => $path ) {
                    if ( file_exists( $path ) ) {
                        $perms = substr( sprintf( '%o', fileperms( $path ) ), -4 );
                        $writable = is_writable( $path );
                        $results[ $label ] = [
                            'path'        => $path,
                            'permissions' => $perms,
                            'writable'    => $writable,
                            'status'      => ( 'wp-config.php' === $label && $writable ) ? 'Warning: Config file is writable by webserver' : 'OK',
                        ];
                    }
                }
                return [
                    'success' => true,
                    'files'   => $results,
                ];

            case 'audit_plugins':
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                $all = get_plugins();
                $active = get_option( 'active_plugins', [] );

                $inactive = [];
                foreach ( $all as $file => $meta ) {
                    if ( ! in_array( $file, $active, true ) ) {
                        $inactive[] = [
                            'name'    => $meta['Name'],
                            'version' => $meta['Version'],
                            'file'    => $file,
                        ];
                    }
                }
                return [
                    'success'          => true,
                    'total_installed'  => count( $all ),
                    'active_count'     => count( $active ),
                    'inactive_count'   => count( $inactive ),
                    'inactive_plugins' => $inactive,
                    'advice'           => count( $inactive ) > 3 ? 'Remove unused inactive plugins to minimize attack surface.' : 'Plugin footprint is clean.',
                ];

            case 'scan_debug_log':
                $log_file = WP_CONTENT_DIR . '/debug.log';
                if ( ! file_exists( $log_file ) ) {
                    return [
                        'success' => true,
                        'found'   => false,
                        'message' => 'No debug.log file found. Either WP_DEBUG_LOG is off or no errors occurred.',
                        'entries' => [],
                    ];
                }

                $size = filesize( $log_file );
                if ( $size <= 0 ) {
                    return [
                        'success'       => true,
                        'found'         => true,
                        'file_size_kb'  => 0,
                        'fatal_errors'  => 0,
                        'warnings'      => 0,
                        'recent_errors' => [],
                        'message'       => 'debug.log file is empty (0 bytes).',
                    ];
                }

                $read_size = min( $size, 32768 );
                $fp = fopen( $log_file, 'r' );
                if ( $fp ) {
                    fseek( $fp, -$read_size, SEEK_END );
                    $raw = fread( $fp, $read_size );
                    fclose( $fp );
                } else {
                    $raw = '';
                }

                $lines = explode( "\n", trim( $raw ) );
                $recent_lines = array_slice( $lines, -25 );

                $fatals   = 0;
                $warnings = 0;
                $entries  = [];

                foreach ( $recent_lines as $line ) {
                    $line = trim( $line );
                    if ( empty( $line ) ) {
                        continue;
                    }
                    // Sanitize file system paths and passwords
                    $line = str_replace( [ ABSPATH, WP_CONTENT_DIR ], [ '[ROOT]/', '[WP_CONTENT]/' ], $line );
                    if ( defined( 'DB_PASSWORD' ) && strlen( DB_PASSWORD ) > 3 ) {
                        $line = str_replace( DB_PASSWORD, '[REDACTED]', $line );
                    }

                    if ( stripos( $line, 'PHP Fatal' ) !== false || stripos( $line, 'Parse error' ) !== false ) {
                        $fatals++;
                        $entries[] = [ 'type' => 'fatal', 'line' => substr( $line, 0, 200 ) ];
                    } elseif ( stripos( $line, 'PHP Warning' ) !== false || stripos( $line, 'Notice' ) !== false ) {
                        $warnings++;
                        $entries[] = [ 'type' => 'warning', 'line' => substr( $line, 0, 200 ) ];
                    }
                }

                return [
                    'success'       => true,
                    'found'         => true,
                    'file_size_kb'  => round( $size / 1024, 2 ),
                    'fatal_errors'  => $fatals,
                    'warnings'      => $warnings,
                    'recent_errors' => array_slice( $entries, -10 ),
                ];

            default:
                throw new Exception( "Unknown security action: {$action}" );
        }
    }

    /**
     * Module 6: User & Role Manager
     */
    private static function manage_users( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        $action = $args['action'] ?? 'list_users';

        switch ( $action ) {
            case 'list_users':
                if ( ! current_user_can( 'list_users' ) ) {
                    throw new Exception( 'Permission denied: Cannot list users.' );
                }

                $role   = ( ! empty( $args['role'] ) && 'all' !== $args['role'] ) ? sanitize_text_field( $args['role'] ) : '';
                $page   = max( 1, (int) ( $args['page'] ?? 1 ) );
                $limit  = min( max( (int) ( $args['limit'] ?? 20 ), 1 ), 100 );
                $offset = ( $page - 1 ) * $limit;
                $query_args = [
                    'number' => $limit,
                    'offset' => $offset,
                ];
                if ( ! empty( $role ) ) {
                    $query_args['role'] = $role;
                }
                $users = get_users( $query_args );
                $list  = [];
                foreach ( $users as $u ) {
                    $list[] = [
                        'id'           => $u->ID,
                        'login'        => $u->user_login,
                        'display_name' => $u->display_name,
                        'email'        => $u->user_email,
                        'roles'        => $u->roles,
                        'registered'   => $u->user_registered,
                    ];
                }
                return [
                    'success' => true,
                    'page'    => $page,
                    'limit'   => $limit,
                    'count'   => count( $list ),
                    'users'   => $list,
                ];

            case 'create_user':
                if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'manage_options' ) ) {
                    throw new Exception( 'Permission denied: Cannot create users.' );
                }

                $username = sanitize_user( $args['username'] ?? '' );
                $email    = sanitize_email( $args['email'] ?? '' );
                $role     = sanitize_text_field( $args['role'] ?? 'subscriber' );

                if ( 'administrator' === $role && ! current_user_can( 'manage_options' ) ) {
                    throw new Exception( 'Permission denied: Only administrators can create administrator accounts.' );
                }

                if ( empty( $username ) || empty( $email ) ) {
                    throw new Exception( 'username and email are required to create a user.' );
                }
                if ( username_exists( $username ) ) {
                    throw new Exception( "Username `{$username}` already exists." );
                }
                if ( email_exists( $email ) ) {
                    throw new Exception( "Email `{$email}` is already registered." );
                }

                $password = wp_generate_password( 16, true );
                $new_id   = wp_create_user( $username, $password, $email );
                if ( is_wp_error( $new_id ) ) {
                    throw new Exception( $new_id->get_error_message() );
                }

                $u = new WP_User( $new_id );
                $u->set_role( $role );

                // Dispatch standard WordPress welcome/password notification
                wp_new_user_notification( $new_id, null, 'both' );

                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_users', $args, null, 'executed' );

                return [
                    'success'         => true,
                    'user_id'         => $new_id,
                    'username'        => $username,
                    'email'           => $email,
                    'role'            => $role,
                    'password_status' => 'Generated securely and standard user notification sent.',
                    'audit_id'        => $audit_id,
                    'is_turbo'        => $is_turbo,
                    'message'         => "User `{$username}` created with role `{$role}`.",
                ];

            case 'update_user_role':
                if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
                    throw new Exception( 'Permission denied: Cannot modify user roles.' );
                }

                $target_id = (int) ( $args['user_id'] ?? 0 );
                $new_role  = sanitize_text_field( $args['new_role'] ?? '' );

                if ( 'administrator' === $new_role && ! current_user_can( 'manage_options' ) ) {
                    throw new Exception( 'Permission denied: Only administrators can promote users to administrator.' );
                }

                if ( $target_id <= 0 || empty( $new_role ) ) {
                    throw new Exception( 'user_id and new_role are required.' );
                }

                // Protect currently logged-in user from self-demotion
                if ( $target_id === $user_id && 'administrator' !== $new_role ) {
                    throw new Exception( 'Cannot demote the active administrator account to prevent lockout.' );
                }

                $u = get_user_by( 'ID', $target_id );
                if ( ! $u ) {
                    throw new Exception( "User #{$target_id} not found." );
                }

                $before_roles = $u->roles;
                $u->set_role( $new_role );

                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_users', $args, [ 'roles' => $before_roles ], 'executed' );

                return [
                    'success'   => true,
                    'user_id'   => $target_id,
                    'username'  => $u->user_login,
                    'new_role'  => $new_role,
                    'audit_id'  => $audit_id,
                    'is_turbo'  => $is_turbo,
                    'message'   => "Role updated to `{$new_role}` for user `{$u->user_login}`.",
                ];

            default:
                throw new Exception( "Unknown user action: {$action}" );
        }
    }

    /**
     * Module 7: Scheduled Tasks & WP-Cron Manager
     */
    private static function manage_cron( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator capabilities required for cron management.' );
        }

        $action = $args['action'] ?? 'list_cron_jobs';

        switch ( $action ) {
            case 'list_cron_jobs':
                $crons = _get_cron_array();
                if ( empty( $crons ) ) {
                    return [ 'success' => true, 'count' => 0, 'jobs' => [] ];
                }

                $list = [];
                foreach ( $crons as $timestamp => $hooks ) {
                    foreach ( $hooks as $hook => $data ) {
                        $first_key = array_key_first( $data );
                        $schedule  = $data[ $first_key ]['schedule'] ?? 'single';
                        $list[] = [
                            'hook'       => $hook,
                            'next_run'   => date( 'Y-m-d H:i:s', $timestamp ),
                            'is_overdue' => ( $timestamp < time() ),
                            'schedule'   => $schedule,
                        ];
                    }
                }

                // Return next 15 upcoming/overdue jobs
                return [
                    'success' => true,
                    'count'   => count( $list ),
                    'jobs'    => array_slice( $list, 0, 15 ),
                ];

            case 'run_cron_job':
                if ( ! current_user_can( 'manage_options' ) ) {
                    throw new Exception( 'Permission denied: Administrator capabilities required to trigger scheduled tasks.' );
                }

                $hook = sanitize_text_field( $args['hook_name'] ?? '' );
                if ( empty( $hook ) ) {
                    throw new Exception( 'hook_name is required to run a cron job.' );
                }

                do_action( $hook );
                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_cron', $args, null, 'executed' );

                return [
                    'success'  => true,
                    'hook'     => $hook,
                    'audit_id' => $audit_id,
                    'is_turbo' => $is_turbo,
                    'message'  => "Triggered cron hook `{$hook}` successfully.",
                ];

            default:
                throw new Exception( "Unknown cron action: {$action}" );
        }
    }

    /**
     * Module 8: Snapshot & Restore Rollback Engine
     */
    private static function manage_backup( array $args, int $session_id, int $user_id, bool $is_turbo ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permission denied: Administrator capabilities required for snapshots.' );
        }

        $action = $args['action'] ?? 'create_snapshot';

        switch ( $action ) {
            case 'create_snapshot':
                $snapshot_id = 'snap_' . date( 'Ymd_His' ) . '_' . wp_generate_password( 6, false );
                $payload = [
                    'id'          => $snapshot_id,
                    'created_at'  => current_time( 'mysql' ),
                    'user_id'     => $user_id,
                    'options'     => [
                        'blogname'        => get_option( 'blogname' ),
                        'blogdescription' => get_option( 'blogdescription' ),
                    ],
                    'custom_css'  => wp_get_custom_css(),
                    'snippets'    => WPChat_Code_Engine::get_snippets(),
                ];

                set_transient( 'wpchat_snapshot_' . $snapshot_id, $payload, 86400 * 7 ); // 7 days retention

                // Track snapshot index
                $index = get_option( 'wpchat_snapshot_index', [] );
                $index[] = [
                    'id'         => $snapshot_id,
                    'created_at' => $payload['created_at'],
                ];
                update_option( 'wpchat_snapshot_index', array_slice( $index, -10 ) );

                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_backup', $args, null, 'executed' );

                return [
                    'success'     => true,
                    'snapshot_id' => $snapshot_id,
                    'audit_id'    => $audit_id,
                    'is_turbo'    => $is_turbo,
                    'message'     => "Snapshot `{$snapshot_id}` created. Restore point active.",
                ];

            case 'list_snapshots':
                $index = get_option( 'wpchat_snapshot_index', [] );
                return [
                    'success'   => true,
                    'count'     => count( $index ),
                    'snapshots' => $index,
                ];

            case 'restore_snapshot':
                $snapshot_id = sanitize_text_field( $args['snapshot_id'] ?? '' );
                if ( empty( $snapshot_id ) ) {
                    throw new Exception( 'snapshot_id is required.' );
                }

                $data = get_transient( 'wpchat_snapshot_' . $snapshot_id );
                if ( ! is_array( $data ) ) {
                    throw new Exception( "Snapshot `{$snapshot_id}` not found or expired." );
                }

                if ( isset( $data['options']['blogname'] ) ) {
                    update_option( 'blogname', $data['options']['blogname'] );
                }
                if ( isset( $data['options']['blogdescription'] ) ) {
                    update_option( 'blogdescription', $data['options']['blogdescription'] );
                }
                if ( isset( $data['custom_css'] ) ) {
                    wp_update_custom_css_post( $data['custom_css'] );
                }

                $audit_id = WPChat_Audit_Logger::log( $session_id, $user_id, 'wp_manage_backup', $args, null, 'executed' );

                return [
                    'success'     => true,
                    'snapshot_id' => $snapshot_id,
                    'audit_id'    => $audit_id,
                    'is_turbo'    => $is_turbo,
                    'message'     => "Restored configuration from snapshot `{$snapshot_id}`.",
                ];

            default:
                throw new Exception( "Unknown backup action: {$action}" );
        }
    }
}
