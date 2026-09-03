<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'poet_register_import_page');

function poet_register_import_page(): void
{
    if (get_option('poet_initial_import_complete')) {
        return;
    }
    add_submenu_page(
        'edit.php?post_type=poet_therapist',
        'ייבוא מוסמכות',
        'ייבוא JSON',
        'manage_options',
        'poet-import',
        'poet_render_import_page'
    );
}

function poet_render_import_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    if (get_option('poet_initial_import_complete')) {
        wp_die(esc_html__('The initial POET import is already complete.'));
    }

    $result = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['poet_import_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['poet_import_nonce'])), 'poet_import')) {
        $result = poet_handle_import();
    }

    $default_json = POET_DIR_PATH . 'data/therapists.json';
    ?>
    <div class="wrap" dir="rtl">
        <h1>ייבוא למאגר מטפלות POET</h1>
        <p>העלו את הקובץ <code>therapists.json</code> שנוצר מסקריפט הניקוי. הייבוא מתאים רשומות לפי המזהה הקבוע, מעדכן רק שדות שאינם מוגנים, ואינו מוחק כרטיסים שחסרים בקובץ.</p>
        <?php if (is_array($result)) : ?>
            <div class="notice notice-<?php echo empty($result['error']) ? 'success' : 'error'; ?>">
                <p><?php echo esc_html($result['message']); ?></p>
                <?php if (!empty($result['stats'])) : ?>
                    <p>
                        לפני: <?php echo (int) $result['stats']['before']; ?> ·
                        אחרי: <?php echo (int) $result['stats']['after']; ?> ·
                        מזהים שהוקצו: <?php echo (int) $result['stats']['migrated']; ?> ·
                        נוצרו: <?php echo (int) $result['stats']['created']; ?> ·
                        עודכנו: <?php echo (int) $result['stats']['updated']; ?> ·
                        שדות מוגנים שנשמרו: <?php echo (int) $result['stats']['locked_fields']; ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('poet_import', 'poet_import_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>קובץ JSON</th>
                    <td><input type="file" name="poet_json" accept="application/json,.json"></td>
                </tr>
                <?php if (file_exists($default_json)) : ?>
                    <tr>
                        <th>או קובץ מקומי</th>
                        <td>
                            <label>
                                <input type="checkbox" name="poet_use_bundled" value="1">
                                ייבוא מ־<code>poet-directory/data/therapists.json</code>
                            </label>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <?php submit_button('ייבוא'); ?>
        </form>
    </div>
    <?php
}

function poet_handle_import(): array
{
    $payload = null;
    if (!empty($_POST['poet_use_bundled'])) {
        $path = POET_DIR_PATH . 'data/therapists.json';
        if (!file_exists($path)) {
            return ['error' => true, 'message' => 'הקובץ המקומי לא נמצא.'];
        }
        $payload = json_decode((string) file_get_contents($path), true);
    } elseif (!empty($_FILES['poet_json']['tmp_name'])) {
        $raw = file_get_contents($_FILES['poet_json']['tmp_name']);
        $payload = json_decode((string) $raw, true);
    } else {
        return ['error' => true, 'message' => 'לא נבחר קובץ.'];
    }

    if (!is_array($payload)) {
        return ['error' => true, 'message' => 'JSON לא תקין.'];
    }

    $items = $payload['therapists'] ?? $payload;
    if (!is_array($items)) {
        return ['error' => true, 'message' => 'לא נמצאה רשימת מרפאות בקובץ.'];
    }

    poet_insert_default_terms();
    $preflight = poet_import_preflight($items);
    if (is_wp_error($preflight)) {
        return ['error' => true, 'message' => $preflight->get_error_message()];
    }

    $stats = [
        'before' => count($preflight['existing_posts']),
        'after' => count($preflight['existing_posts']),
        'migrated' => 0,
        'created' => 0,
        'updated' => 0,
        'locked_fields' => 0,
    ];

    foreach ($preflight['plan'] as $operation) {
        $result = poet_apply_import_item($operation['item'], $operation['post_id']);
        if (is_wp_error($result)) {
            return [
                'error' => true,
                'message' => 'הייבוא נעצר: ' . $result->get_error_message(),
                'stats' => $stats,
            ];
        }
        $stats[$operation['action']]++;
        if ($operation['action'] === 'migrated') {
            $stats['updated']++;
        }
        $stats['locked_fields'] += $result['locked_fields'];
    }

    $stats['after'] = (int) wp_count_posts('poet_therapist')->publish
        + (int) wp_count_posts('poet_therapist')->draft
        + (int) wp_count_posts('poet_therapist')->private;
    if (count($items) === 380 && $stats['after'] === 380) {
        update_option('poet_initial_import_complete', '1', false);
    }

    return [
        'error' => false,
        'message' => 'הייבוא הושלם ללא מחיקת רשומות.',
        'stats' => $stats,
    ];
}

function poet_import_preflight(array $items)
{
    $post_ids = get_posts([
        'post_type' => 'poet_therapist',
        'post_status' => ['publish', 'draft', 'private'],
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    $posts_by_therapist_id = [];
    $idless_posts = [];
    foreach ($post_ids as $post_id) {
        $therapist_id = poet_valid_therapist_id(get_post_meta($post_id, '_poet_therapist_id', true));
        if (!$therapist_id) {
            $idless_posts[] = (int) $post_id;
            continue;
        }
        if (isset($posts_by_therapist_id[$therapist_id])) {
            return new WP_Error(
                'duplicate_wp_id',
                sprintf('נמצאו שני כרטיסים עם אותו מזהה קבוע: %s. לא בוצעו שינויים.', $therapist_id)
            );
        }
        $posts_by_therapist_id[$therapist_id] = (int) $post_id;
    }

    $seen_import_ids = [];
    $claimed_posts = [];
    $plan = [];
    foreach ($items as $index => $item) {
        if (!is_array($item) || empty($item['name'])) {
            return new WP_Error('invalid_row', sprintf('רשומה %d אינה תקינה. לא בוצעו שינויים.', $index + 1));
        }
        $therapist_id = poet_valid_therapist_id($item['therapist_id'] ?? '');
        if (!$therapist_id) {
            return new WP_Error(
                'missing_id',
                sprintf('לרשומה %s אין מזהה קבוע תקין. לא בוצעו שינויים.', sanitize_text_field((string) $item['name']))
            );
        }
        if (isset($seen_import_ids[$therapist_id])) {
            return new WP_Error(
                'duplicate_import_id',
                sprintf('המזהה %s מופיע פעמיים בקובץ. לא בוצעו שינויים.', $therapist_id)
            );
        }
        $seen_import_ids[$therapist_id] = true;

        if (isset($posts_by_therapist_id[$therapist_id])) {
            $post_id = $posts_by_therapist_id[$therapist_id];
            $plan[] = ['action' => 'updated', 'post_id' => $post_id, 'item' => $item];
            continue;
        }

        $candidates = poet_find_migration_candidates($item, $idless_posts);
        if (count($candidates) > 1) {
            return new WP_Error(
                'ambiguous_match',
                sprintf(
                    'נמצאה התאמה לא חד־משמעית עבור %s (כרטיסים: %s). לא בוצעו שינויים.',
                    sanitize_text_field((string) $item['name']),
                    implode(', ', $candidates)
                )
            );
        }
        if (count($candidates) === 1) {
            $post_id = (int) $candidates[0];
            if (isset($claimed_posts[$post_id])) {
                return new WP_Error(
                    'duplicate_claim',
                    sprintf('שתי רשומות בקובץ מתאימות לכרטיס %d. לא בוצעו שינויים.', $post_id)
                );
            }
            $claimed_posts[$post_id] = true;
            $plan[] = ['action' => 'migrated', 'post_id' => $post_id, 'item' => $item];
            continue;
        }

        $plan[] = ['action' => 'created', 'post_id' => 0, 'item' => $item];
    }

    return [
        'existing_posts' => $post_ids,
        'plan' => $plan,
    ];
}

function poet_find_migration_candidates(array $item, array $post_ids): array
{
    $name = poet_normalize_identity_text((string) ($item['name'] ?? ''));
    $emails = array_values(array_filter(array_map(
        static fn($value): string => strtolower(sanitize_email((string) $value)),
        (array) ($item['emails'] ?? [])
    )));
    $phones = array_values(array_filter(array_map(
        static fn($value): string => poet_normalize_phone((string) $value),
        (array) ($item['phones'] ?? [])
    )));

    $name_matches = [];
    $email_matches = [];
    $phone_matches = [];
    foreach ($post_ids as $post_id) {
        if (poet_normalize_identity_text(get_the_title($post_id)) === $name) {
            $name_matches[] = (int) $post_id;
        }
        $post_emails = array_map('strtolower', poet_json_meta($post_id, '_poet_emails'));
        if ($emails && array_intersect($emails, $post_emails)) {
            $email_matches[] = (int) $post_id;
        }
        $post_phones = array_values(array_filter(array_map('poet_normalize_phone', poet_json_meta($post_id, '_poet_phones'))));
        if ($phones && array_intersect($phones, $post_phones)) {
            $phone_matches[] = (int) $post_id;
        }
    }

    if ($email_matches) {
        if ($name_matches) {
            $compatible = array_values(array_intersect($email_matches, $name_matches));
            return $compatible ?: array_values(array_unique(array_merge($email_matches, $name_matches)));
        }
        return array_values(array_unique($email_matches));
    }
    if (count($name_matches) === 1) {
        if ($phone_matches && !in_array($name_matches[0], $phone_matches, true)) {
            return array_values(array_unique(array_merge($name_matches, $phone_matches)));
        }
        return $name_matches;
    }
    if (count($name_matches) > 1) {
        $narrowed = array_values(array_intersect($name_matches, $phone_matches));
        return count($narrowed) === 1 ? $narrowed : $name_matches;
    }
    return array_values(array_unique($phone_matches));
}

function poet_apply_import_item(array $item, int $post_id)
{
    $therapist_id = poet_valid_therapist_id($item['therapist_id'] ?? '');
    if (!$therapist_id) {
        return new WP_Error('invalid_id', 'מזהה מטפל/ת אינו תקין.');
    }

    $is_new = $post_id === 0;
    if ($is_new) {
        $post_id = wp_insert_post([
            'post_type' => 'poet_therapist',
            'post_title' => sanitize_text_field((string) $item['name']),
            'post_status' => 'publish',
        ], true);
        if (is_wp_error($post_id) || !$post_id) {
            return is_wp_error($post_id) ? $post_id : new WP_Error('create_failed', 'יצירת הכרטיס נכשלה.');
        }
    }

    $existing_id = poet_valid_therapist_id(get_post_meta($post_id, '_poet_therapist_id', true));
    if ($existing_id && $existing_id !== $therapist_id) {
        return new WP_Error('immutable_id', sprintf('לא ניתן לשנות את המזהה הקבוע של כרטיס %d.', $post_id));
    }
    if (!$existing_id) {
        update_post_meta($post_id, '_poet_therapist_id', $therapist_id);
    }
    if (!metadata_exists('post', $post_id, '_poet_visible')) {
        update_post_meta($post_id, '_poet_visible', '1');
    }
    update_post_meta($post_id, '_poet_source_row', absint($item['source_row'] ?? 0));
    update_post_meta($post_id, '_poet_certified', '1');

    $overrides = poet_manual_overrides($post_id);
    $locked_fields = 0;
    $update_meta = static function (string $field, string $meta_key, $value) use ($post_id, $overrides, &$locked_fields): void {
        if (in_array($field, $overrides, true)) {
            $locked_fields++;
            return;
        }
        update_post_meta($post_id, $meta_key, $value);
    };
    $update_terms = static function (string $field, string $taxonomy, array $terms) use ($post_id, $overrides, &$locked_fields): void {
        if (in_array($field, $overrides, true)) {
            $locked_fields++;
            return;
        }
        wp_set_object_terms($post_id, array_values(array_unique(array_map('sanitize_title', $terms))), $taxonomy, false);
    };

    if (in_array('name', $overrides, true)) {
        $locked_fields++;
    } else {
        $updated = wp_update_post([
            'ID' => $post_id,
            'post_title' => sanitize_text_field((string) $item['name']),
        ], true);
        if (is_wp_error($updated)) {
            return $updated;
        }
    }

    $phones = array_values(array_filter(array_map('strval', (array) ($item['phones'] ?? []))));
    $emails = array_values(array_filter(array_map('sanitize_email', (array) ($item['emails'] ?? []))));
    $settlements = array_values(array_filter(array_map('strval', (array) ($item['settlements'] ?? []))));
    $settlement_label = (string) ($item['settlement_label'] ?? implode(' / ', $settlements));

    if (in_array('settlement', $overrides, true)) {
        $locked_fields++;
    } else {
        update_post_meta($post_id, '_poet_settlement', sanitize_text_field($settlement_label));
        update_post_meta($post_id, '_poet_settlements', wp_json_encode($settlements, JSON_UNESCAPED_UNICODE));
    }
    $update_meta('phones', '_poet_phones', wp_json_encode($phones, JSON_UNESCAPED_UNICODE));
    $update_meta('emails', '_poet_emails', wp_json_encode($emails, JSON_UNESCAPED_UNICODE));
    $update_meta('age_min', '_poet_age_min', isset($item['age_min']) && $item['age_min'] !== null ? (string) $item['age_min'] : '');
    $update_meta('age_max', '_poet_age_max', isset($item['age_max']) && $item['age_max'] !== null ? (string) $item['age_max'] : '');
    $update_meta('age_label', '_poet_age_label', sanitize_text_field((string) ($item['age_label'] ?? '')));
    $update_meta('age_open', '_poet_age_open', !empty($item['age_open_ended']) ? '1' : '0');
    $update_meta('notes', '_poet_notes', sanitize_textarea_field((string) ($item['notes'] ?? '')));
    $update_meta('metiv', '_poet_metiv', sanitize_text_field((string) ($item['metiv'] ?? '')));
    $update_meta('education', '_poet_education', !empty($item['education']) ? '1' : '0');

    $update_terms('region', 'poet_region', (array) ($item['regions'] ?? []));
    $update_terms('fund', 'poet_fund', (array) ($item['funds'] ?? []));
    $update_terms('language', 'poet_language', (array) ($item['languages'] ?? []));
    $modalities = [];
    if (!empty($item['in_person'])) {
        $modalities[] = 'in_person';
    }
    if (!empty($item['online'])) {
        $modalities[] = 'online';
    }
    $update_terms('modality', 'poet_modality', $modalities ?: ['in_person']);

    return ['post_id' => $post_id, 'locked_fields' => $locked_fields];
}
