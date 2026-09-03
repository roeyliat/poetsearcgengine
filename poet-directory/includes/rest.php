<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'poet_register_rest_routes');

function poet_register_rest_routes(): void
{
    register_rest_route('poet/v1', '/therapists', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => 'poet_rest_therapists',
    ]);
}

function poet_rest_therapists(): WP_REST_Response
{
    $posts = get_posts([
        'post_type' => 'poet_therapist',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_poet_visible',
                'value' => '1',
                'compare' => '=',
            ],
            [
                'key' => '_poet_visible',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ]);

    $data = array_map('poet_therapist_payload', $posts);
    return new WP_REST_Response([
        'count' => count($data),
        'therapists' => $data,
    ], 200);
}
