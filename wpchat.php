<?php
/**
 * Plugin Name:       WPChat — Autonomous AI WordPress Copilot
 * Plugin URI:        https://github.com/wpchat/wpchat
 * Description:       Manage, build, and modify your entire WordPress site via natural conversation powered by AI (Gemini, OpenRouter, NVIDIA NIM, Custom OpenAI) with tool calling and human-in-the-loop approval gates.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            MD Ikram Hossain Joy
 * License:           GPL-2.0-or-later
 * Text Domain:       wpchat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin Constants
define( 'WPCHAT_VERSION', '1.0.0' );
define( 'WPCHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCHAT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload Classes
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-activator.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-db.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-site-context.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-gemini-client.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-openai-client.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-ai-client.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-tool-registry.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-tool-executor.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-action-gate.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-audit-logger.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-code-engine.php';
require_once WPCHAT_PLUGIN_DIR . 'includes/class-wpchat-rest-api.php';

/**
 * Main Plugin Class
 */
final class WPChat {

    private static ?WPChat $instance = null;

    public static function instance(): WPChat {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( __FILE__, [ 'WPChat_Activator', 'activate' ] );
        register_deactivation_hook( __FILE__, [ 'WPChat_Activator', 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init(): void {
        // Initialize Code Engine (snippets, CSS, header/footer)
        WPChat_Code_Engine::init();

        // Initialize REST API
        WPChat_REST_API::init();

        // User deletion cleanup hook
        add_action( 'deleted_user', [ 'WPChat_DB', 'cleanup_deleted_user' ] );

        // Admin hooks
        if ( is_admin() ) {
            add_action( 'admin_head', [ $this, 'inject_preconnect_fonts' ] );
            add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
            add_action( 'admin_footer', [ $this, 'render_admin_root' ] );
        }
    }

    public function inject_preconnect_fonts(): void {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&family=Google+Sans+Text:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n";
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __( 'WPChat Settings', 'wpchat' ),
            __( 'WPChat', 'wpchat' ),
            'manage_options',
            'wpchat-settings',
            [ $this, 'render_settings_page' ],
            plugins_url( 'assets/logo-menu-32.webp', __FILE__ ),
            65
        );
    }

    public function render_settings_page(): void {
        ?>
        <div class="wrap" id="wpchat-settings-page" style="margin-right:20px;max-width:none;padding-top:8px;">
            <div id="wpchat-settings-mount"></div>
        </div>
        <?php
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( ! WPChat_REST_API::check_chat_permission() ) {
            return;
        }

        $js_file  = WPCHAT_PLUGIN_DIR . 'build/index.js';
        $css_file = WPCHAT_PLUGIN_DIR . 'build/index.css';

        $js_ver  = file_exists( $js_file ) ? filemtime( $js_file ) : WPCHAT_VERSION;
        $css_ver = file_exists( $css_file ) ? filemtime( $css_file ) : WPCHAT_VERSION;

        wp_enqueue_style(
            'wpchat-google-fonts',
            'https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&family=Google+Sans+Text:wght@400;500;600;700&display=swap',
            [],
            null
        );

        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'wpchat-app-css',
                WPCHAT_PLUGIN_URL . 'build/index.css',
                [ 'wpchat-google-fonts' ],
                $css_ver
            );
            wp_add_inline_style(
                'wpchat-app-css',
                '#toplevel_page_wpchat-settings .wp-menu-image img { width: 18px; height: 18px; padding-top: 8px; opacity: 0.85; transition: opacity .15s ease-in-out; }' .
                '#toplevel_page_wpchat-settings:hover .wp-menu-image img, #toplevel_page_wpchat-settings.current .wp-menu-image img { opacity: 1; }' .
                '.admin-color-light #toplevel_page_wpchat-settings .wp-menu-image img { filter: invert(1); }'
            );
        }

        if ( file_exists( $js_file ) ) {
            wp_enqueue_script(
                'wpchat-app-js',
                WPCHAT_PLUGIN_URL . 'build/index.js',
                [ 'wp-element' ],
                $js_ver,
                true
            );

            wp_localize_script(
                'wpchat-app-js',
                'wpchatConfig',
                [
                    'restUrl'       => esc_url_raw( rest_url( 'wpchat/v1/' ) ),
                    'nonce'         => wp_create_nonce( 'wp_rest' ),
                    'currentUser'   => wp_get_current_user()->display_name,
                    'siteName'      => get_bloginfo( 'name' ),
                    'siteTagline'   => get_bloginfo( 'description' ),
                    'adminUrl'      => admin_url(),
                    'defaultMode'      => get_option( 'wpchat_approval_mode', 'copilot' ),
                    'aiProvider'       => WPChat_AI_Client::get_active_provider(),
                    'activeModel'      => WPChat_AI_Client::get_active_model(),
                    'hasApiKey'        => WPChat_AI_Client::has_api_key(),
                    'hasGeminiKey'     => WPChat_AI_Client::has_api_key( 'gemini' ),
                    'hasOpenrouterKey' => WPChat_AI_Client::has_api_key( 'openrouter' ),
                    'hasNvidiaKey'     => WPChat_AI_Client::has_api_key( 'nvidia' ),
                    'hasCustomKey'     => WPChat_AI_Client::has_api_key( 'custom' ),
                    'customBaseUrl'    => (string) get_option( 'wpchat_custom_base_url', 'https://api.openai.com/v1' ),
                    'isAdmin'          => current_user_can( 'manage_options' ),
                    'pluginVersion'    => WPCHAT_VERSION,
                    'authorName'    => 'MD Ikram Hossain Joy',
                    'activeTheme'   => wp_get_theme()->get( 'Name' ),
                    'logoUrl'       => esc_url_raw( plugin_dir_url( __FILE__ ) . 'assets/bot-avatar.webp' ),
                    'logoDark'      => esc_url_raw( plugin_dir_url( __FILE__ ) . 'assets/logo-emblem-dark.webp' ),
                    'logoLight'     => esc_url_raw( plugin_dir_url( __FILE__ ) . 'assets/logo-emblem-light.webp' ),
                    'assetsUrl'     => esc_url_raw( plugin_dir_url( __FILE__ ) . 'assets/' ),
                    'i18n'          => [
                        'placeholder'      => sprintf( __( 'Ask WPChat to build or edit %s...', 'wpchat' ), get_bloginfo( 'name' ) ),
                        'newChat'          => __( 'New Chat', 'wpchat' ),
                        'emptyTitle'       => sprintf( __( 'How can I help %s today?', 'wpchat' ), get_bloginfo( 'name' ) ),
                        'emptySubtitle'    => sprintf( __( 'AI copilot for %s. Build posts, plugins, styles, and tools.', 'wpchat' ), get_bloginfo( 'name' ) ),
                        'enterToSend'      => __( 'Press Enter to send', 'wpchat' ),
                        'apiKeyRequired'   => __( 'API Key Needed', 'wpchat' ),
                        'apiKeyDesc'       => __( 'Add an API key to start using WPChat.', 'wpchat' ),
                        'enterApiKey'      => __( 'Enter API Key', 'wpchat' ),
                        'suggestedActions' => __( 'Quick Ideas', 'wpchat' ),
                        'recentChats'      => __( 'Past Chats', 'wpchat' ),
                        'noPreviousChats'  => __( 'No chats yet', 'wpchat' ),
                        'codeStudio'       => __( 'Code Studio', 'wpchat' ),
                        'settings'         => __( 'Settings', 'wpchat' ),
                        'closeDrawer'      => __( 'Close (Esc)', 'wpchat' ),
                        'connected'        => __( 'Ready', 'wpchat' ),
                        'missingKey'       => __( 'No Key', 'wpchat' ),
                        'promptRedirect'   => __( 'Redirect logged-out users on checkout', 'wpchat' ),
                        'promptCss'        => __( 'Add custom CSS for sticky menu', 'wpchat' ),
                        'promptGa'         => __( 'Add Google tag to header', 'wpchat' ),
                        'promptPost'       => sprintf( __( 'Write a quick blog post for %s', 'wpchat' ), get_bloginfo( 'name' ) ),
                        'alwaysAsk'        => __( 'Always Ask', 'wpchat' ),
                        'alwaysAskDesc'    => __( 'Confirm before every change', 'wpchat' ),
                        'copilot'          => __( 'Copilot', 'wpchat' ),
                        'copilotDesc'      => __( 'Run safe actions; confirm big changes', 'wpchat' ),
                        'turbo'            => __( 'Turbo', 'wpchat' ),
                        'turboDesc'        => __( 'Run directly with 1-click undo', 'wpchat' ),
                        'undo'             => __( 'Undo', 'wpchat' ),
                        'executed'         => __( 'Done', 'wpchat' ),
                        'rejected'         => __( 'Cancelled', 'wpchat' ),
                    ],
                ]
            );
        }
    }

    public function render_admin_root(): void {
        if ( ! WPChat_REST_API::check_chat_permission() ) {
            return;
        }
        echo '<div id="wpchat-root"></div>';
    }
}

// Launch plugin
WPChat::instance();
