<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'poet_register_meta_boxes');
add_action('save_post_poet_therapist', 'poet_save_therapist_meta', 10, 2);
add_filter('manage_poet_therapist_posts_columns', 'poet_admin_columns');
add_action('manage_poet_therapist_posts_custom_column', 'poet_admin_column_content', 10, 2);
add_filter('manage_edit-poet_therapist_sortable_columns', 'poet_sortable_columns');
add_action('pre_get_posts', 'poet_admin_list_query');
add_action('admin_notices', 'poet_admin_shortcode_notice');
add_filter('posts_search', 'poet_admin_search_business_fields', 10, 2);
add_filter('post_row_actions', 'poet_therapist_row_actions', 10, 2);
add_filter('bulk_actions-edit-poet_therapist', 'poet_remove_delete_bulk_actions');
add_filter('views_edit-poet_therapist', 'poet_hide_wordpress_status_views');
add_action('admin_post_poet_toggle_visibility', 'poet_toggle_therapist_visibility');
add_filter('post_updated_messages', 'poet_therapist_updated_messages');
add_filter('wp_insert_post_data', 'poet_force_therapist_published', 10, 2);
add_filter('redirect_post_location', 'poet_validation_redirect', 10, 2);
add_action('admin_footer-post.php', 'poet_admin_edit_script');
add_action('admin_footer-post-new.php', 'poet_admin_edit_script');
add_action('admin_footer-edit.php', 'poet_admin_list_script');
add_action('add_meta_boxes_poet_therapist', 'poet_remove_wordpress_meta_boxes', 100);
add_filter('screen_options_show_screen', 'poet_hide_therapist_screen_options', 10, 2);
add_action('current_screen', 'poet_remove_therapist_help');
add_filter('login_redirect', 'poet_manager_login_redirect', 10, 3);
add_action('admin_init', 'poet_restrict_manager_admin');
add_action('admin_menu', 'poet_hide_manager_admin_menus', 999);
add_action('admin_enqueue_scripts', 'poet_enqueue_admin_assets');
add_filter('admin_body_class', 'poet_admin_body_class');
add_action('in_admin_header', 'poet_manager_app_header', 0);
add_filter('show_admin_bar', 'poet_manager_show_admin_bar');
add_filter('admin_footer_text', 'poet_manager_footer_text', 99);
add_filter('update_footer', 'poet_manager_footer_text', 99);
add_filter('disable_months_dropdown', 'poet_disable_months_dropdown', 10, 2);
add_filter('edit_poet_therapist_per_page', static fn(): int => 1000);
add_filter('list_table_primary_column', 'poet_list_primary_column', 10, 2);

function poet_is_limited_poet_manager(?WP_User $user = null): bool
{
    if (!$user instanceof WP_User) {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
    }
    if (!$user->exists() || user_can($user, 'manage_options')) {
        return false;
    }
    return in_array('poet_manager', (array) $user->roles, true);
}

function poet_manager_screen_url(): string
{
    return admin_url('edit.php?post_type=poet_therapist');
}

function poet_manager_login_redirect($redirect_to, $requested_redirect_to, $user)
{
    if (!poet_is_limited_poet_manager($user instanceof WP_User ? $user : null)) {
        return $redirect_to;
    }

    $target = is_string($requested_redirect_to) && $requested_redirect_to !== ''
        ? $requested_redirect_to
        : (string) $redirect_to;

    if (poet_is_allowed_manager_redirect($target)) {
        return $target;
    }

    return poet_manager_screen_url();
}

function poet_is_allowed_manager_redirect(string $url): bool
{
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $query = [];
    parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

    if (str_ends_with($path, '/wp-admin/post-new.php') && ($query['post_type'] ?? '') === 'poet_therapist') {
        return true;
    }
    if (str_ends_with($path, '/wp-admin/edit.php') && ($query['post_type'] ?? '') === 'poet_therapist') {
        return true;
    }
    if (str_ends_with($path, '/wp-admin/post.php') && !empty($query['post'])) {
        return get_post_type(absint($query['post'])) === 'poet_therapist';
    }

    return false;
}

function poet_restrict_manager_admin(): void
{
    if (!poet_is_limited_poet_manager() || wp_doing_ajax()) {
        return;
    }

    global $pagenow;
    $allowed_pages = ['edit.php', 'post.php', 'post-new.php', 'admin-post.php'];
    if (!in_array((string) $pagenow, $allowed_pages, true)) {
        wp_safe_redirect(poet_manager_screen_url());
        exit;
    }

    $post_type = sanitize_key(wp_unslash($_REQUEST['post_type'] ?? ''));
    if ($pagenow === 'edit.php' && $post_type !== 'poet_therapist') {
        wp_safe_redirect(poet_manager_screen_url());
        exit;
    }
    if ($pagenow === 'post-new.php' && $post_type !== 'poet_therapist') {
        wp_safe_redirect(admin_url('post-new.php?post_type=poet_therapist'));
        exit;
    }
    if ($pagenow === 'post.php') {
        $post_id = absint($_REQUEST['post'] ?? $_REQUEST['post_ID'] ?? 0);
        if ($post_id && get_post_type($post_id) !== 'poet_therapist') {
            wp_safe_redirect(poet_manager_screen_url());
            exit;
        }
    }
    if ($pagenow === 'admin-post.php') {
        $action = sanitize_key(wp_unslash($_REQUEST['action'] ?? ''));
        if ($action !== 'poet_toggle_visibility') {
            wp_safe_redirect(poet_manager_screen_url());
            exit;
        }
    }
}

function poet_hide_manager_admin_menus(): void
{
    if (!poet_is_limited_poet_manager()) {
        return;
    }

    remove_menu_page('index.php');
    remove_menu_page('edit.php');
    remove_menu_page('upload.php');
    remove_menu_page('edit.php?post_type=page');
    remove_menu_page('edit-comments.php');
    remove_menu_page('themes.php');
    remove_menu_page('plugins.php');
    remove_menu_page('users.php');
    remove_menu_page('tools.php');
    remove_menu_page('options-general.php');
    remove_menu_page('profile.php');
}

function poet_is_therapist_admin_screen(): bool
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    return $screen
        && $screen->post_type === 'poet_therapist'
        && in_array($screen->base, ['edit', 'post'], true);
}

function poet_enqueue_admin_assets(): void
{
    if (!poet_is_therapist_admin_screen() && !(poet_is_limited_poet_manager() && is_admin())) {
        return;
    }
    wp_enqueue_style(
        'poet-admin-fonts',
        'https://fonts.googleapis.com/css2?family=Assistant:wght@400;600;700;800&family=David+Libre:wght@500;700&display=swap',
        [],
        false
    );
    wp_enqueue_style(
        'poet-admin',
        POET_DIR_URL . 'public/css/admin.css',
        ['poet-admin-fonts'],
        POET_DIR_VERSION
    );
}

function poet_admin_body_class(string $classes): string
{
    if (poet_is_therapist_admin_screen()) {
        $classes .= ' poet-therapist-admin';
    }
    if (poet_is_limited_poet_manager()) {
        $classes .= ' poet-manager-app';
    }
    return $classes;
}

function poet_manager_app_header(): void
{
    if (!poet_is_limited_poet_manager() || !poet_is_therapist_admin_screen()) {
        return;
    }
    ?>
    <div class="poet-manager-bar" dir="rtl">
        <img src="https://frisch-ot.com/wp-content/uploads/2021/10/POET-logoR-72dpi.png" alt="POET">
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">יציאה</a>
    </div>
    <?php
}

function poet_manager_show_admin_bar(bool $show): bool
{
    return poet_is_limited_poet_manager() ? false : $show;
}

function poet_manager_footer_text($text)
{
    return poet_is_limited_poet_manager() ? '' : $text;
}

function poet_disable_months_dropdown(bool $disable, string $post_type): bool
{
    return $post_type === 'poet_therapist' ? true : $disable;
}

function poet_list_primary_column(string $default, string $screen_id): string
{
    return $screen_id === 'edit-poet_therapist' ? 'title' : $default;
}

function poet_admin_shortcode_notice(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'poet_therapist') {
        return;
    }
    $saved = sanitize_key(wp_unslash($_GET['poet_saved'] ?? ''));
    if ($saved === 'added') {
        echo '<div class="poet-admin-flash">המטפלת נוספה בהצלחה.</div>';
    } elseif ($saved === 'updated') {
        echo '<div class="poet-admin-flash">פרטי המטפלת נשמרו בהצלחה.</div>';
    }
    $visibility = sanitize_key(wp_unslash($_GET['poet_visibility'] ?? ''));
    if ($visibility === 'shown') {
        echo '<div class="poet-admin-flash">המטפלת מוצגת כעת בחיפוש הציבורי.</div>';
    } elseif ($visibility === 'hidden') {
        echo '<div class="poet-admin-flash">המטפלת הוסתרה מהחיפוש הציבורי ונשמרה במערכת.</div>';
    }
    $post_id = absint($_GET['post'] ?? 0);
    if ($post_id && !empty($_GET['poet_validation'])) {
        $key = 'poet_validation_' . get_current_user_id() . '_' . $post_id;
        $errors = get_transient($key);
        delete_transient($key);
        if (is_array($errors) && $errors) {
            echo '<div class="notice notice-error"><p><strong>לא ניתן להציג את המטפלת בחיפוש:</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }
    }
}

function poet_register_meta_boxes(): void
{
    add_meta_box(
        'poet_therapist_details',
        'פרטי המטפלת',
        'poet_render_meta_box',
        'poet_therapist',
        'normal',
        'high'
    );
}

function poet_render_meta_box(WP_Post $post): void
{
    wp_nonce_field('poet_save_therapist', 'poet_therapist_nonce');

    $name = $post->post_status === 'auto-draft' ? '' : $post->post_title;
    $visible = !metadata_exists('post', $post->ID, '_poet_visible')
        || (bool) get_post_meta($post->ID, '_poet_visible', true);
    $settlement = (string) get_post_meta($post->ID, '_poet_settlement', true);
    $age_min = (string) get_post_meta($post->ID, '_poet_age_min', true);
    $age_max = (string) get_post_meta($post->ID, '_poet_age_max', true);
    $age_label = (string) get_post_meta($post->ID, '_poet_age_label', true);
    $age_open = (bool) get_post_meta($post->ID, '_poet_age_open', true);
    $phones = implode("\n", poet_json_meta($post->ID, '_poet_phones'));
    $emails = implode("\n", poet_json_meta($post->ID, '_poet_emails'));
    $notes = (string) get_post_meta($post->ID, '_poet_notes', true);
    $metiv = (string) get_post_meta($post->ID, '_poet_metiv', true);
    $education = (bool) get_post_meta($post->ID, '_poet_education', true);

    $selected = [
        'poet_region' => poet_term_slugs($post->ID, 'poet_region'),
        'poet_fund' => poet_term_slugs($post->ID, 'poet_fund'),
        'poet_language' => poet_term_slugs($post->ID, 'poet_language'),
        'poet_modality' => poet_term_slugs($post->ID, 'poet_modality'),
    ];
    ?>
    <div class="poet-admin-grid">
        <p class="full">
            <label for="poet_name">שם המטפלת</label>
            <input type="text" class="widefat" id="poet_name" name="poet_name" value="<?php echo esc_attr($name); ?>" required>
        </p>
        <div class="poet-admin-visibility full">
            <label>
                <input type="checkbox" id="poet_visible" name="poet_visible" value="1" <?php checked($visible); ?>>
                <strong>מוצגת בחיפוש הציבורי</strong>
            </label>
            <span class="description">כיבוי האפשרות יסתיר את המטפלת מהאתר, אך כל פרטיה יישארו שמורים.</span>
        </div>
        <p class="full">
            <label for="poet_settlement">יישוב / עיר</label>
            <input type="text" class="widefat" id="poet_settlement" name="poet_settlement" value="<?php echo esc_attr($settlement); ?>">
            <span class="description">אפשר כמה יישובים מופרדים ב־ /</span>
        </p>
        <p>
            <label for="poet_age_min">גיל מינימום</label>
            <input type="number" step="0.5" min="0" id="poet_age_min" name="poet_age_min" value="<?php echo esc_attr($age_min); ?>">
        </p>
        <p>
            <label for="poet_age_max">גיל מקסימום</label>
            <input type="number" step="0.5" min="0" id="poet_age_max" name="poet_age_max" value="<?php echo esc_attr($age_max); ?>">
        </p>
        <p>
            <label for="poet_age_label">תווית גיל לתצוגה</label>
            <input type="text" class="widefat" id="poet_age_label" name="poet_age_label" value="<?php echo esc_attr($age_label); ?>" placeholder="למשל 3–7">
        </p>
        <p>
            <label>
                <input type="checkbox" name="poet_age_open" value="1" <?php checked($age_open); ?>>
                טווח פתוח (למשל 3+)
            </label>
        </p>
        <p>
            <label for="poet_phones">טלפונים (שורה לכל מספר)</label>
            <textarea class="widefat" rows="3" id="poet_phones" name="poet_phones"><?php echo esc_textarea($phones); ?></textarea>
        </p>
        <p>
            <label for="poet_emails">מיילים (שורה לכל כתובת)</label>
            <textarea class="widefat" rows="3" id="poet_emails" name="poet_emails"><?php echo esc_textarea($emails); ?></textarea>
        </p>
        <p class="full">
            <label>אזור גיאוגרפי</label>
            <div class="poet-admin-checks">
                <?php foreach (poet_region_labels() as $slug => $label) : ?>
                    <label>
                        <input type="checkbox" name="poet_region[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected['poet_region'], true)); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </p>
        <p class="full">
            <label>קופת חולים / מסגרת</label>
            <div class="poet-admin-checks">
                <?php foreach (poet_fund_labels() as $slug => $label) : ?>
                    <label>
                        <input type="checkbox" name="poet_fund[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected['poet_fund'], true)); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </p>
        <p class="full">
            <label>שפות טיפול</label>
            <div class="poet-admin-checks">
                <?php foreach (poet_language_labels() as $slug => $label) : ?>
                    <label>
                        <input type="checkbox" name="poet_language[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected['poet_language'], true)); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </p>
        <p class="full">
            <label>אופן הטיפול</label>
            <div class="poet-admin-checks">
                <?php foreach (poet_modality_labels() as $slug => $label) : ?>
                    <label>
                        <input type="checkbox" name="poet_modality[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected['poet_modality'], true)); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </p>
        <p>
            <label>
                <input type="checkbox" name="poet_education" value="1" <?php checked($education); ?>>
                מקבלת דרך משרד החינוך / מתי״א
            </label>
        </p>
        <p>
            <label for="poet_metiv">שם המתי״א</label>
            <input type="text" class="widefat" id="poet_metiv" name="poet_metiv" value="<?php echo esc_attr($metiv); ?>">
        </p>
        <p class="full">
            <label for="poet_notes">הערות (החזרים, הסדרים וכו׳)</label>
            <textarea class="widefat" rows="3" id="poet_notes" name="poet_notes"><?php echo esc_textarea($notes); ?></textarea>
        </p>
        <div class="poet-admin-save full">
            <button type="submit" name="publish" id="publish" class="button button-primary button-large" value="1">שמירה</button>
            <a class="button poet-admin-cancel" href="<?php echo esc_url(poet_manager_screen_url()); ?>">ביטול</a>
        </div>
    </div>
    <?php
}

function poet_save_therapist_meta(int $post_id, WP_Post $post): void
{
    if (!isset($_POST['poet_therapist_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['poet_therapist_nonce'])), 'poet_save_therapist')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $settlement = sanitize_text_field(wp_unslash($_POST['poet_settlement'] ?? ''));
    $settlements = poet_sanitize_string_list($settlement);
    $phones = poet_sanitize_string_list(wp_unslash($_POST['poet_phones'] ?? ''));
    $emails = array_map('sanitize_email', poet_sanitize_string_list(wp_unslash($_POST['poet_emails'] ?? '')));
    $emails = array_values(array_filter($emails));

    $new_values = [
        'name' => sanitize_text_field(wp_unslash($_POST['poet_name'] ?? $post->post_title)),
        'settlement' => $settlement,
        'age_min' => sanitize_text_field(wp_unslash($_POST['poet_age_min'] ?? '')),
        'age_max' => sanitize_text_field(wp_unslash($_POST['poet_age_max'] ?? '')),
        'age_label' => sanitize_text_field(wp_unslash($_POST['poet_age_label'] ?? '')),
        'age_open' => empty($_POST['poet_age_open']) ? '0' : '1',
        'phones' => $phones,
        'emails' => $emails,
        'notes' => sanitize_textarea_field(wp_unslash($_POST['poet_notes'] ?? '')),
        'metiv' => sanitize_text_field(wp_unslash($_POST['poet_metiv'] ?? '')),
        'education' => empty($_POST['poet_education']) ? '0' : '1',
        'region' => array_map('sanitize_text_field', (array) ($_POST['poet_region'] ?? [])),
        'fund' => array_map('sanitize_text_field', (array) ($_POST['poet_fund'] ?? [])),
        'language' => array_map('sanitize_text_field', (array) ($_POST['poet_language'] ?? [])),
        'modality' => array_map('sanitize_text_field', (array) ($_POST['poet_modality'] ?? [])),
    ];
    $online_only = in_array('online', $new_values['modality'], true)
        && !in_array('in_person', $new_values['modality'], true);
    $validation_errors = [];
    if ($new_values['name'] === '') {
        $validation_errors[] = 'יש להזין שם מטפלת.';
    }
    if (!$phones && !$emails) {
        $validation_errors[] = 'יש להזין לפחות טלפון או כתובת מייל.';
    }
    if ($settlement === '' && !$online_only) {
        $validation_errors[] = 'יש להזין עיר, אלא אם הטיפול הוא מקוון בלבד.';
    }

    update_post_meta($post_id, '_poet_settlement', $settlement);
    update_post_meta($post_id, '_poet_settlements', wp_json_encode($settlements, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, '_poet_phones', wp_json_encode($phones, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, '_poet_emails', wp_json_encode($emails, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, '_poet_age_min', $new_values['age_min']);
    update_post_meta($post_id, '_poet_age_max', $new_values['age_max']);
    update_post_meta($post_id, '_poet_age_label', $new_values['age_label']);
    update_post_meta($post_id, '_poet_age_open', $new_values['age_open']);
    update_post_meta($post_id, '_poet_notes', $new_values['notes']);
    update_post_meta($post_id, '_poet_metiv', $new_values['metiv']);
    update_post_meta($post_id, '_poet_education', $new_values['education']);
    update_post_meta($post_id, '_poet_certified', '1');
    if (!poet_valid_therapist_id(get_post_meta($post_id, '_poet_therapist_id', true))) {
        update_post_meta($post_id, '_poet_therapist_id', 'poet-' . wp_generate_uuid4());
    }
    update_post_meta(
        $post_id,
        '_poet_visible',
        !$validation_errors && !empty($_POST['poet_visible']) ? '1' : '0'
    );
    if ($validation_errors) {
        set_transient(
            'poet_validation_' . get_current_user_id() . '_' . $post_id,
            $validation_errors,
            MINUTE_IN_SECONDS
        );
    }

    $tax_map = [
        'poet_region' => $new_values['region'],
        'poet_fund' => $new_values['fund'],
        'poet_language' => $new_values['language'],
        'poet_modality' => $new_values['modality'],
    ];
    foreach ($tax_map as $taxonomy => $slugs) {
        wp_set_object_terms($post_id, $slugs, $taxonomy, false);
    }
}

function poet_is_therapist_visible(int $post_id): bool
{
    return !metadata_exists('post', $post_id, '_poet_visible')
        || (bool) get_post_meta($post_id, '_poet_visible', true);
}

function poet_admin_search_business_fields(string $search, WP_Query $query): string
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'poet_therapist') {
        return $search;
    }
    $term = trim((string) $query->get('s'));
    if ($term === '') {
        return $search;
    }

    global $wpdb;
    $like = '%' . $wpdb->esc_like($term) . '%';
    return $wpdb->prepare(" AND {$wpdb->posts}.post_title LIKE %s", $like);
}

function poet_therapist_row_actions(array $actions, WP_Post $post): array
{
    if ($post->post_type !== 'poet_therapist') {
        return $actions;
    }
    return [];
}

function poet_visibility_toggle_url(int $post_id): string
{
    return wp_nonce_url(
        admin_url('admin-post.php?action=poet_toggle_visibility&post_id=' . $post_id),
        'poet_toggle_visibility_' . $post_id
    );
}

function poet_remove_delete_bulk_actions(array $actions): array
{
    return [];
}

function poet_hide_wordpress_status_views(array $views): array
{
    return [];
}

function poet_toggle_therapist_visibility(): void
{
    $post_id = absint($_GET['post_id'] ?? 0);
    if (!$post_id || get_post_type($post_id) !== 'poet_therapist' || !current_user_can('edit_post', $post_id)) {
        wp_die(esc_html__('You are not allowed to edit this therapist.'));
    }
    check_admin_referer('poet_toggle_visibility_' . $post_id);
    $visible = !poet_is_therapist_visible($post_id);
    update_post_meta($post_id, '_poet_visible', $visible ? '1' : '0');
    wp_safe_redirect(add_query_arg(
        [
            'post_type' => 'poet_therapist',
            'poet_visibility' => $visible ? 'shown' : 'hidden',
        ],
        admin_url('edit.php')
    ));
    exit;
}

function poet_therapist_updated_messages(array $messages): array
{
    $messages['poet_therapist'] = array_fill(0, 11, '');
    $messages['poet_therapist'][1] = 'פרטי המטפלת נשמרו בהצלחה.';
    $messages['poet_therapist'][6] = 'המטפלת נוספה ונשמרה בהצלחה.';
    return $messages;
}

function poet_force_therapist_published(array $data, array $postarr): array
{
    if (
        ($data['post_type'] ?? '') === 'poet_therapist'
        && isset($_POST['poet_therapist_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['poet_therapist_nonce'])), 'poet_save_therapist')
    ) {
        $data['post_title'] = sanitize_text_field(wp_unslash($_POST['poet_name'] ?? ''));
        $data['post_status'] = 'publish';
    }
    return $data;
}

function poet_validation_redirect(string $location, int $post_id): string
{
    if (get_post_type($post_id) !== 'poet_therapist') {
        return $location;
    }
    $key = 'poet_validation_' . get_current_user_id() . '_' . $post_id;
    if (get_transient($key)) {
        $location = remove_query_arg('message', $location);
        return add_query_arg('poet_validation', '1', $location);
    }
    $original = sanitize_key(wp_unslash($_POST['original_post_status'] ?? ''));
    $saved = in_array($original, ['auto-draft', 'new', 'draft'], true) ? 'added' : 'updated';
    return add_query_arg('poet_saved', $saved, poet_manager_screen_url());
}

function poet_remove_wordpress_meta_boxes(): void
{
    $boxes = [
        'submitdiv',
        'slugdiv',
        'authordiv',
        'revisionsdiv',
        'commentstatusdiv',
        'commentsdiv',
        'trackbacksdiv',
        'postcustom',
        'formatdiv',
    ];
    foreach ($boxes as $box) {
        remove_meta_box($box, 'poet_therapist', 'normal');
        remove_meta_box($box, 'poet_therapist', 'advanced');
        remove_meta_box($box, 'poet_therapist', 'side');
    }
}

function poet_hide_therapist_screen_options(bool $show, WP_Screen $screen): bool
{
    return $screen->post_type === 'poet_therapist' ? false : $show;
}

function poet_remove_therapist_help(WP_Screen $screen): void
{
    if ($screen->post_type === 'poet_therapist') {
        $screen->remove_help_tabs();
    }
}

function poet_admin_list_script(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'poet_therapist' || $screen->base !== 'edit') {
        return;
    }
    ?>
    <script>
        (function () {
            const input = document.getElementById('post-search-input');
            if (input) {
                input.placeholder = 'חיפוש לפי שם מטפלת';
            }
            const submit = document.getElementById('search-submit');
            if (submit) {
                submit.value = 'חיפוש';
            }
        }());
    </script>
    <?php
}

function poet_admin_edit_script(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'poet_therapist') {
        return;
    }
    ?>
    <script>
        (function () {
            const form = document.getElementById('post');
            const name = document.getElementById('poet_name');
            const city = document.getElementById('poet_settlement');
            const phones = document.getElementById('poet_phones');
            const emails = document.getElementById('poet_emails');
            const save = document.getElementById('publish');
            if (!form || !name || !city || !phones || !emails || !save) return;

            save.value = 'שמירה';
            name.required = true;

            function validate() {
                phones.setCustomValidity('');
                city.setCustomValidity('');
                const online = form.querySelector('input[name="poet_modality[]"][value="online"]');
                const inPerson = form.querySelector('input[name="poet_modality[]"][value="in_person"]');
                const onlineOnly = online && online.checked && (!inPerson || !inPerson.checked);
                if (!phones.value.trim() && !emails.value.trim()) {
                    phones.setCustomValidity('יש להזין לפחות טלפון או כתובת מייל.');
                }
                if (!city.value.trim() && !onlineOnly) {
                    city.setCustomValidity('יש להזין עיר, אלא אם הטיפול הוא מקוון בלבד.');
                }
                return form.checkValidity();
            }

            form.addEventListener('submit', function (event) {
                if (!validate()) {
                    event.preventDefault();
                    form.reportValidity();
                }
            });
            [city, phones, emails].forEach(function (field) {
                field.addEventListener('input', validate);
            });
            form.querySelectorAll('input[name="poet_modality[]"]').forEach(function (field) {
                field.addEventListener('change', validate);
            });
        }());
    </script>
    <?php
}

function poet_admin_columns(array $columns): array
{
    return [
        'title' => 'שם המטפלת',
        'poet_region' => 'אזור',
        'poet_city' => 'יישוב',
        'poet_phone' => 'טלפון',
        'poet_status' => 'סטטוס',
        'poet_actions' => '',
    ];
}

function poet_admin_column_content(string $column, int $post_id): void
{
    switch ($column) {
        case 'poet_region':
            $regions = wp_get_object_terms($post_id, 'poet_region', ['fields' => 'names']);
            echo (!is_wp_error($regions) && $regions) ? esc_html(implode(', ', $regions)) : '—';
            break;
        case 'poet_city':
            $city = (string) get_post_meta($post_id, '_poet_settlement', true);
            echo $city !== '' ? esc_html($city) : '—';
            break;
        case 'poet_phone':
            $phones = poet_json_meta($post_id, '_poet_phones');
            echo $phones ? esc_html(implode(' / ', $phones)) : '—';
            break;
        case 'poet_status':
            $visible = poet_is_therapist_visible($post_id);
            printf(
                '<span class="poet-status %s">%s</span>',
                esc_attr($visible ? 'is-visible' : 'is-hidden'),
                esc_html($visible ? 'מוצגת' : 'מוסתרת')
            );
            break;
        case 'poet_actions':
            $visible = poet_is_therapist_visible($post_id);
            printf(
                '<div class="poet-row-actions"><a class="poet-btn-edit" href="%s">עריכה</a><a class="poet-btn-toggle" href="%s">%s</a></div>',
                esc_url(get_edit_post_link($post_id) ?: ''),
                esc_url(poet_visibility_toggle_url($post_id)),
                esc_html($visible ? 'הסתרה' : 'הצגה')
            );
            break;
    }
}

function poet_sortable_columns(array $columns): array
{
    $columns['title'] = 'title';
    return $columns;
}

function poet_admin_list_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'poet_therapist') {
        return;
    }
    $query->set('posts_per_page', 1000);
    if ($query->get('orderby') === '' || $query->get('orderby') === 'date') {
        $query->set('orderby', 'title');
        $query->set('order', 'ASC');
    }
}
