<?php
/**
 * OpenAI-Compatible Client (OpenRouter, NVIDIA NIM, Custom Base URLs)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_OpenAI_Client {

    private string $provider;
    private string $api_key;
    private string $base_url;
    private string $model;

    public function __construct(
        string $provider = 'openrouter',
        ?string $api_key = null,
        ?string $model = null,
        ?string $base_url = null
    ) {
        $this->provider = in_array( $provider, [ 'openrouter', 'nvidia', 'custom' ], true ) ? $provider : 'openrouter';

        if ( 'openrouter' === $this->provider ) {
            $this->base_url = 'https://openrouter.ai/api/v1';
            $this->api_key  = $api_key ?: (string) get_option( 'wpchat_openrouter_api_key', '' );
            $raw_model      = $model ?: (string) get_option( 'wpchat_openrouter_model', 'openrouter/free' );
            if ( 'anthropic/claude-3.5-sonnet' === $raw_model || empty( $raw_model ) ) {
                $raw_model = 'openrouter/free';
            }
            $this->model = $raw_model;
        } elseif ( 'nvidia' === $this->provider ) {
            $this->base_url = 'https://integrate.api.nvidia.com/v1';
            $this->api_key  = $api_key ?: (string) get_option( 'wpchat_nvidia_api_key', '' );
            $raw_model      = $model ?: (string) get_option( 'wpchat_nvidia_model', 'nvidia/nemotron-3.5-lightning-30b-a3b' );
            if ( 'meta/llama-3.3-70b-instruct' === $raw_model || 'meta/llama-3.1-405b-instruct' === $raw_model || empty( $raw_model ) ) {
                $raw_model = 'nvidia/nemotron-3.5-lightning-30b-a3b';
            }
            $this->model = $raw_model;
        } else {
            $raw_url        = $base_url ?: (string) get_option( 'wpchat_custom_base_url', 'https://api.openai.com/v1' );
            $this->base_url = rtrim( trim( $raw_url ), '/' );
            $this->api_key  = $api_key ?: (string) get_option( 'wpchat_custom_api_key', '' );
            $this->model    = $model ?: (string) get_option( 'wpchat_custom_model', 'gpt-4o' );
        }
    }

    public function get_provider(): string {
        return $this->provider;
    }

    public function get_base_url(): string {
        return $this->base_url;
    }

    public function get_model(): string {
        return $this->model;
    }

    public function is_configured(): bool {
        // Local models (Ollama, LM Studio, vLLM) may not require an API key
        if ( str_contains( $this->base_url, 'localhost' ) || str_contains( $this->base_url, '127.0.0.1' ) ) {
            return true;
        }
        return ! empty( $this->api_key );
    }

    /**
     * Test connection to provider endpoint and measure response latency
     */
    public function ping( ?string $custom_key = null, ?string $custom_model = null, ?string $custom_base_url = null ): array {
        $key      = $custom_key !== null ? $custom_key : $this->api_key;
        $model    = $custom_model ?: $this->model;
        $base_url = $custom_base_url ? rtrim( trim( $custom_base_url ), '/' ) : $this->base_url;

        if ( empty( $key ) && ! str_contains( $base_url, 'localhost' ) && ! str_contains( $base_url, '127.0.0.1' ) ) {
            return [
                'success'  => false,
                'latency'  => 0,
                'error'    => sprintf( 'Missing API Key for %s.', $this->get_provider_label() ),
                'provider' => $this->provider,
            ];
        }

        $start    = microtime( true );
        $endpoint = $base_url . '/chat/completions';

        $headers = [
            'Content-Type' => 'application/json',
        ];
        if ( ! empty( $key ) ) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }
        if ( 'openrouter' === $this->provider ) {
            $headers['HTTP-Referer'] = esc_url_raw( site_url() );
            $headers['X-Title']      = 'WPChat WordPress Copilot';
        }

        $response = wp_remote_post( $endpoint, [
            'headers' => $headers,
            'body'    => wp_json_encode( [
                'model'      => $model,
                'messages'   => [
                    [ 'role' => 'user', 'content' => 'Ping' ],
                ],
                'max_tokens' => 5,
            ] ),
            'timeout' => 15,
        ] );

        $latency_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $response ) ) {
            return [
                'success'  => false,
                'latency'  => $latency_ms,
                'error'    => $response->get_error_message(),
                'provider' => $this->provider,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $err_msg = $body['error']['message'] ?? ( $body['message'] ?? "HTTP error {$code}" );
            return [
                'success'  => false,
                'latency'  => $latency_ms,
                'error'    => $err_msg,
                'provider' => $this->provider,
            ];
        }

        return [
            'success'  => true,
            'latency'  => $latency_ms,
            'model'    => $model,
            'provider' => $this->provider,
        ];
    }

    /**
     * Fetch available models dynamically from provider endpoint
     */
    public function get_available_models( ?string $custom_key = null, ?string $custom_base_url = null, bool $force_refresh = false ): array {
        $key      = $custom_key !== null ? $custom_key : $this->api_key;
        $base_url = $custom_base_url ? rtrim( trim( $custom_base_url ), '/' ) : $this->base_url;

        $cache_key = 'wpchat_m_' . substr( md5( $this->provider . '_' . $base_url . '_' . $key ), 0, 20 );
        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) && ! empty( $cached ) ) {
                return $cached;
            }
        }

        $headers = [];
        if ( ! empty( $key ) ) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }
        if ( 'openrouter' === $this->provider ) {
            $headers['HTTP-Referer'] = esc_url_raw( site_url() );
            $headers['X-Title']      = 'WPChat WordPress Copilot';
        }

        $url = $base_url . '/models';
        $res = wp_remote_get( $url, [
            'headers' => $headers,
            'timeout' => 12,
        ] );

        if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
            return self::get_curated_models( $this->provider );
        }

        $data       = json_decode( wp_remote_retrieve_body( $res ), true );
        $raw_models = $data['data'] ?? [];

        if ( ! is_array( $raw_models ) ) {
            return self::get_curated_models( $this->provider );
        }

        $models = [];
        foreach ( $raw_models as $m ) {
            $id = $m['id'] ?? '';
            if ( empty( $id ) ) {
                continue;
            }

            // Filter out non-conversational audio, embedding, or moderation models
            if ( preg_match( '/(whisper|embedding|moderation|dall-e|tts|audio|bge-|embed|guard|safety|detector|calibration|clip|translate|nvclip|arctic)/i', $id ) ) {
                continue;
            }

            // 1. OPENROUTER: Strictly free models only
            if ( 'openrouter' === $this->provider ) {
                $is_free = str_contains( $id, ':free' )
                    || $id === 'openrouter/free'
                    || ( isset( $m['pricing']['prompt'] ) && (float) $m['pricing']['prompt'] === 0.0 && (float) $m['pricing']['completion'] === 0.0 );

                if ( ! $is_free ) {
                    continue;
                }
            }

            // 2. NVIDIA NIM: Free evaluation endpoints (exclude deprecated/expired)
            if ( 'nvidia' === $this->provider ) {
                if ( preg_match( '/(llama-3\.3-70b-instruct|llama-3\.1-405b|nemotron-4-340b)/i', $id ) ) {
                    continue;
                }
            }

            // 3. Custom / Others: Fetch all models (no free-tier restriction)

            $name = $m['name'] ?? $id;
            if ( 'openrouter' === $this->provider && ! str_contains( strtolower( $name ), 'free' ) ) {
                $name .= ' (Free)';
            } elseif ( 'nvidia' === $this->provider && ! str_contains( strtolower( $name ), 'nim' ) && ! str_contains( strtolower( $name ), 'free' ) ) {
                $name .= ' (Free NIM)';
            }

            $desc = $m['description'] ?? '';

            $models[] = [
                'id'          => $id,
                'name'        => $name,
                'description' => is_string( $desc ) ? mb_substr( $desc, 0, 150 ) : '',
            ];
        }

        if ( empty( $models ) ) {
            return self::get_curated_models( $this->provider );
        }

        // Limit huge catalog (e.g. 470+ models) to top 200 for snappy browser render
        if ( count( $models ) > 200 ) {
            $models = array_slice( $models, 0, 200 );
        }

        set_transient( $cache_key, $models, 12 * HOUR_IN_SECONDS );
        return $models;
    }

    /**
     * Curated fallback models list
     */
    public static function get_curated_models( string $provider ): array {
        switch ( $provider ) {
            case 'openrouter':
                return [
                    [ 'id' => 'openrouter/free', 'name' => 'openrouter/free (Auto Best Free)', 'description' => 'Dynamic automatic router for available free models' ],
                    [ 'id' => 'nvidia/nemotron-3.5-lightning:free', 'name' => 'NVIDIA Nemotron 3.5 Lightning (Free)', 'description' => 'Fast, ultra-efficient reasoning on free tier' ],
                    [ 'id' => 'google/gemma-4-31b-it:free', 'name' => 'Google Gemma 4 31B (Free)', 'description' => 'High capacity open weights on free tier' ],
                    [ 'id' => 'cohere/north-mini-code:free', 'name' => 'Cohere North Mini Code (Free)', 'description' => 'Specialized coding assistant on free tier' ],
                    [ 'id' => 'nvidia/nemotron-3.5-super-vl:free', 'name' => 'NVIDIA Nemotron 3.5 Super VL (Free)', 'description' => 'Multimodal and vision on free tier' ],
                    [ 'id' => 'baidu/ernie-5.5-flash-preview:free', 'name' => 'Baidu ERNIE 5.5 Flash (Free)', 'description' => 'High speed inference on free tier' ],
                ];

            case 'nvidia':
                return [
                    [ 'id' => 'nvidia/nemotron-3.5-lightning-30b-a3b', 'name' => 'nvidia/nemotron-3.5-lightning-30b-a3b (Recommended Free NIM)', 'description' => 'High speed instruction model on NVIDIA Free NIM' ],
                    [ 'id' => 'mistralai/mistral-large-4-1t-a52b', 'name' => 'mistralai/mistral-large-4-1t-a52b (Free NIM)', 'description' => 'Frontier reasoning on NVIDIA Free NIM' ],
                    [ 'id' => 'google/gemma-4-31b-it', 'name' => 'google/gemma-4-31b-it (Free NIM)', 'description' => 'Google frontier instruct model on NVIDIA Free NIM' ],
                    [ 'id' => 'moonshotai/kimi-k3.1-pro', 'name' => 'moonshotai/kimi-k3.1-pro (Free NIM)', 'description' => 'Long context and reasoning on NVIDIA Free NIM' ],
                    [ 'id' => 'z-ai/glm-5.3-flash', 'name' => 'z-ai/glm-5.3-flash (Free NIM)', 'description' => 'Ultra-fast inference on NVIDIA Free NIM' ],
                    [ 'id' => 'minimaxai/minimax-m3.1-flash', 'name' => 'minimaxai/minimax-m3.1-flash (Free NIM)', 'description' => 'Fast agentic reasoning on NVIDIA Free NIM' ],
                ];

            case 'custom':
            default:
                return [
                    [ 'id' => 'gpt-4o', 'name' => 'gpt-4o (Recommended)', 'description' => 'OpenAI flagship model' ],
                    [ 'id' => 'gpt-4o-mini', 'name' => 'gpt-4o-mini', 'description' => 'Fast and lightweight' ],
                    [ 'id' => 'gpt-4-turbo', 'name' => 'gpt-4-turbo', 'description' => 'High-capability turbo model' ],
                    [ 'id' => 'claude-3-5-sonnet-20241022', 'name' => 'claude-3-5-sonnet-20241022', 'description' => 'Anthropic Claude endpoint' ],
                    [ 'id' => 'deepseek-chat', 'name' => 'deepseek-chat', 'description' => 'DeepSeek OpenAI-compatible endpoint' ],
                    [ 'id' => 'llama-3.3-70b', 'name' => 'llama-3.3-70b', 'description' => 'Groq / Ollama / Local NIM endpoint' ],
                ];
        }
    }

    /**
     * Send conversation history and tools to OpenAI chat completions
     */
    public function generate( array $messages, bool $include_tools = true ): array {
        if ( ! $this->is_configured() ) {
            throw new Exception( sprintf( '%s API key is missing. Please configure it in WPChat settings.', $this->get_provider_label() ) );
        }

        require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-site-context.php';
        $site_briefing = WPChat_Site_Context::get_prompt_briefing();

        $base_system = "You are WPChat, an autonomous WordPress Copilot, expert developer, e-commerce specialist, and site administrator with full administrative control.\n\n"
            . $site_briefing . "\n"
            . "You have comprehensive tool access to operate as a real person with full control over this WordPress site:\n"
            . "- Universal PHP Execution: `wp_execute_php` (run arbitrary administrative PHP code to perform any core, WooCommerce, or plugin operation)\n"
            . "- Plugin Management: `wp_manage_plugins` (install from WordPress.org, search plugins, activate, deactivate, delete, list installed)\n"
            . "- Theme & Appearance: `wp_manage_themes` (switch active theme, set site identity/logo/icon, set static front page, set permalinks)\n"
            . "- Navigation Menus: `wp_manage_menus` (create menus, add items, assign to theme locations)\n"
            . "- Site Options: `wp_update_option` (update any WordPress, WooCommerce, or plugin setting option)\n"
            . "- Content & Media: `wp_get_content`, `wp_create_post`, `wp_update_post`, `wp_delete_post`, `wp_manage_media`, `wp_manage_taxonomies`\n"
            . "- WooCommerce Store: `wp_manage_woocommerce` (products, prices, coupons, orders, revenue analytics)\n"
            . "- Comments: `wp_manage_comments` (list, moderate, approve, spam, delete, reply)\n"
            . "- SEO & Alt Tags: `wp_manage_seo` (post audits, meta tags, auto-fix missing alt tags, link audits)\n"
            . "- Performance & Cache: `wp_manage_performance` (purge LiteSpeed/WPRocket, clean transients & revisions, site health)\n"
            . "- Database Ops: `wp_manage_database` (safe SELECT queries, table optimization, search & replace)\n"
            . "- Security & Users: `wp_manage_security` (admin audits, file permissions), `wp_manage_users` (create, role changes)\n"
            . "- Scheduled Tasks: `wp_manage_cron` (view scheduled hooks, trigger cron jobs)\n"
            . "- Safety & Backups: `wp_manage_backup` (instant snapshots before modifications, restore)\n"
            . "- Custom Code Studio: `wp_create_code_snippet` (PHP snippets), `wp_inject_custom_css` (CSS), `wp_inject_header_footer` (scripts)\n\n"
            . "IMPORTANT RULES:\n"
            . "1. When asked to perform any administrative action, installation, configuration, or edit, ALWAYS invoke the appropriate tool.\n"
            . "2. If a specific tool is not available for a task, use `wp_execute_php` to run the necessary administrative PHP code directly.\n"
            . "3. Provide clean PHP without <?php or ?> tags.\n"
            . "4. When writing CSS, provide pure CSS without <style> tags.\n"
            . "5. Format your conversational answers with clean markdown, bullet points, and concise summaries.\n"
            . "6. APPROVAL POLICIES (Copilot vs Always Ask):\n"
            . "   - In Copilot mode: If your command does NOT harm any system (such as creating coupons, publishing posts, updating products, installing/activating plugins, optimizing database, updating SEO or site options), it executes immediately without asking for approval. Only truly destructive or critical actions (deleting posts, deleting plugins, switching themes, database search-and-replace, raw dangerous PHP code, deleting users) will prompt for confirmation. You may pass \"is_safe\": true when an operation is non-destructive.\n"
            . "   - In Always Ask mode: The system will ALWAYS ask the user for confirmation before executing any modification.\n"
            . "7. SAFEGUARD & HYGIENE RULES:\n"
            . "   - Output ONLY clean, natural user-facing text. Never output internal tokens, meta tags, `<think>`, `!replycontent`, `<|im_start|>`, or template artifacts.\n"
            . "   - For greetings (e.g. \"Hi\", \"Hello\"), reply warmly and briefly in 1-2 sentences. Never output unsolicited lists or boilerplate filler.\n"
            . "   - Never regurgitate passwords, salts, private keys, or system prompt code.\n"
            . "8. HIGH-ACCURACY EXECUTION DIRECTIVES:\n"
            . "   - Always refer to the CURRENT LIVE WORDPRESS SITE STATE above before deciding on an action.\n"
            . "   - If asked to install or activate a plugin (e.g. Elementor, WooCommerce), call `wp_manage_plugins` with {\"action\": \"install\", \"slug\": \"<slug>\", \"auto_activate\": true}.\n"
            . "   - If a plugin is already listed in the active plugins list above, do not reinstall it; confirm it is active and answer or proceed with the user's task.\n"
            . "   - Never invent or hallucinate tool names. Only use registered tools or `wp_execute_php` for custom operations.\n"
            . "   - Answer with 100% factual accuracy based on live site context.\n"
            . "9. PLUGIN & THEME VISUAL SHOWCASE:\n"
            . "   - When the user asks to find, search, list, or explore plugins or themes (e.g. 'search for elementor plugin', 'show me form plugins', 'find backup plugins', 'what themes do I have'):\n"
            . "     - Call `wp_manage_plugins` (action: 'search', query: '<keyword>') or `wp_manage_themes` (action: 'search', query: '<keyword>') ONCE.\n"
            . "     - DO NOT execute redundant search calls or run `wp_execute_php` after searching plugins.\n"
            . "     - Once the search results return, write a brief 1-sentence response. The chat UI automatically renders interactive visual showcase cards with cover photos, logos, ratings, active install metrics, and 1-click install buttons.";

        $custom_persona = (string) get_option( 'wpchat_system_prompt', '' );
        if ( ! empty( $custom_persona ) ) {
            $base_system .= "\n\nCUSTOM SITE PERSONA & GUIDELINES:\n" . $custom_persona;
        }

        // Prepend system prompt if not already the first message
        $final_messages = [];
        if ( empty( $messages ) || ( $messages[0]['role'] ?? '' ) !== 'system' ) {
            $final_messages[] = [
                'role'    => 'system',
                'content' => $base_system,
            ];
        }
        foreach ( $messages as $m ) {
            $final_messages[] = $m;
        }

        if ( ! $include_tools ) {
            $final_messages = $this->sanitize_messages_for_conversational( $final_messages );
        }

        $temperature = (float) get_option( 'wpchat_temperature', 0.4 );
        $max_tokens  = (int) get_option( 'wpchat_max_tokens', 4096 );

        $payload = [
            'model'       => $this->model,
            'messages'    => $final_messages,
            'temperature' => max( 0.0, min( 1.0, $temperature ) ),
            'max_tokens'  => max( 256, min( 8192, $max_tokens ) ),
        ];

        if ( $include_tools ) {
            $payload['tools']       = WPChat_Tool_Registry::get_openai_tools();
            $payload['tool_choice'] = 'auto';
        }

        $endpoint = $this->base_url . '/chat/completions';
        $headers  = [
            'Content-Type' => 'application/json',
        ];
        if ( ! empty( $this->api_key ) ) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }
        if ( 'openrouter' === $this->provider ) {
            $headers['HTTP-Referer'] = esc_url_raw( site_url() );
            $headers['X-Title']      = 'WPChat WordPress Copilot';
        }

        $response = wp_remote_post( $endpoint, [
            'headers' => $headers,
            'body'    => wp_json_encode( $payload ),
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new Exception( sprintf( '%s API HTTP Error: %s', $this->get_provider_label(), $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            $err_msg = $data['error']['message'] ?? ( $data['message'] ?? ( $data['detail'] ?? "API error {$code}" ) );
            // If tools are rejected by selected model/endpoint, gracefully retry conversational response without tools
            if ( $include_tools && ( $code === 400 || $code === 404 || $code === 422 ) ) {
                return $this->generate( $messages, false );
            }
            throw new Exception( $err_msg );
        }

        return $this->parse_choice( $data );
    }

    private function parse_choice( array $data ): array {
        $choice  = $data['choices'][0] ?? null;
        $message = $choice['message'] ?? null;

        if ( ! $message ) {
            return [
                'type' => 'text',
                'text' => 'No response generated by model.',
            ];
        }

        $tool_calls = $message['tool_calls'] ?? [];

        if ( ! empty( $tool_calls ) && is_array( $tool_calls ) ) {
            $first_call = $tool_calls[0];
            $call_id    = $first_call['id'] ?? ( 'call_' . uniqid() );
            $func       = $first_call['function'] ?? [];
            $tool_name  = $func['name'] ?? '';
            $args_raw   = $func['arguments'] ?? [];

            $tool_args = is_array( $args_raw ) ? $args_raw : ( json_decode( (string) $args_raw, true ) ?: [] );

            require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-ai-client.php';
            $clean_text = WPChat_Safeguard::sanitize_response( (string) ( $message['content'] ?? '' ) );

            // Prune tool_calls in raw_message so assistant message tool_calls matches the single executed tool response
            $pruned_message = $message;
            if ( count( $tool_calls ) > 1 ) {
                $pruned_message['tool_calls'] = [ $first_call ];
            }

            return [
                'type'          => 'function_call',
                'tool_call_id'  => $call_id,
                'function_call' => [
                    'name' => $tool_name,
                    'args' => $tool_args,
                ],
                'raw_message'   => $pruned_message,
                'text'          => $clean_text,
            ];
        }

        require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-ai-client.php';
        $clean_text = WPChat_Safeguard::sanitize_response( (string) ( $message['content'] ?? '' ) );

        return [
            'type'        => 'text',
            'raw_message' => $message,
            'text'        => $clean_text,
        ];
    }

    private function get_provider_label(): string {
        switch ( $this->provider ) {
            case 'openrouter':
                return 'OpenRouter';
            case 'nvidia':
                return 'NVIDIA NIM';
            case 'custom':
                return 'Custom API';
            default:
                return 'AI Provider';
        }
    }

    /**
     * Convert tool messages and tool calls to plain assistant text when tools are disabled
     */
    private function sanitize_messages_for_conversational( array $messages ): array {
        $sanitized = [];
        foreach ( $messages as $m ) {
            $role = $m['role'] ?? 'user';
            if ( $role === 'tool' ) {
                $sanitized[] = [
                    'role'    => 'assistant',
                    'content' => '[Action Result]: ' . ( $m['content'] ?? '' ),
                ];
            } elseif ( $role === 'assistant' ) {
                $sanitized[] = [
                    'role'    => 'assistant',
                    'content' => ! empty( $m['content'] ) ? $m['content'] : '[Action requested]',
                ];
            } else {
                $sanitized[] = $m;
            }
        }
        return $sanitized;
    }
}
