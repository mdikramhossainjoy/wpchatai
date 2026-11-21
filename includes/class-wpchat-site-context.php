<?php
/**
 * Dynamic Live Site Context Provider
 * 100% AJAX detectable without requiring page refresh.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_Site_Context {

    private static ?array $cached_context = null;

    /**
     * Gather live WordPress environment context
     */
    public static function get_context( bool $force_refresh = false ): array {
        if ( ! $force_refresh && null !== self::$cached_context ) {
            return self::$cached_context;
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $active_plugins_opt = get_option( 'active_plugins', [] );
        $all_plugins        = function_exists( 'get_plugins' ) ? get_plugins() : [];

        // Active plugins list
        $active_plugins_list = [];
        foreach ( $active_plugins_opt as $plugin_file ) {
            $info = $all_plugins[ $plugin_file ] ?? null;
            $name = $info['Name'] ?? basename( dirname( $plugin_file ) );
            $active_plugins_list[] = [
                'name'    => $name,
                'file'    => $plugin_file,
                'version' => $info['Version'] ?? '',
                'slug'    => dirname( $plugin_file ) !== '.' ? dirname( $plugin_file ) : basename( $plugin_file, '.php' ),
            ];
        }

        // Check WooCommerce live
        $has_woo = class_exists( 'WooCommerce' )
            || in_array( 'woocommerce/woocommerce.php', $active_plugins_opt, true );

        $woo_data = [
            'active' => $has_woo,
        ];

        if ( $has_woo ) {
            $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
            $symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : '$';
            $products_obj = wp_count_posts( 'product' );
            $orders_obj   = wp_count_posts( 'shop_order' );
            $products_count = isset( $products_obj->publish ) ? (int) $products_obj->publish : 0;
            $orders_count   = isset( $orders_obj->publish ) ? (int) $orders_obj->publish : 0;

            $woo_data['currency']        = $currency;
            $woo_data['currency_symbol'] = $symbol;
            $woo_data['total_products']  = $products_count;
            $woo_data['total_orders']    = $orders_count;
        }

        // Check Elementor live
        $has_elementor = defined( 'ELEMENTOR_VERSION' ) || in_array( 'elementor/elementor.php', $active_plugins_opt, true );
        $elementor_data = [
            'active'  => $has_elementor,
            'version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
        ];

        // Check SEO plugins
        $has_yoast     = defined( 'WPSEO_VERSION' ) || in_array( 'wordpress-seo/wp-seo.php', $active_plugins_opt, true );
        $has_rankmath  = defined( 'RANK_MATH_VERSION' ) || in_array( 'seo-by-rank-math/rank-math.php', $active_plugins_opt, true );
        $seo_plugin    = $has_yoast ? 'Yoast SEO' : ( $has_rankmath ? 'Rank Math' : 'Core' );

        // Check Caching plugins
        $cache_engine = 'Core Object Cache';
        if ( defined( 'LSCWP_V' ) ) {
            $cache_engine = 'LiteSpeed Cache';
        } elseif ( defined( 'WP_ROCKET_VERSION' ) ) {
            $cache_engine = 'WP Rocket';
        } elseif ( function_exists( 'wp_cache_clear_cache' ) ) {
            $cache_engine = 'WP Super Cache';
        }

        $theme = wp_get_theme();

        // Recent posts
        $recent_posts = wp_get_recent_posts( [
            'numberposts' => 5,
            'post_status' => 'publish',
        ] );
        $recent_list = [];
        if ( is_array( $recent_posts ) ) {
            foreach ( $recent_posts as $p ) {
                $recent_list[] = [
                    'id'    => $p['ID'],
                    'title' => $p['post_title'],
                ];
            }
        }

        $post_counts = wp_count_posts( 'post' );
        $page_counts = wp_count_posts( 'page' );
        $user_counts = count_users();

        $context = [
            'site_name'             => get_bloginfo( 'name' ),
            'site_tagline'          => get_bloginfo( 'description' ),
            'site_url'              => home_url(),
            'admin_url'             => admin_url(),
            'wp_version'            => get_bloginfo( 'version' ),
            'php_version'           => PHP_VERSION,
            'active_theme'          => [
                'name'    => $theme->get( 'Name' ),
                'version' => $theme->get( 'Version' ),
            ],
            'plugins_active_count'  => count( $active_plugins_opt ),
            'active_plugins'        => $active_plugins_list,
            'woocommerce'           => $woo_data,
            'elementor'             => $elementor_data,
            'seo'                   => [
                'plugin' => $seo_plugin,
                'active' => ( 'Core' !== $seo_plugin ),
            ],
            'performance'           => [
                'cache_engine' => $cache_engine,
                'memory_limit' => ini_get( 'memory_limit' ),
            ],
            'counts'                => [
                'posts'      => isset( $post_counts->publish ) ? (int) $post_counts->publish : 0,
                'pages'      => isset( $page_counts->publish ) ? (int) $page_counts->publish : 0,
                'media'      => array_sum( (array) wp_count_attachments() ),
                'categories' => (int) wp_count_terms( [ 'taxonomy' => 'category' ] ),
                'users'      => (int) ( $user_counts['total_users'] ?? 1 ),
            ],
            'recent_posts'          => $recent_list,
            'timestamp'             => time(),
        ];

        self::$cached_context = $context;
        return $context;
    }

    /**
     * Build rich text prompt briefing for Gemini/OpenAI system instruction
     */
    public static function get_prompt_briefing(): string {
        $ctx = self::get_context();
        $woo = $ctx['woocommerce'];
        $ele = $ctx['elementor'] ?? [ 'active' => false ];

        $briefing = "=== CURRENT LIVE WORDPRESS SITE STATE (REAL-TIME AJAX EVALUATED) ===\n";
        $briefing .= "Site Name: {$ctx['site_name']} ({$ctx['site_url']})\n";
        $briefing .= "Theme: {$ctx['active_theme']['name']} v{$ctx['active_theme']['version']}\n";
        $briefing .= "WordPress: v{$ctx['wp_version']} | PHP: v{$ctx['php_version']}\n";
        $briefing .= "Content: {$ctx['counts']['posts']} posts, {$ctx['counts']['pages']} pages, {$ctx['counts']['media']} media items, {$ctx['counts']['users']} users.\n";

        // Active plugins breakdown
        $briefing .= "Active Plugins (" . count( $ctx['active_plugins'] ) . " installed/active):\n";
        if ( empty( $ctx['active_plugins'] ) ) {
            $briefing .= "- None\n";
        } else {
            foreach ( $ctx['active_plugins'] as $pl ) {
                $briefing .= "- {$pl['name']} (slug: {$pl['slug']}, file: {$pl['file']})\n";
            }
        }

        if ( $woo['active'] ) {
            $briefing .= "WOOCOMMERCE DETECTED: Active e-commerce store!\n";
            $briefing .= "- Currency: {$woo['currency']} ({$woo['currency_symbol']})\n";
            $briefing .= "- Total Products: {$woo['total_products']}\n";
            $briefing .= "- Total Orders: {$woo['total_orders']}\n";
            $briefing .= "- Use `wp_manage_woocommerce` for products, coupons, prices, and orders!\n";
        } else {
            $briefing .= "WOOCOMMERCE STATUS: Not active.\n";
        }

        if ( $ele['active'] ) {
            $briefing .= "ELEMENTOR DETECTED: Elementor Page Builder is active (v{$ele['version']}).\n";
        }

        $briefing .= "SEO Handler: {$ctx['seo']['plugin']}\n";
        $briefing .= "Cache Engine: {$ctx['performance']['cache_engine']}\n";

        if ( ! empty( $ctx['recent_posts'] ) ) {
            $recent_titles = array_map( function( $p ) {
                return "#{$p['id']} \"{$p['title']}\"";
            }, $ctx['recent_posts'] );
            $briefing .= "Recent Published Posts: " . implode( ', ', $recent_titles ) . "\n";
        }

        $briefing .= "====================================================================\n";

        return $briefing;
    }
}
