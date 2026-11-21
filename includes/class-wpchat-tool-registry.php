<?php
/**
 * Tool Registry & Schema Declarations for Gemini
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPChat_Tool_Registry {

    /**
     * Forbidden options that can NEVER be changed by AI (Encryption keys, salts, passwords)
     */
    const FORBIDDEN_OPTIONS = [
        'active_plugins',
        'siteurl',
        'home',
        'admin_email',
        'users_can_register',
        'default_role',
        'wpchat_killswitch',
        'wpchat_blocked_tools',
        'wpchat_allowed_role',
        'template',
        'stylesheet',
        'cron',
        'auth_key',
        'secure_auth_key',
        'logged_in_key',
        'nonce_key',
        'auth_salt',
        'secure_auth_salt',
        'logged_in_salt',
        'nonce_salt',
        'mailserver_pass',
        'ftp_credentials',
    ];

    /**
     * Allowed options - maintained for backwards compatibility
     */
    const ALLOWED_OPTIONS = [
        'blogname',
        'blogdescription',
        'posts_per_page',
        'date_format',
        'time_format',
        'start_of_week',
        'default_category',
        'default_comment_status',
        'default_ping_status',
    ];

    /**
     * Return all Gemini function declarations
     */
    public static function get_declarations(): array {
        return [
            [
                'name'        => 'wp_get_site_meta',
                'description' => 'Get current WordPress site information, active theme, WordPress version, site URL, and installed plugins.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => (object) [],
                ],
            ],
            [
                'name'        => 'wp_get_content',
                'description' => 'Search and query WordPress posts, pages, or custom post types.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'post_type'   => [
                            'type'        => 'STRING',
                            'description' => 'Post type: post, page, or custom slug. Default is post.',
                        ],
                        'post_status' => [
                            'type'        => 'STRING',
                            'description' => 'publish, draft, pending, trash, or any. Default is any.',
                        ],
                        'search'      => [
                            'type'        => 'STRING',
                            'description' => 'Keyword search term to filter posts by title or content.',
                        ],
                        'number'      => [
                            'type'        => 'INTEGER',
                            'description' => 'Maximum number of items to return (1-20). Default 10.',
                        ],
                        'post_id'     => [
                            'type'        => 'INTEGER',
                            'description' => 'Optional specific post ID to fetch full content for.',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'wp_create_post',
                'description' => 'Create a new post, page, or custom post type in WordPress.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'post_title'   => [
                            'type'        => 'STRING',
                            'description' => 'The title of the post.',
                        ],
                        'post_content' => [
                            'type'        => 'STRING',
                            'description' => 'The HTML or Gutenberg block content of the post.',
                        ],
                        'post_status'  => [
                            'type'        => 'STRING',
                            'enum'        => [ 'draft', 'publish', 'pending' ],
                            'description' => 'Status: draft (recommended for safety), publish, or pending.',
                        ],
                        'post_type'    => [
                            'type'        => 'STRING',
                            'description' => 'Post type: post, page, etc. Default is post.',
                        ],
                        'post_excerpt' => [
                            'type'        => 'STRING',
                            'description' => 'Short summary or excerpt for the post.',
                        ],
                    ],
                    'required'   => [ 'post_title', 'post_content' ],
                ],
            ],
            [
                'name'        => 'wp_update_post',
                'description' => 'Update an existing WordPress post or page.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'post_id'      => [
                            'type'        => 'INTEGER',
                            'description' => 'ID of the post to update.',
                        ],
                        'post_title'   => [
                            'type'        => 'STRING',
                            'description' => 'Updated title (optional).',
                        ],
                        'post_content' => [
                            'type'        => 'STRING',
                            'description' => 'Updated content (optional).',
                        ],
                        'post_status'  => [
                            'type'        => 'STRING',
                            'enum'        => [ 'draft', 'publish', 'pending', 'trash' ],
                            'description' => 'Updated status (optional).',
                        ],
                        'post_excerpt' => [
                            'type'        => 'STRING',
                            'description' => 'Updated excerpt (optional).',
                        ],
                    ],
                    'required'   => [ 'post_id' ],
                ],
            ],
            [
                'name'        => 'wp_delete_post',
                'description' => 'Move a post, page, or attachment to the trash or permanently delete it.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'post_id'      => [
                            'type'        => 'INTEGER',
                            'description' => 'The ID of the post to delete.',
                        ],
                        'force_delete' => [
                            'type'        => 'BOOLEAN',
                            'description' => 'True for permanent delete, false to move to trash. Default false.',
                        ],
                    ],
                    'required'   => [ 'post_id' ],
                ],
            ],
            [
                'name'        => 'wp_update_option',
                'description' => 'Update any WordPress, WooCommerce, or plugin setting option (blogname, blogdescription, posts_per_page, timezone_string, etc.).',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'option_name'  => [
                            'type'        => 'STRING',
                            'description' => 'The setting key to update (e.g. blogname, posts_per_page, timezone_string, default_comment_status).',
                        ],
                        'option_value' => [
                            'type'        => 'STRING',
                            'description' => 'The new value for the option (strings, numbers, or JSON-encoded strings for complex data).',
                        ],
                    ],
                    'required'   => [ 'option_name', 'option_value' ],
                ],
            ],
            [
                'name'        => 'wp_manage_plugins',
                'description' => 'Complete plugin management: search WordPress.org directory, install new plugins, activate, deactivate, delete plugins, or list all installed plugins.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'        => [
                            'type'        => 'STRING',
                            'enum'        => [ 'list', 'search', 'install', 'activate', 'deactivate', 'delete' ],
                            'description' => 'Action to perform: list installed, search wordpress.org, install new plugin by slug, activate, deactivate, or delete.',
                        ],
                        'slug'          => [
                            'type'        => 'STRING',
                            'description' => 'Plugin slug for search or installation (e.g. elementor, woocommerce, contact-form-7, wordfence, wordpress-seo).',
                        ],
                        'query'         => [
                            'type'        => 'STRING',
                            'description' => 'Keyword query to search plugins on WordPress.org (e.g. elementor, contact form, backup, seo).',
                        ],
                        'search'        => [
                            'type'        => 'STRING',
                            'description' => 'Alias for query.',
                        ],
                        'plugin_file'   => [
                            'type'        => 'STRING',
                            'description' => 'Plugin file path relative to wp-content/plugins (e.g. woocommerce/woocommerce.php, akismet/akismet.php) for activate/deactivate/delete.',
                        ],
                        'auto_activate' => [
                            'type'        => 'BOOLEAN',
                            'description' => 'Whether to automatically activate after installing. Default is true.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_toggle_plugin',
                'description' => 'Activate or deactivate an installed WordPress plugin (legacy wrapper for wp_manage_plugins).',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'plugin_file' => [
                            'type'        => 'STRING',
                            'description' => 'The plugin file path relative to wp-content/plugins (e.g. akismet/akismet.php).',
                        ],
                        'action'      => [
                            'type'        => 'STRING',
                            'enum'        => [ 'activate', 'deactivate', 'install' ],
                            'description' => 'Either activate or deactivate.',
                        ],
                    ],
                    'required'   => [ 'plugin_file', 'action' ],
                ],
            ],
            [
                'name'        => 'wp_execute_php',
                'description' => 'Universal administrative execution: run arbitrary PHP code on the WordPress site. Gives complete control over any WordPress core, WooCommerce, theme, or plugin function.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'code'        => [
                            'type'        => 'STRING',
                            'description' => 'Executable PHP code to run (without <?php opening or ?> closing tags).',
                        ],
                        'description' => [
                            'type'        => 'STRING',
                            'description' => 'Clear human-readable summary of what this code does.',
                        ],
                    ],
                    'required'   => [ 'code', 'description' ],
                ],
            ],
            [
                'name'        => 'wp_manage_themes',
                'description' => 'Manage WordPress themes, search WordPress.org themes, install themes, switch active theme, set site identity (title, tagline, logo), configure static front page, and configure permalinks.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'               => [
                            'type'        => 'STRING',
                            'enum'        => [ 'list_themes', 'search', 'install', 'switch_theme', 'set_identity', 'set_front_page', 'set_permalinks' ],
                            'description' => 'Theme management operation: list installed themes, search wordpress.org, install new theme, switch active theme, set identity, set front page, or set permalinks.',
                        ],
                        'slug'                 => [
                            'type'        => 'STRING',
                            'description' => 'Theme slug to search or install from WordPress.org (e.g. astra, oceanwp, neve, hello-elementor).',
                        ],
                        'query'                => [
                            'type'        => 'STRING',
                            'description' => 'Keyword query to search themes on WordPress.org.',
                        ],
                        'stylesheet'           => [
                            'type'        => 'STRING',
                            'description' => 'Theme stylesheet slug to switch to (e.g. twentytwentyfour, astra).',
                        ],
                        'site_title'           => [
                            'type'        => 'STRING',
                            'description' => 'New site title for set_identity.',
                        ],
                        'tagline'              => [
                            'type'        => 'STRING',
                            'description' => 'New site description / tagline for set_identity.',
                        ],
                        'site_icon_id'         => [
                            'type'        => 'INTEGER',
                            'description' => 'Attachment ID of image for site icon / favicon.',
                        ],
                        'logo_id'              => [
                            'type'        => 'INTEGER',
                            'description' => 'Attachment ID of image for custom site logo.',
                        ],
                        'page_on_front'        => [
                            'type'        => 'INTEGER',
                            'description' => 'Page ID to set as static homepage for set_front_page.',
                        ],
                        'page_for_posts'       => [
                            'type'        => 'INTEGER',
                            'description' => 'Page ID to set as posts / blog page for set_front_page.',
                        ],
                        'permalink_structure'  => [
                            'type'        => 'STRING',
                            'description' => 'Custom permalink structure (e.g. /%postname%/, /%year%/%monthnum%/%postname%/) for set_permalinks.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_menus',
                'description' => 'Manage WordPress navigation menus: list menus & theme locations, create menus, add menu items (posts, pages, custom links), and assign menus to locations.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'    => [
                            'type'        => 'STRING',
                            'enum'        => [ 'list_menus', 'create_menu', 'add_item', 'assign_location' ],
                            'description' => 'Navigation menu action.',
                        ],
                        'menu_name' => [
                            'type'        => 'STRING',
                            'description' => 'Name of the menu when creating.',
                        ],
                        'menu_id'   => [
                            'type'        => 'INTEGER',
                            'description' => 'Menu ID for adding items or assigning locations.',
                        ],
                        'title'     => [
                            'type'        => 'STRING',
                            'description' => 'Label for the menu item.',
                        ],
                        'url'       => [
                            'type'        => 'STRING',
                            'description' => 'URL for custom menu links.',
                        ],
                        'type'      => [
                            'type'        => 'STRING',
                            'enum'        => [ 'custom', 'post_type', 'taxonomy' ],
                            'description' => 'Menu item type (custom, post_type, or taxonomy). Default is custom.',
                        ],
                        'object_id' => [
                            'type'        => 'INTEGER',
                            'description' => 'ID of page/post/term if type is post_type or taxonomy.',
                        ],
                        'location'  => [
                            'type'        => 'STRING',
                            'description' => 'Theme menu location slug (e.g. primary, header, footer) for assign_location.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_comments',
                'description' => 'Manage WordPress comments: list comments by status, moderate comments (approve, hold, spam, trash, delete), and reply to comments.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'     => [
                            'type'        => 'STRING',
                            'enum'        => [ 'list_comments', 'update_status', 'reply' ],
                            'description' => 'Comment management action.',
                        ],
                        'comment_id' => [
                            'type'        => 'INTEGER',
                            'description' => 'Target comment ID.',
                        ],
                        'status'     => [
                            'type'        => 'STRING',
                            'enum'        => [ 'approve', 'hold', 'spam', 'trash', 'delete' ],
                            'description' => 'Status to set: approve, hold, spam, trash, or delete.',
                        ],
                        'content'    => [
                            'type'        => 'STRING',
                            'description' => 'Reply content text for reply action.',
                        ],
                        'post_id'    => [
                            'type'        => 'INTEGER',
                            'description' => 'Filter comments by post ID or parent post ID for reply.',
                        ],
                        'limit'      => [
                            'type'        => 'INTEGER',
                            'description' => 'Max comments to return (1-50). Default 10.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_taxonomies',
                'description' => 'Create terms or assign categories/tags to a post.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'    => [
                            'type'        => 'STRING',
                            'enum'        => [ 'create_term', 'assign_terms' ],
                            'description' => 'Action to perform.',
                        ],
                        'taxonomy'  => [
                            'type'        => 'STRING',
                            'description' => 'Taxonomy slug (category, post_tag, etc.).',
                        ],
                        'term_name' => [
                            'type'        => 'STRING',
                            'description' => 'Name of the term when creating.',
                        ],
                        'post_id'   => [
                            'type'        => 'INTEGER',
                            'description' => 'Post ID when assigning terms.',
                        ],
                        'terms'     => [
                            'type'        => 'STRING',
                            'description' => 'Comma-separated term names or IDs to assign.',
                        ],
                    ],
                    'required'   => [ 'action', 'taxonomy' ],
                ],
            ],
            [
                'name'        => 'wp_manage_media',
                'description' => 'Search the Media Library or update image alt text/metadata.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'    => [
                            'type'        => 'STRING',
                            'enum'        => [ 'search', 'update_meta' ],
                            'description' => 'search for images or update_meta for existing image.',
                        ],
                        'search'    => [
                            'type'        => 'STRING',
                            'description' => 'Filename or title keyword to search.',
                        ],
                        'media_id'  => [
                            'type'        => 'INTEGER',
                            'description' => 'Attachment ID when updating metadata.',
                        ],
                        'alt_text'  => [
                            'type'        => 'STRING',
                            'description' => 'New alt text for the image.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_inject_custom_css',
                'description' => 'Add site-wide custom CSS stylesheets or styling rules via the WordPress Customizer.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'css'  => [
                            'type'        => 'STRING',
                            'description' => 'The raw CSS rules to inject (without <style> tags).',
                        ],
                        'mode' => [
                            'type'        => 'STRING',
                            'enum'        => [ 'append', 'replace' ],
                            'description' => 'Whether to append to existing custom CSS or replace it completely. Default append.',
                        ],
                    ],
                    'required'   => [ 'css' ],
                ],
            ],
            [
                'name'        => 'wp_inject_header_footer',
                'description' => 'Inject scripts, tracking tags (Google Analytics, Meta Pixel, Schema markup) into site header or footer.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'title'     => [
                            'type'        => 'STRING',
                            'description' => 'Descriptive name for the script (e.g. Google Analytics 4, Meta Pixel).',
                        ],
                        'location'  => [
                            'type'        => 'STRING',
                            'enum'        => [ 'js_header', 'js_footer' ],
                            'description' => 'Where to inject: js_header (wp_head) or js_footer (wp_footer).',
                        ],
                        'code'      => [
                            'type'        => 'STRING',
                            'description' => 'HTML or JavaScript code including <script> or <meta> tags.',
                        ],
                    ],
                    'required'   => [ 'title', 'location', 'code' ],
                ],
            ],
            [
                'name'        => 'wp_create_code_snippet',
                'description' => 'Create a custom PHP code snippet (hook, filter, custom shortcode, redirect, automation) with syntax linting and crash protection.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'title'   => [
                            'type'        => 'STRING',
                            'description' => 'Descriptive title for the snippet (e.g. Redirect logged-out checkout to login).',
                        ],
                        'code'    => [
                            'type'        => 'STRING',
                            'description' => 'Raw PHP code without <?php opening tags.',
                        ],
                        'active'  => [
                            'type'        => 'BOOLEAN',
                            'description' => 'Whether to activate immediately after verification. Default true.',
                        ],
                    ],
                    'required'   => [ 'title', 'code' ],
                ],
            ],
            [
                'name'        => 'wp_toggle_code_snippet',
                'description' => 'Activate, deactivate, or delete a custom code snippet.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'snippet_id' => [
                            'type'        => 'INTEGER',
                            'description' => 'The ID of the snippet.',
                        ],
                        'action'     => [
                            'type'        => 'STRING',
                            'enum'        => [ 'activate', 'deactivate', 'delete' ],
                            'description' => 'Action to perform.',
                        ],
                    ],
                    'required'   => [ 'snippet_id', 'action' ],
                ],
            ],
            [
                'name'        => 'wp_list_code_snippets',
                'description' => 'List all custom snippets, CSS, and header/footer scripts created on this site.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'type' => [
                            'type'        => 'STRING',
                            'enum'        => [ 'all', 'php', 'css', 'js_header', 'js_footer' ],
                            'description' => 'Filter by code type. Default all.',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'wp_manage_woocommerce',
                'description' => 'Manage WooCommerce store: list/update products, prices, stock, create discount coupons, view recent orders, and get sales analytics.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'         => [
                            'type'        => 'STRING',
                            'enum'        => [ 'list_products', 'update_product', 'create_coupon', 'list_orders', 'store_analytics' ],
                            'description' => 'Store action to execute.',
                        ],
                        'product_id'     => [
                            'type'        => 'INTEGER',
                            'description' => 'Product ID for update_product.',
                        ],
                        'regular_price'  => [
                            'type'        => 'STRING',
                            'description' => 'New regular price.',
                        ],
                        'sale_price'     => [
                            'type'        => 'STRING',
                            'description' => 'New sale price.',
                        ],
                        'stock_status'   => [
                            'type'        => 'STRING',
                            'enum'        => [ 'instock', 'outofstock' ],
                            'description' => 'Stock status.',
                        ],
                        'stock_quantity' => [
                            'type'        => 'INTEGER',
                            'description' => 'Inventory quantity.',
                        ],
                        'coupon_code'    => [
                            'type'        => 'STRING',
                            'description' => 'Coupon code string (e.g. SAVE20).',
                        ],
                        'discount_type'  => [
                            'type'        => 'STRING',
                            'enum'        => [ 'percent', 'fixed_cart', 'fixed_product' ],
                            'description' => 'Coupon type. Default percent.',
                        ],
                        'amount'         => [
                            'type'        => 'NUMBER',
                            'description' => 'Coupon discount value (e.g. 20 for 20% or $20).',
                        ],
                        'expiry_date'    => [
                            'type'        => 'STRING',
                            'description' => 'Expiry date formatted as YYYY-MM-DD.',
                        ],
                        'free_shipping'  => [
                            'type'        => 'BOOLEAN',
                            'description' => 'Whether coupon grants free shipping.',
                        ],
                        'order_status'   => [
                            'type'        => 'STRING',
                            'description' => 'Filter orders: any, processing, completed, on-hold, pending.',
                        ],
                        'search'         => [
                            'type'        => 'STRING',
                            'description' => 'Product or order search keyword.',
                        ],
                        'limit'          => [
                            'type'        => 'INTEGER',
                            'description' => 'Max items to return (1-20). Default 10.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_seo',
                'description' => 'SEO audit, meta title/description generation, and automated media alt tag fixer.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'           => [
                            'type'        => 'STRING',
                            'enum'        => [ 'audit_post_seo', 'update_meta', 'fix_missing_alt_tags', 'check_broken_links' ],
                            'description' => 'SEO action to execute.',
                        ],
                        'post_id'          => [
                            'type'        => 'INTEGER',
                            'description' => 'Target post or page ID.',
                        ],
                        'meta_title'       => [
                            'type'        => 'STRING',
                            'description' => 'Custom SEO title tag.',
                        ],
                        'meta_description' => [
                            'type'        => 'STRING',
                            'description' => 'Meta description tag for search engines.',
                        ],
                        'focus_keyword'    => [
                            'type'        => 'STRING',
                            'description' => 'Target SEO keyword for this content.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_performance',
                'description' => 'Clean transients, purge cache (LiteSpeed, WP Rocket, Super Cache), remove post revisions, and inspect site health.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action' => [
                            'type'        => 'STRING',
                            'enum'        => [ 'health_check', 'flush_cache', 'clean_transients', 'clean_revisions' ],
                            'description' => 'Performance action to run.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_database',
                'description' => 'Safe read-only SQL queries, table optimization, and safe search & replace across content.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'      => [
                            'type'        => 'STRING',
                            'enum'        => [ 'query_summary', 'optimize_tables', 'search_replace_preview', 'search_replace' ],
                            'description' => 'Database operation.',
                        ],
                        'query'       => [
                            'type'        => 'STRING',
                            'description' => 'Safe SELECT-only SQL query (writes blocked).',
                        ],
                        'search_str'  => [
                            'type'        => 'STRING',
                            'description' => 'String to search for in posts.',
                        ],
                        'replace_str' => [
                            'type'        => 'STRING',
                            'description' => 'Replacement string.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_security',
                'description' => 'Audit admin accounts, file permissions (wp-config.php, uploads), and scan plugins for security.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action' => [
                            'type'        => 'STRING',
                            'enum'        => [ 'audit_admins', 'check_file_permissions', 'audit_plugins', 'scan_debug_log' ],
                            'description' => 'Security check action.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_users',
                'description' => 'Manage WordPress users: list users by role, create new user accounts, and change user roles safely.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'     => [
                            'type'        => 'STRING',
                            'enum'        => [ 'list_users', 'create_user', 'update_user_role' ],
                            'description' => 'User management action.',
                        ],
                        'role'       => [
                            'type'        => 'STRING',
                            'enum'        => [ 'all', 'administrator', 'editor', 'author', 'contributor', 'subscriber', 'customer' ],
                            'description' => 'User role filter or role to assign.',
                        ],
                        'username'   => [
                            'type'        => 'STRING',
                            'description' => 'User login name.',
                        ],
                        'email'      => [
                            'type'        => 'STRING',
                            'description' => 'User email address.',
                        ],
                        'user_id'    => [
                            'type'        => 'INTEGER',
                            'description' => 'User ID for role update.',
                        ],
                        'new_role'   => [
                            'type'        => 'STRING',
                            'enum'        => [ 'subscriber', 'customer', 'contributor', 'author', 'editor', 'administrator' ],
                            'description' => 'Target role to assign.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_cron',
                'description' => 'View scheduled WordPress cron jobs and manually trigger overdue or scheduled tasks.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'    => [
                            'type'        => 'STRING',
                            'enum'        => [ 'list_cron_jobs', 'run_cron_job' ],
                            'description' => 'Cron action to perform.',
                        ],
                        'hook_name' => [
                            'type'        => 'STRING',
                            'description' => 'Scheduled hook name to trigger (e.g. wp_version_check).',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
            [
                'name'        => 'wp_manage_backup',
                'description' => 'Create instant configuration/content snapshots before major edits, list restore points, or rollback.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'action'      => [
                            'type'        => 'STRING',
                            'enum'        => [ 'create_snapshot', 'list_snapshots', 'restore_snapshot' ],
                            'description' => 'Snapshot operation.',
                        ],
                        'snapshot_id' => [
                            'type'        => 'STRING',
                            'description' => 'Snapshot ID to restore.',
                        ],
                    ],
                    'required'   => [ 'action' ],
                ],
            ],
        ];
    }

    /**
     * Get tool risk classification: 'read', 'low', 'high', 'blocked'
     */
    public static function get_risk_level( string $tool_name, array $args ): string {
        $clean_tool = sanitize_key( $tool_name );
        if ( ! str_starts_with( $clean_tool, 'wp_' ) ) {
            $clean_tool = 'wp_' . $clean_tool;
        }
        $tool_name = $clean_tool;
        $action    = $args['action'] ?? '';

        // Aliases
        switch ( $tool_name ) {
            case 'wp_install_plugin':
            case 'wp_activate_plugin':
            case 'wp_deactivate_plugin':
            case 'wp_toggle_plugin':
                if ( 'wp_install_plugin' === $tool_name && ! isset( $args['action'] ) ) {
                    $args['action'] = 'install';
                }
                if ( 'wp_activate_plugin' === $tool_name && ! isset( $args['action'] ) ) {
                    $args['action'] = 'activate';
                }
                if ( 'wp_deactivate_plugin' === $tool_name && ! isset( $args['action'] ) ) {
                    $args['action'] = 'deactivate';
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
                $tool_name = 'wp_manage_themes';
                break;
            case 'wp_manage_menu':
                $tool_name = 'wp_manage_menus';
                break;
            case 'wp_manage_comment':
                $tool_name = 'wp_manage_comments';
                break;
        }

        switch ( $tool_name ) {
            case 'wp_get_site_meta':
            case 'wp_get_content':
            case 'wp_list_code_snippets':
                return 'read';

            case 'wp_manage_media':
                return ( $action === 'search' ) ? 'read' : 'low';

            case 'wp_create_post':
                return 'low'; // Safe: creating content never harms the system

            case 'wp_update_post':
                return 'low'; // Safe: edits are logged and reversible via Undo

            case 'wp_delete_post':
                return 'high';

            case 'wp_update_option':
                $option = strtolower( trim( $args['option_name'] ?? '' ) );
                if ( in_array( $option, self::FORBIDDEN_OPTIONS, true ) || str_starts_with( $option, 'wpchat_' ) ) {
                    return 'blocked';
                }
                return 'high'; // Site configuration modifications require human confirmation

            // Plugins
            case 'wp_manage_plugins':
                $act = sanitize_key( $args['action'] ?? '' );
                if ( empty( $act ) && ( ! empty( $args['slug'] ) || ! empty( $args['plugin'] ) ) ) {
                    $act = 'install';
                }
                if ( in_array( $act, [ 'list', 'search' ], true ) ) {
                    return 'read';
                }
                return 'high'; // install, activate, deactivate, delete all require human approval

            // Universal PHP execution
            case 'wp_execute_php':
                return 'high';

            // Themes
            case 'wp_manage_themes':
                $act = sanitize_key( $args['action'] ?? 'list_themes' );
                if ( in_array( $act, [ 'list_themes', 'search' ], true ) ) {
                    return 'read';
                }
                if ( in_array( $act, [ 'switch_theme', 'install' ], true ) ) {
                    return 'high'; // Changes entire active theme or installs
                }
                return 'low'; // set_identity, set_front_page, set_permalinks

            // Navigation Menus
            case 'wp_manage_menus':
                if ( 'list_menus' === $action ) {
                    return 'read';
                }
                return 'low'; // create_menu, add_item, assign_location

            // Comments
            case 'wp_manage_comments':
                if ( 'list_comments' === $action ) {
                    return 'read';
                }
                if ( in_array( $action, [ 'reply', 'approve' ], true ) ) {
                    return 'low'; // Safe: approving or replying
                }
                if ( 'update_status' === $action && ( $args['status'] ?? '' ) === 'approve' ) {
                    return 'low'; // Safe: approving comments
                }
                return 'high'; // delete, spam

            case 'wp_manage_taxonomies':
                return 'low';

            case 'wp_inject_custom_css':
                return 'low';

            case 'wp_create_code_snippet':
            case 'wp_toggle_code_snippet':
                return 'high'; // Code execution: writing/activating PHP snippets requires explicit approval

            case 'wp_inject_header_footer':
                return 'high'; // Injects raw script tags into wp_head/wp_footer

            // WooCommerce
            case 'wp_manage_woocommerce':
                if ( in_array( $action, [ 'list_products', 'list_orders', 'store_analytics' ], true ) ) {
                    return 'read';
                }
                if ( in_array( $action, [ 'create_coupon', 'update_product' ], true ) ) {
                    return 'low'; // Safe: creates coupons or updates products
                }
                return 'low';

            // SEO
            case 'wp_manage_seo':
                if ( in_array( $action, [ 'audit_post_seo', 'check_broken_links' ], true ) ) {
                    return 'read';
                }
                return 'low'; // update_meta, fix_missing_alt_tags

            // Performance
            case 'wp_manage_performance':
                if ( 'health_check' === $action ) {
                    return 'read';
                }
                return 'low'; // flush_cache, clean_transients, clean_revisions

            // Database
            case 'wp_manage_database':
                if ( in_array( $action, [ 'query_summary', 'search_replace_preview' ], true ) ) {
                    return 'read';
                }
                if ( 'optimize_tables' === $action ) {
                    return 'low';
                }
                return 'high'; // search_replace

            // Security
            case 'wp_manage_security':
                return 'read';

            // Users
            case 'wp_manage_users':
                if ( 'list_users' === $action ) {
                    return 'read';
                }
                // Any user modification (create, delete, role change) requires explicit approval
                return 'high';

            // Cron
            case 'wp_manage_cron':
                return ( 'list_cron_jobs' === $action ) ? 'read' : 'low';

            // Backup & Snapshots
            case 'wp_manage_backup':
                if ( 'list_snapshots' === $action ) {
                    return 'read';
                }
                if ( 'restore_snapshot' === $action ) {
                    return 'high'; // Overwrites site configuration
                }
                return 'low'; // create_snapshot is safe

            default:
                return 'high';
        }
    }

    /**
     * Return all tools formatted for OpenAI-compatible endpoints (OpenRouter, NVIDIA NIM, Custom)
     */
    public static function get_openai_tools(): array {
        $declarations = self::get_declarations();
        $openai_tools = [];

        foreach ( $declarations as $decl ) {
            $openai_tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $decl['name'],
                    'description' => $decl['description'] ?? '',
                    'parameters'  => self::convert_gemini_schema_to_openai( $decl['parameters'] ?? [] ),
                ],
            ];
        }

        return $openai_tools;
    }

    /**
     * Recursively convert Gemini schema types (uppercase OBJECT, STRING) to JSON Schema (lowercase)
     */
    public static function convert_gemini_schema_to_openai( $schema ) {
        if ( is_object( $schema ) ) {
            $schema = (array) $schema;
        }
        if ( ! is_array( $schema ) ) {
            return $schema;
        }

        $converted = [];
        foreach ( $schema as $k => $v ) {
            if ( $k === 'type' && is_string( $v ) ) {
                $converted['type'] = strtolower( $v );
            } elseif ( is_array( $v ) || is_object( $v ) ) {
                $converted[ $k ] = self::convert_gemini_schema_to_openai( $v );
            } else {
                $converted[ $k ] = $v;
            }
        }

        if ( isset( $converted['type'] ) && $converted['type'] === 'object' ) {
            if ( ! isset( $converted['properties'] ) || empty( $converted['properties'] ) ) {
                $converted['properties'] = (object) [];
            }
        }

        if ( isset( $converted['properties'] ) && empty( $converted['properties'] ) ) {
            $converted['properties'] = (object) [];
        }

        return $converted;
    }
}
