<?php
/**
 * Database Layer for WPChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_DB {

    /**
     * Create a new chat session
     */
    public static function create_session( int $user_id, string $title = 'New Chat', string $mode = 'copilot' ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_sessions';

        $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'title'      => sanitize_text_field( $title ),
                'mode'       => in_array( $mode, [ 'always_ask', 'copilot', 'turbo' ], true ) ? $mode : 'copilot',
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Get all chat sessions for a user
     */
    public static function get_sessions( int $user_id, int $limit = 30, int $offset = 0 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_sessions';

        $limit  = min( max( $limit, 1 ), 100 );
        $offset = max( $offset, 0 );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get total session count for user
     */
    public static function get_sessions_count( int $user_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_sessions';

        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id )
        );
    }

    /**
     * Get single session
     */
    public static function get_session( int $session_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_sessions';

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $session_id ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Update session mode
     */
    public static function update_session_mode( int $session_id, string $mode ): bool {
        global $wpdb;
        if ( ! in_array( $mode, [ 'always_ask', 'copilot', 'turbo' ], true ) ) {
            return false;
        }

        $table = $wpdb->prefix . 'wpchat_sessions';
        return false !== $wpdb->update(
            $table,
            [ 'mode' => $mode ],
            [ 'id' => $session_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Update session title
     */
    public static function update_session_title( int $session_id, string $title ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_sessions';
        return false !== $wpdb->update(
            $table,
            [ 'title' => sanitize_text_field( $title ) ],
            [ 'id' => $session_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Delete session and associated messages
     */
    public static function delete_session( int $session_id ): bool {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'wpchat_sessions';
        $table_messages = $wpdb->prefix . 'wpchat_messages';
        $table_audit    = $wpdb->prefix . 'wpchat_audit_log';

        $wpdb->delete( $table_messages, [ 'session_id' => $session_id ], [ '%d' ] );
        $wpdb->delete( $table_audit, [ 'session_id' => $session_id ], [ '%d' ] );
        return false !== $wpdb->delete( $table_sessions, [ 'id' => $session_id ], [ '%d' ] );
    }

    /**
     * Add a message
     */
    public static function add_message( int $session_id, string $role, string $content, ?array $tool_call_data = null ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_messages';

        $wpdb->insert(
            $table,
            [
                'session_id'     => $session_id,
                'role'           => $role,
                'content'        => $content,
                'tool_call_data' => $tool_call_data ? wp_json_encode( $tool_call_data ) : null,
                'created_at'     => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Resolve pending action in message records (marks as approved/rejected and strips token)
     */
    public static function resolve_pending_action( int $session_id, string $token, string $new_status ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_messages';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, tool_call_data FROM {$table} WHERE session_id = %d AND tool_call_data LIKE %s",
                $session_id,
                '%' . $wpdb->esc_like( $token ) . '%'
            ),
            ARRAY_A
        );

        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $data = json_decode( $row['tool_call_data'], true );
                if ( is_array( $data ) && ( ( $data['token'] ?? '' ) === $token ) ) {
                    $data['status']   = $new_status;
                    $data['resolved'] = true;
                    unset( $data['token'] ); // Do not leave execution token stored anywhere

                    $wpdb->update(
                        $table,
                        [ 'tool_call_data' => wp_json_encode( $data ) ],
                        [ 'id' => (int) $row['id'] ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                }
            }
        }
    }

    /**
     * Get messages for a session
     */
    public static function get_messages( int $session_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_messages';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE session_id = %d ORDER BY id ASC",
                $session_id
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return [];
        }

        foreach ( $rows as &$row ) {
            if ( ! empty( $row['tool_call_data'] ) ) {
                $row['tool_call_data'] = json_decode( $row['tool_call_data'], true );
            }
        }

        return $rows;
    }

    /**
     * Sliding window history trimmer to prevent context window overflow while preserving tool-call pairs
     */
    public static function trim_message_history( array $messages, int $limit = 30 ): array {
        if ( count( $messages ) <= $limit ) {
            return $messages;
        }

        $slice_start = count( $messages ) - $limit;

        // If slice_start begins on a tool response, walk back to include the model tool call
        while ( $slice_start > 0 && isset( $messages[ $slice_start ] ) && ( $messages[ $slice_start ]['role'] === 'tool' ) ) {
            $slice_start--;
        }

        // If slice_start begins on a model tool call, walk back to include the initiating user message
        if ( $slice_start > 0 && isset( $messages[ $slice_start ] ) && ( $messages[ $slice_start ]['role'] === 'model' ) && ! empty( $messages[ $slice_start ]['tool_call_data'] ) ) {
            if ( isset( $messages[ $slice_start - 1 ] ) && $messages[ $slice_start - 1 ]['role'] === 'user' ) {
                $slice_start--;
            }
        }

        // Ensure the conversation history slice strictly begins on a user message
        while ( $slice_start < count( $messages ) && isset( $messages[ $slice_start ] ) && ( $messages[ $slice_start ]['role'] !== 'user' ) ) {
            $slice_start++;
        }

        // Fallback: If walking forward exhausted all items, walk back to find nearest user message
        if ( $slice_start >= count( $messages ) ) {
            $slice_start = count( $messages ) - 1;
            while ( $slice_start > 0 && isset( $messages[ $slice_start ] ) && ( $messages[ $slice_start ]['role'] !== 'user' ) ) {
                $slice_start--;
            }
        }

        return array_values( array_slice( $messages, $slice_start ) );
    }

    /**
     * Convert messages to Gemini API contents format (sliding window of last 30 turns)
     */
    public static function get_gemini_history( int $session_id, int $limit = 30 ): array {
        $raw_messages = self::get_messages( $session_id );
        $messages     = self::trim_message_history( $raw_messages, $limit );
        $contents     = [];
        $count        = count( $messages );

        for ( $i = 0; $i < $count; $i++ ) {
            $msg          = $messages[ $i ];
            $role         = ( $msg['role'] === 'user' ) ? 'user' : 'model';
            $next_is_tool = ( $i + 1 < $count && $messages[ $i + 1 ]['role'] === 'tool' );

            if ( $msg['role'] === 'tool' ) {
                // Gemini tool responses strictly require role 'function' with functionResponse
                $tool_data = $msg['tool_call_data'] ?? [];
                $name      = $tool_data['tool_name'] ?? 'wp_tool';
                $result    = $tool_data['result'] ?? [ 'output' => $msg['content'] ];

                // Ensure response is always an object, never a JSON list []
                $formatted_response = (object) ( is_array( $result ) && empty( $result ) ? (object) [] : $result );

                $part = [
                    'functionResponse' => [
                        'name'     => $name,
                        'response' => $formatted_response,
                    ],
                ];

                $contents[] = [
                    'role'  => 'function',
                    'parts' => [ self::sanitize_gemini_part( $part ) ],
                ];
            } elseif ( ! empty( $msg['tool_call_data'] ) && ! empty( $msg['tool_call_data']['raw_parts'] ) ) {
                if ( $next_is_tool ) {
                    $sanitized_parts = [];
                    foreach ( $msg['tool_call_data']['raw_parts'] as $p ) {
                        $sanitized_parts[] = self::sanitize_gemini_part( $p );
                    }
                    $contents[] = [
                        'role'  => 'model',
                        'parts' => $sanitized_parts,
                    ];
                } else {
                    $text_content = trim( (string) ( $msg['content'] ?? '' ) );
                    $contents[] = [
                        'role'  => 'model',
                        'parts' => [
                            [ 'text' => ! empty( $text_content ) ? $text_content : 'Action pending or cancelled.' ],
                        ],
                    ];
                }
            } elseif ( ! empty( $msg['tool_call_data'] ) && isset( $msg['tool_call_data']['functionCall'] ) ) {
                if ( $next_is_tool ) {
                    $parts = [];
                    $text_content = trim( (string) ( $msg['content'] ?? '' ) );
                    if ( ! empty( $text_content ) ) {
                        $parts[] = [ 'text' => $text_content ];
                    }
                    $parts[] = self::sanitize_gemini_part( [
                        'functionCall' => $msg['tool_call_data']['functionCall'],
                    ] );
                    $contents[] = [
                        'role'  => 'model',
                        'parts' => $parts,
                    ];
                } else {
                    $text_content = trim( (string) ( $msg['content'] ?? '' ) );
                    $fn_name      = $msg['tool_call_data']['functionCall']['name'] ?? 'tool';
                    $contents[] = [
                        'role'  => 'model',
                        'parts' => [
                            [ 'text' => ! empty( $text_content ) ? $text_content : "Action {$fn_name} pending or cancelled." ],
                        ],
                    ];
                }
            } else {
                $text_content = trim( (string) ( $msg['content'] ?? '' ) );
                if ( empty( $text_content ) ) {
                    $text_content = ' ';
                }
                $contents[] = [
                    'role'  => $role,
                    'parts' => [
                        [ 'text' => $text_content ],
                    ],
                ];
            }
        }

        return $contents;
    }

    /**
     * Convert messages to OpenAI chat completions messages format (sliding window of last 30 turns)
     */
    public static function get_openai_history( int $session_id, int $limit = 30 ): array {
        $raw_messages = self::get_messages( $session_id );
        $messages     = self::trim_message_history( $raw_messages, $limit );
        $history      = [];
        $last_tool_call_id = null;

        // Build index of executed tool responses by tool_call_id
        $executed_calls = [];
        foreach ( $messages as $m ) {
            if ( $m['role'] === 'tool' ) {
                $cid = $m['tool_call_data']['tool_call_id'] ?? null;
                if ( $cid ) {
                    $executed_calls[ $cid ] = true;
                }
            }
        }

        foreach ( $messages as $msg ) {
            $meta = $msg['tool_call_data'] ?? [];
            $role = $msg['role'];

            if ( $role === 'user' ) {
                $history[] = [
                    'role'    => 'user',
                    'content' => $msg['content'],
                ];
            } elseif ( $role === 'tool' ) {
                $tool_data   = $meta;
                $call_id     = $tool_data['tool_call_id'] ?? ( $last_tool_call_id ?: ( 'call_' . ( $msg['id'] - 1 ) ) );
                $result      = $tool_data['result'] ?? [ 'output' => $msg['content'] ];
                $content_str = is_string( $result ) ? $result : wp_json_encode( $result );

                $history[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call_id,
                    'content'      => $content_str,
                ];
            } else {
                $is_pending = ( isset( $meta['status'] ) && $meta['status'] === 'pending_approval' );
                $call_id    = $meta['tool_call_id'] ?? ( 'call_' . $msg['id'] );

                // Only attach tool_calls if action was NOT pending and was actually executed by a following tool response
                $has_executed_tool = ( ! $is_pending && isset( $executed_calls[ $call_id ] ) );

                if ( $has_executed_tool && ( ! empty( $meta['functionCall'] ) || ! empty( $meta['tool_name'] ) || ! empty( $meta['raw_message']['tool_calls'] ) ) ) {
                    $last_tool_call_id = $call_id;

                    if ( ! empty( $meta['raw_message']['tool_calls'] ) ) {
                        $tool_calls = [];
                        foreach ( $meta['raw_message']['tool_calls'] as $tc ) {
                            if ( isset( $tc['id'] ) && isset( $executed_calls[ $tc['id'] ] ) ) {
                                $tool_calls[] = $tc;
                            }
                        }
                        if ( empty( $tool_calls ) && ! empty( $meta['raw_message']['tool_calls'][0] ) ) {
                            $tool_calls = [ $meta['raw_message']['tool_calls'][0] ];
                        }
                    } else {
                        $tool_name = $meta['functionCall']['name'] ?? ( $meta['tool_name'] ?? 'wp_tool' );
                        $tool_args = $meta['functionCall']['args'] ?? ( $meta['tool_args'] ?? [] );

                        $tool_calls = [
                            [
                                'id'       => $call_id,
                                'type'     => 'function',
                                'function' => [
                                    'name'      => $tool_name,
                                    'arguments' => is_string( $tool_args ) ? $tool_args : wp_json_encode( $tool_args ),
                                ],
                            ],
                        ];
                    }

                    $history[] = [
                        'role'       => 'assistant',
                        'content'    => ! empty( $msg['content'] ) ? $msg['content'] : null,
                        'tool_calls' => $tool_calls,
                    ];
                } else {
                    $history[] = [
                        'role'    => 'assistant',
                        'content' => $msg['content'],
                    ];
                }
            }
        }

        return $history;
    }

    /**
     * Ensure functionCall.args and functionResponse.response are encoded as JSON objects {} and never lists []
     */
    private static function sanitize_gemini_part( array $part ): array {
        if ( isset( $part['functionCall'] ) ) {
            $call = $part['functionCall'];
            $args = $call['args'] ?? null;
            if ( empty( $args ) || ( is_array( $args ) && empty( $args ) ) ) {
                $call['args'] = (object) [];
            } else {
                $call['args'] = (object) $args;
            }
            return [ 'functionCall' => $call ];
        }

        if ( isset( $part['functionResponse'] ) ) {
            $resp = $part['functionResponse'];
            $resp_data = $resp['response'] ?? null;
            if ( empty( $resp_data ) || ( is_array( $resp_data ) && empty( $resp_data ) ) ) {
                $resp['response'] = (object) [];
            } elseif ( ! is_array( $resp_data ) && ! is_object( $resp_data ) ) {
                $resp['response'] = (object) [ 'result' => $resp_data ];
            } else {
                $resp['response'] = (object) $resp_data;
            }
            return [ 'functionResponse' => $resp ];
        }

        if ( isset( $part['text'] ) ) {
            $t = trim( (string) $part['text'] );
            return [ 'text' => ! empty( $t ) ? $t : ' ' ];
        }

        return $part;
    }

    /**
     * Get paginated audit logs with user info
     */
    public static function get_audit_logs( int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_audit_log';

        $limit  = min( max( $limit, 1 ), 100 );
        $offset = max( $offset, 0 );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if ( empty( $results ) ) {
            return [];
        }

        foreach ( $results as &$row ) {
            $user = get_userdata( (int) $row['user_id'] );
            $row['user_name'] = $user ? $user->display_name : 'Unknown User';
            $row['tool_args'] = json_decode( $row['tool_args'], true ) ?: [];
            $row['previous_state'] = ! empty( $row['previous_state'] ) ? json_decode( $row['previous_state'], true ) : null;
        }

        return $results;
    }

    /**
     * Get total count of audit log entries
     */
    public static function get_audit_logs_count(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpchat_audit_log';
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Get database table stats for WPChat tables
     */
    public static function get_db_stats(): array {
        global $wpdb;

        $tables = [
            'sessions' => $wpdb->prefix . 'wpchat_sessions',
            'messages' => $wpdb->prefix . 'wpchat_messages',
            'audit'    => $wpdb->prefix . 'wpchat_audit_log',
            'snippets' => $wpdb->prefix . 'wpchat_snippets',
        ];

        $counts = [];
        $total_bytes = 0;

        foreach ( $tables as $key => $table_name ) {
            $counts[ $key ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

            $size_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT (data_length + index_length) AS bytes 
                     FROM information_schema.TABLES 
                     WHERE table_schema = %s AND table_name = %s",
                    DB_NAME,
                    $table_name
                )
            );

            if ( $size_row && isset( $size_row->bytes ) ) {
                $total_bytes += (int) $size_row->bytes;
            }
        }

        return [
            'sessions_count' => $counts['sessions'],
            'messages_count' => $counts['messages'],
            'audit_count'    => $counts['audit'],
            'snippets_count' => $counts['snippets'],
            'total_size_kb'  => round( $total_bytes / 1024, 2 ),
        ];
    }

    /**
     * Purge WPChat data by target
     */
    public static function purge_data( string $target ): array {
        global $wpdb;

        switch ( $target ) {
            case 'all_chats':
                $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wpchat_messages" );
                $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wpchat_sessions" );
                return [ 'success' => true, 'message' => 'All chat sessions and messages cleared.' ];

            case 'audit_logs':
                $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wpchat_audit_log" );
                return [ 'success' => true, 'message' => 'Audit log history cleared.' ];

            case 'transients':
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpchat_%' OR option_name LIKE '_transient_timeout_wpchat_%'" );
                delete_option( 'wpchat_pending_tokens' );
                return [ 'success' => true, 'message' => 'Action tokens and transients purged.' ];

            default:
                return [ 'success' => false, 'message' => 'Invalid purge target.' ];
        }
    }

    /**
     * Clean up all sessions and messages for deleted WordPress user
     */
    public static function cleanup_deleted_user( int $user_id ): void {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'wpchat_sessions';
        $table_messages = $wpdb->prefix . 'wpchat_messages';
        $table_audit    = $wpdb->prefix . 'wpchat_audit_log';

        $session_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table_sessions} WHERE user_id = %d", $user_id ) );
        if ( ! empty( $session_ids ) ) {
            $in = implode( ',', array_map( 'intval', $session_ids ) );
            $wpdb->query( "DELETE FROM {$table_messages} WHERE session_id IN ({$in})" );
            $wpdb->query( "DELETE FROM {$table_audit} WHERE session_id IN ({$in})" );
            $wpdb->query( "DELETE FROM {$table_sessions} WHERE id IN ({$in})" );
        }
    }
}
