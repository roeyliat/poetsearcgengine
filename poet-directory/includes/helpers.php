<?php

if (!defined('ABSPATH')) {
    exit;
}

function poet_region_labels(): array
{
    return [
        'north' => 'צפון',
        'sharon' => 'שרון',
        'center' => 'מרכז',
        'jerusalem' => 'ירושלים',
        'south' => 'דרום',
    ];
}

function poet_fund_labels(): array
{
    return [
        'maccabi' => 'מכבי',
        'clalit' => 'כללית',
        'meuhedet' => 'מאוחדת',
        'leumit' => 'לאומית',
        'private' => 'קליניקה פרטית',
        'education' => 'משרד החינוך / מתי״א',
    ];
}

function poet_language_labels(): array
{
    return [
        'hebrew' => 'עברית',
        'arabic' => 'ערבית',
        'english' => 'אנגלית',
        'spanish' => 'ספרדית',
        'russian' => 'רוסית',
        'yiddish' => 'יידיש',
        'french' => 'צרפתית',
        'sign_language' => 'שפת סימנים',
    ];
}

function poet_modality_labels(): array
{
    return [
        'in_person' => 'פרונטלי',
        'online' => 'מקוון',
    ];
}

function poet_import_field_labels(): array
{
    return [
        'name' => 'שם',
        'settlement' => 'יישוב / עיר',
        'age_min' => 'גיל מינימום',
        'age_max' => 'גיל מקסימום',
        'age_label' => 'תווית גיל',
        'age_open' => 'טווח גיל פתוח',
        'phones' => 'טלפונים',
        'emails' => 'מיילים',
        'region' => 'אזור גיאוגרפי',
        'fund' => 'קופת חולים / מסגרת',
        'language' => 'שפות טיפול',
        'modality' => 'אופן הטיפול',
        'education' => 'משרד החינוך / מתי״א',
        'metiv' => 'שם המתי״א',
        'notes' => 'הערות',
    ];
}

function poet_manual_overrides(int $post_id): array
{
    $raw = get_post_meta($post_id, '_poet_manual_overrides', true);
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw)) {
        return [];
    }
    $allowed = array_keys(poet_import_field_labels());
    return array_values(array_intersect(array_unique(array_map('strval', $raw)), $allowed));
}

function poet_set_manual_overrides(int $post_id, array $overrides): void
{
    $allowed = array_keys(poet_import_field_labels());
    $clean = array_values(array_intersect(array_unique(array_map('strval', $overrides)), $allowed));
    update_post_meta($post_id, '_poet_manual_overrides', wp_json_encode($clean));
}

function poet_field_is_overridden(int $post_id, string $field): bool
{
    return in_array($field, poet_manual_overrides($post_id), true);
}

function poet_normalize_identity_text(string $value): string
{
    $value = remove_accents(strtolower(trim($value)));
    $value = preg_replace('/^(ד[״"]?ר|דר)\s+/u', '', $value) ?? $value;
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? $value;
}

function poet_normalize_phone(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function poet_valid_therapist_id($value): string
{
    $value = strtolower(trim((string) $value));
    return preg_match('/^poet-[0-9a-f-]{36}$/', $value) ? $value : '';
}

function poet_json_meta(int $post_id, string $key): array
{
    $raw = get_post_meta($post_id, $key, true);
    if (is_array($raw)) {
        return array_values(array_filter($raw, static fn($item) => $item !== '' && $item !== null));
    }
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_values(array_filter($decoded)) : [];
}

function poet_sanitize_string_list($value): array
{
    if (is_string($value)) {
        $value = preg_split('/\r\n|\r|\n|\s*[,\/;]\s*/', $value) ?: [];
    }
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    foreach ($value as $item) {
        $item = trim(wp_strip_all_tags((string) $item));
        if ($item !== '') {
            $out[] = $item;
        }
    }
    return array_values(array_unique($out));
}

function poet_therapist_payload(WP_Post $post): array
{
    $phones = poet_json_meta($post->ID, '_poet_phones');
    $emails = poet_json_meta($post->ID, '_poet_emails');
    $settlements = poet_json_meta($post->ID, '_poet_settlements');
    $age_min = get_post_meta($post->ID, '_poet_age_min', true);
    $age_max = get_post_meta($post->ID, '_poet_age_max', true);

    return [
        'id' => $post->ID,
        'name' => get_the_title($post),
        'settlement_label' => (string) get_post_meta($post->ID, '_poet_settlement', true),
        'settlements' => $settlements,
        'regions' => poet_term_slugs($post->ID, 'poet_region'),
        'region_labels' => poet_term_names($post->ID, 'poet_region'),
        'age_min' => $age_min === '' ? null : (float) $age_min,
        'age_max' => $age_max === '' ? null : (float) $age_max,
        'age_label' => (string) get_post_meta($post->ID, '_poet_age_label', true),
        'age_open_ended' => (bool) get_post_meta($post->ID, '_poet_age_open', true),
        'phones' => $phones,
        'emails' => $emails,
        'languages' => poet_term_slugs($post->ID, 'poet_language'),
        'language_labels' => poet_term_names($post->ID, 'poet_language'),
        'funds' => poet_term_slugs($post->ID, 'poet_fund'),
        'fund_labels' => poet_term_names($post->ID, 'poet_fund'),
        'education' => (bool) get_post_meta($post->ID, '_poet_education', true),
        'metiv' => (string) get_post_meta($post->ID, '_poet_metiv', true),
        'online' => has_term('online', 'poet_modality', $post),
        'in_person' => has_term('in_person', 'poet_modality', $post),
        'notes' => (string) get_post_meta($post->ID, '_poet_notes', true),
        'certified' => true,
    ];
}

function poet_term_slugs(int $post_id, string $taxonomy): array
{
    $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);
    return is_wp_error($terms) ? [] : $terms;
}

function poet_term_names(int $post_id, string $taxonomy): array
{
    $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'names']);
    return is_wp_error($terms) ? [] : $terms;
}
