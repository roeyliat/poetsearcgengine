<?php

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('poet_directory', 'poet_directory_shortcode');
add_action('wp_enqueue_scripts', 'poet_register_assets');
add_filter('use_block_editor_for_post_type', 'poet_disable_block_editor', 10, 2);

function poet_disable_block_editor(bool $use, string $type): bool
{
    return $type === 'poet_therapist' ? false : $use;
}

function poet_register_assets(): void
{
    wp_register_style(
        'poet-directory',
        POET_DIR_URL . 'public/css/directory.css',
        [],
        POET_DIR_VERSION
    );
    wp_register_script(
        'poet-directory',
        POET_DIR_URL . 'public/js/directory.js',
        [],
        POET_DIR_VERSION,
        true
    );
}

function poet_enqueue_directory_assets(): void
{
    wp_enqueue_style('poet-directory');
    wp_enqueue_script('poet-directory');
    wp_localize_script('poet-directory', 'POET_DIRECTORY', [
        'endpoint' => rest_url('poet/v1/therapists'),
    ]);
}

function poet_directory_shortcode(): string
{
    poet_enqueue_directory_assets();
    ob_start();
    ?>
    <div class="poet-directory" dir="rtl" lang="he">
        <section class="poet-hub">
            <div class="poet-directory-brand">
                <img class="poet-logo" src="https://frisch-ot.com/wp-content/uploads/2021/10/POET-logoR-72dpi.png" alt="POET">
            </div>
            <p class="poet-kicker">Parental Occupational Executive Training</p>
            <div class="poet-directory-title-row">
                <h2 class="poet-hub-title">גישת POET</h2>
                <a class="poet-admin-header-entry" href="<?php echo esc_url(admin_url('edit.php?post_type=poet_therapist')); ?>">כניסת מנהל</a>
            </div>
            <p class="poet-hub-lead">
                POET (פריש, תירוש ורוזנבלום, 2020) היא התערבות מבוססת ראיות מחקריות לשיפור התפקוד והתפקודים הניהוליים של ילדים עם תסמיני ADHD.
            </p>

            <div class="poet-hub-grid">
                <article>
                    <h3>מטרות לפי מה שחשוב להורים</h3>
                    <ul>
                        <li>לקום מהשולחן רק לאחר סיום ארוחת הערב</li>
                        <li>לזכור את רצף הפעולות הרצויות בהתארגנות הבוקר</li>
                        <li>לשחק באופן עצמאי במשחק עם משמעות אחר הצהריים</li>
                        <li>להמתין 2–3 דקות כשהם מאוד רוצים להגיד משהו וההורים באמצע שיחה</li>
                    </ul>
                </article>
                <article>
                    <h3>מה מקבלים ההורים</h3>
                    <p>ידע שמאפשר להבין את הסיבות לקשיים התפקודיים של הילדים, וכלים מעשיים ליישום בבית כדי לשפר את התפקוד.</p>
                    <h3>מי מיישמת את הגישה</h3>
                    <p>ה־POET מיושמת אך ורק על ידי מרפאים בעיסוק בעלי רישיון משרד הבריאות, שעברו קורס הכשרה מקצועית בגישת POET ועמדו בכל דרישותיו.</p>
                </article>
            </div>

            <details class="poet-hub-details">
                <summary>למי הטיפול מתאים — תנאי סף</summary>
                <ul>
                    <li>ילדים עם הבנת שפה תקינה</li>
                    <li>ללא הפרעת התנהגות וללא אבחנות בתחום הרגשי (כגון דיכאון או חרדה)</li>
                    <li>ניתן להפנות ילדים עם קשיי תפקוד המחשידים בתסמיני ADHD — לא נדרשת אבחנה רשמית</li>
                    <li>הטיפול מותאם גם להורים שמתמודדים בעצמם עם תסמיני ADHD</li>
                    <li>התאמה אישית לקשיים תפקודיים ולמיומנויות נוספות שעלולות לגרוע מתפקוד הילד (למשל סרבול מוטורי, רגישות חושית)</li>
                    <li>קיימות ראיות מחקריות לישימות ה־POET וליעילותה — גם באופן מקוון</li>
                </ul>
            </details>
        </section>

        <section class="poet-search" aria-label="חיפוש מרפאות בעיסוק מוסמכות POET">
            <div class="poet-search-head">
                <h2>כלי לחיפוש מרפאות/ים בעיסוק POET</h2>
                <p>חפשו לפי שם, יישוב או סינון לפי אזור, קופה, שפה, גיל ואופן הטיפול.</p>
            </div>

            <div class="poet-filters">
                <label class="poet-search-field">
                    <span>חיפוש חופשי</span>
                    <input type="search" id="poet-q" placeholder="שם, עיר או יישוב">
                </label>
                <label class="poet-search-field poet-age-field">
                    <span>גיל הילד</span>
                    <input type="number" id="poet-age" min="0" max="99" step="1" placeholder="הכל">
                </label>
                <fieldset>
                    <legend>אזור</legend>
                    <div class="poet-chips" data-filter="region">
                        <?php foreach (poet_region_labels() as $slug => $label) : ?>
                            <label><input type="checkbox" value="<?php echo esc_attr($slug); ?>"> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>קופה / מסגרת</legend>
                    <div class="poet-chips" data-filter="fund">
                        <?php foreach (poet_fund_labels() as $slug => $label) : ?>
                            <label><input type="checkbox" value="<?php echo esc_attr($slug); ?>"> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>אופן הטיפול</legend>
                    <div class="poet-chips" data-filter="modality">
                        <?php foreach (poet_modality_labels() as $slug => $label) : ?>
                            <label><input type="checkbox" value="<?php echo esc_attr($slug); ?>"> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>שפת טיפול</legend>
                    <div class="poet-chips" data-filter="language">
                        <?php foreach (poet_language_labels() as $slug => $label) : ?>
                            <?php if (in_array($slug, ['russian', 'sign_language'], true)) {
                                continue;
                            } ?>
                            <label><input type="checkbox" value="<?php echo esc_attr($slug); ?>"> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <button type="button" class="poet-clear" id="poet-clear">ניקוי סינון</button>
            </div>

            <p class="poet-count" id="poet-count" aria-live="polite"></p>
            <div class="poet-cards" id="poet-cards"></div>
            <p class="poet-empty" id="poet-empty" hidden>לא נמצאו מרפאות בעיסוק לפי הסינון שנבחר.</p>
        </section>
    </div>
    <?php
    return (string) ob_get_clean();
}
