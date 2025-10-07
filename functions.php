<?php
/**
 * Grant Insight Perfect - Functions File (Consolidated & Clean Edition)
 * 
 * Simplified structure with consolidated files in single /inc/ directory
 * - Removed unused code and duplicate functionality
 * - Merged related files for better organization
 * - Eliminated folder over-organization
 * 
 * @package Grant_Insight_Perfect
 * @version 9.0.0 (Consolidated Edition)
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// テーマバージョン定数
if (!defined('GI_THEME_VERSION')) {
    define('GI_THEME_VERSION', '9.0.0');
}
if (!defined('GI_THEME_PREFIX')) {
    define('GI_THEME_PREFIX', 'gi_');
}

// 統合されたファイルの読み込み（シンプルな配列）
$inc_dir = get_template_directory() . '/inc/';

$required_files = array(
    // Core files
    'theme-foundation.php',        // テーマ設定、投稿タイプ、タクソノミー
    'data-processing.php',         // データ処理・ヘルパー関数
    
    // Admin & UI
    'admin-functions.php',         // 管理画面カスタマイズ + メタボックス (統合済み)
    'acf-fields.php',              // ACF設定とフィールド定義
    
    // Core functionality
    'card-display.php',            // カードレンダリング・表示機能
    'ajax-functions.php',          // AJAX処理
    'ai-functions.php',            // AI機能・検索履歴 (統合済み)
    
    // Google Sheets integration (consolidated into one file)
    'google-sheets-integration.php', // Google Sheets統合（全機能統合版）
    'safe-sync-manager.php',         // 安全同期管理システム
    'disable-auto-sync.php'          // 自動同期無効化
);

// ファイルを安全に読み込み
foreach ($required_files as $file) {
    $file_path = $inc_dir . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // デバッグモードの場合のみエラーログに記録
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Grant Insight: Missing required file: ' . $file);
        }
    }
}

// グローバルで使えるヘルパー関数
if (!function_exists('gi_render_card')) {
    function gi_render_card($post_id, $view = 'grid') {
        if (class_exists('GrantCardRenderer')) {
            $renderer = GrantCardRenderer::getInstance();
            return $renderer->render($post_id, $view);
        }
        
        // フォールバック
        return '<div class="grant-card-error">カードレンダラーが利用できません</div>';
    }
}

/**
 * テーマの最終初期化
 */
function gi_final_init() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Grant Insight: Theme initialized successfully v' . GI_THEME_VERSION);
    }
}
add_action('wp_loaded', 'gi_final_init', 999);

/**
 * クリーンアップ処理
 */
function gi_theme_cleanup() {
    // 不要なオプションの削除
    delete_option('gi_login_attempts');
    delete_option('gi_mobile_cache');
    delete_transient('gi_site_stats_v2');
    
    // オブジェクトキャッシュのフラッシュ（存在する場合のみ）
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}
add_action('switch_theme', 'gi_theme_cleanup');

/**
 * スクリプトにdefer属性を追加（最適化版）
 */
if (!function_exists('gi_add_defer_attribute')) {
    function gi_add_defer_attribute($tag, $handle, $src) {
        // 管理画面では処理しない
        if (is_admin()) {
            return $tag;
        }
        
        // WordPressコアスクリプトは除外
        if (strpos($src, 'wp-includes/js/') !== false) {
            return $tag;
        }
        
        // 既にdefer/asyncがある場合はスキップ
        if (strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }
        
        // 特定のハンドルにのみdeferを追加
        $defer_handles = array(
            'gi-main-js',
            'gi-frontend-js',
            'gi-mobile-enhanced'
        );
        
        if (in_array($handle, $defer_handles)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
}

// フィルターの重複登録を防ぐ
remove_filter('script_loader_tag', 'gi_add_defer_attribute', 10);
add_filter('script_loader_tag', 'gi_add_defer_attribute', 10, 3);

/**
 * モバイル用AJAX - さらに読み込み
 */
function gi_ajax_load_more_grants() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $page = intval($_POST['page'] ?? 1);
    $posts_per_page = 10;
    
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $posts_per_page,
        'post_status' => 'publish',
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        wp_send_json_error('No more posts found');
    }
    
    ob_start();
    
    while ($query->have_posts()): $query->the_post();
        echo gi_render_card(get_the_ID(), 'mobile');
    endwhile;
    
    wp_reset_postdata();
    
    $html = ob_get_clean();
    
    wp_send_json_success([
        'html' => $html,
        'page' => $page,
        'max_pages' => $query->max_num_pages,
        'found_posts' => $query->found_posts
    ]);
}
add_action('wp_ajax_gi_load_more_grants', 'gi_ajax_load_more_grants');
add_action('wp_ajax_nopriv_gi_load_more_grants', 'gi_ajax_load_more_grants');

/**
 * テーマのアクティベーションチェック
 */
function gi_theme_activation_check() {
    // PHP バージョンチェック
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo 'Grant Insight テーマはPHP 7.4以上が必要です。現在のバージョン: ' . PHP_VERSION;
            echo '</p></div>';
        });
    }
    
    // WordPress バージョンチェック
    global $wp_version;
    if (version_compare($wp_version, '5.8', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>';
            echo 'Grant Insight テーマはWordPress 5.8以上を推奨します。';
            echo '</p></div>';
        });
    }
    
    // 必須プラグインチェック（ACFなど）
    if (!class_exists('ACF') && is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info"><p>';
            echo 'Grant Insight テーマの全機能を利用するには、Advanced Custom Fields (ACF) プラグインのインストールを推奨します。';
            echo '</p></div>';
        });
    }
}
add_action('after_setup_theme', 'gi_theme_activation_check');

/**
 * エラーハンドリング用のグローバル関数
 */
if (!function_exists('gi_log_error')) {
    function gi_log_error($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = '[Grant Insight Error] ' . $message;
            if (!empty($context)) {
                $log_message .= ' | Context: ' . print_r($context, true);
            }
            error_log($log_message);
        }
    }
}

/**
 * 削除された機能のCronタスクを無効化
 */
add_action('init', function() {
    $deprecated_cron_hooks = array(
        'giji_auto_import_hook',        // J-Grants (削除済み)
        'gi_excel_auto_export_hook'     // Excel (削除済み)
    );
    
    foreach ($deprecated_cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
});

/**
 * テーマ設定のデフォルト値を取得
 */
if (!function_exists('gi_get_theme_option')) {
    function gi_get_theme_option($option_name, $default = null) {
        $theme_options = get_option('gi_theme_options', array());
        
        if (isset($theme_options[$option_name])) {
            return $theme_options[$option_name];
        }
        
        return $default;
    }
}

/**
 * テーマ設定を保存
 */
if (!function_exists('gi_update_theme_option')) {
    function gi_update_theme_option($option_name, $value) {
        $theme_options = get_option('gi_theme_options', array());
        $theme_options[$option_name] = $value;
        
        return update_option('gi_theme_options', $theme_options);
    }
}

/**
 * テーマのバージョンアップグレード処理
 */
function gi_theme_version_upgrade() {
    $current_version = get_option('gi_installed_version', '0.0.0');
    
    if (version_compare($current_version, GI_THEME_VERSION, '<')) {
        // 9.0.0への統合アップグレード
        if (version_compare($current_version, '9.0.0', '<')) {
            // キャッシュのクリア
            gi_theme_cleanup();
            // URLリライト更新
            flush_rewrite_rules();
        }
        
        // バージョン更新
        update_option('gi_installed_version', GI_THEME_VERSION);
        
        // アップグレード完了通知
        if (is_admin()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo 'Grant Insight テーマが v' . GI_THEME_VERSION . ' (Consolidated Edition) にアップグレードされました。';
                echo '</p></div>';
            });
        }
    }
}
add_action('init', 'gi_theme_version_upgrade');

/**
 * データベーステーブル作成
 */
function gi_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // AI検索履歴テーブル
    $search_history_table = $wpdb->prefix . 'gi_search_history';
    $sql1 = "CREATE TABLE IF NOT EXISTS $search_history_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        search_query text NOT NULL,
        search_filter varchar(50) DEFAULT NULL,
        results_count int(11) DEFAULT 0,
        clicked_results text DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // ユーザー設定テーブル
    $user_preferences_table = $wpdb->prefix . 'gi_user_preferences';
    $sql2 = "CREATE TABLE IF NOT EXISTS $user_preferences_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        preference_key varchar(100) NOT NULL,
        preference_value text DEFAULT NULL,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_preference (user_id, preference_key)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    
    // バージョン管理
    update_option('gi_db_version', '1.0.0');
}

// テーマ有効化時にテーブル作成
add_action('after_switch_theme', 'gi_create_database_tables');

// 既存のインストールでもテーブル作成を確認
add_action('init', function() {
    $db_version = get_option('gi_db_version', '0');
    if (version_compare($db_version, '1.0.0', '<')) {
        gi_create_database_tables();
    }
});

// デバッグ情報の出力
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_footer', function() {
        echo '<!-- Grant Insight: Consolidated version v' . GI_THEME_VERSION . ' loaded successfully -->';
        echo '<!-- Files loaded: ' . count($required_files) . ' -->';
    });
}

/**
 * =============================================================================
 * お問い合わせフォーム処理
 * =============================================================================
 */

/**
 * お問い合わせフォーム送信処理
 */
add_action('wp_ajax_submit_contact_form', 'gi_handle_contact_form');
add_action('wp_ajax_nopriv_submit_contact_form', 'gi_handle_contact_form');

function gi_handle_contact_form() {
    // Nonce検証
    if (!isset($_POST['contact_nonce']) || !wp_verify_nonce($_POST['contact_nonce'], 'contact_form_nonce')) {
        wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
        return;
    }
    
    // データの取得とサニタイズ
    $name = isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '';
    $email = isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
    $company = isset($_POST['contact_company']) ? sanitize_text_field($_POST['contact_company']) : '';
    $subject = isset($_POST['contact_subject']) ? sanitize_text_field($_POST['contact_subject']) : '';
    $message = isset($_POST['contact_message']) ? sanitize_textarea_field($_POST['contact_message']) : '';
    
    // バリデーション
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => '必須項目をすべて入力してください'));
        return;
    }
    
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'メールアドレスの形式が正しくありません'));
        return;
    }
    
    // 管理者メール送信
    $to = get_option('admin_email');
    $email_subject = '[Grant Insight] お問い合わせ: ' . $subject;
    
    $email_body = "【お問い合わせ内容】\n\n";
    $email_body .= "お名前: {$name}\n";
    $email_body .= "メールアドレス: {$email}\n";
    $email_body .= "会社名・団体名: {$company}\n";
    $email_body .= "件名: {$subject}\n\n";
    $email_body .= "お問い合わせ内容:\n";
    $email_body .= $message . "\n\n";
    $email_body .= "---\n";
    $email_body .= "送信日時: " . current_time('Y-m-d H:i:s') . "\n";
    $email_body .= "送信元IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        'Reply-To: ' . $name . ' <' . $email . '>'
    );
    
    // メール送信
    $sent = wp_mail($to, $email_subject, $email_body, $headers);
    
    if ($sent) {
        // 自動返信メール
        $auto_reply_subject = '[Grant Insight] お問い合わせを受け付けました';
        $auto_reply_body = "{$name} 様\n\n";
        $auto_reply_body .= "この度は、Grant Insight Perfectにお問い合わせいただき、誠にありがとうございます。\n\n";
        $auto_reply_body .= "以下の内容でお問い合わせを受け付けました。\n";
        $auto_reply_body .= "2営業日以内にご返信させていただきます。\n\n";
        $auto_reply_body .= "【お問い合わせ内容】\n";
        $auto_reply_body .= "件名: {$subject}\n\n";
        $auto_reply_body .= $message . "\n\n";
        $auto_reply_body .= "---\n";
        $auto_reply_body .= "Grant Insight Perfect\n";
        $auto_reply_body .= get_site_url() . "\n";
        
        $auto_reply_headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        wp_mail($email, $auto_reply_subject, $auto_reply_body, $auto_reply_headers);
        
        wp_send_json_success(array('message' => 'お問い合わせを送信しました'));
    } else {
        wp_send_json_error(array('message' => 'メール送信に失敗しました'));
    }
}
/**
 * 都道府県タームを持つ投稿に、自動的に市町村タームも追加（強化版）
 * 地域制限タイプに応じて適切な市町村タームを設定
 */
add_action('save_post_grant', 'gi_sync_prefecture_to_municipality', 20, 3);
function gi_sync_prefecture_to_municipality($post_id, $post, $update) {
    // 自動保存、リビジョン、自動下書きをスキップ
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // 都道府県タームを取得
    $prefectures = wp_get_post_terms($post_id, 'grant_prefecture', ['fields' => 'all']);
    
    if (!empty($prefectures) && !is_wp_error($prefectures)) {
        // 地域制限タイプを確認
        $regional_limitation = get_post_meta($post_id, 'regional_limitation', true);
        
        $municipality_term_ids = [];
        
        foreach ($prefectures as $prefecture) {
            if ($regional_limitation === 'prefecture_only' || empty($regional_limitation) || $regional_limitation === 'nationwide') {
                // 都道府県レベルの助成金：都道府県名の市町村タームを自動設定
                $pref_level_slug = $prefecture->slug . '-prefecture-level';
                $pref_muni_term = get_term_by('slug', $pref_level_slug, 'grant_municipality');
                
                if (!$pref_muni_term) {
                    // 都道府県レベルの市町村タームを作成
                    $result = wp_insert_term(
                        $prefecture->name,
                        'grant_municipality',
                        [
                            'slug' => $pref_level_slug,
                            'description' => $prefecture->name . '全域対象の助成金'
                        ]
                    );
                    
                    if (!is_wp_error($result)) {
                        $municipality_term_ids[] = $result['term_id'];
                        // メタデータを設定
                        add_term_meta($result['term_id'], 'prefecture_slug', $prefecture->slug);
                        add_term_meta($result['term_id'], 'prefecture_name', $prefecture->name);
                        add_term_meta($result['term_id'], 'is_prefecture_level', '1');
                    }
                } else {
                    $municipality_term_ids[] = $pref_muni_term->term_id;
                    // メタデータがなければ設定
                    if (!get_term_meta($pref_muni_term->term_id, 'prefecture_slug', true)) {
                        add_term_meta($pref_muni_term->term_id, 'prefecture_slug', $prefecture->slug);
                        add_term_meta($pref_muni_term->term_id, 'prefecture_name', $prefecture->name);
                        add_term_meta($pref_muni_term->term_id, 'is_prefecture_level', '1');
                    }
                }
                
                // この都道府県の市町村データが未初期化なら初期化
                gi_ensure_municipalities_for_prefecture($prefecture->slug, $prefecture->name);
                
            } elseif ($regional_limitation === 'municipality_only') {
                // 市町村レベルの助成金：手動選択された市町村のみ保持
                // 自動では何もしない（手動選択を尊重）
                continue;
            }
        }
        
        // 既存の市町村タームとマージ（municipality_onlyの場合は手動選択を保持）
        if (!empty($municipality_term_ids)) {
            $existing_munis = wp_get_post_terms($post_id, 'grant_municipality', ['fields' => 'ids']);
            if (!is_wp_error($existing_munis)) {
                if ($regional_limitation === 'municipality_only') {
                    // 市町村限定の場合、既存の手動選択を優先
                    $manual_munis = array_filter($existing_munis, function($term_id) {
                        $term = get_term($term_id, 'grant_municipality');
                        return $term && !empty($term->parent); // 親がある＝実際の市町村
                    });
                    
                    if (!empty($manual_munis)) {
                        // 手動選択がある場合はそれを優先
                        wp_set_post_terms($post_id, $manual_munis, 'grant_municipality', false);
                    } else {
                        // 手動選択がない場合は都道府県レベルを設定
                        $all_muni_ids = array_unique(array_merge($existing_munis, $municipality_term_ids));
                        wp_set_post_terms($post_id, $all_muni_ids, 'grant_municipality', false);
                    }
                } else {
                    // 都道府県レベルの場合は自動設定とマージ
                    $all_muni_ids = array_unique(array_merge($existing_munis, $municipality_term_ids));
                    wp_set_post_terms($post_id, $all_muni_ids, 'grant_municipality', false);
                }
            } else {
                // 既存がない場合は新規のみセット
                wp_set_post_terms($post_id, $municipality_term_ids, 'grant_municipality', false);
            }
        }
    }
}

/**
 * 指定都道府県の市町村データを確実に初期化
 */
function gi_ensure_municipalities_for_prefecture($prefecture_slug, $prefecture_name) {
    $option_key = 'gi_municipalities_init_' . $prefecture_slug;
    $initialized = get_option($option_key, false);
    
    if (!$initialized) {
        gi_init_municipalities_for_prefecture($prefecture_slug, $prefecture_name);
        update_option($option_key, true);
    }
}

/**
 * 既存の投稿全てに対して都道府県→市町村の同期を実行（一度だけ実行）
 */
add_action('admin_init', 'gi_sync_all_prefecture_to_municipality_once');
function gi_sync_all_prefecture_to_municipality_once() {
    $sync_done = get_option('gi_prefecture_municipality_sync_done', false);
    
    if (!$sync_done) {
        // 全ての助成金投稿を取得
        $grants = get_posts([
            'post_type' => 'grant',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        
        foreach ($grants as $grant_id) {
            gi_sync_prefecture_to_municipality($grant_id, get_post($grant_id), true);
        }
        
        // 完了フラグを保存
        update_option('gi_prefecture_municipality_sync_done', true);
    }
}

/**
 * 市町村データ初期化を強制実行する関数
 */
function gi_force_initialize_municipalities() {
    // 既存のすべての市町村データを削除
    $existing_municipalities = get_terms([
        'taxonomy' => 'grant_municipality',
        'hide_empty' => false,
        'fields' => 'ids'
    ]);
    
    if (!is_wp_error($existing_municipalities)) {
        foreach ($existing_municipalities as $term_id) {
            wp_delete_term($term_id, 'grant_municipality');
        }
    }
    
    // 市町村データを再初期化
    if (function_exists('gi_initialize_all_municipalities')) {
        return gi_initialize_all_municipalities();
    }
    
    return ['success' => false, 'message' => 'gi_initialize_all_municipalities関数が見つかりません'];
}

/**
 * 市町村データ強制初期化AJAXハンドラー
 */
add_action('wp_ajax_gi_force_initialize_municipalities', 'gi_ajax_force_initialize_municipalities');
function gi_ajax_force_initialize_municipalities() {
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'gi_municipality_init_nonce') && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => '権限が不足しています']);
        return;
    }
    
    try {
        $result = gi_force_initialize_municipalities();
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => '市町村データの強制初期化が完了しました',
                'data' => $result
            ]);
        } else {
            wp_send_json_error([
                'message' => '初期化に失敗しました: ' . $result['message']
            ]);
        }
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'エラーが発生しました: ' . $e->getMessage()
        ]);
    }
}

/**
 * 都道府県レベル助成金の市町村を一括修正するAJAX関数
 */
add_action('wp_ajax_gi_bulk_fix_prefecture_municipalities', 'gi_ajax_bulk_fix_prefecture_municipalities');
function gi_ajax_bulk_fix_prefecture_municipalities() {
    // nonce確認
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'gi_bulk_fix_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => '権限が不足しています']);
        return;
    }
    
    try {
        error_log('Bulk Fix Prefecture Municipalities: Starting process');
        
        // 1. 都道府県・市町村データの初期化
        if (function_exists('gi_initialize_all_municipalities')) {
            error_log('Bulk Fix: Calling gi_initialize_all_municipalities');
            $init_result = gi_initialize_all_municipalities();
            error_log('Bulk Fix: Initialization result: ' . json_encode($init_result));
        } else {
            error_log('Bulk Fix: gi_initialize_all_municipalities function not found');
            $init_result = ['success' => false, 'message' => '初期化関数が見つかりません'];
        }
        
        // 2. 都道府県レベル助成金を取得
        error_log('Bulk Fix: Searching for prefecture-level grants');
        $grants_query = new WP_Query([
            'post_type' => 'grant',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'regional_limitation',
                    'value' => ['prefecture_only', 'nationwide', ''],
                    'compare' => 'IN'
                ]
            ]
        ]);
        
        error_log('Bulk Fix: Found ' . $grants_query->found_posts . ' prefecture-level grants');
        
        $fixed_count = 0;
        $error_count = 0;
        $details = [];
        
        if ($grants_query->have_posts()) {
            while ($grants_query->have_posts()) {
                $grants_query->the_post();
                $post_id = get_the_ID();
                $post_title = get_the_title();
                
                // 都道府県を取得
                $prefectures = wp_get_post_terms($post_id, 'grant_prefecture');
                if (!empty($prefectures) && !is_wp_error($prefectures)) {
                    $municipality_term_ids = [];
                    
                    foreach ($prefectures as $prefecture) {
                        // 都道府県レベルの市町村タームを取得または作成
                        $pref_level_slug = $prefecture->slug . '-prefecture-level';
                        $pref_muni_term = get_term_by('slug', $pref_level_slug, 'grant_municipality');
                        
                        if (!$pref_muni_term) {
                            // 都道府県レベルの市町村タームを作成
                            $result = wp_insert_term(
                                $prefecture->name,
                                'grant_municipality',
                                [
                                    'slug' => $pref_level_slug,
                                    'description' => $prefecture->name . '全域対象の助成金'
                                ]
                            );
                            
                            if (!is_wp_error($result)) {
                                $municipality_term_ids[] = $result['term_id'];
                                // メタデータ設定
                                add_term_meta($result['term_id'], 'prefecture_slug', $prefecture->slug);
                                add_term_meta($result['term_id'], 'prefecture_name', $prefecture->name);
                                add_term_meta($result['term_id'], 'is_prefecture_level', '1');
                            }
                        } else {
                            $municipality_term_ids[] = $pref_muni_term->term_id;
                            // メタデータがなければ追加
                            if (!get_term_meta($pref_muni_term->term_id, 'prefecture_slug', true)) {
                                add_term_meta($pref_muni_term->term_id, 'prefecture_slug', $prefecture->slug);
                                add_term_meta($pref_muni_term->term_id, 'prefecture_name', $prefecture->name);
                                add_term_meta($pref_muni_term->term_id, 'is_prefecture_level', '1');
                            }
                        }
                    }
                    
                    // 市町村を設定
                    if (!empty($municipality_term_ids)) {
                        $existing_munis = wp_get_post_terms($post_id, 'grant_municipality', ['fields' => 'ids']);
                        if (!is_wp_error($existing_munis)) {
                            $all_muni_ids = array_unique(array_merge($existing_munis, $municipality_term_ids));
                            wp_set_post_terms($post_id, $all_muni_ids, 'grant_municipality', false);
                            $fixed_count++;
                            $details[] = "✅ {$post_title} - 市町村設定完了";
                        } else {
                            wp_set_post_terms($post_id, $municipality_term_ids, 'grant_municipality', false);
                            $fixed_count++;
                            $details[] = "✅ {$post_title} - 市町村新規設定";
                        }
                    } else {
                        $error_count++;
                        $details[] = "❌ {$post_title} - 市町村タームの作成に失敗";
                    }
                } else {
                    $error_count++;
                    $details[] = "❌ {$post_title} - 都道府県タームが見つかりません";
                }
            }
            wp_reset_postdata();
        }
        
        error_log("Bulk Fix: Completed - Fixed: {$fixed_count}, Errors: {$error_count}");
        
        wp_send_json_success([
            'message' => "一括修正完了: 修正 {$fixed_count} 件, エラー {$error_count} 件",
            'fixed_count' => $fixed_count,
            'error_count' => $error_count,
            'initialization' => $init_result,
            'details' => array_slice($details, 0, 10) // 最初の10件のみ
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => '一括修正中にエラーが発生しました: ' . $e->getMessage()
        ]);
    }
}
