<?php
/**
 * Grant Archive Template - Clean & Professional Edition v9.0
 * File: archive-grant.php
 * 
 * シンプルでスタイリッシュな信頼感のあるデザイン
 * 機能はそのまま、デザインをクリーンに刷新
 * 
 * @package Grant_Insight_Clean
 * @version 9.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// 必要な関数の存在確認
$required_functions = [
    'gi_safe_get_meta',
    'gi_get_formatted_deadline',
    'gi_map_application_status_ui',
    'gi_get_user_favorites',
    'gi_get_grant_amount_display'
];

// URLパラメータから検索条件を取得（両方のパラメータ名に対応）
$search_params = [
    'search' => sanitize_text_field($_GET['s'] ?? ''),
    'category' => sanitize_text_field($_GET['category'] ?? $_GET['grant_category'] ?? ''),
    'prefecture' => sanitize_text_field($_GET['prefecture'] ?? ''),
    'municipality' => sanitize_text_field($_GET['municipality'] ?? ''),
    'region' => sanitize_text_field($_GET['region'] ?? ''),
    'amount' => sanitize_text_field($_GET['amount'] ?? ''),
    'status' => sanitize_text_field($_GET['status'] ?? ''),
    'difficulty' => sanitize_text_field($_GET['difficulty'] ?? ''),
    'success_rate' => sanitize_text_field($_GET['success_rate'] ?? ''),
    'application_method' => sanitize_text_field($_GET['method'] ?? ''),
    'is_featured' => sanitize_text_field($_GET['featured'] ?? ''),
    'sort' => sanitize_text_field($_GET['sort'] ?? 'date_desc'),
    'view' => sanitize_text_field($_GET['view'] ?? 'grid'),
    'page' => max(1, intval($_GET['paged'] ?? 1))
];

// 統計データ取得
$stats = function_exists('gi_get_cached_stats') ? gi_get_cached_stats() : [
    'total_grants' => wp_count_posts('grant')->publish ?? 0,
    'active_grants' => 0,
    'prefecture_count' => count(get_terms(['taxonomy' => 'grant_prefecture', 'hide_empty' => true])),
    'avg_success_rate' => 65
];

// お気に入りリスト取得
$user_favorites = function_exists('gi_get_user_favorites_cached') ? 
    gi_get_user_favorites_cached() : 
    (function_exists('gi_get_user_favorites') ? gi_get_user_favorites() : []);

// 初期表示用クエリの構築
$initial_args = [
    'post_type' => 'grant',
    'posts_per_page' => 12,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC',
    'no_found_rows' => false
];

// 検索条件の適用
if (!empty($search_params['search'])) {
    $initial_args['s'] = $search_params['search'];
}

// タクソノミークエリ
$tax_query = ['relation' => 'AND'];
if (!empty($search_params['category'])) {
    $tax_query[] = [
        'taxonomy' => 'grant_category',
        'field' => 'slug',
        'terms' => explode(',', $search_params['category'])
    ];
}
// 都道府県フィルター
if (!empty($search_params['prefecture'])) {
    $tax_query[] = [
        'taxonomy' => 'grant_prefecture',
        'field' => 'slug',
        'terms' => explode(',', $search_params['prefecture'])
    ];
}
// 市町村フィルター
if (!empty($search_params['municipality'])) {
    $tax_query[] = [
        'taxonomy' => 'grant_municipality',
        'field' => 'slug',
        'terms' => explode(',', $search_params['municipality'])
    ];
}
if (count($tax_query) > 1) {
    $initial_args['tax_query'] = $tax_query;
}

// メタクエリ
$meta_query = ['relation' => 'AND'];

if (!empty($search_params['status'])) {
    $statuses = explode(',', $search_params['status']);
    $db_statuses = array_map(function($s) {
        return $s === 'active' ? 'open' : ($s === 'upcoming' ? 'upcoming' : $s);
    }, $statuses);
    
    $meta_query[] = [
        'key' => 'application_status',
        'value' => $db_statuses,
        'compare' => 'IN'
    ];
}

if (!empty($search_params['is_featured']) && $search_params['is_featured'] === '1') {
    $meta_query[] = [
        'key' => 'is_featured',
        'value' => '1',
        'compare' => '='
    ];
}

if (count($meta_query) > 1) {
    $initial_args['meta_query'] = $meta_query;
}

// ソート処理
switch($search_params['sort']) {
    case 'amount_desc':
        $initial_args['orderby'] = 'meta_value_num';
        $initial_args['meta_key'] = 'max_amount_numeric';
        $initial_args['order'] = 'DESC';
        break;
    case 'featured_first':
        $initial_args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
        $initial_args['meta_key'] = 'is_featured';
        break;
    default:
        $initial_args['orderby'] = 'date';
        $initial_args['order'] = 'DESC';
}

// クエリ実行
$grants_query = new WP_Query($initial_args);

// タクソノミー取得
$all_categories = get_terms([
    'taxonomy' => 'grant_category',
    'hide_empty' => true,  // 0件のカテゴリを非表示
    'orderby' => 'count',
    'order' => 'DESC'
]);

// 都道府県タクソノミー取得
$all_prefectures = get_terms([
    'taxonomy' => 'grant_prefecture',
    'hide_empty' => true,
    'orderby' => 'name',
    'order' => 'ASC'
]);

// 市町村タクソノミー取得
$all_municipalities = [];
if (!empty($search_params['prefecture'])) {
    // 選択された都道府県の市町村のみ取得
    $prefecture_slugs = explode(',', $search_params['prefecture']);
    foreach ($prefecture_slugs as $pref_slug) {
        $pref_municipalities = get_terms([
            'taxonomy' => 'grant_municipality',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'parent_prefecture',
                    'value' => $pref_slug,
                    'compare' => '='
                ]
            ]
        ]);
        if (!is_wp_error($pref_municipalities)) {
            $all_municipalities = array_merge($all_municipalities, $pref_municipalities);
        }
    }
} else {
    // すべての市町村を取得（制限付き）
    $all_municipalities = get_terms([
        'taxonomy' => 'grant_municipality',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 50  // 最大50件
    ]);
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700;800&display=swap" as="style">
    
    <!-- Critical CSS -->
    <style>
    /* ===== Clean & Professional Design System - Monochrome Edition ===== */
    :root {
        /* Core Colors - Stylish Monochrome */
        --primary: #000000;
        --primary-light: #262626;
        --primary-dark: #000000;
        --secondary: #525252;
        --accent: #171717;
        
        /* Neutral Colors - Monochrome Palette */
        --white: #ffffff;
        --gray-50: #fafafa;
        --gray-100: #f5f5f5;
        --gray-200: #e5e5e5;
        --gray-300: #d4d4d4;
        --gray-400: #a3a3a3;
        --gray-500: #737373;
        --gray-600: #525252;
        --gray-700: #404040;
        --gray-800: #262626;
        --gray-900: #171717;
        
        /* Semantic Colors */
        --success: #22c55e;
        --warning: #f59e0b;
        --danger: #ef4444;
        --info: #000000;
        
        /* Typography */
        --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        --font-japanese: 'Noto Sans JP', 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', 'Yu Gothic Medium', 'Meiryo', sans-serif;
        
        /* Spacing */
        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.25rem;
        --space-6: 1.5rem;
        --space-8: 2rem;
        --space-10: 2.5rem;
        --space-12: 3rem;
        --space-16: 4rem;
        --space-20: 5rem;
        
        /* Border Radius */
        --radius-sm: 0.25rem;
        --radius-md: 0.375rem;
        --radius-lg: 0.5rem;
        --radius-xl: 0.75rem;
        --radius-2xl: 1rem;
        
        /* Shadows */
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        
        /* Transitions */
        --transition: all 0.15s ease-in-out;
        --transition-slow: all 0.3s ease-in-out;
    }
    
    /* Reset & Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    html {
        font-size: 16px;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    body {
        font-family: var(--font-primary);
        color: var(--gray-900);
        background-color: var(--gray-50);
        font-weight: 400;
    }
    
    /* Container */
    .clean-container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 var(--space-4);
    }
    
    @media (min-width: 768px) {
        .clean-container {
            padding: 0 var(--space-6);
        }
    }
    
    /* ===== HEADER SECTION ===== */
    .clean-header {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        padding: var(--space-8) 0;
    }
    
    .clean-header-content {
        text-align: center;
        max-width: 800px;
        margin: 0 auto;
    }
    
    .clean-title {
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: var(--space-4);
        letter-spacing: -0.025em;
    }
    
    .clean-subtitle {
        font-size: 1.125rem;
        color: var(--gray-600);
        font-weight: 400;
        max-width: 600px;
        margin: 0 auto;
    }
    
    /* ===== SEARCH SECTION ===== */
    .clean-search-section {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        padding: var(--space-6) 0;
    }
    
    .clean-search-wrapper {
        max-width: 600px;
        margin: 0 auto var(--space-6);
    }
    
    .clean-search-box {
        position: relative;
    }
    
    .clean-search-input {
        width: 100%;
        padding: var(--space-4) var(--space-4) var(--space-4) 3rem;
        border: 2px solid var(--gray-300);
        border-radius: var(--radius-lg);
        font-size: 1rem;
        font-weight: 400;
        background: var(--white);
        transition: var(--transition);
    }
    
    .clean-search-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
    }
    
    .clean-search-input::placeholder {
        color: var(--gray-500);
    }
    
    .clean-search-icon {
        position: absolute;
        left: var(--space-4);
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        font-size: 1rem;
    }
    
    .clean-search-clear {
        position: absolute;
        right: var(--space-4);
        top: 50%;
        transform: translateY(-50%);
        width: 1.75rem;
        height: 1.75rem;
        border: none;
        background: var(--gray-300);
        color: var(--gray-600);
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
        font-size: 0.75rem;
    }
    
    .clean-search-clear:hover {
        background: var(--danger);
        color: var(--white);
    }
    
    /* Quick Filters */
    .clean-filters {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
        justify-content: center;
    }
    
    .clean-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-3) var(--space-4);
        background: var(--gray-100);
        color: var(--gray-700);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-2xl);
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        white-space: nowrap;
        position: relative;
        overflow: hidden;
    }
    
    .clean-filter-pill::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s ease;
    }
    
    .clean-filter-pill:hover {
        background: var(--gray-200);
        border-color: var(--gray-300);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }
    
    .clean-filter-pill:hover::before {
        left: 100%;
    }
    
    .clean-filter-pill.active {
        background: var(--primary);
        color: var(--white);
        border-color: var(--primary);
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }
    
    .clean-filter-pill:focus {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }
    
    .clean-filter-pill i {
        font-size: 0.75rem;
    }
    
    .clean-filter-count {
        background: rgba(255, 255, 255, 0.2);
        color: inherit;
        padding: 0 var(--space-2);
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
        min-width: 1.25rem;
        text-align: center;
    }
    
    .clean-filter-pill:not(.active) .clean-filter-count {
        background: var(--primary);
        color: var(--white);
    }
    
    /* ===== CONTROLS SECTION ===== */
    .clean-controls {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        padding: var(--space-4) 0;
    }
    
    .clean-controls-inner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--space-4);
        flex-wrap: wrap;
    }
    
    .clean-controls-left,
    .clean-controls-right {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }
    
    .clean-select {
        padding: var(--space-2) var(--space-4);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        background: var(--white);
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 150px;
    }
    
    .clean-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
    }
    
    .clean-filter-button {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-4);
        background: var(--white);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        color: var(--gray-700);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .clean-filter-button:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .clean-filter-button.has-filters {
        background: var(--primary);
        color: var(--white);
        border-color: var(--primary);
    }
    
    .clean-view-toggle {
        display: flex;
        background: var(--gray-100);
        border-radius: var(--radius-md);
        padding: 2px;
    }
    
    .clean-view-btn {
        padding: var(--space-2) var(--space-3);
        background: transparent;
        border: none;
        color: var(--gray-600);
        cursor: pointer;
        border-radius: calc(var(--radius-md) - 2px);
        transition: var(--transition);
        font-size: 0.875rem;
    }
    
    .clean-view-btn:hover {
        color: var(--gray-800);
    }
    
    .clean-view-btn.active {
        background: var(--white);
        color: var(--primary);
        box-shadow: var(--shadow-sm);
    }
    
    /* ===== MAIN LAYOUT ===== */
    .clean-main {
        padding: var(--space-8) 0;
        min-height: 50vh;
    }
    
    .clean-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: var(--space-8);
        align-items: start;
    }
    
    /* ===== SIDEBAR ===== */
    .clean-sidebar {
        position: sticky;
        top: 120px;
        max-height: calc(100vh - 140px);
        overflow-y: auto;
        padding-right: var(--space-2);
    }
    
    /* サイドバーのスクロールバー */
    .clean-sidebar::-webkit-scrollbar {
        width: 8px;
    }
    
    .clean-sidebar::-webkit-scrollbar-track {
        background: var(--gray-100);
        border-radius: var(--radius-md);
    }
    
    .clean-sidebar::-webkit-scrollbar-thumb {
        background: var(--gray-400);
        border-radius: var(--radius-md);
        transition: var(--transition);
    }
    
    .clean-sidebar::-webkit-scrollbar-thumb:hover {
        background: var(--gray-600);
    }
    
    /* Firefox scrollbar */
    .clean-sidebar {
        scrollbar-width: thin;
        scrollbar-color: var(--gray-400) var(--gray-100);
    }
    
    .clean-filter-card {
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        overflow: hidden;
    }
    
    .clean-filter-header {
        background: var(--gray-50);
        padding: var(--space-4);
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .clean-filter-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-900);
        margin: 0;
    }
    
    .clean-filter-close {
        display: none;
        width: 2rem;
        height: 2rem;
        border: none;
        background: var(--gray-200);
        color: var(--gray-600);
        cursor: pointer;
        border-radius: 50%;
        transition: var(--transition);
    }
    
    .clean-filter-close:hover {
        background: var(--gray-300);
    }
    
    .clean-filter-body {
        padding: var(--space-4);
        max-height: calc(100vh - 200px);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--gray-400) var(--gray-100);
    }
    
    .clean-filter-body::-webkit-scrollbar {
        width: 6px;
    }
    
    .clean-filter-body::-webkit-scrollbar-track {
        background: var(--gray-100);
        border-radius: var(--radius-sm);
    }
    
    .clean-filter-body::-webkit-scrollbar-thumb {
        background: var(--gray-400);
        border-radius: var(--radius-sm);
        transition: var(--transition);
    }
    
    .clean-filter-body::-webkit-scrollbar-thumb:hover {
        background: var(--gray-600);
    }
    
    .clean-filter-group {
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--gray-200);
    }
    
    .clean-filter-group:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .clean-filter-group-title {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: var(--space-3);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .clean-filter-option {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2);
        cursor: pointer;
        border-radius: var(--radius-md);
        transition: var(--transition);
    }
    
    .clean-filter-option:hover {
        background: var(--gray-50);
    }
    
    .clean-filter-checkbox,
    .clean-filter-radio {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
        cursor: pointer;
    }
    
    .clean-filter-label {
        flex: 1;
        font-size: 0.875rem;
        color: var(--gray-700);
        font-weight: 400;
        cursor: pointer;
    }
    
    .clean-filter-count {
        background: var(--gray-100);
        color: var(--gray-600);
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0 var(--space-2);
        border-radius: var(--radius-sm);
        min-width: 1.25rem;
        text-align: center;
    }
    
    /* ===== RESULTS HEADER ===== */
    .clean-results-header {
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-6);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .clean-results-info {
        display: flex;
        align-items: baseline;
        gap: var(--space-2);
    }
    
    .clean-results-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-900);
    }
    
    .clean-results-text {
        font-size: 0.875rem;
        color: var(--gray-600);
        font-weight: 500;
    }
    
    .clean-loading-indicator {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--gray-600);
        font-size: 0.875rem;
    }
    
    .clean-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid var(--gray-300);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* ===== GRANTS GRID ===== */
    .clean-grants-container {
        position: relative;
        min-height: 400px;
    }
    
    .clean-grants-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: var(--space-6);
        margin-bottom: var(--space-8);
    }
    
    .clean-grants-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
        margin-bottom: var(--space-8);
    }
    
    /* Grant Card */
    .clean-grant-card {
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        overflow: hidden;
        transition: var(--transition-slow);
        cursor: pointer;
    }
    
    .clean-grant-card:hover {
        border-color: var(--gray-300);
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }
    
    .clean-grant-card-header {
        padding: var(--space-4);
        border-bottom: 1px solid var(--gray-200);
    }
    
    .clean-grant-card-body {
        padding: var(--space-4);
    }
    
    .clean-grant-card-footer {
        padding: var(--space-3) var(--space-4);
        background: var(--gray-50);
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    /* ===== PAGINATION ===== */
    .clean-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: var(--space-1);
        margin-top: var(--space-8);
    }
    
    .clean-page-btn {
        min-width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--white);
        border: 1px solid var(--gray-300);
        color: var(--gray-700);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border-radius: var(--radius-md);
        transition: var(--transition);
        text-decoration: none;
    }
    
    .clean-page-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .clean-page-btn.current {
        background: var(--primary);
        color: var(--white);
        border-color: var(--primary);
    }
    
    /* ===== NO RESULTS ===== */
    .clean-no-results {
        text-align: center;
        padding: var(--space-12);
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
    }
    
    .clean-no-results-icon {
        font-size: 3rem;
        color: var(--gray-400);
        margin-bottom: var(--space-4);
    }
    
    .clean-no-results-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: var(--space-2);
    }
    
    .clean-no-results-text {
        color: var(--gray-600);
        margin-bottom: var(--space-6);
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .clean-reset-button {
        padding: var(--space-3) var(--space-6);
        background: var(--primary);
        color: var(--white);
        border: none;
        border-radius: var(--radius-md);
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .clean-reset-button:hover {
        background: var(--primary-dark);
    }
    
    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 1024px) {
        .clean-layout {
            grid-template-columns: 1fr;
        }
        
        .clean-sidebar {
            position: static;
            margin-bottom: var(--space-6);
        }
        
        .clean-grants-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-3);
        }
    }
    
    @media (max-width: 768px) {
        .clean-container {
            padding: 0 var(--space-4);
        }
        
        .clean-title {
            font-size: 1.875rem;
        }
        
        .clean-subtitle {
            font-size: 1rem;
        }
        
        .clean-search-section {
            padding: var(--space-4) 0;
        }
        
        .clean-filters {
            justify-content: flex-start;
            overflow-x: auto;
            padding-bottom: var(--space-2);
        }
        
        .clean-controls-inner {
            flex-direction: column;
            align-items: stretch;
        }
        
        .clean-controls-left,
        .clean-controls-right {
            width: 100%;
            justify-content: space-between;
        }
        
        .clean-view-toggle {
            display: none;
        }
        
        .clean-grants-grid {
            grid-template-columns: 1fr;
            gap: var(--space-4);
        }
        
        /* Mobile Sidebar */
        .clean-sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--white);
            z-index: 1000;
            transition: left 0.3s ease;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch; /* iOS smooth scrolling */
            overscroll-behavior: contain;
            max-height: 100vh;
        }
        
        .clean-filter-body {
            max-height: calc(100vh - 120px);
            padding-bottom: var(--space-8); /* 底部余白确保内容可以完全滚动 */
        }
        
        .clean-filter-list-container {
            max-height: 240px; /* モバイルでは少し小さく */
        }
        
        .clean-sidebar.active {
            left: 0;
        }
        
        .clean-filter-close {
            display: flex;
        }
    }
    
    @media (max-width: 480px) {
        .clean-header {
            padding: var(--space-6) 0;
        }
        
        .clean-title {
            font-size: 1.5rem;
        }
        
        .clean-subtitle {
            font-size: 0.9375rem;
        }
        
        .clean-search-input {
            padding: var(--space-3) var(--space-3) var(--space-3) 2.5rem;
            font-size: 0.9375rem;
        }
        
        .clean-search-icon {
            left: var(--space-3);
        }
        
        .clean-search-clear {
            right: var(--space-3);
        }
        
        .clean-filter-pill {
            font-size: 0.8125rem;
            padding: var(--space-2) var(--space-3);
        }
    }
    
    /* ===== LOADING OVERLAY ===== */
    .clean-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.95);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: var(--radius-lg);
    }
    
    .clean-loading-overlay .clean-spinner {
        width: 2rem;
        height: 2rem;
    }
    
    /* ===== UTILITY CLASSES ===== */
    .clean-hidden {
        display: none !important;
    }
    
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    
    .clean-filter-count-active {
        animation: pulse 0.5s ease-in-out;
    }
    
    /* ===== FILTER MORE BUTTON ===== */
    .clean-filter-more-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 0.875rem;
        transition: var(--transition);
        padding: var(--space-2);
        border-radius: var(--radius-md);
        width: 100%;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        color: var(--primary);
        font-weight: 500;
    }
    
    .clean-filter-more-btn:hover {
        background: var(--gray-50);
        color: var(--primary-dark);
    }
    
    .clean-filter-more-item {
        transition: opacity 0.3s ease, max-height 0.3s ease;
    }
    
    .clean-filter-more-item.hidden {
        display: none;
    }
    
    /* ===== FOCUS STYLES ===== */
    button:focus-visible,
    input:focus-visible,
    select:focus-visible,
    a:focus-visible {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }
    
    /* ===== FILTER ACTIONS ===== */
    .clean-filter-actions {
        padding: var(--space-4) 0;
        border-top: 1px solid var(--gray-200);
        margin-top: var(--space-4);
    }
    
    .clean-reset-button {
        width: 100%;
        padding: var(--space-3) var(--space-4);
        background: var(--gray-100);
        color: var(--gray-700);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
    }
    
    .clean-reset-button:hover {
        background: var(--gray-200);
        border-color: var(--gray-400);
    }
    
    .clean-reset-button:active {
        transform: translateY(1px);
    }
    
    /* ===== LOADING STATES ===== */
    .clean-filter-loading {
        opacity: 0.6;
        pointer-events: none;
        position: relative;
    }
    
    .clean-filter-loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 16px;
        height: 16px;
        border: 2px solid var(--gray-300);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        transform: translate(-50%, -50%);
    }
    
    /* ===== ENHANCED ANIMATIONS ===== */
    .clean-filter-pill {
        animation: fadeInUp 0.3s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .clean-grant-card {
        animation: slideIn 0.4s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* ===== ENHANCED RESPONSIVE DESIGN ===== */
    @media (max-width: 640px) {
        .clean-filters {
            gap: var(--space-2);
            padding: var(--space-2) 0;
        }
        
        .clean-filter-pill {
            font-size: 0.8125rem;
            padding: var(--space-2) var(--space-3);
        }
        
        .clean-filter-pill i {
            font-size: 0.6875rem;
        }
        
        .clean-controls-inner {
            gap: var(--space-3);
        }
    }
    
    /* ===== ACCESSIBILITY ENHANCEMENTS ===== */
    @media (prefers-reduced-motion: reduce) {
        .clean-filter-pill,
        .clean-grant-card,
        .clean-loading-overlay .clean-spinner {
            animation: none;
        }
        
        .clean-filter-pill::before {
            display: none;
        }
        
        * {
            transition: none !important;
        }
    }
    
    @media (prefers-color-scheme: dark) {
        :root {
            --gray-50: #0f0f0f;
            --gray-100: #1a1a1a;
            --gray-200: #2a2a2a;
            --gray-300: #3a3a3a;
            --white: #1f1f1f;
        }
    }
    
    /* ===== FILTER COUNT INDICATOR ===== */
    .clean-filter-count {
        background: var(--primary);
        color: var(--white);
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0 var(--space-2);
        border-radius: var(--radius-sm);
        min-width: 1.25rem;
        text-align: center;
        animation: pulse 0.5s ease-in-out;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .clean-filter-pill.active .clean-filter-count {
        background: rgba(255, 255, 255, 0.2);
    }
    
    /* ===== ENHANCED FILTER LIST CONTAINER WITH SMOOTH SCROLL ===== */
    .clean-filter-list-container {
        max-height: 280px;
        overflow-y: auto;
        margin-bottom: var(--space-3);
        padding-right: var(--space-1);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-md);
        background: var(--white);
        scroll-behavior: smooth;
        overscroll-behavior: contain;
    }
    
    .clean-filter-list-container::-webkit-scrollbar {
        width: 8px;
    }
    
    .clean-filter-list-container::-webkit-scrollbar-track {
        background: var(--gray-50);
        border-radius: var(--radius-md);
        margin: 4px 0;
    }
    
    .clean-filter-list-container::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, var(--gray-400) 0%, var(--gray-500) 100%);
        border-radius: var(--radius-md);
        border: 2px solid var(--gray-50);
        transition: all 0.3s ease;
    }
    
    .clean-filter-list-container::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, var(--gray-600) 0%, var(--gray-700) 100%);
        border-color: var(--white);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .clean-filter-list-container::-webkit-scrollbar-thumb:active {
        background: var(--gray-800);
    }
    
    /* Firefox scrollbar */
    .clean-filter-list-container {
        scrollbar-width: thin;
        scrollbar-color: var(--gray-400) var(--gray-50);
    }
    
    /* スクロール位置インジケーター */
    .clean-filter-list-container {
        position: relative;
    }
    
    .clean-filter-list-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 8px;
        height: 20px;
        background: linear-gradient(180deg, var(--white) 0%, transparent 100%);
        pointer-events: none;
        z-index: 2;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .clean-filter-list-container::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 8px;
        height: 20px;
        background: linear-gradient(0deg, var(--white) 0%, transparent 100%);
        pointer-events: none;
        z-index: 2;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .clean-filter-list-container.has-scroll-top::before {
        opacity: 1;
    }
    
    .clean-filter-list-container.has-scroll-bottom::after {
        opacity: 1;
    }
    
    /* Scroll fade indicators */
    .clean-filter-group {
        position: relative;
    }
    
    .clean-filter-group::after {
        content: '';
        position: absolute;
        bottom: 60px;
        left: 0;
        right: 20px;
        height: 20px;
        background: linear-gradient(transparent, var(--white));
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .clean-filter-group.has-scroll::after {
        opacity: 1;
    }
    
    /* Enhanced filter options for better scrolling */
    .clean-filter-option {
        padding: var(--space-3) var(--space-2);
        margin-bottom: 2px;
        border-radius: var(--radius-md);
    }
    
    .clean-filter-option:hover {
        background: var(--gray-50);
    }
    
    /* ===== 検索フィールド用スタイル ===== */
    .clean-filter-search-input {
        width: 100%;
        padding: var(--space-2) var(--space-3);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 400;
        background: var(--white);
        transition: var(--transition);
        padding-right: 2.5rem;
    }
    
    .clean-filter-search-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
    }
    
    .category-search-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .filter-search-clear {
        position: absolute;
        right: 0.5rem;
        background: none;
        border: none;
        color: var(--gray-500);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 50%;
        width: 1.5rem;
        height: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        transition: var(--transition);
    }
    
    .filter-search-clear:hover {
        background: var(--gray-200);
        color: var(--gray-700);
    }
    
    .filter-search-toggle {
        margin-left: auto;
        cursor: pointer;
        padding: 0.25rem;
        color: var(--gray-600);
        border-radius: 50%;
        width: 1.5rem;
        height: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
        font-size: 0.75rem;
    }
    
    .filter-search-toggle:hover {
        background: var(--gray-200);
        color: var(--primary);
    }
    
    /* ===== カーセンサー風上部フィルターバー ===== */
    .top-filter-bar {
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-6);
        box-shadow: var(--shadow-sm);
    }
    
    .top-filter-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: var(--space-3);
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }
    
    .top-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--space-4);
        align-items: start;
    }
    
    .top-filter-column {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }
    
    .top-filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--gray-700);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: var(--space-1);
    }
    
    .top-filter-select {
        padding: var(--space-2) var(--space-3);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        background: var(--white);
        font-size: 0.875rem;
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
    }
    
    .top-filter-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
    }
    
    .top-filter-buttons {
        display: flex;
        gap: var(--space-2);
        margin-top: var(--space-3);
    }
    
    .top-filter-btn {
        padding: var(--space-2) var(--space-4);
        background: var(--gray-100);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        color: var(--gray-700);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .top-filter-btn:hover {
        background: var(--gray-200);
        border-color: var(--gray-400);
    }
    
    .top-filter-btn.active {
        background: var(--primary);
        color: var(--white);
        border-color: var(--primary);
    }
    
    .top-filter-apply-btn {
        background: var(--primary);
        color: var(--white);
        border: 1px solid var(--primary);
        font-weight: 600;
    }
    
    .top-filter-apply-btn:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    
    /* Prefecture and Municipality Filter Styling */
    .prefecture-filter-group,
    .municipality-filter-group {
        background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
        border-radius: var(--radius-xl);
        padding: var(--space-5);
        margin-bottom: var(--space-6);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        border: 1px solid var(--gray-200);
    }
    
    .prefecture-option,
    .municipality-option {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: var(--radius-md);
        margin-bottom: 2px;
    }
    
    .prefecture-option:hover,
    .municipality-option:hover {
        background: var(--gray-50);
        transform: translateX(4px);
    }
    
    .prefecture-option input:checked + .clean-filter-label,
    .municipality-option input:checked + .clean-filter-label {
        font-weight: 700;
        color: var(--primary);
    }
    
    .prefecture-search-wrapper,
    .municipality-search-wrapper {
        position: relative;
        display: none;
        align-items: center;
        gap: 0.5rem;
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-md);
        padding: var(--space-1);
        margin-bottom: var(--space-3);
    }
    
    .loading-municipality-message {
        text-align: center;
        padding: var(--space-8) var(--space-4);
        color: var(--gray-600);
        font-size: 0.875rem;
    }
    
    .loading-municipality-message i {
        margin-right: var(--space-2);
        color: var(--primary);
    }
    
    .filter-sub-title {
        font-size: 0.6875rem;
        font-weight: 700;
        color: var(--gray-700);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: var(--space-3);
        margin-top: var(--space-4);
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }
    
    .filter-hint {
        font-size: 0.625rem;
        font-weight: 400;
        color: var(--gray-500);
        text-transform: none;
        letter-spacing: normal;
        margin-left: auto;
    }
    
    .no-municipality-message {
        padding: var(--space-3);
        text-align: center;
        color: var(--gray-500);
        font-size: 0.75rem;
        background: var(--gray-50);
        border-radius: var(--radius-md);
        margin-top: var(--space-2);
    }
    
    .filter-sub-title::before {
        content: '';
        width: 3px;
        height: 12px;
        background: var(--primary);
        border-radius: 2px;
    }
    
    /* Prefecture and municipality styling removed per user request */
    
    /* Region highlighting removed */
    
    /* アニメーション */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Prefecture and Municipality Animations */
    .prefecture-option,
    .municipality-option {
        animation: slideIn 0.2s ease-out;
    }
    
    .prefecture-filter-group,
    .municipality-filter-group {
        animation: fadeInUp 0.3s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* レスポンシブ対応 */
    @media (max-width: 1024px) {
        .top-filter-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-3);
        }
    }
    
    @media (max-width: 768px) {
        /* Prefecture and Municipality Responsive Styling */
    @media (max-width: 768px) {
        .prefecture-filter-group,
        .municipality-filter-group {
            padding: var(--space-4);
        }
        
        .prefecture-search-wrapper,
        .municipality-search-wrapper {
            margin-bottom: var(--space-2);
        }
        
        .filter-sub-title {
            font-size: 0.625rem;
        }
        
        .top-filter-grid {
            grid-template-columns: 1fr;
            gap: var(--space-2);
        }
        
        .top-filter-column {
            width: 100%;
        }
    }
        
        .top-filter-bar {
            padding: var(--space-3);
            margin-bottom: var(--space-4);
        }
        
        .top-filter-grid {
            grid-template-columns: 1fr;
            gap: var(--space-3);
        }
        
        .top-filter-buttons {
            flex-direction: column;
            gap: var(--space-2);
        }
        
        .top-filter-btn {
            width: 100%;
            justify-content: center;
        }
        
        /* 検索フィールドのモバイル最適化 */
        .clean-filter-search-input {
            font-size: 16px; /* iOS zoom 防止 */
        }
        
        .filter-search-toggle {
            padding: 0.5rem;
            width: 2rem;
            height: 2rem;
        }
    }
    
    @media (max-width: 480px) {
        .top-filter-title {
            font-size: 0.8125rem;
        }
        
        .top-filter-select {
            font-size: 0.8125rem;
            padding: var(--space-2);
        }
        
        .top-filter-btn {
            font-size: 0.8125rem;
            padding: var(--space-2) var(--space-3);
        }
    }
    
    /* ===== UX向上：ホバーアニメーション ===== */
    .clean-filter-option:hover {
        background: var(--gray-50);
        transform: translateX(2px);
    }
    
    .clean-filter-option input:checked + .clean-filter-label {
        font-weight: 600;
        color: var(--primary);
    }
    
    /* アイコン付きフィルタータイトルのスタイル */
    .clean-filter-group-title i {
        color: var(--primary);
        margin-right: 0.5rem;
        font-size: 0.875rem;
    }
    
    .filter-sub-title i {
        color: var(--primary);
        margin-right: 0.25rem;
        font-size: 0.75rem;
    }
    
    /* ===== 白黒スタイリッシュ：カテゴリフィルター ===== */
    .category-filter-group {
        background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
        border-radius: var(--radius-xl);
        padding: var(--space-5);
        margin-bottom: var(--space-6);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        border: 1px solid var(--gray-200);
    }
    
    .category-grid-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: var(--space-3);
        margin-bottom: var(--space-3);
    }
    
    .category-chip-fa {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: var(--space-4);
        background: var(--white);
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: center;
        min-height: 100px;
        position: relative;
        overflow: hidden;
    }
    
    /* ホバー時のグラデーションエフェクト */
    .category-chip-fa::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, 
            transparent, 
            rgba(0, 0, 0, 0.02), 
            transparent
        );
        transition: left 0.6s ease;
    }
    
    .category-chip-fa:hover::before {
        left: 100%;
    }
    
    /* 通常状態のホバー */
    .category-chip-fa:hover {
        border-color: var(--gray-900);
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }
    
    .category-chip-fa:hover .category-icon-wrapper {
        transform: scale(1.1);
    }
    
    /* 選択状態 */
    .category-chip-fa.selected {
        background: linear-gradient(135deg, var(--gray-900) 0%, var(--black) 100%);
        border-color: var(--gray-900);
        color: var(--white);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }
    
    .category-chip-fa.selected::before {
        background: linear-gradient(90deg, 
            transparent, 
            rgba(255, 255, 255, 0.1), 
            transparent
        );
    }
    
    /* アイコンラッパー */
    .category-icon-wrapper {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--gray-100);
        border-radius: 50%;
        margin-bottom: var(--space-3);
        transition: all 0.3s ease;
    }
    
    .category-chip-fa.selected .category-icon-wrapper {
        background: rgba(255, 255, 255, 0.15);
        animation: pulse 0.6s ease;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.15); }
    }
    
    /* Font Awesome アイコン */
    .category-fa-icon {
        font-size: 1.5rem;
        color: var(--gray-900);
        transition: all 0.3s ease;
    }
    
    .category-chip-fa.selected .category-fa-icon {
        color: var(--white);
    }
    
    /* カテゴリ名 */
    .category-chip-fa .category-name {
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.3;
        margin-bottom: var(--space-1);
        color: var(--gray-900);
        transition: all 0.3s ease;
    }
    
    .category-chip-fa.selected .category-name {
        color: var(--white);
        font-weight: 700;
    }
    
    /* カウントバッジ */
    .category-count-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: var(--gray-900);
        color: var(--white);
        font-size: 0.6875rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 12px;
        min-width: 24px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .category-chip-fa.selected .category-count-badge {
        background: rgba(255, 255, 255, 0.25);
        color: var(--white);
        backdrop-filter: blur(4px);
    }
    
    /* フォーカス状態（アクセシビリティ） */
    .category-chip-fa:focus-within {
        outline: 3px solid var(--gray-900);
        outline-offset: 2px;
    }
    
    /* AI検索ヒント */
    .ai-search-hint {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* レスポンシブ: カテゴリグリッド */
    @media (max-width: 1024px) {
        .category-grid-container {
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: var(--space-2);
        }
    }
    
    @media (max-width: 768px) {
        .category-filter-group {
            padding: var(--space-4);
        }
        
        .category-grid-container {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: var(--space-2);
        }
        
        .category-chip-fa {
            min-height: 85px;
            padding: var(--space-3);
        }
        
        .category-icon-wrapper {
            width: 40px;
            height: 40px;
            margin-bottom: var(--space-2);
        }
        
        .category-fa-icon {
            font-size: 1.25rem;
        }
        
        .category-chip-fa .category-name {
            font-size: 0.8125rem;
        }
        
        .category-count-badge {
            top: 6px;
            right: 6px;
            font-size: 0.625rem;
            padding: 2px 6px;
        }
    }
    
    @media (max-width: 480px) {
        .category-grid-container {
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: var(--space-1);
        }
        
        .category-chip-fa {
            min-height: 75px;
            padding: var(--space-2);
        }
        
        .category-icon-wrapper {
            width: 36px;
            height: 36px;
        }
        
        .category-fa-icon {
            font-size: 1.125rem;
        }
        
        .category-chip-fa .category-name {
            font-size: 0.75rem;
        }
    }
    </style>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body <?php body_class('clean-archive-page'); ?>>

<!-- Header Section -->
<section class="clean-header">
    <div class="clean-container">
        <div class="clean-header-content">
            <h1 class="clean-title">助成金・補助金検索</h1>
            <p class="clean-subtitle">
                <?php 
                if (!empty($search_params['search']) || !empty($search_params['category'])) {
                    echo '検索条件に該当する助成金・補助金を表示しています。最適な制度を見つけてビジネスを成長させましょう。';
                } else {
                    echo '全国の助成金・補助金を簡単検索。あなたにピッタリの制度を見つけてビジネスの成長を支援します。';
                }
                ?>
            </p>
        </div>
    </div>
</section>

<!-- Search Section -->
<section class="clean-search-section">
    <div class="clean-container">
        <!-- Search Box -->
        <div class="clean-search-wrapper">
            <div class="clean-search-box">
                <input type="text" 
                       id="clean-search-input" 
                       name="search" 
                       placeholder="🤖 AI検索：「DXを推進したい」「環境に優しい事業」など自然な言葉で検索..." 
                       class="clean-search-input"
                       value="<?php echo esc_attr($search_params['search']); ?>"
                       autocomplete="off">
                <button type="button" id="clean-search-clear" class="clean-search-clear" <?php echo empty($search_params['search']) ? 'style="display:none"' : ''; ?>>
                    ×
                </button>
            </div>
            <div class="ai-search-hint" style="text-align: center; margin-top: 0.5rem; font-size: 0.75rem; color: var(--gray-600);">
                AI機能強化検索：意図を理解して最適な補助金を提案します
            </div>
        </div>

        <!-- Quick Filters -->
        <div class="clean-filters" role="group" aria-label="クイックフィルター">
            <button class="clean-filter-pill <?php echo empty($search_params['status']) && empty($search_params['is_featured']) && empty($search_params['amount']) ? 'active' : ''; ?>" data-filter="all" aria-pressed="<?php echo empty($search_params['status']) && empty($search_params['is_featured']) && empty($search_params['amount']) ? 'true' : 'false'; ?>">
                すべて
            </button>
            <button class="clean-filter-pill <?php echo $search_params['is_featured'] === '1' ? 'active' : ''; ?>" data-filter="featured" aria-pressed="<?php echo $search_params['is_featured'] === '1' ? 'true' : 'false'; ?>">
                おすすめ
            </button>
            <button class="clean-filter-pill <?php echo $search_params['status'] === 'active' ? 'active' : ''; ?>" data-filter="active" aria-pressed="<?php echo $search_params['status'] === 'active' ? 'true' : 'false'; ?>">
                募集中
                <?php if ($stats['active_grants'] > 0): ?>
                <span class="clean-filter-count" aria-label="<?php echo $stats['active_grants']; ?>件"><?php echo $stats['active_grants']; ?></span>
                <?php endif; ?>
            </button>
            <button class="clean-filter-pill <?php echo $search_params['amount'] === '1000-3000' || $search_params['amount'] === '3000+' ? 'active' : ''; ?>" data-filter="large-amount" aria-pressed="<?php echo $search_params['amount'] === '1000-3000' || $search_params['amount'] === '3000+' ? 'true' : 'false'; ?>">
                高額助成金
            </button>
            <button class="clean-filter-pill <?php echo $search_params['amount'] === '0-100' ? 'active' : ''; ?>" data-filter="small-medium" aria-pressed="<?php echo $search_params['amount'] === '0-100' ? 'true' : 'false'; ?>">
                中小規模
            </button>
            <button class="clean-filter-pill" data-filter="upcoming" aria-pressed="false">
                募集予定
            </button>
            <button class="clean-filter-pill" data-filter="deadline-soon" aria-pressed="false">
                締切間近
            </button>
            
            <!-- 提案6: AI Filter Optimization Button -->
            <button class="clean-filter-pill ai-optimize-filter-btn" onclick="openFilterOptimization()" aria-pressed="false" style="background: linear-gradient(135deg, #000 0%, #1a1a1a 100%); color: #fff; border: 2px solid #000;">
                AI最適化
            </button>
        </div>
    </div>
</section>

<!-- Controls Section -->
<section class="clean-controls">
    <div class="clean-container">
        <div class="clean-controls-inner">
            <div class="clean-controls-left">
                <select id="clean-sort-select" class="clean-select">
                    <option value="date_desc" <?php selected($search_params['sort'], 'date_desc'); ?>>新着順</option>
                    <option value="featured_first" <?php selected($search_params['sort'], 'featured_first'); ?>>おすすめ順</option>
                    <option value="amount_desc" <?php selected($search_params['sort'], 'amount_desc'); ?>>金額が高い順</option>
                    <option value="deadline_asc" <?php selected($search_params['sort'], 'deadline_asc'); ?>>締切が近い順</option>
                    <option value="success_rate_desc" <?php selected($search_params['sort'], 'success_rate_desc'); ?>>採択率順</option>
                </select>

                <button id="clean-filter-toggle" class="clean-filter-button" aria-expanded="false" aria-controls="clean-filter-sidebar">
                    詳細フィルター
                    <span id="clean-filter-count" class="clean-filter-count" style="display:none" aria-label="フィルター適用数">0</span>
                </button>
                
                <button id="clean-clear-all-filters" class="clean-filter-button" style="display:none" aria-label="すべてのフィルターをクリア">
                    クリア
                </button>
            </div>

            <div class="clean-controls-right">
                <div class="clean-view-toggle">
                    <button id="clean-grid-view" 
                            class="clean-view-btn <?php echo $search_params['view'] === 'grid' ? 'active' : ''; ?>" 
                            data-view="grid" 
                            title="グリッド表示">
                        グリッド
                    </button>
                    <button id="clean-list-view" 
                            class="clean-view-btn <?php echo $search_params['view'] === 'list' ? 'active' : ''; ?>" 
                            data-view="list" 
                            title="リスト表示">
                        リスト
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Top Filter Bar (カーセンサー風) -->
<section class="clean-main-filters">
    <div class="clean-container">
        <div class="top-filter-bar">
            <h3 class="top-filter-title">
                <i class="fas fa-filter"></i>
                詳細検索条件
            </h3>
            <div class="top-filter-grid">
                <div class="top-filter-column">
                    <label class="top-filter-label">カテゴリ</label>
                    <select class="top-filter-select" id="top-category-select">
                        <option value="">すべてのカテゴリ</option>
                        <?php if (!empty($all_categories) && !is_wp_error($all_categories)): ?>
                            <?php foreach (array_slice($all_categories, 0, 10) as $category): ?>
                            <option value="<?php echo esc_attr($category->slug); ?>" 
                                    <?php selected(in_array($category->slug, explode(',', $search_params['category']))); ?>>
                                <?php echo esc_html($category->name); ?> (<?php echo $category->count; ?>)
                            </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="top-filter-column">
                    <label class="top-filter-label">都道府県</label>
                    <select class="top-filter-select" id="top-prefecture-select">
                        <option value="">全国</option>
                        <?php if (!empty($all_prefectures) && !is_wp_error($all_prefectures)): ?>
                            <?php foreach ($all_prefectures as $prefecture): ?>
                            <option value="<?php echo esc_attr($prefecture->slug); ?>" 
                                    <?php selected(in_array($prefecture->slug, explode(',', $search_params['prefecture']))); ?>>
                                <?php echo esc_html($prefecture->name); ?> (<?php echo $prefecture->count; ?>)
                            </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="top-filter-column">
                    <label class="top-filter-label">市町村</label>
                    <select class="top-filter-select" id="top-municipality-select">
                        <option value="">都道府県を選択してください</option>
                        <?php if (!empty($all_municipalities) && !is_wp_error($all_municipalities)): ?>
                            <?php foreach ($all_municipalities as $municipality): ?>
                            <option value="<?php echo esc_attr($municipality->slug); ?>" 
                                    <?php selected(in_array($municipality->slug, explode(',', $search_params['municipality']))); ?>>
                                <?php echo esc_html($municipality->name); ?> (<?php echo $municipality->count; ?>)
                            </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="top-filter-column">
                    <label class="top-filter-label">助成金額</label>
                    <select class="top-filter-select" id="top-amount-select">
                        <option value="">金額指定なし</option>
                        <option value="0-100" <?php selected($search_params['amount'], '0-100'); ?>>〜100万円</option>
                        <option value="100-500" <?php selected($search_params['amount'], '100-500'); ?>>100〜500万円</option>
                        <option value="500-1000" <?php selected($search_params['amount'], '500-1000'); ?>>500〜1000万円</option>
                        <option value="1000-3000" <?php selected($search_params['amount'], '1000-3000'); ?>>1000〜3000万円</option>
                        <option value="3000+" <?php selected($search_params['amount'], '3000+'); ?>>3000万円以上</option>
                    </select>
                </div>
                <div class="top-filter-column">
                    <label class="top-filter-label">募集状況</label>
                    <select class="top-filter-select" id="top-status-select">
                        <option value="">すべての状況</option>
                        <option value="active" <?php selected($search_params['status'], 'active'); ?>>募集中</option>
                        <option value="upcoming" <?php selected($search_params['status'], 'upcoming'); ?>>募集予定</option>
                        <option value="closed" <?php selected($search_params['status'], 'closed'); ?>>募集終了</option>
                    </select>
                </div>
            </div>
            <div class="top-filter-buttons">
                <button type="button" class="top-filter-btn top-filter-apply-btn" onclick="applyTopFilters()">
                    <i class="fas fa-search"></i> この条件で検索
                </button>
                <button type="button" class="top-filter-btn" onclick="clearTopFilters()">
                    <i class="fas fa-undo"></i> クリア
                </button>
                <button type="button" class="top-filter-btn <?php echo $search_params['is_featured'] === '1' ? 'active' : ''; ?>" 
                        onclick="toggleFeaturedFilter()" id="top-featured-btn">
                    <i class="fas fa-star"></i> おすすめのみ
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<section class="clean-main">
    <div class="clean-container">
        <div class="clean-layout">
            
            <!-- Sidebar Filters -->
            <aside id="clean-filter-sidebar" class="clean-sidebar" role="complementary" aria-labelledby="filter-title">
                <div class="clean-filter-card">
                    <div class="clean-filter-header">
                        <h3 id="filter-title" class="clean-filter-title">詳細フィルター</h3>
                        <button id="clean-filter-close" class="clean-filter-close" aria-label="フィルターパネルを閉じる">
                            ×
                        </button>
                    </div>

                    <div class="clean-filter-body">
                        
                        <!-- Special Filters -->
                        <div class="clean-filter-group">
                            <h4 class="clean-filter-group-title">特別条件</h4>
                            <label class="clean-filter-option">
                                <input type="checkbox" 
                                       name="is_featured" 
                                       value="1" 
                                       class="clean-filter-checkbox featured-checkbox"
                                       <?php checked($search_params['is_featured'], '1'); ?>>
                                <span class="clean-filter-label">おすすめの助成金のみ</span>
                            </label>
                        </div>

                        <!-- Prefecture Filters -->
                        <?php if (!empty($all_prefectures) && !is_wp_error($all_prefectures)): ?>
                        <div class="clean-filter-group prefecture-filter-group">
                            <h4 class="clean-filter-group-title">
                                <i class="fas fa-map-marker-alt"></i>
                                都道府県
                                <span class="filter-search-toggle" onclick="togglePrefectureSearch()" title="都道府県検索">
                                    <i class="fas fa-search"></i>
                                </span>
                            </h4>
                            
                            <!-- 都道府県検索フィールド -->
                            <div class="prefecture-search-wrapper" id="prefecture-search-wrapper" style="display: none; margin-bottom: 1rem;">
                                <input type="text" id="prefecture-search-input" placeholder="都道府県名で検索..." 
                                       class="clean-filter-search-input" onkeyup="filterPrefectures(this.value)">
                                <button type="button" class="filter-search-clear" onclick="clearPrefectureSearch()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <!-- 都道府県リスト -->
                            <div class="clean-filter-list-container" style="max-height: 320px;">
                                <?php 
                                $prefecture_limit = 10;
                                $selected_prefectures = explode(',', $search_params['prefecture']);
                                $prefecture_count = count($all_prefectures);
                                
                                $has_selected_pref = !empty(array_filter($selected_prefectures));
                                $show_all_pref_initially = $has_selected_pref;
                                
                                foreach ($all_prefectures as $index => $prefecture): 
                                    $is_selected_pref = in_array($prefecture->slug, $selected_prefectures);
                                    $is_hidden = !$show_all_pref_initially && $index >= $prefecture_limit;
                                ?>
                                <label class="clean-filter-option prefecture-option <?php echo $is_hidden ? 'clean-filter-more-item hidden' : ''; ?>" 
                                       data-prefecture-name="<?php echo esc_attr(strtolower($prefecture->name)); ?>" 
                                       title="<?php echo esc_attr($prefecture->name); ?>">
                                    <input type="checkbox" 
                                           name="prefectures[]" 
                                           value="<?php echo esc_attr($prefecture->slug); ?>" 
                                           class="clean-filter-checkbox prefecture-checkbox"
                                           <?php checked($is_selected_pref); ?>>
                                    <span class="clean-filter-label"><?php echo esc_html($prefecture->name); ?></span>
                                    <?php if ($prefecture->count > 0): ?>
                                    <span class="clean-filter-count"><?php echo esc_html($prefecture->count); ?></span>
                                    <?php endif; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($prefecture_count > $prefecture_limit): ?>
                            <button type="button" class="clean-filter-more-btn" data-target="prefecture">
                                <span class="show-more-text <?php echo $show_all_pref_initially ? 'hidden' : ''; ?>">
                                    <i class="fas fa-chevron-down"></i> さらに表示 (+<?php echo $prefecture_count - $prefecture_limit; ?>)
                                </span>
                                <span class="show-less-text <?php echo !$show_all_pref_initially ? 'hidden' : ''; ?>">
                                    <i class="fas fa-chevron-up"></i> 表示を減らす
                                </span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Municipality Filters -->
                        <div class="clean-filter-group municipality-filter-group">
                            <h4 class="clean-filter-group-title">
                                <i class="fas fa-building"></i>
                                市町村
                                <span class="filter-search-toggle" onclick="toggleMunicipalitySearch()" title="市町村検索">
                                    <i class="fas fa-search"></i>
                                </span>
                            </h4>
                            
                            <!-- 市町村検索フィールド -->
                            <div class="municipality-search-wrapper" id="municipality-search-wrapper" style="display: none; margin-bottom: 1rem;">
                                <input type="text" id="municipality-search-input" placeholder="市町村名で検索..." 
                                       class="clean-filter-search-input" onkeyup="filterMunicipalities(this.value)">
                                <button type="button" class="filter-search-clear" onclick="clearMunicipalitySearch()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="filter-sub-title">
                                <i class="fas fa-info-circle"></i>
                                都道府県を選択すると市町村が表示されます
                                <span class="filter-hint">都道府県選択後に表示</span>
                            </div>
                            
                            <!-- 市町村リスト -->
                            <div class="clean-filter-list-container municipality-list" id="municipality-list-container" style="max-height: 280px;">
                                <?php if (!empty($all_municipalities) && !is_wp_error($all_municipalities)): ?>
                                    <?php 
                                    $selected_municipalities = explode(',', $search_params['municipality']);
                                    foreach ($all_municipalities as $municipality): 
                                        $is_selected_muni = in_array($municipality->slug, $selected_municipalities);
                                    ?>
                                    <label class="clean-filter-option municipality-option" 
                                           data-municipality-name="<?php echo esc_attr(strtolower($municipality->name)); ?>" 
                                           title="<?php echo esc_attr($municipality->name); ?>">
                                        <input type="checkbox" 
                                               name="municipalities[]" 
                                               value="<?php echo esc_attr($municipality->slug); ?>" 
                                               class="clean-filter-checkbox municipality-checkbox"
                                               <?php checked($is_selected_muni); ?>>
                                        <span class="clean-filter-label"><?php echo esc_html($municipality->name); ?></span>
                                        <?php if ($municipality->count > 0): ?>
                                        <span class="clean-filter-count"><?php echo esc_html($municipality->count); ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-municipality-message">
                                        都道府県を選択すると、該当する市町村が表示されます。
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Category Filters (白黒スタイリッシュ - チェックボックス形式) -->
                        <?php if (!empty($all_categories) && !is_wp_error($all_categories)): ?>
                        <div class="clean-filter-group category-filter-group">
                            <h4 class="clean-filter-group-title">
                                <i class="fas fa-tags" style="margin-right: 0.5rem;"></i>
                                カテゴリ
                                <span class="filter-search-toggle" onclick="toggleCategorySearch()" title="カテゴリ検索">
                                    <i class="fas fa-search"></i>
                                </span>
                            </h4>
                            
                            <!-- カテゴリ検索フィールド -->
                            <div class="category-search-wrapper" id="category-search-wrapper" style="display: none; margin-bottom: 1rem;">
                                <input type="text" id="category-search-input" placeholder="カテゴリ名で検索..." 
                                       class="clean-filter-search-input" onkeyup="filterCategories(this.value)">
                                <button type="button" class="filter-search-clear" onclick="clearCategorySearch()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <!-- カテゴリリスト（チェックボックス形式） -->
                            <div class="clean-filter-list-container" style="max-height: 320px;">
                                <?php 
                                $category_limit = 12;
                                $selected_categories = explode(',', $search_params['category']);
                                $category_count = count($all_categories);
                                
                                $has_selected_cat = !empty(array_filter($selected_categories));
                                $show_all_cat_initially = $has_selected_cat;
                                
                                foreach ($all_categories as $index => $category): 
                                    $is_selected_cat = in_array($category->slug, $selected_categories);
                                    $is_hidden = !$show_all_cat_initially && $index >= $category_limit;
                                ?>
                                <label class="clean-filter-option category-option <?php echo $is_hidden ? 'clean-filter-more-item hidden' : ''; ?>" 
                                       data-category-name="<?php echo esc_attr(strtolower($category->name)); ?>" 
                                       title="<?php echo esc_attr($category->name); ?>">
                                    <input type="checkbox" 
                                           name="categories[]" 
                                           value="<?php echo esc_attr($category->slug); ?>" 
                                           class="clean-filter-checkbox category-checkbox"
                                           <?php checked($is_selected_cat); ?>>
                                    <span class="clean-filter-label"><?php echo esc_html($category->name); ?></span>
                                    <?php if ($category->count > 0): ?>
                                    <span class="clean-filter-count"><?php echo esc_html($category->count); ?></span>
                                    <?php endif; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($category_count > $category_limit): ?>
                            <button type="button" class="clean-filter-more-btn" data-target="category">
                                <span class="show-more-text <?php echo $show_all_cat_initially ? 'hidden' : ''; ?>">
                                    <i class="fas fa-chevron-down"></i> さらに表示 (+<?php echo $category_count - $category_limit; ?>)
                                </span>
                                <span class="show-less-text <?php echo !$show_all_cat_initially ? 'hidden' : ''; ?>">
                                    <i class="fas fa-chevron-up"></i> 表示を減らす
                                </span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Amount Filters -->
                        <div class="clean-filter-group">
                            <h4 class="clean-filter-group-title">助成金額</h4>
                            <?php
                            $amount_ranges = [
                                '' => 'すべての金額',
                                '0-100' => '〜100万円',
                                '100-500' => '100〜500万円',
                                '500-1000' => '500〜1000万円',
                                '1000-3000' => '1000〜3000万円',
                                '3000+' => '3000万円以上'
                            ];
                            foreach ($amount_ranges as $value => $label):
                            ?>
                            <label class="clean-filter-option" tabindex="0">
                                <input type="radio" 
                                       name="amount" 
                                       value="<?php echo esc_attr($value); ?>" 
                                       class="clean-filter-radio amount-radio"
                                       <?php checked($search_params['amount'], $value); ?>
                                       aria-describedby="amount-<?php echo esc_attr($value ?: 'all'); ?>-desc">
                                <span class="clean-filter-label" id="amount-<?php echo esc_attr($value ?: 'all'); ?>-desc"><?php echo esc_html($label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Status Filters -->
                        <div class="clean-filter-group">
                            <h4 class="clean-filter-group-title">募集状況</h4>
                            <?php
                            $status_options = [
                                'active' => '募集中',
                                'upcoming' => '募集予定',
                                'closed' => '募集終了'
                            ];
                            foreach ($status_options as $value => $label):
                                $selected_statuses = explode(',', $search_params['status']);
                                $is_selected = in_array($value, $selected_statuses);
                            ?>
                            <label class="clean-filter-option" tabindex="0">
                                <input type="checkbox" 
                                       name="status[]" 
                                       value="<?php echo esc_attr($value); ?>" 
                                       class="clean-filter-checkbox status-checkbox"
                                       <?php checked($is_selected); ?>
                                       aria-describedby="status-<?php echo esc_attr($value); ?>-desc">
                                <span class="clean-filter-label" id="status-<?php echo esc_attr($value); ?>-desc"><?php echo esc_html($label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Application Method Filters -->
                        <div class="clean-filter-group">
                            <h4 class="clean-filter-group-title">申請方法</h4>
                            <label class="clean-filter-option" tabindex="0">
                                <input type="checkbox" 
                                       name="application_method[]" 
                                       value="online" 
                                       class="clean-filter-checkbox method-checkbox"
                                       aria-describedby="method-online-desc">
                                <span class="clean-filter-label" id="method-online-desc">
                                    オンライン申請
                                </span>
                            </label>
                            <label class="clean-filter-option" tabindex="0">
                                <input type="checkbox" 
                                       name="application_method[]" 
                                       value="mail" 
                                       class="clean-filter-checkbox method-checkbox"
                                       aria-describedby="method-mail-desc">
                                <span class="clean-filter-label" id="method-mail-desc">
                                    郵送申請
                                </span>
                            </label>
                            <label class="clean-filter-option" tabindex="0">
                                <input type="checkbox" 
                                       name="application_method[]" 
                                       value="direct" 
                                       class="clean-filter-checkbox method-checkbox"
                                       aria-describedby="method-direct-desc">
                                <span class="clean-filter-label" id="method-direct-desc">
                                    持参申請
                                </span>
                            </label>
                        </div>

                        <!-- Reset Filters -->
                        <div class="clean-filter-actions">
                            <button type="button" id="clean-reset-filters" class="clean-reset-button">
                                フィルターをリセット
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="clean-content">
                <!-- Results Header -->
                <div class="clean-results-header">
                    <div class="clean-results-info">
                        <span id="clean-results-count" class="clean-results-number"><?php echo number_format($grants_query->found_posts); ?></span>
                        <span class="clean-results-text">件の助成金</span>
                    </div>
                    <div id="clean-loading" class="clean-loading-indicator clean-hidden">
                        <div class="clean-spinner"></div>
                        <span>更新中...</span>
                    </div>
                </div>

                <!-- Grants Container -->
                <div id="clean-grants-container" class="clean-grants-container">
                    <div id="clean-grants-display">
                        <?php if ($grants_query->have_posts()): ?>
                            <div class="<?php echo $search_params['view'] === 'grid' ? 'clean-grants-grid' : 'clean-grants-list'; ?>">
                                <?php
                                while ($grants_query->have_posts()):
                                    $grants_query->the_post();
                                    
                                    $GLOBALS['current_view'] = $search_params['view'];
                                    $GLOBALS['user_favorites'] = $user_favorites;
                                    
                                    get_template_part('template-parts/grant-card-unified');
                                endwhile;
                                ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($grants_query->max_num_pages > 1): ?>
                            <div class="clean-pagination">
                                <?php
                                $pagination_args = [
                                    'total' => $grants_query->max_num_pages,
                                    'current' => max(1, $search_params['page']),
                                    'format' => '?paged=%#%',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'type' => 'array',
                                    'end_size' => 2,
                                    'mid_size' => 2
                                ];
                                
                                $pagination_links = paginate_links($pagination_args);
                                if ($pagination_links) {
                                    foreach ($pagination_links as $link) {
                                        $link = str_replace('class="page-numbers', 'class="clean-page-btn page-numbers', $link);
                                        $link = str_replace('class="clean-page-btn page-numbers current', 'class="clean-page-btn page-numbers current', $link);
                                        echo $link;
                                    }
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="clean-no-results">
                                <div class="clean-no-results-icon">
                                    ×
                                </div>
                                <h3 class="clean-no-results-title">該当する助成金が見つかりませんでした</h3>
                                <p class="clean-no-results-text">検索条件を変更して再度お試しください。</p>
                                <button id="clean-reset-search" class="clean-reset-button">
                                    検索をリセット
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <?php wp_reset_postdata(); ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</section>

<!-- JavaScript -->
<script>
/**
 * Clean Grant Archive JavaScript
 * シンプルでクリーンなデザイン用の機能
 */
(function() {
    'use strict';
    
    // 重複実行防止
    if (window.CleanGrantsInitialized) {
        console.log('CleanGrants already initialized, skipping...');
        return;
    }
    window.CleanGrantsInitialized = true;
    
    // Configuration
    const config = {
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('gi_ajax_nonce'); ?>',
        debounceDelay: 300,
        searchDelay: 500
    };
    
    // State Management
    const state = {
        currentView: '<?php echo $search_params['view']; ?>',
        currentPage: <?php echo $search_params['page']; ?>,
        isLoading: false,
        filters: {
            search: '<?php echo esc_js($search_params['search']); ?>',
            categories: <?php echo json_encode(array_filter(explode(',', $search_params['category']))); ?>,
            prefectures: <?php echo json_encode(array_filter(explode(',', $search_params['prefecture']))); ?>,
            municipalities: <?php echo json_encode(array_filter(explode(',', $search_params['municipality']))); ?>,
            region: '<?php echo esc_js($search_params['region']); ?>',
            amount: '<?php echo esc_js($search_params['amount']); ?>',
            status: <?php echo json_encode(array_filter(explode(',', $search_params['status']))); ?>,
            is_featured: '<?php echo esc_js($search_params['is_featured']); ?>',
            sort: '<?php echo esc_js($search_params['sort']); ?>'
        }
    };
    
    // DOM Elements
    const elements = {};
    
    // Timers
    let debounceTimer = null;
    let searchTimer = null;
    
    /**
     * Initialize
     */
    function init() {
        cacheElements();
        bindEvents();
        updateFilterCount();
        initializeCardInteractions();
    }
    
    /**
     * Cache DOM elements
     */
    function cacheElements() {
        const ids = [
            'clean-search-input', 'clean-search-clear',
            'clean-sort-select', 'clean-filter-toggle', 'clean-filter-sidebar',
            'clean-filter-close', 'clean-grid-view', 'clean-list-view',
            'clean-reset-search', 'clean-results-count', 'clean-loading',
            'clean-grants-container', 'clean-grants-display', 'clean-filter-count'
        ];
        
        ids.forEach(id => {
            elements[id.replace(/-/g, '_')] = document.getElementById(id);
        });
        
        elements.quickFilters = document.querySelectorAll('.clean-filter-pill');
        elements.filterCheckboxes = document.querySelectorAll('.clean-filter-checkbox');
        elements.filterRadios = document.querySelectorAll('.clean-filter-radio');
    }
    
    /**
     * Bind events
     */
    function bindEvents() {
        // Search
        if (elements.clean_search_input) {
            elements.clean_search_input.addEventListener('input', handleSearchInput);
            elements.clean_search_input.addEventListener('keypress', handleSearchKeypress);
        }
        
        if (elements.clean_search_clear) {
            elements.clean_search_clear.addEventListener('click', handleSearchClear);
        }
        
        // Sort
        if (elements.clean_sort_select) {
            elements.clean_sort_select.addEventListener('change', handleSortChange);
        }
        
        // Filter toggle
        if (elements.clean_filter_toggle) {
            elements.clean_filter_toggle.addEventListener('click', toggleFilterSidebar);
        }
        
        if (elements.clean_filter_close) {
            elements.clean_filter_close.addEventListener('click', closeFilterSidebar);
        }
        
        // View switcher
        if (elements.clean_grid_view) {
            elements.clean_grid_view.addEventListener('click', () => switchView('grid'));
        }
        
        if (elements.clean_list_view) {
            elements.clean_list_view.addEventListener('click', () => switchView('list'));
        }
        
        // Quick filters
        elements.quickFilters.forEach(filter => {
            filter.addEventListener('click', handleQuickFilter);
        });
        
        // Filter inputs
        [...elements.filterCheckboxes, ...elements.filterRadios].forEach(input => {
            input.addEventListener('change', handleFilterChange);
        });
        
        // Reset
        if (elements.clean_reset_search) {
            elements.clean_reset_search.addEventListener('click', resetAllFilters);
        }
        
        // Clear all filters button
        const clearAllBtn = document.getElementById('clean-clear-all-filters');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', resetAllFilters);
        }
        
        // Reset filters button in sidebar
        const resetFiltersBtn = document.getElementById('clean-reset-filters');
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', resetAllFilters);
        }
        
        // Pagination
        document.addEventListener('click', handlePaginationClick);
        
        // 都道府県フィルターのイベントリスナー
        document.querySelectorAll('.prefecture-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                handleFilterChange();
                // 都道府県選択変更時に市町村を更新
                loadMunicipalitiesForSidebarFilter();
            });
        });
        
        // 市町村フィルターのイベントリスナー
        document.querySelectorAll('.municipality-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleFilterChange);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts);
        
        // More filters toggle
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.clean-filter-more-btn');
            if (btn) {
                handleMoreFiltersToggle(e);
            }
        });
        
        // Initialize scroll indicators
        initScrollIndicators();
        
        // Initialize mobile scroll enhancements
        initMobileScrollEnhancements();
    }
    
    /**
     * Handle search input
     */
    function handleSearchInput(e) {
        state.filters.search = e.target.value;
        elements.clean_search_clear.style.display = e.target.value ? 'block' : 'none';
        
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            loadGrants();
        }, config.searchDelay);
    }
    
    /**
     * Handle search keypress
     */
    function handleSearchKeypress(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadGrants();
        }
    }
    
    /**
     * Handle search clear
     */
    function handleSearchClear() {
        elements.clean_search_input.value = '';
        elements.clean_search_clear.style.display = 'none';
        state.filters.search = '';
        loadGrants();
    }
    
    /**
     * Handle sort change
     */
    function handleSortChange(e) {
        state.filters.sort = e.target.value;
        state.currentPage = 1;
        loadGrants();
    }
    
    /**
     * Handle quick filter
     */
    function handleQuickFilter(e) {
        const filter = e.currentTarget.dataset.filter;
        
        elements.quickFilters.forEach(f => f.classList.remove('active'));
        e.currentTarget.classList.add('active');
        
        resetFiltersState();
        
        switch(filter) {
            case 'featured':
                state.filters.is_featured = '1';
                break;
            case 'active':
                state.filters.status = ['active'];
                break;
            case 'large-amount':
                state.filters.amount = '1000-3000';
                break;
            case 'small-medium':
                state.filters.amount = '0-100';
                break;
            case 'upcoming':
                state.filters.status = ['upcoming'];
                break;
            case 'deadline-soon':
                // 締切間近のロジックを追加（例：1週間以内）
                state.filters.deadline_range = '1week';
                break;
            default:
                break;
        }
        
        state.currentPage = 1;
        updateFilterCount();
        loadGrants();
    }
    
    /**
     * Handle filter change
     */
    function handleFilterChange() {
        updateFiltersFromForm();
        updateFilterCount();
        
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            state.currentPage = 1;
            loadGrants();
        }, config.debounceDelay);
    }
    
    /**
     * Update filters from form
     */
    function updateFiltersFromForm() {
        state.filters.categories = Array.from(
            document.querySelectorAll('.category-checkbox:checked')
        ).map(cb => cb.value);
        
        state.filters.prefectures = Array.from(
            document.querySelectorAll('.prefecture-checkbox:checked')
        ).map(cb => cb.value);
        
        state.filters.municipalities = Array.from(
            document.querySelectorAll('.municipality-checkbox:checked')
        ).map(cb => cb.value);
        
        const featuredCheckbox = document.querySelector('.featured-checkbox:checked');
        state.filters.is_featured = featuredCheckbox ? '1' : '';
        
        const amountRadio = document.querySelector('.amount-radio:checked');
        state.filters.amount = amountRadio ? amountRadio.value : '';
    }
    
    /**
     * Update filter count
     */
    function updateFilterCount() {
        const count = 
            state.filters.categories.length +
            state.filters.prefectures.length +
            state.filters.municipalities.length +
            (state.filters.amount ? 1 : 0) +
            state.filters.status.length +
            (state.filters.is_featured ? 1 : 0);
        
        if (elements.clean_filter_count) {
            elements.clean_filter_count.textContent = count;
            elements.clean_filter_count.style.display = count > 0 ? 'inline-block' : 'none';
            elements.clean_filter_count.setAttribute('aria-label', `${count}個のフィルターが適用中`);
        }
        
        if (elements.clean_filter_toggle) {
            elements.clean_filter_toggle.classList.toggle('has-filters', count > 0);
            elements.clean_filter_toggle.setAttribute('aria-expanded', 
                elements.clean_filter_sidebar?.classList.contains('active') ? 'true' : 'false');
        }
        
        // Clear all button visibility
        const clearAllBtn = document.getElementById('clean-clear-all-filters');
        if (clearAllBtn) {
            clearAllBtn.style.display = count > 0 ? 'inline-flex' : 'none';
        }
        
        // Add visual feedback
        if (count > 0) {
            elements.clean_filter_count?.classList.add('clean-filter-count-active');
        } else {
            elements.clean_filter_count?.classList.remove('clean-filter-count-active');
        }
    }
    
    /**
     * Switch view
     */
    function switchView(view) {
        if (state.currentView === view) return;
        
        state.currentView = view;
        
        elements.clean_grid_view.classList.toggle('active', view === 'grid');
        elements.clean_list_view.classList.toggle('active', view === 'list');
        
        loadGrants();
    }
    
    /**
     * Toggle filter sidebar
     */
    function toggleFilterSidebar() {
        const isActive = elements.clean_filter_sidebar.classList.contains('active');
        
        if (isActive) {
            closeFilterSidebar();
        } else {
            elements.clean_filter_sidebar.classList.add('active');
            elements.clean_filter_toggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            
            // フォーカス管理
            const firstInput = elements.clean_filter_sidebar.querySelector('input, button');
            if (firstInput) {
                firstInput.focus();
            }
        }
    }
    
    /**
     * Close filter sidebar
     */
    function closeFilterSidebar() {
        elements.clean_filter_sidebar.classList.remove('active');
        elements.clean_filter_toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        
        // フォーカスを戻す
        elements.clean_filter_toggle.focus();
    }
    
    /**
     * Reset all filters
     */
    function resetAllFilters() {
        resetFiltersState();
        
        elements.filterCheckboxes.forEach(cb => cb.checked = false);
        elements.filterRadios.forEach(rb => rb.checked = rb.value === '');
        
        elements.quickFilters.forEach(f => f.classList.remove('active'));
        document.querySelector('.clean-filter-pill[data-filter="all"]')?.classList.add('active');
        
        if (elements.clean_search_input) {
            elements.clean_search_input.value = '';
        }
        if (elements.clean_search_clear) {
            elements.clean_search_clear.style.display = 'none';
        }
        
        state.currentPage = 1;
        updateFilterCount();
        loadGrants();
    }
    
    /**
     * Reset filters state
     */
    function resetFiltersState() {
        state.filters = {
            search: '',
            categories: [],
            prefectures: [],
            municipalities: [],
            amount: '',
            status: [],
            is_featured: '',
            sort: state.filters.sort
        };
    }
    
    /**
     * Handle pagination
     */
    function handlePaginationClick(e) {
        if (e.target.classList.contains('clean-page-btn') || e.target.closest('.clean-page-btn')) {
            e.preventDefault();
            
            const btn = e.target.classList.contains('clean-page-btn') ? e.target : e.target.closest('.clean-page-btn');
            const href = btn.getAttribute('href');
            
            if (href) {
                const url = new URL(href, window.location.origin);
                const page = parseInt(url.searchParams.get('paged')) || 1;
                
                if (page !== state.currentPage) {
                    state.currentPage = page;
                    loadGrants();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }
        }
    }
    
    /**
     * Handle keyboard shortcuts
     */
    function handleKeyboardShortcuts(e) {
        if (e.key === 'Escape') {
            closeFilterSidebar();
        }
    }
    
    // Prefecture and region handling functions removed per user request
    
    /**
     * Load grants via AJAX
     */
    async function loadGrants() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        showLoading();
        
        try {
            // デバッグログ
            console.log('Loading grants with filters:', state.filters);
            console.log('AJAX URL:', config.ajaxUrl);
            console.log('Nonce:', config.nonce);
            
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gi_load_grants',
                    nonce: config.nonce,
                    search: state.filters.search,
                    categories: JSON.stringify(state.filters.categories),
                    prefectures: JSON.stringify(state.filters.prefectures),
                    municipalities: JSON.stringify(state.filters.municipalities),
                    amount: state.filters.amount,
                    status: JSON.stringify(state.filters.status),
                    only_featured: state.filters.is_featured,
                    sort: state.filters.sort,
                    view: state.currentView,
                    page: state.currentPage
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                renderGrants(data.data);
                updateURL();
            } else {
                showNoResults();
            }
        } catch (error) {
            console.error('Error loading grants:', error);
            console.error('Fetch URL:', config.ajaxUrl);
            console.error('Request data:', {
                action: 'gi_load_grants',
                nonce: config.nonce,
                search: state.filters.search,
                categories: JSON.stringify(state.filters.categories),
                prefectures: JSON.stringify(state.filters.prefectures),
                municipalities: JSON.stringify(state.filters.municipalities)
            });
            showError();
        } finally {
            state.isLoading = false;
            hideLoading();
        }
    }
    
    /**
     * Render grants
     */
    function renderGrants(data) {
        const { grants, pagination, stats } = data;
        
        if (elements.clean_results_count) {
            elements.clean_results_count.textContent = stats?.total_found ? number_format(stats.total_found) : '0';
        }
        
        if (grants && grants.length > 0) {
            const containerClass = state.currentView === 'grid' ? 'clean-grants-grid' : 'clean-grants-list';
            elements.clean_grants_display.innerHTML = `
                <div class="${containerClass}">
                    ${grants.map(grant => grant.html).join('')}
                </div>
            `;
            
            initializeCardInteractions();
        } else {
            showNoResults();
        }
    }
    
    /**
     * Initialize card interactions
     */
    function initializeCardInteractions() {
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('click', handleFavoriteClick);
        });
    }
    
    /**
     * Handle favorite click
     */
    async function handleFavoriteClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const btn = e.currentTarget;
        const postId = btn.dataset.postId;
        
        btn.style.opacity = '0.5';
        btn.style.pointerEvents = 'none';
        
        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gi_toggle_favorite',
                    nonce: config.nonce,
                    post_id: postId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                btn.textContent = data.data.is_favorite ? '♥' : '♡';
                btn.style.color = data.data.is_favorite ? '#dc2626' : '#6b7280';
                
                showNotification(data.data.message, 'success');
            }
        } catch (error) {
            console.error('Error toggling favorite:', error);
            showNotification('エラーが発生しました', 'error');
        } finally {
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
        }
    }
    
    /**
     * Show loading
     */
    function showLoading() {
        if (elements.clean_loading) {
            elements.clean_loading.classList.remove('clean-hidden');
        }
        
        const container = elements.clean_grants_container;
        if (container && !container.querySelector('.clean-loading-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'clean-loading-overlay';
            overlay.innerHTML = `
                <div class="clean-spinner" role="status" aria-label="検索中"></div>
                <span class="sr-only">助成金を検索しています...</span>
            `;
            container.appendChild(overlay);
        }
        
        // フィルターボタンにローディング状態を追加
        elements.quickFilters.forEach(btn => {
            btn.classList.add('clean-filter-loading');
        });
    }
    
    /**
     * Hide loading
     */
    function hideLoading() {
        if (elements.clean_loading) {
            elements.clean_loading.classList.add('clean-hidden');
        }
        
        const overlay = document.querySelector('.clean-loading-overlay');
        if (overlay) {
            overlay.remove();
        }
        
        // フィルターボタンからローディング状態を削除
        elements.quickFilters.forEach(btn => {
            btn.classList.remove('clean-filter-loading');
        });
    }
    
    /**
     * Show no results
     */
    function showNoResults() {
        elements.clean_grants_display.innerHTML = `
            <div class="clean-no-results">
                <div class="clean-no-results-icon">
                    ×
                </div>
                <h3 class="clean-no-results-title">該当する助成金が見つかりませんでした</h3>
                <p class="clean-no-results-text">検索条件を変更して再度お試しください。</p>
                <button class="clean-reset-button" onclick="CleanGrants.resetAllFilters()">
                    検索をリセット
                </button>
            </div>
        `;
    }
    
    /**
     * Show error
     */
    function showError() {
        elements.clean_grants_display.innerHTML = `
            <div class="clean-no-results">
                <div class="clean-no-results-icon">
                    !
                </div>
                <h3 class="clean-no-results-title">エラーが発生しました</h3>
                <p class="clean-no-results-text">しばらく時間をおいて再度お試しください</p>
                <button class="clean-reset-button" onclick="window.location.reload()">
                    ページを再読み込み
                </button>
            </div>
        `;
    }
    
    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: ${type === 'success' ? '#16a34a' : type === 'error' ? '#dc2626' : '#000000'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.05);
            z-index: 10000;
            font-weight: 500;
            max-width: 300px;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    /**
     * Update URL
     */
    function updateURL() {
        const params = new URLSearchParams();
        
        if (state.filters.search) params.set('s', state.filters.search);
        if (state.filters.categories.length) params.set('category', state.filters.categories.join(','));
        if (state.filters.prefectures.length) params.set('prefecture', state.filters.prefectures.join(','));
        if (state.filters.municipalities.length) params.set('municipality', state.filters.municipalities.join(','));
        if (state.filters.amount) params.set('amount', state.filters.amount);
        if (state.filters.status.length) params.set('status', state.filters.status.join(','));
        if (state.filters.is_featured) params.set('featured', '1');
        if (state.filters.sort !== 'date_desc') params.set('sort', state.filters.sort);
        if (state.currentView !== 'grid') params.set('view', state.currentView);
        if (state.currentPage > 1) params.set('paged', state.currentPage);
        
        const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newURL);
    }
    
    /**
     * Handle more filters toggle
     */
    function handleMoreFiltersToggle(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const btn = e.target.closest('.clean-filter-more-btn');
        if (!btn) return;
        
        const filterGroup = btn.closest('.clean-filter-group');
        if (!filterGroup) return;
        
        const moreItems = filterGroup.querySelectorAll('.clean-filter-more-item');
        const showMoreText = btn.querySelector('.show-more-text');
        const showLessText = btn.querySelector('.show-less-text');
        const showMoreIcon = btn.querySelector('.show-more-icon');
        const showLessIcon = btn.querySelector('.show-less-icon');
        
        const isCollapsed = showMoreText && !showMoreText.classList.contains('hidden');
        
        if (isCollapsed) {
            moreItems.forEach(item => item.classList.remove('hidden'));
            if (showMoreText) showMoreText.classList.add('hidden');
            if (showLessText) showLessText.classList.remove('hidden');
            if (showMoreIcon) showMoreIcon.classList.add('hidden');
            if (showLessIcon) showLessIcon.classList.remove('hidden');
        } else {
            moreItems.forEach(item => item.classList.add('hidden'));
            if (showMoreText) showMoreText.classList.remove('hidden');
            if (showLessText) showLessText.classList.add('hidden');
            if (showMoreIcon) showMoreIcon.classList.remove('hidden');
            if (showLessIcon) showLessIcon.classList.add('hidden');
        }
    }
    
    /**
     * Initialize enhanced scroll indicators for filter lists
     */
    function initScrollIndicators() {
        document.querySelectorAll('.clean-filter-list-container').forEach(container => {
            const filterGroup = container.closest('.clean-filter-group');
            
            function updateScrollIndicator() {
                const hasScroll = container.scrollHeight > container.clientHeight;
                const scrollTop = container.scrollTop;
                const scrollBottom = container.scrollTop + container.clientHeight;
                const scrollHeight = container.scrollHeight;
                
                // Top fade indicator
                const hasScrollTop = scrollTop > 10;
                const hasScrollBottom = scrollBottom < scrollHeight - 10;
                
                container.classList.toggle('has-scroll-top', hasScrollTop);
                container.classList.toggle('has-scroll-bottom', hasScrollBottom);
                
                // Legacy support
                if (filterGroup) {
                    filterGroup.classList.toggle('has-scroll', hasScrollBottom);
                }
            }
            
            // Initial check
            setTimeout(updateScrollIndicator, 100);
            
            // Update on scroll with throttling
            let scrollTimeout;
            container.addEventListener('scroll', () => {
                if (scrollTimeout) return;
                scrollTimeout = setTimeout(() => {
                    updateScrollIndicator();
                    scrollTimeout = null;
                }, 16); // ~60fps
            }, { passive: true });
            
            // Update on resize
            window.addEventListener('resize', updateScrollIndicator);
            
            // Update when filter items are shown/hidden
            const observer = new MutationObserver(() => {
                setTimeout(updateScrollIndicator, 50);
            });
            observer.observe(container, { 
                childList: true, 
                subtree: true, 
                attributes: true, 
                attributeFilter: ['class', 'style'] 
            });
            
            // Smooth scroll to selected items
            const checkedItems = container.querySelectorAll('input:checked');
            if (checkedItems.length > 0) {
                setTimeout(() => {
                    const firstChecked = checkedItems[0].closest('.clean-filter-option');
                    if (firstChecked) {
                        firstChecked.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'nearest' 
                        });
                    }
                }, 200);
            }
        });
    }
    
    /**
     * Enhanced smooth scrolling for mobile
     */
    function initMobileScrollEnhancements() {
        if ('ontouchstart' in window) {
            // iOS momentum scrolling
            document.querySelectorAll('.clean-filter-list-container, .clean-sidebar, .clean-filter-body').forEach(element => {
                element.style.webkitOverflowScrolling = 'touch';
                element.style.overscrollBehavior = 'contain';
            });
            
            // Prevent body scroll when scrolling inside containers
            document.querySelectorAll('.clean-filter-list-container').forEach(container => {
                let startY = 0;
                
                container.addEventListener('touchstart', (e) => {
                    startY = e.touches[0].pageY;
                }, { passive: true });
                
                container.addEventListener('touchmove', (e) => {
                    const currentY = e.touches[0].pageY;
                    const scrollTop = container.scrollTop;
                    const scrollHeight = container.scrollHeight;
                    const clientHeight = container.clientHeight;
                    
                    if (
                        (currentY > startY && scrollTop === 0) ||
                        (currentY < startY && scrollTop + clientHeight >= scrollHeight)
                    ) {
                        // Allow native overscroll behavior
                        return;
                    }
                }, { passive: true });
            });
        }
    }
    
    /**
     * Format number
     */
    function number_format(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // Public API
    window.CleanGrants = {
        resetAllFilters,
        loadGrants,
        switchView
    };
    
    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            init();
            // Re-initialize scroll indicators after DOM is fully loaded
            setTimeout(() => {
                initScrollIndicators();
                initMobileScrollEnhancements();
            }, 300);
        });
    } else {
        init();
        // Re-initialize scroll indicators immediately
        setTimeout(() => {
            initScrollIndicators();
            initMobileScrollEnhancements();
        }, 100);
    }
})();

// ============================================
// フィルター検索機能
// ============================================

/**
 * カテゴリ検索のトグル
 */
function toggleCategorySearch() {
    const wrapper = document.getElementById('category-search-wrapper');
    const input = document.getElementById('category-search-input');
    
    if (wrapper.style.display === 'none') {
        wrapper.style.display = 'flex';
        input.focus();
    } else {
        wrapper.style.display = 'none';
        input.value = '';
        filterCategories('');
    }
}

/**
 * カテゴリフィルタリング
 */
function filterCategories(searchTerm) {
    const categories = document.querySelectorAll('.category-option');
    const term = searchTerm.toLowerCase().trim();
    
    categories.forEach(category => {
        const categoryName = category.dataset.categoryName;
        const isVisible = !term || categoryName.includes(term);
        category.style.display = isVisible ? '' : 'none';
    });
}

/**
 * カテゴリ検索のクリア
 */
function clearCategorySearch() {
    const input = document.getElementById('category-search-input');
    input.value = '';
    filterCategories('');
    input.focus();
}

/**
 * 都道府県検索のトグル
 */
function togglePrefectureSearch() {
    const wrapper = document.getElementById('prefecture-search-wrapper');
    const input = document.getElementById('prefecture-search-input');
    
    if (wrapper.style.display === 'none') {
        wrapper.style.display = 'flex';
        input.focus();
    } else {
        wrapper.style.display = 'none';
        input.value = '';
        // Prefecture filtering removed
    }
}

// Prefecture filtering functions removed per user request

// ============================================
// 上部フィルターバー機能
// ============================================

/**
 * 上部フィルターの適用
 */
function applyTopFilters() {
    const category = document.getElementById('top-category-select').value;
    const prefecture = document.getElementById('top-prefecture-select').value;
    const municipality = document.getElementById('top-municipality-select').value;
    const amount = document.getElementById('top-amount-select').value;
    const status = document.getElementById('top-status-select').value;
    const featured = document.getElementById('top-featured-btn').classList.contains('active') ? '1' : '';
    
    const params = new URLSearchParams();
    if (category) params.set('category', category);
    if (prefecture) params.set('prefecture', prefecture);
    if (municipality) params.set('municipality', municipality);
    if (amount) params.set('amount', amount);
    if (status) params.set('status', status);
    if (featured) params.set('featured', '1');
    
    // 現在の検索語を保持
    const currentSearch = document.getElementById('clean-search-input')?.value;
    if (currentSearch) params.set('s', currentSearch);
    
    const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.location.href = newURL;
}

/**
 * 上部フィルターのクリア
 */
function clearTopFilters() {
    document.getElementById('top-category-select').value = '';
    document.getElementById('top-prefecture-select').value = '';
    document.getElementById('top-municipality-select').value = '';
    document.getElementById('top-amount-select').value = '';
    document.getElementById('top-status-select').value = '';
    document.getElementById('top-featured-btn').classList.remove('active');
    
    // 検索語も保持してリダイレクト
    const currentSearch = document.getElementById('clean-search-input')?.value;
    const newURL = window.location.pathname + (currentSearch ? '?s=' + encodeURIComponent(currentSearch) : '');
    window.location.href = newURL;
}

/**
 * おすすめフィルターのトグル
 */
function toggleFeaturedFilter() {
    const btn = document.getElementById('top-featured-btn');
    btn.classList.toggle('active');
}

/**
 * 上部フィルターの変更を監視
 */
document.addEventListener('DOMContentLoaded', function() {
    // セレクトボックスの変更を監視
    ['top-category-select', 'top-prefecture-select', 'top-municipality-select', 'top-amount-select', 'top-status-select'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', function() {
                // 都道府県が変更された場合、市町村を更新
                if (id === 'top-prefecture-select') {
                    loadMunicipalitiesForTopFilter(this.value);
                }
                console.log(`${id} changed to: ${this.value}`);
            });
        }
    });
});

// ============================================
// 市町村データロード機能
// ============================================

/**
 * 上部フィルター用：都道府県選択時の市町村ロード
 */
function loadMunicipalitiesForTopFilter(prefectureSlug) {
    const municipalitySelect = document.getElementById('top-municipality-select');
    if (!municipalitySelect) return;
    
    // ローディング状態にする
    municipalitySelect.innerHTML = '<option value="">読み込み中...</option>';
    municipalitySelect.disabled = true;
    
    if (!prefectureSlug) {
        municipalitySelect.innerHTML = '<option value="">都道府県を選択してください</option>';
        municipalitySelect.disabled = false;
        return;
    }
    
    // URLデコードしてスラッグを正規化
    let decodedPrefectureSlug = prefectureSlug;
    try {
        decodedPrefectureSlug = decodeURIComponent(prefectureSlug);
        console.log('Decoded prefecture slug:', decodedPrefectureSlug);
    } catch (e) {
        console.log('Prefecture slug decode failed, using original:', prefectureSlug);
    }
    
    // AJAXで市町村データを取得（改善版）
    console.log('Loading municipalities for prefecture:', decodedPrefectureSlug);
    
    const requestData = {
        action: 'gi_get_municipalities_for_prefecture',
        nonce: '<?php echo wp_create_nonce('gi_ajax_nonce'); ?>',
        prefecture_slug: decodedPrefectureSlug
    };
    
    console.log('AJAX request data:', requestData);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(requestData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Municipality response data:', data);
        
        if (data.success) {
            const municipalities = data.data.municipalities || [];
            console.log('Municipalities count:', municipalities.length);
            
            let options = '<option value="">すべての市町村</option>';
            municipalities.forEach(municipality => {
                options += `<option value="${municipality.slug}">${municipality.name} (${municipality.count || 0})</option>`;
            });
            municipalitySelect.innerHTML = options;
            
            // デバッグ情報がある場合は表示
            if (data.data.debug) {
                console.log('Debug info:', data.data.debug);
            }
        } else {
            console.error('Municipality request failed:', data.data || data);
            let errorMessage = '市町村データの取得に失敗しました';
            if (data.data && data.data.message) {
                errorMessage = data.data.message;
            }
            municipalitySelect.innerHTML = `<option value="">${errorMessage}</option>`;
            
            // デバッグ情報を表示
            if (data.data && data.data.debug) {
                console.error('Error debug info:', data.data.debug);
            }
        }
    })
    .catch(error => {
        console.error('Municipality loading error:', error);
        municipalitySelect.innerHTML = '<option value="">ネットワークエラーが発生しました</option>';
    })
    .finally(() => {
        municipalitySelect.disabled = false;
    });
}

/**
 * サイドバーフィルター用：都道府県選択時の市町村ロード
 */
function loadMunicipalitiesForSidebarFilter() {
    const selectedPrefectures = Array.from(
        document.querySelectorAll('.prefecture-checkbox:checked')
    ).map(cb => {
        // URLデコードしてスラッグを正規化
        try {
            return decodeURIComponent(cb.value);
        } catch (e) {
            console.log('Prefecture slug decode failed for:', cb.value);
            return cb.value;
        }
    });
    
    const municipalityContainer = document.getElementById('municipality-list-container');
    if (!municipalityContainer) return;
    
    if (selectedPrefectures.length === 0) {
        municipalityContainer.innerHTML = `
            <div class="no-municipality-message">
                都道府県を選択すると、該当する市町村が表示されます。
            </div>
        `;
        return;
    }
    
    // ローディング表示
    municipalityContainer.innerHTML = `
        <div class="loading-municipality-message">
            <i class="fas fa-spinner fa-spin"></i>
            市町村データを読み込み中...
        </div>
    `;
    
    // AJAXで市町村データを取得（改善版）
    console.log('Sidebar: Loading municipalities for prefectures:', selectedPrefectures);
    
    const requestData = {
        action: 'gi_get_municipalities_for_prefectures',
        nonce: '<?php echo wp_create_nonce('gi_ajax_nonce'); ?>',
        prefecture_slugs: JSON.stringify(selectedPrefectures)
    };
    
    console.log('Sidebar AJAX request data:', requestData);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(requestData)
    })
    .then(response => {
        console.log('Sidebar response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Sidebar municipality response data:', data);
        
        if (data.success) {
            const municipalities = data.data.municipalities || [];
            console.log('Sidebar municipalities loaded:', municipalities.length);
            
            let html = '';
            const currentMunicipalities = Array.from(
                document.querySelectorAll('.municipality-checkbox:checked')
            ).map(cb => cb.value);
            
            municipalities.forEach(municipality => {
                const isSelected = currentMunicipalities.includes(municipality.slug);
                html += `
                    <label class="clean-filter-option municipality-option" 
                           data-municipality-name="${municipality.name.toLowerCase()}" 
                           title="${municipality.name}">
                        <input type="checkbox" 
                               name="municipalities[]" 
                               value="${municipality.slug}" 
                               class="clean-filter-checkbox municipality-checkbox"
                               ${isSelected ? 'checked' : ''}>
                        <span class="clean-filter-label">${municipality.name}</span>
                        <span class="clean-filter-count">${municipality.count || 0}</span>
                    </label>
                `;
            });
            
            if (municipalities.length > 0) {
                municipalityContainer.innerHTML = html;
                
                // イベントリスナーを再設定
                municipalityContainer.querySelectorAll('.municipality-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', handleFilterChange);
                });
            } else {
                municipalityContainer.innerHTML = `
                    <div class="no-municipality-message">
                        選択された都道府県に対応する市町村が見つかりません。
                    </div>
                `;
            }
            
            // デバッグ情報がある場合は表示
            if (data.data.debug) {
                console.log('Sidebar debug info:', data.data.debug);
            }
        } else {
            console.error('Sidebar municipality request failed:', data.data || data);
            let errorMessage = '市町村データの取得に失敗しました';
            if (data.data && data.data.message) {
                errorMessage = data.data.message;
            }
            municipalityContainer.innerHTML = `
                <div class="no-municipality-message">
                    ${errorMessage}
                </div>
            `;
            
            // デバッグ情報を表示
            if (data.data && data.data.debug) {
                console.error('Sidebar error debug info:', data.data.debug);
            }
        }
    })
    .catch(error => {
        console.error('Sidebar municipality loading error:', error);
        municipalityContainer.innerHTML = `
            <div class="no-municipality-message">
                ネットワークエラーが発生しました。ページを再読み込みしてください。
            </div>
        `;
    });
}

/**
 * 都道府県検索機能
 */
function togglePrefectureSearch() {
    const wrapper = document.getElementById('prefecture-search-wrapper');
    const input = document.getElementById('prefecture-search-input');
    
    if (wrapper.style.display === 'none') {
        wrapper.style.display = 'flex';
        input.focus();
    } else {
        wrapper.style.display = 'none';
        input.value = '';
        filterPrefectures('');
    }
}

function filterPrefectures(searchTerm) {
    const prefectures = document.querySelectorAll('.prefecture-option');
    const term = searchTerm.toLowerCase().trim();
    
    prefectures.forEach(prefecture => {
        const prefectureName = prefecture.dataset.prefectureName;
        const isVisible = !term || prefectureName.includes(term);
        prefecture.style.display = isVisible ? '' : 'none';
    });
}

function clearPrefectureSearch() {
    const input = document.getElementById('prefecture-search-input');
    input.value = '';
    filterPrefectures('');
    input.focus();
}

/**
 * 市町村検索機能
 */
function toggleMunicipalitySearch() {
    const wrapper = document.getElementById('municipality-search-wrapper');
    const input = document.getElementById('municipality-search-input');
    
    if (wrapper.style.display === 'none') {
        wrapper.style.display = 'flex';
        input.focus();
    } else {
        wrapper.style.display = 'none';
        input.value = '';
        filterMunicipalities('');
    }
}

function filterMunicipalities(searchTerm) {
    const municipalities = document.querySelectorAll('.municipality-option');
    const term = searchTerm.toLowerCase().trim();
    
    municipalities.forEach(municipality => {
        const municipalityName = municipality.dataset.municipalityName;
        const isVisible = !term || municipalityName.includes(term);
        municipality.style.display = isVisible ? '' : 'none';
    });
}

function clearMunicipalitySearch() {
    const input = document.getElementById('municipality-search-input');
    input.value = '';
    filterMunicipalities('');
    input.focus();
}

// ============================================
// 提案6: AI Filter Optimization
// ============================================

/**
 * AI フィルター最適化モーダルを開く
 */
function openFilterOptimization() {
    // ユーザーの閲覧履歴を分析
    const userHistory = JSON.parse(localStorage.getItem('gi_view_history') || '[]');
    const searchHistory = JSON.parse(localStorage.getItem('gi_search_history') || '[]');
    
    // AIによる推奨フィルター設定を生成
    const recommendations = analyzeUserPatterns(userHistory, searchHistory);
    
    // モーダルを作成
    const modal = document.createElement('div');
    modal.className = 'ai-filter-modal';
    modal.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        animation: fadeIn 0.3s ease;
    `;
    
    modal.innerHTML = `
        <div class="ai-filter-content" style="background: #fff; border-radius: 1rem; max-width: 600px; width: 100%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: slideUp 0.3s ease;">
            <div style="padding: 2rem; border-bottom: 2px solid #000;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h2 style="margin: 0; font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem;">
                        <span style="font-size: 2rem;">🤖</span>
                        AIフィルター最適化
                    </h2>
                    <button onclick="closeFilterOptimization()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s;">
                        ✕
                    </button>
                </div>
                <p style="color: #666; margin: 0; font-size: 0.875rem;">
                    あなたの検索パターンを分析し、最適なフィルター設定を提案します
                </p>
            </div>
            
            <div style="padding: 2rem;">
                ${recommendations.length > 0 ? `
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem;">
                            推奨フィルター設定
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            ${recommendations.map((rec, index) => `
                                <div class="filter-recommendation" style="background: #fafafa; padding: 1.5rem; border-radius: 0.75rem; border: 2px solid #e5e5e5; transition: all 0.3s; cursor: pointer;" onclick="applyRecommendation(${index})">
                                    <div style="display: flex; align-items: start; gap: 1rem; margin-bottom: 1rem;">
                                        <div style="background: #000; color: #fff; width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">
                                            ${index + 1}
                                        </div>
                                        <div style="flex: 1;">
                                            <h4 style="margin: 0 0 0.5rem 0; font-weight: 700; font-size: 0.9375rem;">
                                                ${rec.title}
                                            </h4>
                                            <p style="margin: 0; font-size: 0.8125rem; color: #666; line-height: 1.5;">
                                                ${rec.description}
                                            </p>
                                        </div>
                                        <div style="background: #fbbf24; color: #000; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 700; white-space: nowrap;">
                                            ${rec.confidence}% マッチ
                                        </div>
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                        ${rec.filters.map(f => `
                                            <span style="background: #fff; border: 1px solid #000; padding: 0.375rem 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.375rem;">
                                                <i class="fas ${f.icon}"></i>
                                                ${f.label}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : `
                    <div style="text-align: center; padding: 3rem 1rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem; font-weight: 700;">検索</div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.5rem;">
                            まだ十分なデータがありません
                        </h3>
                        <p style="color: #666; font-size: 0.875rem; margin-bottom: 2rem;">
                            助成金を検索・閲覧すると、AIが最適な設定を提案できるようになります
                        </p>
                        <button onclick="closeFilterOptimization()" style="background: #000; color: #fff; border: none; padding: 0.75rem 2rem; border-radius: 0.5rem; font-weight: 700; cursor: pointer;">
                            検索を開始する
                        </button>
                    </div>
                `}
                
                <div style="margin-top: 2rem; padding: 1.5rem; background: #f0f9ff; border-radius: 0.75rem; border-left: 4px solid #2563eb;">
                    <h4 style="margin: 0 0 0.75rem 0; font-weight: 700; font-size: 0.875rem;">
                        分析に基づく推奨理由
                    </h4>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8125rem; color: #333; line-height: 1.6;">
                        <li>閲覧回数: ${userHistory.length}件</li>
                        <li>検索回数: ${searchHistory.length}回</li>
                        <li>よく見るカテゴリ: ${getMostFrequentCategory(userHistory)}</li>
                        <li>平均助成金額: ${getAverageAmount(userHistory)}</li>
                    </ul>
                </div>
            </div>
            
            <div style="padding: 1.5rem 2rem; border-top: 1px solid #e5e5e5; display: flex; gap: 1rem; justify-content: flex-end;">
                <button onclick="closeFilterOptimization()" style="background: #fff; border: 2px solid #000; color: #000; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 700; cursor: pointer; transition: all 0.3s;">
                    キャンセル
                </button>
                <button onclick="clearHistoryAndRecommendations()" style="background: #000; color: #fff; border: 2px solid #000; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 700; cursor: pointer; transition: all 0.3s;">
                    履歴をクリア
                </button>
            </div>
        </div>
        
        <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .filter-recommendation:hover {
            border-color: #000 !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        </style>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Store recommendations globally for apply function
    window.aiFilterRecommendations = recommendations;
}

/**
 * モーダルを閉じる
 */
function closeFilterOptimization() {
    const modal = document.querySelector('.ai-filter-modal');
    if (modal) {
        modal.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
            modal.remove();
            document.body.style.overflow = '';
        }, 300);
    }
}

/**
 * ユーザーのパターンを分析
 */
function analyzeUserPatterns(history, searches) {
    if (history.length === 0 && searches.length === 0) {
        return [];
    }
    
    const recommendations = [];
    
    // パターン1: 頻繁に見るカテゴリー
    const categoryFreq = {};
    history.forEach(item => {
        if (item.category) {
            categoryFreq[item.category] = (categoryFreq[item.category] || 0) + 1;
        }
    });
    
    const topCategory = Object.keys(categoryFreq).sort((a, b) => categoryFreq[b] - categoryFreq[a])[0];
    
    if (topCategory) {
        recommendations.push({
            title: `${topCategory}に特化した検索`,
            description: `あなたは「${topCategory}」カテゴリを${categoryFreq[topCategory]}回閲覧しています。このカテゴリに絞り込むことをお勧めします。`,
            confidence: 85,
            filters: [
                { icon: 'fa-tag', label: topCategory },
                { icon: 'fa-circle-dot', label: '募集中' }
            ],
            params: { category: topCategory, status: 'active' }
        });
    }
    
    // パターン2: 高額助成金への関心
    const avgAmount = history.reduce((sum, item) => sum + (item.amount || 0), 0) / history.length;
    if (avgAmount > 1000000) {
        recommendations.push({
            title: '高額助成金を優先表示',
            description: `平均${Math.floor(avgAmount / 10000)}万円の助成金を閲覧しています。1000万円以上の高額助成金に絞り込みます。`,
            confidence: 78,
            filters: [
                { icon: 'fa-coins', label: '高額助成金' },
                { icon: 'fa-sort-amount-down', label: '金額順' }
            ],
            params: { amount: '1000-3000', sort: 'amount_desc' }
        });
    }
    
    // パターン3: 締切間近を優先
    if (searches.some(s => s.includes('締切') || s.includes('期限'))) {
        recommendations.push({
            title: '締切間近の助成金を優先',
            description: '締切に関する検索が多いため、期限が迫っている助成金を優先的に表示します。',
            confidence: 72,
            filters: [
                { icon: 'fa-clock', label: '締切間近' },
                { icon: 'fa-calendar-alt', label: '締切順' }
            ],
            params: { deadline: 'soon', sort: 'deadline_asc' }
        });
    }
    
    return recommendations;
}

/**
 * 推奨設定を適用
 */
function applyRecommendation(index) {
    const rec = window.aiFilterRecommendations[index];
    if (!rec) return;
    
    // URLパラメータを構築
    const params = new URLSearchParams(rec.params);
    
    // ページをリロード
    window.location.href = window.location.pathname + '?' + params.toString();
}

/**
 * 履歴をクリア
 */
function clearHistoryAndRecommendations() {
    if (confirm('閲覧履歴と検索履歴をすべて削除しますか？')) {
        localStorage.removeItem('gi_view_history');
        localStorage.removeItem('gi_search_history');
        closeFilterOptimization();
        
        // トースト通知
        const toast = document.createElement('div');
        toast.textContent = '✓ 履歴をクリアしました';
        toast.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #000;
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 700;
            z-index: 10001;
            animation: slideUp 0.3s ease;
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
}

/**
 * ヘルパー関数
 */
function getMostFrequentCategory(history) {
    const freq = {};
    history.forEach(item => {
        if (item.category) freq[item.category] = (freq[item.category] || 0) + 1;
    });
    const top = Object.keys(freq).sort((a, b) => freq[b] - freq[a])[0];
    return top || '未分析';
}

function getAverageAmount(history) {
    const amounts = history.filter(item => item.amount > 0).map(item => item.amount);
    if (amounts.length === 0) return '未分析';
    const avg = amounts.reduce((sum, val) => sum + val, 0) / amounts.length;
    return Math.floor(avg / 10000) + '万円';
}
</script>

<?php get_footer(); ?>