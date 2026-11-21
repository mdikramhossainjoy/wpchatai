<?php
/**
 * Unified AI Client Factory & Router
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_AI_Client {

    /**
     * Get configured client instance for the requested or currently active provider
     */
    public static function get_client(
        ?string $provider = null,
        ?string $api_key = null,
        ?string $model = null,
        ?string $base_url = null
    ) {
        $active_provider = $provider ?: self::get_active_provider();

        if ( 'gemini' === $active_provider ) {
            return new WPChat_Gemini_Client( $api_key, $model );
        }

        return new WPChat_OpenAI_Client( $active_provider, $api_key, $model, $base_url );
    }

    /**
     * Get active provider key: 'gemini', 'openrouter', 'nvidia', or 'custom'
     */
    public static function get_active_provider(): string {
        $provider = (string) get_option( 'wpchat_ai_provider', 'gemini' );
        if ( ! in_array( $provider, [ 'gemini', 'openrouter', 'nvidia', 'custom' ], true ) ) {
            return 'gemini';
        }
        return $provider;
    }

    /**
     * Get current model for the given provider
     */
    public static function get_active_model( ?string $provider = null ): string {
        $prov = $provider ?: self::get_active_provider();
        switch ( $prov ) {
            case 'openrouter':
                $m = (string) get_option( 'wpchat_openrouter_model', 'openrouter/free' );
                return ( 'anthropic/claude-3.5-sonnet' === $m || empty( $m ) ) ? 'openrouter/free' : $m;
            case 'nvidia':
                $m = (string) get_option( 'wpchat_nvidia_model', 'nvidia/nemotron-3.5-lightning-30b-a3b' );
                return ( 'meta/llama-3.3-70b-instruct' === $m || 'meta/llama-3.1-405b-instruct' === $m || empty( $m ) ) ? 'nvidia/nemotron-3.5-lightning-30b-a3b' : $m;
            case 'custom':
                return (string) get_option( 'wpchat_custom_model', 'gpt-4o' );
            case 'gemini':
            default:
                return (string) get_option( 'wpchat_gemini_model', 'gemini-flash-latest' );
        }
    }

    /**
     * Check if a specific or active provider has an API key configured
     */
    public static function has_api_key( ?string $provider = null ): bool {
        $prov = $provider ?: self::get_active_provider();
        switch ( $prov ) {
            case 'openrouter':
                return ! empty( get_option( 'wpchat_openrouter_api_key' ) );
            case 'nvidia':
                return ! empty( get_option( 'wpchat_nvidia_api_key' ) );
            case 'custom':
                $custom_url = (string) get_option( 'wpchat_custom_base_url', '' );
                if ( str_contains( $custom_url, 'localhost' ) || str_contains( $custom_url, '127.0.0.1' ) ) {
                    return true;
                }
                return ! empty( get_option( 'wpchat_custom_api_key' ) );
            case 'gemini':
            default:
                return ! empty( get_option( 'wpchat_gemini_api_key' ) ) || ! empty( get_option( 'wpchat_api_key' ) );
        }
    }
}

/**
 * AI Response Safeguard & Content Hygiene Engine
 */
class WPChat_Safeguard {

    /**
     * Comprehensive response sanitizer and safeguard filter
     */
    public static function sanitize_response( string $text ): string {
        if ( empty( $text ) ) {
            return '';
        }

        // 1. Strip thought blocks (DeepSeek, reasoning models, custom thought tags)
        $text = preg_replace( '/<think\b[^>]*>[\s\S]*?<\/think>/i', '', $text );
        $text = preg_replace( '/<thought\b[^>]*>[\s\S]*?<\/thought>/i', '', $text );
        $text = preg_replace( '/<think\b[^>]*>[\s\S]*$/i', '', $text );
        $text = preg_replace( '/<thought\b[^>]*>[\s\S]*$/i', '', $text );
        $text = preg_replace( '/\[(?:thought|internal|system|debug)\][\s\S]*?\[\/(?:thought|internal|system|debug)\]/i', '', $text );
        $text = preg_replace( '/^\[(?:thought|internal|system|debug)\].*?$/im', '', $text );

        // 2. Strip leaked model control tokens, chat tokens, role headers
        $text = preg_replace( '/<\|(?:im_start|im_end|system|assistant|user|endoftext|padding)[^>]*\|>/i', '', $text );
        $text = preg_replace( '/<\|[a-z0-9_ -]+\|>/i', '', $text );
        $text = preg_replace( '/\[(?:INST|SYS|OUT)\]/i', '', $text );
        $text = preg_replace( '/\[\/(?:INST|SYS|OUT)\]/i', '', $text );

        // 3. Fix and strip corrupted replycontent artifacts (e.g. !replycontentm -> I'm)
        $text = preg_replace( '/!replycontentm\b/i', "I'm", $text );
        $text = preg_replace( '/!replycontent[a-z0-9_]*/i', '', $text );
        $text = preg_replace( '/\[replycontent[a-z0-9_]*\]/i', '', $text );

        // 4. Strip broken image markdown tags that point to single letters or internal tokens
        $text = preg_replace( '/!\[[^\]]*\]\([a-z0-9]{1,2}\)/i', '', $text );

        // 5. Redact sensitive credentials (API keys, salts, passwords)
        $text = preg_replace( '/AIzaSy[A-Za-z0-9_-]{33}/', '[REDACTED_API_KEY]', $text );
        $text = preg_replace( '/sk-(?:or-v1-)?[A-Za-z0-9_-]{24,}/', '[REDACTED_API_KEY]', $text );
        $text = preg_replace( '/nvapi-[A-Za-z0-9_-]{24,}/', '[REDACTED_API_KEY]', $text );

        if ( defined( 'AUTH_KEY' ) && strlen( AUTH_KEY ) > 10 ) {
            $text = str_replace( AUTH_KEY, '[REDACTED_SALT]', $text );
        }
        if ( defined( 'SECURE_AUTH_KEY' ) && strlen( SECURE_AUTH_KEY ) > 10 ) {
            $text = str_replace( SECURE_AUTH_KEY, '[REDACTED_SALT]', $text );
        }
        if ( defined( 'LOGGED_IN_KEY' ) && strlen( LOGGED_IN_KEY ) > 10 ) {
            $text = str_replace( LOGGED_IN_KEY, '[REDACTED_SALT]', $text );
        }
        if ( defined( 'NONCE_KEY' ) && strlen( NONCE_KEY ) > 10 ) {
            $text = str_replace( NONCE_KEY, '[REDACTED_SALT]', $text );
        }
        if ( defined( 'DB_PASSWORD' ) && strlen( DB_PASSWORD ) > 3 ) {
            $text = str_replace( DB_PASSWORD, '[REDACTED_PASSWORD]', $text );
        }

        // 6. Normalize whitespace
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );
        $trimmed = trim( $text );

        if ( empty( $trimmed ) ) {
            return 'I completed the action, but no additional response was generated.';
        }

        return $trimmed;
    }

    /**
     * Verify if text violates basic safety policies
     */
    public static function is_harmful( string $text ): bool {
        $patterns = [
            '/\b(?:eval|base64_decode|gzinflate|str_rot13)\s*\(/i',
            '/\b(?:passthru|shell_exec|exec|system|popen|proc_open)\s*\(/i',
            '/DROP\s+TABLE\b/i',
            '/TRUNCATE\s+TABLE\b/i',
            '/UNION\s+SELECT\b/i',
        ];
        foreach ( $patterns as $pat ) {
            if ( preg_match( $pat, $text ) ) {
                return true;
            }
        }
        return false;
    }
}
