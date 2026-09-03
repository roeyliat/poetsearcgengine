<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'poet_register_post_type');
add_action('init', 'poet_register_taxonomies');
add_action('init', 'poet_register_meta_fields');

function poet_register_post_type(): void
{
    register_post_type('poet_therapist', [
        'labels' => [
            'name' => 'מטפלות POET',
            'singular_name' => 'מטפלת POET',
            'add_new' => 'הוספת מטפלת',
            'add_new_item' => 'הוספת מטפלת חדשה',
            'edit_item' => 'עריכת מטפלת',
            'new_item' => 'מטפלת חדשה',
            'view_item' => 'צפייה במטפלת',
            'search_items' => 'חיפוש מטפלות',
            'not_found' => 'לא נמצאו מטפלות',
            'all_items' => 'כל המטפלות',
            'menu_name' => 'מאגר מטפלות POET',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-groups',
        'menu_position' => 26,
        'supports' => [],
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => false,
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'capabilities' => [
            'edit_post' => 'manage_options',
            'read_post' => 'manage_options',
            'delete_post' => 'do_not_allow',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'create_posts' => 'manage_options',
            'delete_posts' => 'do_not_allow',
            'delete_private_posts' => 'do_not_allow',
            'delete_published_posts' => 'do_not_allow',
            'delete_others_posts' => 'do_not_allow',
            'edit_private_posts' => 'manage_options',
            'edit_published_posts' => 'manage_options',
        ],
    ]);
}

function poet_register_taxonomies(): void
{
    $common = [
        'public' => false,
        'show_ui' => false,
        'hierarchical' => false,
        'show_admin_column' => false,
        'rewrite' => false,
    ];

    register_taxonomy('poet_region', 'poet_therapist', array_merge($common, [
        'labels' => ['name' => 'אזורים', 'singular_name' => 'אזור'],
    ]));
    register_taxonomy('poet_fund', 'poet_therapist', array_merge($common, [
        'labels' => ['name' => 'מסגרות', 'singular_name' => 'מסגרת'],
    ]));
    register_taxonomy('poet_language', 'poet_therapist', array_merge($common, [
        'labels' => ['name' => 'שפות', 'singular_name' => 'שפה'],
    ]));
    register_taxonomy('poet_modality', 'poet_therapist', array_merge($common, [
        'labels' => ['name' => 'אופן טיפול', 'singular_name' => 'אופן טיפול'],
    ]));
}

function poet_register_meta_fields(): void
{
    register_post_meta('poet_therapist', '_poet_therapist_id', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'poet_valid_therapist_id',
        'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
    ]);
    register_post_meta('poet_therapist', '_poet_manual_overrides', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
    ]);
    register_post_meta('poet_therapist', '_poet_source_row', [
        'type' => 'integer',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'absint',
        'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
    ]);
    register_post_meta('poet_therapist', '_poet_visible', [
        'type' => 'boolean',
        'single' => true,
        'default' => true,
        'show_in_rest' => false,
        'sanitize_callback' => static fn($value): bool => (bool) $value,
        'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
    ]);
}

function poet_insert_default_terms(): void
{
    $sets = [
        'poet_region' => poet_region_labels(),
        'poet_fund' => poet_fund_labels(),
        'poet_language' => poet_language_labels(),
        'poet_modality' => poet_modality_labels(),
    ];

    foreach ($sets as $taxonomy => $terms) {
        foreach ($terms as $slug => $name) {
            if (!term_exists($slug, $taxonomy)) {
                wp_insert_term($name, $taxonomy, ['slug' => $slug]);
            }
        }
    }
}
