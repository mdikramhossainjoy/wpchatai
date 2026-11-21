<?php
/**
 * REST API Endpoints for WPChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_REST_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        $namespace = 'wpchat/v1';

        // Chat endpoint
        register_rest_route( $namespace, '/chat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_chat' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Sessions
        register_rest_route( $namespace, '/sessions', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_sessions' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'create_session' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
        ] );

        register_rest_route( $namespace, '/sessions/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [ __CLASS__, 'delete_session' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        register_rest_route( $namespace, '/sessions/(?P<id>\d+)/messages', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_messages' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Action Approval / Rejection
        register_rest_route( $namespace, '/action/approve', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_approve' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        register_rest_route( $namespace, '/action/reject', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_reject' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Undo
        register_rest_route( $namespace, '/action/undo', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_undo' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Settings (Strict Administrator Only)
        register_rest_route( $namespace, '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_settings' ],
                'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'update_settings' ],
                'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
            ],
        ] );

        // Code Snippets Studio (Strict Administrator Only)
        register_rest_route( $namespace, '/snippets', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_snippets' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        // Available Models (Strict Administrator Only)
        register_rest_route( $namespace, '/models', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_models' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        // Dynamic Live Context (100% AJAX, detects WooCommerce/plugins live)
        register_rest_route( $namespace, '/context', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_site_context' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        register_rest_route( $namespace, '/snippets/toggle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'toggle_snippet' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        register_rest_route( $namespace, '/snippets/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [ __CLASS__, 'delete_snippet' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        // Live API Ping & Latency Tester (Strict Administrator Only)
        register_rest_route( $namespace, '/ping', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_ping' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        // Audit Log Handlers (Strict Administrator Only)
        register_rest_route( $namespace, '/audit-log', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_audit_log' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        register_rest_route( $namespace, '/audit-log/export', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'export_audit_log' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        // Maintenance & Diagnostics Handlers (Strict Administrator Only)
        register_rest_route( $namespace, '/maintenance/stats', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_maintenance_stats' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        register_rest_route( $namespace, '/maintenance/purge', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_maintenance_purge' ],
            'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
        ] );

        // Prompt Recipes / Quick Ideas
        register_rest_route( $namespace, '/recipes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_recipes' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Dynamic AI Ideas
        register_rest_route( $namespace, '/ideas', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_dynamic_ideas' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );
    }

    /**
     * Permission check for general chat usage (configurable role: admin, editor, or author)
     */
    public static function check_permission(): bool {
        return self::check_chat_permission();
    }

    public static function check_chat_permission(): bool {
        $allowed_role = get_option( 'wpchat_allowed_role', 'administrator' );
        if ( 'author' === $allowed_role ) {
            return current_user_can( 'publish_posts' );
        }
        if ( 'editor' === $allowed_role ) {
            return current_user_can( 'edit_pages' );
        }
        return current_user_can( 'manage_options' );
    }

    /**
     * Strict administrator permission check for security settings, code snippets, and maintenance
     */
    public static function check_admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Return live site context (100% AJAX, detects newly installed plugins/WooCommerce live)
     */
    public static function get_site_context(): WP_REST_Response {
        require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-site-context.php';
        return new WP_REST_Response( WPChat_Site_Context::get_context(), 200 );
    }

    /**
     * Main Chat Conversation Handler
     */
    public static function handle_chat( WP_REST_Request $request ): WP_REST_Response {
        @set_time_limit( 300 );
        $user_id    = get_current_user_id();
        $session_id = (int) $request->get_param( 'session_id' );
        $user_text  = trim( (string) $request->get_param( 'message' ) );
        $mode       = sanitize_key( (string) $request->get_param( 'mode' ) );

        if ( empty( $user_text ) ) {
            return new WP_REST_Response( [ 'error' => 'Message cannot be empty.' ], 400 );
        }

        // Initialize session if not present
        if ( ! $session_id || ! WPChat_DB::get_session( $session_id ) ) {
            $default_mode = ! empty( $mode ) ? $mode : (string) get_option( 'wpchat_approval_mode', 'copilot' );
            $title        = mb_substr( $user_text, 0, 40 );
            $session_id   = WPChat_DB::create_session( $user_id, $title, $default_mode );
        } else {
            $existing_session = WPChat_DB::get_session( $session_id );
            if ( (int) ( $existing_session['user_id'] ?? 0 ) !== $user_id && ! current_user_can( 'manage_options' ) ) {
                return new WP_REST_Response( [ 'error' => 'Unauthorized session access.' ], 403 );
            }
            if ( ! empty( $mode ) ) {
                WPChat_DB::update_session_mode( $session_id, $mode );
            }
        }

        $session = WPChat_DB::get_session( $session_id );
        if ( ! $session || ( (int) ( $session['user_id'] ?? 0 ) !== $user_id && ! current_user_can( 'manage_options' ) ) ) {
            return new WP_REST_Response( [ 'error' => 'Unauthorized session access.' ], 403 );
        }
        $active_mode = $session['mode'] ?? 'copilot';

        // 1. Add User Message
        WPChat_DB::add_message( $session_id, 'user', $user_text );

        // 2. Initialize AI Client
        $provider = WPChat_AI_Client::get_active_provider();
        $client   = WPChat_AI_Client::get_client( $provider );
        if ( ! $client->is_configured() ) {
            $prov_name = ( 'gemini' === $provider ) ? 'Google Gemini' : ( ( 'openrouter' === $provider ) ? 'OpenRouter' : ( ( 'nvidia' === $provider ) ? 'NVIDIA NIM' : 'AI Provider' ) );
            $err_text  = sprintf( '%s API key is not configured. Please set it in WPChat settings.', $prov_name );
            WPChat_DB::add_message( $session_id, 'model', $err_text );
            return new WP_REST_Response( [
                'status'     => 'error',
                'error'      => $err_text,
                'needs_auth' => true,
                'session_id' => $session_id,
                'messages'   => WPChat_DB::get_messages( $session_id ),
            ], 200 );
        }

        // 3. Execution Loop (AI <-> Tools)
        $max_iterations = 5;
        $iteration = 0;

        while ( $iteration < $max_iterations ) {
            $iteration++;
            $history = ( 'gemini' === $provider )
                ? WPChat_DB::get_gemini_history( $session_id )
                : WPChat_DB::get_openai_history( $session_id );

            try {
                $response = $client->generate( $history, true );
            } catch ( Throwable $e ) {
                // If tool-assisted generation fails, attempt one graceful conversational retry without tools
                try {
                    $response = $client->generate( $history, false );
                } catch ( Throwable $fallback_e ) {
                    $err_text = "I encountered an issue connecting to the AI model: " . $e->getMessage();
                    WPChat_DB::add_message( $session_id, 'model', $err_text );
                    return new WP_REST_Response( [
                        'status'     => 'error',
                        'error'      => $e->getMessage(),
                        'session_id' => $session_id,
                        'messages'   => WPChat_DB::get_messages( $session_id ),
                    ], 200 );
                }
            }

            if ( $response['type'] === 'function_call' ) {
                $call         = $response['function_call'];
                $tool_name    = $call['name'] ?? '';
                $tool_args    = $call['args'] ?? [];
                $tool_call_id = $response['tool_call_id'] ?? ( 'call_' . uniqid() );

                // Check action gate
                try {
                    $requires_approval = WPChat_Action_Gate::requires_approval( $tool_name, $tool_args, $active_mode );
                } catch ( Throwable $e ) {
                    // Blocked by security policy
                    $content = "Security Error: " . $e->getMessage();
                    WPChat_DB::add_message( $session_id, 'model', $content );
                    break;
                }

                if ( $requires_approval ) {
                    // Gate triggered: Halt and return pending approval card
                    $action_data = WPChat_Action_Gate::create_pending_action( $session_id, $user_id, $tool_name, $tool_args );
                    
                    // Save assistant intent message
                    $msg_content = ! empty( $response['text'] ) ? $response['text'] : "Proposed action: {$action_data['preview']['summary']}";
                    WPChat_DB::add_message(
                        $session_id,
                        'model',
                        $msg_content,
                        [
                            'status'       => 'pending_approval',
                            'token'        => $action_data['token'],
                            'tool_name'    => $tool_name,
                            'tool_args'    => $tool_args,
                            'tool_call_id' => $tool_call_id,
                            'preview'      => $action_data['preview'],
                            'raw_parts'    => $response['raw_parts'] ?? [],
                            'raw_message'  => $response['raw_message'] ?? [],
                        ]
                    );

                    return new WP_REST_Response( [
                        'status'     => 'pending_approval',
                        'session_id' => $session_id,
                        'messages'   => WPChat_DB::get_messages( $session_id ),
                    ] );
                }

                // If not requiring approval: Execute immediately (Read-only or Turbo or Copilot low-risk)
                $is_turbo = ( $active_mode === WPChat_Action_Gate::MODE_TURBO );
                try {
                    $result = WPChat_Tool_Executor::execute( $tool_name, $tool_args, $session_id, $user_id, $is_turbo );
                } catch ( Throwable $e ) {
                    $result = [ 'error' => $e->getMessage() ];
                }

                // Record model call and tool execution result
                WPChat_DB::add_message(
                    $session_id,
                    'model',
                    $response['text'] ?? '',
                    [
                        'functionCall' => $call,
                        'tool_call_id' => $tool_call_id,
                        'raw_parts'    => $response['raw_parts'] ?? [],
                        'raw_message'  => $response['raw_message'] ?? [],
                    ]
                );

                WPChat_DB::add_message(
                    $session_id,
                    'tool',
                    wp_json_encode( $result ),
                    [
                        'tool_name'    => $tool_name,
                        'tool_call_id' => $tool_call_id,
                        'result'       => $result,
                        'is_turbo'     => $is_turbo,
                    ]
                );

                // Loop continues with tool result sent back to AI
                continue;
            }

            // Normal text response
            WPChat_DB::add_message( $session_id, 'model', $response['text'] );
            break;
        }

        return new WP_REST_Response( [
            'status'     => 'complete',
            'session_id' => $session_id,
            'messages'   => WPChat_DB::get_messages( $session_id ),
        ] );
    }

    /**
     * Approve Action Token
     */
    public static function handle_approve( WP_REST_Request $request ): WP_REST_Response {
        @set_time_limit( 300 );
        $token       = sanitize_text_field( (string) $request->get_param( 'token' ) );
        $action_data = WPChat_Action_Gate::get_pending_action( $token );

        if ( ! $action_data ) {
            return new WP_REST_Response( [ 'error' => 'Action token expired or invalid.' ], 400 );
        }

        $session_id = (int) $action_data['session_id'];
        $user_id    = get_current_user_id();
        $tool_name  = (string) $action_data['tool_name'];
        $tool_args  = (array) ( $action_data['tool_args'] ?? [] );

        // Ensure user owns action or has manage_options capability
        if ( isset( $action_data['user_id'] ) && (int) $action_data['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'error' => 'Unauthorized action token.' ], 403 );
        }

        // Consume and remove token immediately so action never gets stuck
        WPChat_Action_Gate::remove_pending_action( $token );
        WPChat_DB::resolve_pending_action( $session_id, $token, 'approved' );

        // Execute action with comprehensive error interception
        try {
            $result = WPChat_Tool_Executor::execute( $tool_name, $tool_args, $session_id, $user_id, false );
        } catch ( Throwable $e ) {
            $error_message = $e->getMessage();
            WPChat_DB::resolve_pending_action( $session_id, $token, 'failed' );

            // Record execution failure in message history
            WPChat_DB::add_message(
                $session_id,
                'tool',
                wp_json_encode( [ 'error' => $error_message ] ),
                [
                    'tool_name' => $tool_name,
                    'result'    => [ 'error' => $error_message ],
                    'failed'    => true,
                ]
            );

            // Record assistant follow-up on error
            WPChat_DB::add_message(
                $session_id,
                'model',
                "Execution of `{$tool_name}` failed: {$error_message}"
            );

            return new WP_REST_Response( [
                'success'    => false,
                'error'      => $error_message,
                'session_id' => $session_id,
                'messages'   => WPChat_DB::get_messages( $session_id ),
            ], 200 );
        }

        // Record tool completion in thread
        $tool_call_id = $action_data['tool_call_id'] ?? '';
        WPChat_DB::add_message(
            $session_id,
            'tool',
            wp_json_encode( $result ),
            [
                'tool_name'    => $tool_name,
                'tool_call_id' => $tool_call_id,
                'result'       => $result,
                'approved'     => true,
            ]
        );

        // Resume AI to generate natural follow-up summary
        $provider = WPChat_AI_Client::get_active_provider();
        $client   = WPChat_AI_Client::get_client( $provider );
        try {
            $history  = ( 'gemini' === $provider )
                ? WPChat_DB::get_gemini_history( $session_id )
                : WPChat_DB::get_openai_history( $session_id );
            $response = $client->generate( $history, false );
            if ( ! empty( $response['text'] ) ) {
                WPChat_DB::add_message( $session_id, 'model', $response['text'] );
            }
        } catch ( Throwable $e ) {
            WPChat_DB::add_message( $session_id, 'model', "Action executed successfully: " . wp_json_encode( $result ) );
        }

        return new WP_REST_Response( [
            'success'    => true,
            'session_id' => $session_id,
            'messages'   => WPChat_DB::get_messages( $session_id ),
        ], 200 );
    }

    /**
     * Reject Action Token
     */
    public static function handle_reject( WP_REST_Request $request ): WP_REST_Response {
        $token       = sanitize_text_field( (string) $request->get_param( 'token' ) );
        $action_data = WPChat_Action_Gate::get_pending_action( $token );

        if ( ! $action_data ) {
            return new WP_REST_Response( [ 'error' => 'Action token expired or invalid.' ], 400 );
        }

        $user_id = get_current_user_id();
        if ( isset( $action_data['user_id'] ) && (int) $action_data['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'error' => 'Permission denied: Cannot reject actions for other users.' ], 403 );
        }

        $session_id   = $action_data['session_id'];
        $tool_call_id = $action_data['tool_call_id'] ?? '';
        WPChat_Action_Gate::remove_pending_action( $token );
        WPChat_DB::resolve_pending_action( $session_id, $token, 'rejected' );

        // Add notice that action was rejected
        WPChat_DB::add_message(
            $session_id,
            'tool',
            'Action stopped.',
            [
                'tool_name'    => $action_data['tool_name'],
                'tool_call_id' => $tool_call_id,
                'rejected'     => true,
            ]
        );

        WPChat_DB::add_message(
            $session_id,
            'model',
            "Action `{$action_data['tool_name']}` was cancelled."
        );

        return new WP_REST_Response( [
            'success'    => true,
            'session_id' => $session_id,
            'messages'   => WPChat_DB::get_messages( $session_id ),
        ] );
    }

    /**
     * Undo Action
     */
    public static function handle_undo( WP_REST_Request $request ): WP_REST_Response {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'error' => 'Insufficient permissions to undo actions.' ], 403 );
        }

        $audit_id = (int) $request->get_param( 'audit_id' );
        if ( ! $audit_id ) {
            return new WP_REST_Response( [ 'error' => 'Missing audit ID.' ], 400 );
        }

        $res = WPChat_Audit_Logger::undo( $audit_id );
        if ( ! $res['success'] ) {
            return new WP_REST_Response( $res, 400 );
        }

        return new WP_REST_Response( $res );
    }

    /**
     * Sessions Listing
     */
    public static function get_sessions( WP_REST_Request $request ): WP_REST_Response {
        $user_id = get_current_user_id();
        $limit   = min( max( (int) ( $request->get_param( 'limit' ) ?: 20 ), 1 ), 100 );
        $page    = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $offset  = (int) ( $request->get_param( 'offset' ) ?? ( ( $page - 1 ) * $limit ) );

        $sessions = WPChat_DB::get_sessions( $user_id, $limit, $offset );
        $total    = WPChat_DB::get_sessions_count( $user_id );

        return new WP_REST_Response( [
            'sessions'    => $sessions,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil( $total / $limit ),
        ] );
    }

    /**
     * Create New Session
     */
    public static function create_session( WP_REST_Request $request ): WP_REST_Response {
        $user_id = get_current_user_id();
        $title   = sanitize_text_field( (string) $request->get_param( 'title' ) ) ?: 'New Chat';
        $mode    = sanitize_key( (string) $request->get_param( 'mode' ) ) ?: 'copilot';

        $id = WPChat_DB::create_session( $user_id, $title, $mode );
        return new WP_REST_Response( [
            'id'    => $id,
            'title' => $title,
            'mode'  => $mode,
        ] );
    }

    /**
     * Delete Session
     */
    public static function delete_session( WP_REST_Request $request ): WP_REST_Response {
        $id = (int) $request->get_param( 'id' );
        $session = WPChat_DB::get_session( $id );
        if ( ! $session ) {
            return new WP_REST_Response( [ 'error' => 'Session not found.' ], 404 );
        }
        $user_id = get_current_user_id();
        if ( (int) $session['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'error' => 'Unauthorized session deletion.' ], 403 );
        }

        WPChat_DB::delete_session( $id );
        return new WP_REST_Response( [ 'success' => true ] );
    }

    /**
     * Get Messages
     */
    public static function get_messages( WP_REST_Request $request ): WP_REST_Response {
        $id = (int) $request->get_param( 'id' );
        $session = WPChat_DB::get_session( $id );
        if ( ! $session ) {
            return new WP_REST_Response( [ 'error' => 'Session not found.' ], 404 );
        }
        $user_id = get_current_user_id();
        if ( (int) $session['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'error' => 'Unauthorized session access.' ], 403 );
        }

        return new WP_REST_Response( [
            'messages' => WPChat_DB::get_messages( $id ),
        ] );
    }

    /**
     * Settings
     */
    public static function get_settings(): WP_REST_Response {
        $provider = WPChat_AI_Client::get_active_provider();
        return new WP_REST_Response( [
            'ai_provider'        => $provider,
            'custom_base_url'    => (string) get_option( 'wpchat_custom_base_url', 'https://api.openai.com/v1' ),
            'model'              => WPChat_AI_Client::get_active_model( $provider ),
            'gemini_model'       => (string) get_option( 'wpchat_gemini_model', 'gemini-flash-latest' ),
            'openrouter_model'   => WPChat_AI_Client::get_active_model( 'openrouter' ),
            'nvidia_model'       => WPChat_AI_Client::get_active_model( 'nvidia' ),
            'custom_model'       => (string) get_option( 'wpchat_custom_model', 'gpt-4o' ),
            'has_api_key'        => WPChat_AI_Client::has_api_key( $provider ),
            'has_gemini_key'     => WPChat_AI_Client::has_api_key( 'gemini' ),
            'has_openrouter_key' => WPChat_AI_Client::has_api_key( 'openrouter' ),
            'has_nvidia_key'     => WPChat_AI_Client::has_api_key( 'nvidia' ),
            'has_custom_key'     => WPChat_AI_Client::has_api_key( 'custom' ),
            'approval_mode'      => get_option( 'wpchat_approval_mode', 'copilot' ),
            'temperature'        => (float) get_option( 'wpchat_temperature', 0.4 ),
            'max_tokens'         => (int) get_option( 'wpchat_max_tokens', 4096 ),
            'system_prompt'      => (string) get_option( 'wpchat_system_prompt', '' ),
            'allowed_role'       => (string) get_option( 'wpchat_allowed_role', 'administrator' ),
            'killswitch'         => (string) get_option( 'wpchat_killswitch', 'off' ),
            'blocked_tools'      => (array) get_option( 'wpchat_blocked_tools', [] ),
        ] );
    }

    public static function update_settings( WP_REST_Request $request ): WP_REST_Response {
        $ai_provider     = sanitize_key( (string) $request->get_param( 'ai_provider' ) );
        $custom_base_url = esc_url_raw( trim( (string) $request->get_param( 'custom_base_url' ) ) );
        $api_key         = sanitize_text_field( (string) $request->get_param( 'api_key' ) );
        $mode            = sanitize_key( (string) $request->get_param( 'approval_mode' ) );
        $model           = sanitize_text_field( (string) ( $request->get_param( 'model' ) ?: $request->get_param( 'gemini_model' ) ) );
        $temperature     = $request->get_param( 'temperature' );
        $max_tokens      = $request->get_param( 'max_tokens' );
        $system_prompt   = $request->get_param( 'system_prompt' );
        $allowed_role    = sanitize_key( (string) $request->get_param( 'allowed_role' ) );
        $killswitch      = sanitize_key( (string) $request->get_param( 'killswitch' ) );
        $blocked_tools   = $request->get_param( 'blocked_tools' );

        if ( in_array( $ai_provider, [ 'gemini', 'openrouter', 'nvidia', 'custom' ], true ) ) {
            update_option( 'wpchat_ai_provider', $ai_provider );
        }
        $active_provider = WPChat_AI_Client::get_active_provider();

        if ( ! empty( $custom_base_url ) ) {
            update_option( 'wpchat_custom_base_url', rtrim( $custom_base_url, '/' ) );
        }

        if ( ! empty( $api_key ) ) {
            switch ( $active_provider ) {
                case 'openrouter':
                    update_option( 'wpchat_openrouter_api_key', $api_key );
                    break;
                case 'nvidia':
                    update_option( 'wpchat_nvidia_api_key', $api_key );
                    break;
                case 'custom':
                    update_option( 'wpchat_custom_api_key', $api_key );
                    break;
                case 'gemini':
                default:
                    update_option( 'wpchat_gemini_api_key', $api_key );
                    break;
            }
        }

        if ( ! empty( $model ) ) {
            switch ( $active_provider ) {
                case 'openrouter':
                    update_option( 'wpchat_openrouter_model', $model );
                    break;
                case 'nvidia':
                    update_option( 'wpchat_nvidia_model', $model );
                    break;
                case 'custom':
                    update_option( 'wpchat_custom_model', $model );
                    break;
                case 'gemini':
                default:
                    update_option( 'wpchat_gemini_model', $model );
                    break;
            }
        }

        if ( in_array( $mode, [ 'always_ask', 'copilot', 'turbo' ], true ) ) {
            update_option( 'wpchat_approval_mode', $mode );
        }
        if ( null !== $temperature ) {
            update_option( 'wpchat_temperature', max( 0.0, min( 1.0, floatval( $temperature ) ) ) );
        }
        if ( null !== $max_tokens ) {
            update_option( 'wpchat_max_tokens', max( 256, min( 8192, intval( $max_tokens ) ) ) );
        }
        if ( null !== $system_prompt ) {
            update_option( 'wpchat_system_prompt', sanitize_textarea_field( (string) $system_prompt ) );
        }
        if ( in_array( $allowed_role, [ 'administrator', 'editor', 'author' ], true ) ) {
            update_option( 'wpchat_allowed_role', $allowed_role );
        }
        if ( in_array( $killswitch, [ 'on', 'off' ], true ) ) {
            update_option( 'wpchat_killswitch', $killswitch );
        }
        if ( is_array( $blocked_tools ) ) {
            $sanitized = array_map( 'sanitize_key', $blocked_tools );
            update_option( 'wpchat_blocked_tools', $sanitized );
        }

        return self::get_settings();
    }

    /**
     * Code Snippets Handlers
     */
    public static function get_snippets( WP_REST_Request $request ): WP_REST_Response {
        $snippets = WPChat_Code_Engine::get_snippets();
        return new WP_REST_Response( [ 'snippets' => $snippets ] );
    }

    public static function toggle_snippet( WP_REST_Request $request ): WP_REST_Response {
        $snippet_id = (int) $request->get_param( 'snippet_id' );
        $active     = (bool) $request->get_param( 'active' );

        $success = WPChat_Code_Engine::toggle_snippet( $snippet_id, $active );
        return new WP_REST_Response( [ 'success' => $success ] );
    }

    public static function delete_snippet( WP_REST_Request $request ): WP_REST_Response {
        $snippet_id = (int) $request->get_param( 'id' );
        $success    = WPChat_Code_Engine::delete_snippet( $snippet_id );
        return new WP_REST_Response( [ 'success' => $success ] );
    }

    /**
     * Models Listing (Dynamic from Provider)
     */
    public static function get_models( WP_REST_Request $request ): WP_REST_Response {
        $provider = sanitize_key( (string) $request->get_param( 'provider' ) ) ?: WPChat_AI_Client::get_active_provider();
        $api_key  = sanitize_text_field( (string) $request->get_param( 'api_key' ) );
        $base_url = sanitize_text_field( (string) $request->get_param( 'base_url' ) );
        $force    = (bool) $request->get_param( 'force' );

        $client = WPChat_AI_Client::get_client( $provider, $api_key ?: null, null, $base_url ?: null );
        if ( 'gemini' === $provider ) {
            $models = $client->get_available_models( $api_key ?: null, $force );
        } else {
            $models = $client->get_available_models( $api_key ?: null, $base_url ?: null, $force );
        }

        return new WP_REST_Response( [
            'provider' => $provider,
            'models'   => $models,
        ] );
    }

    /**
     * Test API Connection & Measure Ping Latency
     */
    public static function handle_ping( WP_REST_Request $request ): WP_REST_Response {
        $provider = sanitize_key( (string) $request->get_param( 'provider' ) ) ?: WPChat_AI_Client::get_active_provider();
        $api_key  = sanitize_text_field( (string) $request->get_param( 'api_key' ) );
        $model    = sanitize_text_field( (string) ( $request->get_param( 'model' ) ?: $request->get_param( 'gemini_model' ) ) );
        $base_url = sanitize_text_field( (string) $request->get_param( 'base_url' ) );

        $client = WPChat_AI_Client::get_client( $provider, $api_key ?: null, $model ?: null, $base_url ?: null );
        if ( 'gemini' === $provider ) {
            $result = $client->ping( $api_key ?: null, $model ?: null );
        } else {
            $result = $client->ping( $api_key ?: null, $model ?: null, $base_url ?: null );
        }

        return new WP_REST_Response( $result, ( ! empty( $result['success'] ) ) ? 200 : 400 );
    }

    /**
     * Audit Log Handlers
     */
    public static function get_audit_log( WP_REST_Request $request ): WP_REST_Response {
        $limit  = min( max( (int) ( $request->get_param( 'limit' ) ?: 20 ), 1 ), 100 );
        $page   = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $offset = (int) ( $request->get_param( 'offset' ) ?? ( ( $page - 1 ) * $limit ) );

        $logs  = WPChat_DB::get_audit_logs( $limit, $offset );
        $total = WPChat_DB::get_audit_logs_count();

        return new WP_REST_Response( [
            'logs'        => $logs,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil( $total / $limit ),
        ] );
    }

    public static function export_audit_log( WP_REST_Request $request ): void {
        require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-audit-logger.php';
        $csv = WPChat_Audit_Logger::export_csv();

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=wpchat-audit-log-' . date( 'Y-m-d' ) . '.csv' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        echo $csv;
        exit;
    }

    /**
     * Maintenance & Diagnostics
     */
    public static function get_maintenance_stats( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $db_stats = WPChat_DB::get_db_stats();

        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        $has_debug_log  = file_exists( $debug_log_path );
        $debug_log_size = $has_debug_log ? round( filesize( $debug_log_path ) / 1024, 2 ) : 0;

        $cron_jobs = _get_cron_array() ?: [];
        $cron_count = 0;
        foreach ( $cron_jobs as $time => $hooks ) {
            $cron_count += count( $hooks );
        }

        return new WP_REST_Response( [
            'database' => $db_stats,
            'system'   => [
                'php_version'       => PHP_VERSION,
                'mysql_version'     => $wpdb->db_version(),
                'memory_limit'      => ini_get( 'memory_limit' ),
                'max_execution_sec' => ini_get( 'max_execution_time' ),
                'has_debug_log'     => $has_debug_log,
                'debug_log_size_kb' => $debug_log_size,
                'cron_count'        => $cron_count,
            ],
        ] );
    }

    public static function handle_maintenance_purge( WP_REST_Request $request ): WP_REST_Response {
        $target = sanitize_key( (string) $request->get_param( 'target' ) );
        $res    = WPChat_DB::purge_data( $target );
        return new WP_REST_Response( $res, $res['success'] ? 200 : 400 );
    }

    /**
     * Curated Prompt Recipes for 1-Click Operations
     */
    public static function get_recipes(): WP_REST_Response {
        $recipes = [
            [
                'category' => 'Maintenance',
                'items'    => [
                    [
                        'title'  => 'Clean Database & Transients',
                        'prompt' => 'Clean all expired transients, post revisions, and optimize database tables.',
                    ],
                    [
                        'title'  => 'Check PHP Error Logs',
                        'prompt' => 'Scan debug.log and report any recent fatal errors or critical warnings.',
                    ],
                    [
                        'title'  => 'Purge Cache',
                        'prompt' => 'Flush all object caches, LiteSpeed cache, and page caches.',
                    ],
                ],
            ],
            [
                'category' => 'SEO & Health',
                'items'    => [
                    [
                        'title'  => 'Fix Missing Alt Tags',
                        'prompt' => 'Scan media library for images without alt tags and generate descriptive tags.',
                    ],
                    [
                        'title'  => 'Audit Broken Links',
                        'prompt' => 'Check recent published posts for broken internal or external links.',
                    ],
                    [
                        'title'  => 'Audit Admin Accounts',
                        'prompt' => 'Audit all administrator users and check file permissions for wp-config.php.',
                    ],
                ],
            ],
            [
                'category' => 'Store (WooCommerce)',
                'items'    => [
                    [
                        'title'  => 'Create Discount Coupon',
                        'prompt' => 'Create a 15% discount coupon named SAVE15 for all products.',
                    ],
                    [
                        'title'  => 'Audit Low Stock Items',
                        'prompt' => 'List all low stock and out-of-stock products that need restocking.',
                    ],
                    [
                        'title'  => 'Store Sales Summary',
                        'prompt' => 'Show me recent order stats, total sales, and best-selling products.',
                    ],
                ],
            ],
            [
                'category' => 'Content & Code',
                'items'    => [
                    [
                        'title'  => 'Redirect Non-Logged Users',
                        'prompt' => 'Create a PHP snippet to redirect non-logged-in visitors away from /checkout.',
                    ],
                    [
                        'title'  => 'Add Custom CSS',
                        'prompt' => 'Add custom CSS to smooth scrolling and refine button hover effects.',
                    ],
                ],
            ],
        ];

        return new WP_REST_Response( [ 'recipes' => $recipes ] );
    }

    /**
     * Dynamic AI Ideas: Inspects site state and queries Gemini to generate smart, personalized prompt ideas
     */
    public static function get_dynamic_ideas( WP_REST_Request $request ): WP_REST_Response {
        $force     = (bool) $request->get_param( 'force' );
        $cache_key = 'wpchat_dynamic_ai_ideas';

        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) && ! empty( $cached ) ) {
                return new WP_REST_Response( [ 'ideas' => $cached, 'cached' => true ], 200 );
            }
        }

        require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-site-context.php';
        $context  = WPChat_Site_Context::get_context();
        $provider = WPChat_AI_Client::get_active_provider();
        $client   = WPChat_AI_Client::get_client( $provider );

        $ideas = [];

        // If active AI provider is configured, ask AI to produce live personalized prompt ideas
        if ( $client->is_configured() ) {
            try {
                $site_name = $context['site_name'] ?? 'WordPress';
                $theme     = $context['active_theme']['name'] ?? 'Default';
                $plugins   = implode( ', ', array_slice( array_column( $context['active_plugins'] ?? [], 'name' ), 0, 8 ) );
                $is_woo    = ! empty( $context['woocommerce']['active'] );
                $products  = $context['woocommerce']['total_products'] ?? 0;

                $prompt = "You are WPChat, an autonomous WordPress AI Copilot. Inspect this site context:\n"
                    . "- Site Name: {$site_name}\n"
                    . "- Active Theme: {$theme}\n"
                    . "- Installed Plugins: {$plugins}\n"
                    . "- WooCommerce: " . ( $is_woo ? "Active ({$products} products)" : "Inactive" ) . "\n\n"
                    . "Generate 5 specific, diverse, high-value prompt suggestions that the site owner can run right now (e.g. database maintenance, SEO alt tags, WooCommerce sale coupon, error log diagnosis, security audit).\n"
                    . "Strictly output ONLY a raw JSON array of 5 objects with 'label' (2 to 4 words) and 'prompt' (1 direct imperative sentence). Do not write any markdown code fences or conversational text.";

                $resp     = $client->generate( [ [ 'role' => 'user', 'content' => $prompt ] ], false );
                $raw_text = $resp['text'] ?? '';
                $clean    = trim( preg_replace( '/^```(?:json)?\s*/i', '', $raw_text ) );
                $clean    = trim( preg_replace( '/\s*```$/', '', $clean ) );
                $parsed   = json_decode( $clean, true );
                if ( is_array( $parsed ) && ! empty( $parsed ) ) {
                    foreach ( $parsed as $item ) {
                        if ( ! empty( $item['label'] ) && ! empty( $item['prompt'] ) ) {
                            $ideas[] = [
                                'label'  => sanitize_text_field( $item['label'] ),
                                'prompt' => sanitize_text_field( $item['prompt'] ),
                            ];
                        }
                    }
                }
            } catch ( Throwable $e ) {
                // Fallback below
            }
        }

        // Context-aware dynamic fallback if API key absent or error
        if ( empty( $ideas ) ) {
            $ideas = [
                [
                    'label'  => 'Clean Database',
                    'prompt' => 'Clean expired transients, post revisions, and optimize database tables.',
                ],
                [
                    'label'  => 'Scan PHP Errors',
                    'prompt' => 'Scan debug.log and report any recent fatal errors or critical warnings.',
                ],
                [
                    'label'  => 'Audit SEO Alt Tags',
                    'prompt' => 'Scan media library for images without alt tags and generate descriptive tags.',
                ],
                [
                    'label'  => 'Admin Security Check',
                    'prompt' => 'Audit all administrator accounts and check file permissions for wp-config.php.',
                ],
            ];

            if ( ! empty( $context['woocommerce']['active'] ) ) {
                $ideas[] = [
                    'label'  => 'Create Store Coupon',
                    'prompt' => 'Create a 15% discount coupon named PROMO15 for all products in the store.',
                ];
                $ideas[] = [
                    'label'  => 'Audit Low Stock',
                    'prompt' => 'List all low stock and out-of-stock products that need restocking.',
                ];
            } else {
                $ideas[] = [
                    'label'  => 'Audit Broken Links',
                    'prompt' => 'Check recent published posts for broken internal or external links.',
                ];
            }
        }

        set_transient( $cache_key, $ideas, 20 * MINUTE_IN_SECONDS );

        return new WP_REST_Response( [ 'ideas' => array_slice( $ideas, 0, 6 ), 'dynamic' => true ], 200 );
    }
}
