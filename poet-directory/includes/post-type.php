<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'poet_register_post_type');
add_action('init', 'poet_register_taxonomies');
add_action('init', 'poet_register_meta_fields');
add_action('init', 'poet_grant_administrator_therapist_caps', 11);

function poet_register_post_type(): void
{
    register_post_type('poet_therapist', [
        'labels' => [
            'name' => 'ניהול מאגר המטפלות',
            'singular_name' => 'מטפלת POET',
            'add_new' => '+ הוספת מטפלת',
            'add_new_item' => 'הוספת מטפלת',
            'edit_item' => 'עריכת מטפלת',
            'new_item' => 'הוספת מטפלת',
            'view_item' => 'צפייה במטפלת',
            'search_items' => 'חיפוש לפי שם מטפלת',
            'not_found' => 'לא נמצאו מטפלות',
            'not_found_in_trash' => 'לא נמצאו מטפלות',
            'all_items' => 'ניהול מאגר המטפלות',
            'menu_name' => 'ניהול מאגר המטפלות',
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
        'capability_type' => ['poet_therapist', 'poet_therapists'],
        'map_meta_cap' => true,
        'capabilities' => [
            'delete_post' => 'do_not_allow',
            'delete_posts' => 'do_not_allow',
            'delete_private_posts' => 'do_not_allow',
            'delete_published_posts' => 'do_not_allow',
            'delete_others_posts' => 'do_not_allow',
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
        'capabilities' => [
            'manage_terms' => 'manage_options',
            'edit_terms' => 'manage_options',
            'delete_terms' => 'manage_options',
            'assign_terms' => 'edit_poet_therapists',
        ],
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
        'auth_callback' => static fn(): bool => current_user_can('edit_poet_therapists'),
    ]);
    register_post_meta('poet_therapist', '_poet_manual_overrides', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => static fn(): bool => current_user_can('edit_poet_therapists'),
    ]);
    register_post_meta('poet_therapist', '_poet_source_row', [
        'type' => 'integer',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'absint',
        'auth_callback' => static fn(): bool => current_user_can('edit_poet_therapists'),
    ]);
    register_post_meta('poet_therapist', '_poet_visible', [
        'type' => 'boolean',
        'single' => true,
        'default' => true,
        'show_in_rest' => false,
        'sanitize_callback' => static fn($value): bool => (bool) $value,
        'auth_callback' => static fn(): bool => current_user_can('edit_poet_therapists'),
    ]);
}

function poet_manager_capabilities(): array
{
    return [
        'read' => true,
        'edit_poet_therapist' => true,
        'read_poet_therapist' => true,
        'edit_poet_therapists' => true,
        'edit_others_poet_therapists' => true,
        'edit_published_poet_therapists' => true,
        'edit_private_poet_therapists' => true,
        'publish_poet_therapists' => true,
        'read_private_poet_therapists' => true,
    ];
}

function poet_register_manager_role(): void
{
    $caps = poet_manager_capabilities();
    $role = get_role('poet_manager');
    if (!$role) {
        add_role('poet_manager', 'מנהלת מאגר POET', $caps);
    } else {
        foreach ($caps as $cap => $grant) {
            if ($grant) {
                $role->add_cap($cap);
            }
        }
    }

    poet_grant_administrator_therapist_caps();
}

function poet_grant_administrator_therapist_caps(): void
{
    $administrator = get_role('administrator');
    if (!$administrator) {
        return;
    }
    foreach (array_keys(poet_manager_capabilities()) as $cap) {
        if ($cap === 'read' || $administrator->has_cap($cap)) {
            continue;
        }
        $administrator->add_cap($cap);
    }
}

function poet_maybe_create_manager_user(): void
{
    if (username_exists('carmitfr') || email_exists('carmitfr@gmail.com')) {
        return;
    }

    $user_id = wp_insert_user([
        'user_login' => 'carmitfr',
        'user_email' => 'carmitfr@gmail.com',
        'user_pass' => wp_generate_password(24, true, true),
        'role' => 'poet_manager',
        'display_name' => 'carmitfr',
    ]);

    if (is_wp_error($user_id) || !$user_id) {
        return;
    }

    wp_send_new_user_notifications((int) $user_id, 'user');
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
