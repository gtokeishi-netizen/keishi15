<?php
/**
 * データ最適化機能のテストスクリプト
 */

// WordPress環境の読み込み
require_once('./wp-config.php');

echo "<h1>データ最適化機能テスト</h1>\n";

// 1. 管理画面メニューが登録されているかチェック
echo "<h2>1. 管理画面メニュー登録状況</h2>\n";
$menu_exists = function_exists('gi_add_optimization_admin_menu');
echo "<p>gi_add_optimization_admin_menu 関数: " . ($menu_exists ? "✅ 存在" : "❌ 不在") . "</p>\n";

$page_exists = function_exists('gi_optimization_admin_page');
echo "<p>gi_optimization_admin_page 関数: " . ($page_exists ? "✅ 存在" : "❌ 不在") . "</p>\n";

// 2. AJAX関数が登録されているかチェック
echo "<h2>2. AJAX関数登録状況</h2>\n";
$ajax_exists = function_exists('gi_ajax_bulk_fix_prefecture_municipalities');
echo "<p>gi_ajax_bulk_fix_prefecture_municipalities 関数: " . ($ajax_exists ? "✅ 存在" : "❌ 不在") . "</p>\n";

// 3. 初期化関数が存在するかチェック
echo "<h2>3. 初期化関数状況</h2>\n";
$init_exists = function_exists('gi_initialize_all_municipalities');
echo "<p>gi_initialize_all_municipalities 関数: " . ($init_exists ? "✅ 存在" : "❌ 不在") . "</p>\n";

$standard_exists = function_exists('gi_get_standard_municipalities_by_prefecture');
echo "<p>gi_get_standard_municipalities_by_prefecture 関数: " . ($standard_exists ? "✅ 存在" : "❌ 不在") . "</p>\n";

// 4. WordPress actionsが登録されているかチェック
echo "<h2>4. WordPress Actions登録状況</h2>\n";
if (has_action('admin_menu', 'gi_add_optimization_admin_menu')) {
    echo "<p>admin_menu action: ✅ 登録済み</p>\n";
} else {
    echo "<p>admin_menu action: ❌ 未登録</p>\n";
}

if (has_action('wp_ajax_gi_bulk_fix_prefecture_municipalities', 'gi_ajax_bulk_fix_prefecture_municipalities')) {
    echo "<p>AJAX action: ✅ 登録済み</p>\n";
} else {
    echo "<p>AJAX action: ❌ 未登録</p>\n";
}

// 5. 現在のデータ状況
echo "<h2>5. 現在のデータ状況</h2>\n";

// 都道府県タームの数
$prefectures = get_terms(['taxonomy' => 'grant_prefecture', 'hide_empty' => false]);
$prefecture_count = is_wp_error($prefectures) ? 0 : count($prefectures);
echo "<p>都道府県タームの数: {$prefecture_count} 件</p>\n";

// 市町村タームの数
$municipalities = get_terms(['taxonomy' => 'grant_municipality', 'hide_empty' => false]);
$municipality_count = is_wp_error($municipalities) ? 0 : count($municipalities);
echo "<p>市町村タームの数: {$municipality_count} 件</p>\n";

// prefecture-levelタームの数
if (!is_wp_error($municipalities)) {
    $prefecture_level_count = 0;
    foreach ($municipalities as $muni) {
        if (strpos($muni->slug, '-prefecture-level') !== false) {
            $prefecture_level_count++;
        }
    }
    echo "<p>都道府県レベルタームの数: {$prefecture_level_count} 件</p>\n";
}

// 都道府県レベル助成金の数
$prefecture_grants = new WP_Query([
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
echo "<p>都道府県レベル助成金の数: {$prefecture_grants->found_posts} 件</p>\n";

// 6. 最適化機能のテスト実行
echo "<h2>6. 機能テスト</h2>\n";

if ($init_exists) {
    echo "<h3>初期化関数テスト</h3>\n";
    try {
        // 小規模テスト（1つの都道府県のみ）
        if ($standard_exists) {
            $test_municipalities = gi_get_standard_municipalities_by_prefecture('tokyo');
            echo "<p>東京都の標準市町村数: " . count($test_municipalities) . " 件</p>\n";
            echo "<p>例: " . implode('、', array_slice($test_municipalities, 0, 5)) . " など</p>\n";
        }
    } catch (Exception $e) {
        echo "<p>❌ テスト失敗: " . $e->getMessage() . "</p>\n";
    }
}

// 7. 推奨アクション
echo "<h2>7. 推奨アクション</h2>\n";
echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #0073aa; border-radius: 4px;'>\n";

if (!$menu_exists || !$ajax_exists) {
    echo "<p><strong>⚠️ 問題:</strong> 必要な関数が不足しています</p>\n";
    echo "<ul>\n";
    if (!$menu_exists) echo "<li>管理画面メニュー関数が不在</li>\n";
    if (!$ajax_exists) echo "<li>AJAX処理関数が不在</li>\n";
    echo "</ul>\n";
} else {
    echo "<p><strong>✅ 正常:</strong> 最適化機能は利用可能です</p>\n";
    echo "<ul>\n";
    echo "<li>WordPress管理画面 → ツール → データ最適化 にアクセス</li>\n";
    echo "<li>「データ最適化を実行」と「🔧 都道府県レベル助成金の市町村を一括修正」を実行</li>\n";
    echo "</ul>\n";
}

echo "</div>\n";

echo "<h2>8. 直接テスト実行</h2>\n";
if (current_user_can('manage_options') && $ajax_exists) {
    echo "<p><button onclick='testOptimization()'>最適化機能をテスト実行</button></p>\n";
    echo "<div id='test-result'></div>\n";
    
    echo "<script>
    function testOptimization() {
        var result = document.getElementById('test-result');
        result.innerHTML = '実行中...';
        
        fetch('" . admin_url('admin-ajax.php') . "', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=gi_bulk_fix_prefecture_municipalities&_wpnonce=" . wp_create_nonce('gi_bulk_fix_nonce') . "'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                result.innerHTML = '<div style=\"background: #d4edda; color: #155724; padding: 10px; border-radius: 4px;\">✅ 成功: ' + data.data.message + '</div>';
            } else {
                result.innerHTML = '<div style=\"background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;\">❌ エラー: ' + data.data.message + '</div>';
            }
        })
        .catch(error => {
            result.innerHTML = '<div style=\"background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;\">❌ 通信エラー: ' + error.message + '</div>';
        });
    }
    </script>\n";
} else {
    echo "<p><em>管理者権限が必要です</em></p>\n";
}

echo "<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 40px; }
h1 { color: #23282d; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
h2 { color: #0073aa; margin-top: 30px; }
p, li { margin: 8px 0; }
button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
button:hover { background: #005a87; }
</style>";
?>