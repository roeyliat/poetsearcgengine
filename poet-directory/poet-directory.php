<?php
/**
 * Plugin Name: POET Directory
 * Plugin URI: https://frisch-ot.com
 * Description: כלי לחיפוש מטפלות מוסמכות בגישת POET — חיפוש, סינון וכרטיסים באתר frisch-ot.com.
 * Version: 1.2.6
 * Author: Dr. Carmit Frisch
 * Text Domain: poet-directory
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POET_DIR_VERSION', '1.2.6');
define('POET_DIR_FILE', __FILE__);
define('POET_DIR_PATH', plugin_dir_path(__FILE__));
define('POET_DIR_URL', plugin_dir_url(__FILE__));

require_once POET_DIR_PATH . 'includes/helpers.php';
require_once POET_DIR_PATH . 'includes/post-type.php';
require_once POET_DIR_PATH . 'includes/admin.php';
require_once POET_DIR_PATH . 'includes/importer.php';
require_once POET_DIR_PATH . 'includes/rest.php';
require_once POET_DIR_PATH . 'includes/shortcode.php';

register_activation_hook(__FILE__, 'poet_directory_activate');
register_deactivation_hook(__FILE__, 'poet_directory_deactivate');

function poet_directory_activate(): void
{
    poet_register_post_type();
    poet_register_taxonomies();
    poet_register_meta_fields();
    poet_insert_default_terms();
    poet_register_manager_role();
    poet_maybe_create_manager_user();
    flush_rewrite_rules();
}

function poet_directory_deactivate(): void
{
    flush_rewrite_rules();
}
