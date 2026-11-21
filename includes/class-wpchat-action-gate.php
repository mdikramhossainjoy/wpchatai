<?php
/**
 * Action Gate & Confirmation Token Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_Action_Gate {

    const MODE_ALWAYS_ASK = 'always_ask';
    const MODE_COPILOT    = 'copilot';
    const MODE_TURBO      = 'turbo';

    /**
     * Determine if tool call requires user approval
     */
    public static function requires_approval( string $tool_name, array $args, string $mode ): bool {
        if ( 'on' === get_option( 'wpchat_killswitch', 'off' ) ) {
            throw new Exception( 'WPChat emergency killswitch is active. All tool executions are halted.' );
        }
        $blocked = (array) get_option( 'wpchat_blocked_tools', [] );
        if ( in_array( $tool_name, $blocked, true ) ) {
            throw new Exception( "Tool '{$tool_name}' is currently blocked by administrator settings." );
        }

        $risk = WPChat_Tool_Registry::get_risk_level( $tool_name, $args );

        if ( 'blocked' === $risk ) {
            throw new Exception( "Security policy blocked tool: {$tool_name}" );
        }

        if ( 'read' === $risk ) {
            return false;
        }

        switch ( $mode ) {
            case self::MODE_ALWAYS_ASK:
                return true; // Always Ask mode: prompt confirmation for all modifications

            case self::MODE_TURBO:
                if ( 'wp_execute_php' === $tool_name ) {
                    return true; // Direct PHP execution strictly requires human confirmation even in Turbo
                }
                return false; // Turbo mode: auto-runs all allowed writes

            case self::MODE_COPILOT:
            default:
                // Copilot mode: High-risk/destructive commands strictly require approval based on server policy
                return 'high' === $risk;
        }
    }

    /**
     * Create a pending confirmation token and diff data
     */
    public static function create_pending_action( int $session_id, int $user_id, string $tool_name, array $args, ?string $tool_call_id = null ): array {
        $token = wp_generate_password( 24, false );

        // Generate diff / preview info
        $preview = self::generate_preview_data( $tool_name, $args );

        $payload = [
            'session_id'   => $session_id,
            'user_id'      => $user_id,
            'tool_name'    => $tool_name,
            'tool_args'    => $args,
            'tool_call_id' => $tool_call_id,
            'preview'      => $preview,
            'created_at'   => time(),
        ];

        // Dual storage: Fast transient (10 mins) + persistent DB option fallback
        set_transient( 'wpchat_act_' . $token, $payload, 600 );
        set_transient( 'wpchat_action_' . $token, $payload, 600 );

        $tokens = get_option( 'wpchat_pending_tokens', [] );
        if ( ! is_array( $tokens ) ) {
            $tokens = [];
        }
        $tokens[ $token ] = $payload;

        // Prune tokens older than 1 hour to prevent bloat
        $now = time();
        foreach ( $tokens as $t => $item ) {
            if ( isset( $item['created_at'] ) && ( $now - $item['created_at'] > 3600 ) ) {
                unset( $tokens[ $t ] );
            }
        }
        update_option( 'wpchat_pending_tokens', $tokens, false );

        return [
            'token'      => $token,
            'tool_name'  => $tool_name,
            'args'       => $args,
            'preview'    => $preview,
            'expires_in' => 600,
        ];
    }

    /**
     * Retrieve and validate pending action token
     */
    public static function get_pending_action( string $token ): ?array {
        if ( empty( $token ) ) {
            return null;
        }

        // Check primary short transient
        $data = get_transient( 'wpchat_act_' . $token );
        if ( is_array( $data ) ) {
            return $data;
        }

        // Check persistent DB option fallback with 600s expiry
        $tokens = get_option( 'wpchat_pending_tokens', [] );
        if ( is_array( $tokens ) && isset( $tokens[ $token ] ) && is_array( $tokens[ $token ] ) ) {
            $item = $tokens[ $token ];
            if ( isset( $item['created_at'] ) && ( time() - (int) $item['created_at'] > 600 ) ) {
                self::remove_pending_action( $token );
                return null;
            }
            return $item;
        }

        // Check legacy transient
        $legacy = get_transient( 'wpchat_action_' . $token );
        if ( is_array( $legacy ) ) {
            return $legacy;
        }

        return null;
    }

    /**
     * Remove pending action token from all storages
     */
    public static function remove_pending_action( string $token ): void {
        delete_transient( 'wpchat_act_' . $token );
        delete_transient( 'wpchat_action_' . $token );

        $tokens = get_option( 'wpchat_pending_tokens', [] );
        if ( is_array( $tokens ) ) {
            if ( isset( $tokens[ $token ] ) ) {
                unset( $tokens[ $token ] );
            }
            if ( empty( $tokens ) ) {
                delete_option( 'wpchat_pending_tokens' );
            } else {
                update_option( 'wpchat_pending_tokens', $tokens, false );
            }
        }
    }

    /**
     * Generate visual before/after preview for confirmation cards
     */
    private static function generate_preview_data( string $tool_name, array $args ): array {
        $preview = [
            'summary'     => '',
            'target_name' => '',
            'diff'        => [],
        ];

        switch ( $tool_name ) {
            case 'wp_create_post':
                $preview['summary']     = 'Create new ' . ( $args['post_type'] ?? 'post' );
                $preview['target_name'] = $args['post_title'] ?? 'Untitled';
                $preview['diff']        = [
                    'Title'  => [ 'before' => '(New)', 'after' => $args['post_title'] ?? '' ],
                    'Status' => [ 'before' => 'None', 'after' => $args['post_status'] ?? 'draft' ],
                    'Type'   => [ 'before' => 'None', 'after' => $args['post_type'] ?? 'post' ],
                ];
                break;

            case 'wp_update_post':
                $post_id  = (int) ( $args['post_id'] ?? 0 );
                $existing = get_post( $post_id );
                $preview['summary']     = "Update Post #{$post_id}";
                $preview['target_name'] = $existing ? $existing->post_title : "Post #{$post_id}";
                $diff = [];
                if ( isset( $args['post_title'] ) && $existing && $existing->post_title !== $args['post_title'] ) {
                    $diff['Title'] = [ 'before' => $existing->post_title, 'after' => $args['post_title'] ];
                }
                if ( isset( $args['post_status'] ) && $existing && $existing->post_status !== $args['post_status'] ) {
                    $diff['Status'] = [ 'before' => $existing->post_status, 'after' => $args['post_status'] ];
                }
                if ( isset( $args['post_content'] ) ) {
                    $diff['Content'] = [
                        'before' => mb_substr( strip_tags( $existing->post_content ?? '' ), 0, 100 ) . '...',
                        'after'  => mb_substr( strip_tags( $args['post_content'] ), 0, 100 ) . '...',
                    ];
                }
                $preview['diff'] = $diff;
                break;

            case 'wp_delete_post':
                $post_id  = (int) ( $args['post_id'] ?? 0 );
                $existing = get_post( $post_id );
                $preview['summary']     = ( ! empty( $args['force_delete'] ) ? 'Permanently Delete' : 'Trash' ) . " Post #{$post_id}";
                $preview['target_name'] = $existing ? $existing->post_title : "Post #{$post_id}";
                $preview['diff']        = [
                    'Status' => [
                        'before' => $existing ? $existing->post_status : 'unknown',
                        'after'  => ! empty( $args['force_delete'] ) ? 'PERMANENTLY DELETED' : 'trash',
                    ],
                ];
                break;

            case 'wp_update_option':
                $option_name = $args['option_name'] ?? '';
                $current     = get_option( $option_name );
                $preview['summary']     = "Modify setting: {$option_name}";
                $preview['target_name'] = $option_name;
                $preview['diff']        = [
                    $option_name => [
                        'before' => is_scalar( $current ) ? (string) $current : '(complex data)',
                        'after'  => (string) ( $args['option_value'] ?? '' ),
                    ],
                ];
                break;

            case 'wp_toggle_plugin':
            case 'wp_install_plugin':
            case 'wp_activate_plugin':
            case 'wp_deactivate_plugin':
            case 'wp_manage_plugins':
                $file   = $args['plugin_file'] ?? ( $args['slug'] ?? ( $args['plugin'] ?? '' ) );
                $action = $args['action'] ?? ( str_contains( $tool_name, 'install' ) || ! empty( $args['slug'] ) || ! empty( $args['plugin'] ) ? 'install' : 'activate' );
                $pname  = $args['slug'] ?? ( $args['plugin'] ?? basename( $file, '.php' ) );
                $preview['summary']     = ucfirst( $action ) . " Plugin: " . ( $pname ?: 'Plugin' );
                $preview['target_name'] = $file ?: ( $pname ?: 'Plugin' );
                $preview['diff']        = [
                    'Action' => [ 'before' => 'Current', 'after' => ucfirst( $action ) ],
                    'Plugin' => [ 'before' => '—', 'after' => $pname ],
                ];
                break;

            case 'wp_execute_php':
            case 'wp_run_php':
            case 'wp_execute_code':
                $code = $args['code'] ?? '';
                $desc = $args['description'] ?? 'Administrative PHP Execution';
                $preview['summary']     = "Execute PHP: " . mb_substr( $desc, 0, 50 );
                $preview['target_name'] = "WordPress Runtime";
                $preview['diff']        = [
                    'Description' => [ 'before' => '—', 'after' => $desc ],
                    'Code'        => [ 'before' => '—', 'after' => $code ],
                ];
                break;

            case 'wp_manage_themes':
                $action = $args['action'] ?? 'switch_theme';
                if ( 'switch_theme' === $action ) {
                    $preview['summary']     = "Switch Active Theme";
                    $preview['target_name'] = $args['stylesheet'] ?? 'New Theme';
                    $preview['diff']        = [
                        'Theme' => [ 'before' => get_stylesheet(), 'after' => $args['stylesheet'] ?? '' ],
                    ];
                } elseif ( 'set_identity' === $action ) {
                    $preview['summary']     = "Update Site Identity";
                    $preview['target_name'] = get_bloginfo( 'name' );
                    $diff = [];
                    if ( isset( $args['site_title'] ) ) {
                        $diff['Site Title'] = [ 'before' => get_bloginfo( 'name' ), 'after' => $args['site_title'] ];
                    }
                    if ( isset( $args['tagline'] ) ) {
                        $diff['Tagline'] = [ 'before' => get_bloginfo( 'description' ), 'after' => $args['tagline'] ];
                    }
                    $preview['diff'] = $diff;
                } elseif ( 'set_front_page' === $action ) {
                    $preview['summary']     = "Configure Homepage Display";
                    $preview['target_name'] = "Reading Settings";
                    $preview['diff']        = [
                        'Front Page' => [ 'before' => get_option( 'page_on_front' ) ?: 'Latest Posts', 'after' => 'Page #' . ( $args['page_on_front'] ?? '' ) ],
                    ];
                } elseif ( 'set_permalinks' === $action ) {
                    $preview['summary']     = "Update Permalink Structure";
                    $preview['target_name'] = "Rewrite Rules";
                    $preview['diff']        = [
                        'Permalinks' => [ 'before' => get_option( 'permalink_structure' ) ?: '/?p=123', 'after' => $args['permalink_structure'] ?? '/%postname%/' ],
                    ];
                } else {
                    $preview['summary']     = "Theme Action: {$action}";
                    $preview['target_name'] = "Theme";
                    $preview['diff']        = [ 'Action' => [ 'before' => 'Current', 'after' => $action ] ];
                }
                break;

            case 'wp_manage_menus':
                $action = $args['action'] ?? 'create_menu';
                if ( 'create_menu' === $action ) {
                    $preview['summary']     = "Create Navigation Menu";
                    $preview['target_name'] = $args['menu_name'] ?? 'Main Menu';
                    $preview['diff']        = [
                        'Menu Name' => [ 'before' => '(New)', 'after' => $args['menu_name'] ?? '' ],
                    ];
                } elseif ( 'add_item' === $action ) {
                    $preview['summary']     = "Add Menu Item";
                    $preview['target_name'] = $args['title'] ?? 'Item';
                    $preview['diff']        = [
                        'Title' => [ 'before' => '(New)', 'after' => $args['title'] ?? '' ],
                        'URL'   => [ 'before' => '—', 'after' => $args['url'] ?? '#' ],
                    ];
                } elseif ( 'assign_location' === $action ) {
                    $preview['summary']     = "Assign Menu Location";
                    $preview['target_name'] = $args['location'] ?? 'Location';
                    $preview['diff']        = [
                        'Location' => [ 'before' => 'Current', 'after' => ( $args['location'] ?? '' ) . ' -> Menu #' . ( $args['menu_id'] ?? '' ) ],
                    ];
                } else {
                    $preview['summary']     = "Menu Action: {$action}";
                    $preview['target_name'] = "Menus";
                    $preview['diff']        = [ 'Action' => [ 'before' => 'Current', 'after' => $action ] ];
                }
                break;

            case 'wp_manage_comments':
                $action = $args['action'] ?? 'update_status';
                if ( 'update_status' === $action ) {
                    $cid = (int) ( $args['comment_id'] ?? 0 );
                    $status = $args['status'] ?? 'approve';
                    $preview['summary']     = ucfirst( $status ) . " Comment #{$cid}";
                    $preview['target_name'] = "Comment #{$cid}";
                    $preview['diff']        = [
                        'Status' => [ 'before' => wp_get_comment_status( $cid ) ?: 'Unknown', 'after' => $status ],
                    ];
                } elseif ( 'reply' === $action ) {
                    $cid = (int) ( $args['comment_id'] ?? 0 );
                    $preview['summary']     = "Reply to Comment #{$cid}";
                    $preview['target_name'] = "Comment #{$cid}";
                    $preview['diff']        = [
                        'Reply Content' => [ 'before' => '—', 'after' => mb_substr( $args['content'] ?? '', 0, 100 ) . '...' ],
                    ];
                } else {
                    $preview['summary']     = "Comment Action: {$action}";
                    $preview['target_name'] = "Comments";
                    $preview['diff']        = [ 'Action' => [ 'before' => 'Current', 'after' => $action ] ];
                }
                break;

            case 'wp_create_code_snippet':
                $preview['summary']     = 'Create & Activate PHP Snippet';
                $preview['target_name'] = $args['title'] ?? 'Custom PHP Snippet';
                $preview['diff']        = [
                    'Title' => [ 'before' => '(New)', 'after' => $args['title'] ?? 'Untitled' ],
                    'Type'  => [ 'before' => 'None', 'after' => 'PHP (Sandboxed)' ],
                    'Code'  => [
                        'before' => '—',
                        'after'  => mb_substr( $args['code'] ?? '', 0, 100 ) . '...',
                    ],
                ];
                break;

            case 'wp_inject_custom_css':
                $preview['summary']     = 'Inject Site-Wide Custom CSS';
                $preview['target_name'] = 'Theme Customizer CSS';
                $preview['diff']        = [
                    'Mode' => [ 'before' => 'Existing CSS', 'after' => $args['mode'] ?? 'append' ],
                    'CSS'  => [
                        'before' => '—',
                        'after'  => mb_substr( $args['css'] ?? '', 0, 100 ) . '...',
                    ],
                ];
                break;

            case 'wp_inject_header_footer':
                $location = ( $args['location'] ?? '' ) === 'js_footer' ? 'Footer (wp_footer)' : 'Header (wp_head)';
                $preview['summary']     = "Inject Script into {$location}";
                $preview['target_name'] = $args['title'] ?? 'Tracking / Meta Script';
                $preview['diff']        = [
                    'Location' => [ 'before' => 'None', 'after' => $location ],
                    'Script'   => [
                        'before' => '—',
                        'after'  => mb_substr( $args['code'] ?? '', 0, 100 ) . '...',
                    ],
                ];
                break;

            case 'wp_toggle_code_snippet':
                $snippet_id = (int) ( $args['snippet_id'] ?? 0 );
                $action     = $args['action'] ?? '';
                $preview['summary']     = ucfirst( $action ) . " Code Snippet #{$snippet_id}";
                $preview['target_name'] = "Snippet #{$snippet_id}";
                $preview['diff']        = [
                    'Action' => [ 'before' => 'Current State', 'after' => ucfirst( $action ) ],
                ];
                break;

            case 'wp_manage_woocommerce':
                $act = $args['action'] ?? '';
                if ( 'create_coupon' === $act ) {
                    $preview['summary']     = "Create Discount Coupon";
                    $preview['target_name'] = strtoupper( $args['coupon_code'] ?? 'COUPON' );
                    $preview['diff']        = [
                        'Code'     => [ 'before' => '—', 'after' => strtoupper( $args['coupon_code'] ?? '' ) ],
                        'Discount' => [ 'before' => '0', 'after' => ( $args['amount'] ?? 10 ) . ( ( $args['discount_type'] ?? '' ) === 'percent' ? '%' : ' (fixed)' ) ],
                        'Expires'  => [ 'before' => '—', 'after' => $args['expiry_date'] ?? 'Never' ],
                    ];
                } elseif ( 'update_product' === $act ) {
                    $pid = (int) ( $args['product_id'] ?? 0 );
                    $preview['summary']     = "Update Product #{$pid}";
                    $preview['target_name'] = "Product #{$pid}";
                    $diff = [];
                    if ( isset( $args['regular_price'] ) ) {
                        $diff['Regular Price'] = [ 'before' => 'Current', 'after' => (string) $args['regular_price'] ];
                    }
                    if ( isset( $args['sale_price'] ) ) {
                        $diff['Sale Price'] = [ 'before' => 'Current', 'after' => (string) $args['sale_price'] ];
                    }
                    if ( isset( $args['stock_status'] ) ) {
                        $diff['Stock Status'] = [ 'before' => 'Current', 'after' => (string) $args['stock_status'] ];
                    }
                    $preview['diff'] = $diff;
                }
                break;

            case 'wp_manage_database':
                $preview['summary']     = "Database Search & Replace";
                $preview['target_name'] = "wp_posts (Content & Titles)";
                $preview['diff']        = [
                    'Search'  => [ 'before' => $args['search_str'] ?? '', 'after' => 'Replaced' ],
                    'Replace' => [ 'before' => '—', 'after' => $args['replace_str'] ?? '' ],
                ];
                break;

            case 'wp_manage_users':
                $act = $args['action'] ?? '';
                if ( 'create_user' === $act ) {
                    $preview['summary']     = "Create New User Account";
                    $preview['target_name'] = $args['username'] ?? 'User';
                    $preview['diff']        = [
                        'Username' => [ 'before' => '—', 'after' => $args['username'] ?? '' ],
                        'Email'    => [ 'before' => '—', 'after' => $args['email'] ?? '' ],
                        'Role'     => [ 'before' => '—', 'after' => $args['role'] ?? 'subscriber' ],
                    ];
                } elseif ( 'update_user_role' === $act ) {
                    $uid = (int) ( $args['user_id'] ?? 0 );
                    $preview['summary']     = "Change User #{$uid} Role";
                    $preview['target_name'] = "User #{$uid}";
                    $preview['diff']        = [
                        'New Role' => [ 'before' => 'Current Role', 'after' => $args['new_role'] ?? 'subscriber' ],
                    ];
                }
                break;

            case 'wp_manage_backup':
                $preview['summary']     = "Restore Site Configuration Snapshot";
                $preview['target_name'] = $args['snapshot_id'] ?? 'Snapshot';
                $preview['diff']        = [
                    'Action' => [ 'before' => 'Current Live State', 'after' => 'Revert to ' . ( $args['snapshot_id'] ?? '' ) ],
                ];
                break;
        }

        // Generic fallback guarantee: summary and target_name never blank
        if ( empty( $preview['summary'] ) ) {
            $preview['summary']     = 'Execute ' . str_replace( 'wp_', '', $tool_name );
            $preview['target_name'] = $tool_name;
            $preview['diff']        = [
                'Action' => [
                    'before' => 'Pending',
                    'after'  => ! empty( $args ) ? wp_json_encode( $args ) : 'Confirmed',
                ],
            ];
        }

        return $preview;
    }
}
