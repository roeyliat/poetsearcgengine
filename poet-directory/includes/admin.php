<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'poet_register_meta_boxes');
add_action('save_post_poet_therapist', 'poet_save_therapist_meta', 10, 2);
add_filter('manage_poet_therapist_posts_columns', 'poet_admin_columns');
add_action('manage_poet_therapist_posts_custom_column', 'poet_admin_column_content', 10, 2);
add_filter('manage_edit-poet_therapist_sortable_columns', 'poet_sortable_columns');
add_action('restrict_manage_posts', 'poet_admin_filters');
add_action('pre_get_posts', 'poet_admin_filter_query');
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
add_action('admin_head', 'poet_admin_edit_styles');
add_action('add_meta_boxes_poet_therapist', 'poet_remove_wordpress_meta_boxes', 100);
add_filter('screen_options_show_screen', 'poet_hide_therapist_screen_options', 10, 2);
add_action('current_screen', 'poet_remove_therapist_help');

function poet_admin_shortcode_notice(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'poet_therapist') {
        return;
    }
    $visibility = sanitize_key(wp_unslash($_GET['poet_visibility'] ?? ''));
    if ($visibility === 'shown') {
        echo '<div class="notice notice-success is-dismissible"><p>המטפלת מוצגת כעת בחיפוש הציבורי.</p></div>';
    } elseif ($visibility === 'hidden') {
        echo '<div class="notice notice-success is-dismissible"><p>המטפלת הוסתרה מהחיפוש הציבורי ונשמרה במערכת.</p></div>';
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
    <style>
        .poet-admin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; direction: rtl; text-align: right; }
        .poet-admin-grid label { font-weight: 600; display: block; margin-bottom: 6px; }
        .poet-admin-grid .full { grid-column: 1 / -1; }
        .poet-admin-checks { display: flex; flex-wrap: wrap; gap: 10px 16px; }
        .poet-admin-checks label { font-weight: 400; }
        .poet-admin-visibility { padding: 14px; border-right: 4px solid #2271b1; background: #f0f6fc; }
        .poet-admin-visibility label { display: flex; align-items: center; gap: 8px; font-size: 15px; }
        .poet-admin-save { display: flex; justify-content: flex-start; padding-top: 8px; border-top: 1px solid #dcdcde; }
        .poet-admin-save .button { min-width: 180px; min-height: 42px; font-size: 15px; font-weight: 600; }
        @media (max-width: 782px) { .poet-admin-grid { grid-template-columns: 1fr; } }
    </style>
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
            <button type="submit" name="publish" id="publish" class="button button-primary button-large" value="1">שמירת מטפלת</button>
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
    return $wpdb->prepare(
        " AND ({$wpdb->posts}.post_title LIKE %s OR EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} poet_search_meta
            WHERE poet_search_meta.post_id = {$wpdb->posts}.ID
            AND poet_search_meta.meta_key IN ('_poet_settlement', '_poet_phones', '_poet_emails')
            AND poet_search_meta.meta_value LIKE %s
        ))",
        $like,
        $like
    );
}

function poet_therapist_row_actions(array $actions, WP_Post $post): array
{
    if ($post->post_type !== 'poet_therapist') {
        return $actions;
    }
    unset($actions['trash'], $actions['delete'], $actions['view'], $actions['inline hide-if-no-js']);
    if (isset($actions['edit'])) {
        $actions['edit'] = '<a href="' . esc_url(get_edit_post_link($post->ID)) . '">עריכה</a>';
    }
    $visible = poet_is_therapist_visible($post->ID);
    $url = wp_nonce_url(
        admin_url('admin-post.php?action=poet_toggle_visibility&post_id=' . $post->ID),
        'poet_toggle_visibility_' . $post->ID
    );
    $actions['poet_visibility'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($url),
        $visible ? 'הסתרה' : 'הצגה'
    );
    return $actions;
}

function poet_remove_delete_bulk_actions(array $actions): array
{
    unset($actions['trash'], $actions['delete'], $actions['edit']);
    return $actions;
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
        $location = add_query_arg('poet_validation', '1', $location);
    }
    return $location;
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

function poet_admin_edit_styles(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'poet_therapist' || $screen->base !== 'post') {
        return;
    }
    ?>
    <style>
        #screen-meta-links, #post-body-content, #postbox-container-1 { display: none !important; }
        #poststuff #post-body.columns-2 { margin-right: 0; }
        #post-body.columns-2 #postbox-container-2 { width: 100%; }
        #poet_therapist_details { border: 0; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12); }
        #poet_therapist_details .postbox-header { border-bottom-color: #e2e4e7; }
    </style>
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

            save.value = 'שמירת מטפלת';
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
        'cb' => $columns['cb'] ?? '',
        'title' => 'שם',
        'poet_city' => 'יישוב',
        'poet_phone' => 'טלפון',
        'poet_email' => 'מייל',
        'poet_langs' => 'שפות',
        'poet_status' => 'סטטוס',
    ];
}

function poet_admin_column_content(string $column, int $post_id): void
{
    switch ($column) {
        case 'poet_city':
            echo esc_html((string) get_post_meta($post_id, '_poet_settlement', true));
            break;
        case 'poet_phone':
            $phones = poet_json_meta($post_id, '_poet_phones');
            echo $phones ? esc_html(implode(' / ', $phones)) : '—';
            break;
        case 'poet_email':
            $emails = poet_json_meta($post_id, '_poet_emails');
            echo $emails ? esc_html(implode(' / ', $emails)) : '—';
            break;
        case 'poet_langs':
            echo esc_html(implode(', ', wp_get_object_terms($post_id, 'poet_language', ['fields' => 'names'])));
            break;
        case 'poet_status':
            $visible = poet_is_therapist_visible($post_id);
            printf(
                '<strong style="color:%s">%s</strong>',
                esc_attr($visible ? '#008a20' : '#646970'),
                esc_html($visible ? 'מוצגת' : 'מוסתרת')
            );
            break;
    }
}

function poet_sortable_columns(array $columns): array
{
    $columns['title'] = 'title';
    return $columns;
}

function poet_admin_filters(string $post_type): void
{
    if ($post_type !== 'poet_therapist') {
        return;
    }
    $region = sanitize_text_field(wp_unslash($_GET['poet_region_filter'] ?? ''));
    $fund = sanitize_text_field(wp_unslash($_GET['poet_fund_filter'] ?? ''));
    echo '<select name="poet_region_filter"><option value="">כל האזורים</option>';
    foreach (poet_region_labels() as $slug => $label) {
        printf('<option value="%s"%s>%s</option>', esc_attr($slug), selected($region, $slug, false), esc_html($label));
    }
    echo '</select>';
    echo '<select name="poet_fund_filter"><option value="">כל המסגרות</option>';
    foreach (poet_fund_labels() as $slug => $label) {
        printf('<option value="%s"%s>%s</option>', esc_attr($slug), selected($fund, $slug, false), esc_html($label));
    }
    echo '</select>';
}

function poet_admin_filter_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'poet_therapist') {
        return;
    }
    $tax = [];
    $region = sanitize_text_field(wp_unslash($_GET['poet_region_filter'] ?? ''));
    $fund = sanitize_text_field(wp_unslash($_GET['poet_fund_filter'] ?? ''));
    if ($region) {
        $tax[] = ['taxonomy' => 'poet_region', 'field' => 'slug', 'terms' => $region];
    }
    if ($fund) {
        $tax[] = ['taxonomy' => 'poet_fund', 'field' => 'slug', 'terms' => $fund];
    }
    if ($tax) {
        $query->set('tax_query', $tax);
    }
}
