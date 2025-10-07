<?php
/**
 * 市町村データ取得問題の診断と修正スクリプト
 */

// このファイルをWordPressテーマディレクトリにアップロードしてブラウザからアクセスしてください
// 例: https://yoursite.com/wp-content/themes/your-theme/debug-municipality-issue.php

// WordPress環境の読み込み
if (!function_exists('get_option')) {
    // WordPressのルートディレクトリのwp-config.phpを読み込み
    $wp_config_path = dirname(dirname(dirname(__FILE__))) . '/wp-config.php';
    
    if (file_exists($wp_config_path)) {
        require_once($wp_config_path);
    } else {
        echo "WordPress環境が読み込めません。このファイルをWordPressテーマディレクトリに配置してアクセスしてください。";
        exit;
    }
}

echo "<h1>市町村データ取得問題の診断</h1>";

// 1. 基本的な分類法の確認
echo "<h2>1. 基本的な分類法の確認</h2>";

$prefecture_taxonomy_exists = taxonomy_exists('grant_prefecture');
$municipality_taxonomy_exists = taxonomy_exists('grant_municipality');

echo "<p>都道府県分類法の存在: " . ($prefecture_taxonomy_exists ? "✅ 存在" : "❌ 不存在") . "</p>";
echo "<p>市町村分類法の存在: " . ($municipality_taxonomy_exists ? "✅ 存在" : "❌ 不存在") . "</p>";

if (!$prefecture_taxonomy_exists || !$municipality_taxonomy_exists) {
    echo "<div style='color: red; font-weight: bold;'>⚠️ 重大な問題: 分類法が登録されていません。テーマのfunctions.phpまたはinc/theme-foundation.phpを確認してください。</div>";
    exit;
}

// 2. 都道府県データの確認
echo "<h2>2. 都道府県データの確認</h2>";

$prefectures = get_terms([
    'taxonomy' => 'grant_prefecture',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
]);

if (is_wp_error($prefectures)) {
    echo "<p style='color: red;'>❌ 都道府県取得エラー: " . $prefectures->get_error_message() . "</p>";
} else {
    $pref_count = count($prefectures);
    echo "<p>✅ 都道府県数: {$pref_count}件</p>";
    
    if ($pref_count > 0) {
        echo "<p>最初の5件:</p><ul>";
        for ($i = 0; $i < min(5, $pref_count); $i++) {
            $pref = $prefectures[$i];
            echo "<li>{$pref->name} (ID: {$pref->term_id}, Slug: {$pref->slug}, Count: {$pref->count})</li>";
        }
        echo "</ul>";
    } else {
        echo "<div style='color: red;'>❌ 都道府県データが存在しません。</div>";
    }
}

// 3. 市町村データの確認
echo "<h2>3. 市町村データの確認</h2>";

$municipalities = get_terms([
    'taxonomy' => 'grant_municipality',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
    'number' => 20 // 最初の20件のみ
]);

if (is_wp_error($municipalities)) {
    echo "<p style='color: red;'>❌ 市町村取得エラー: " . $municipalities->get_error_message() . "</p>";
} else {
    $muni_count = count($municipalities);
    echo "<p>✅ 市町村数（最初の20件）: {$muni_count}件</p>";
    
    if ($muni_count > 0) {
        echo "<p>サンプル:</p><ul>";
        $hierarchical_count = 0;
        $meta_relationship_count = 0;
        
        foreach ($municipalities as $muni) {
            // 親子関係確認
            $parent_info = '';
            if ($muni->parent > 0) {
                $parent = get_term($muni->parent);
                if ($parent && !is_wp_error($parent)) {
                    $parent_info = " → 親: {$parent->name} ({$parent->taxonomy})";
                    $hierarchical_count++;
                }
            }
            
            // メタデータ確認
            $prefecture_meta = get_term_meta($muni->term_id, 'prefecture_slug', true);
            if (!empty($prefecture_meta)) {
                $parent_info .= " | prefecture_slug: {$prefecture_meta}";
                $meta_relationship_count++;
            }
            
            echo "<li>{$muni->name} (ID: {$muni->term_id}){$parent_info}</li>";
        }
        echo "</ul>";
        
        echo "<p>階層的関係がある市町村: {$hierarchical_count}件</p>";
        echo "<p>メタデータ関係がある市町村: {$meta_relationship_count}件</p>";
        
    } else {
        echo "<div style='color: red;'>❌ 市町村データが存在しません。</div>";
    }
}

// 4. 具体的な都道府県での市町村取得テスト
echo "<h2>4. 東京都での市町村取得テスト</h2>";

if (!empty($prefectures)) {
    // 東京都を探す
    $tokyo = null;
    foreach ($prefectures as $pref) {
        if (strpos($pref->name, '東京') !== false) {
            $tokyo = $pref;
            break;
        }
    }
    
    if ($tokyo) {
        echo "<p>東京都が見つかりました: {$tokyo->name} (ID: {$tokyo->term_id}, Slug: {$tokyo->slug})</p>";
        
        // 方法1: 階層的関係での取得
        echo "<h3>方法1: 階層的関係での取得</h3>";
        $tokyo_municipalities_hierarchical = get_terms([
            'taxonomy' => 'grant_municipality',
            'parent' => $tokyo->term_id,
            'hide_empty' => false
        ]);
        
        if (is_wp_error($tokyo_municipalities_hierarchical)) {
            echo "<p style='color: red;'>❌ エラー: " . $tokyo_municipalities_hierarchical->get_error_message() . "</p>";
        } else {
            echo "<p>結果: " . count($tokyo_municipalities_hierarchical) . "件</p>";
            if (count($tokyo_municipalities_hierarchical) > 0) {
                echo "<ul>";
                for ($i = 0; $i < min(3, count($tokyo_municipalities_hierarchical)); $i++) {
                    echo "<li>{$tokyo_municipalities_hierarchical[$i]->name}</li>";
                }
                echo "</ul>";
            }
        }
        
        // 方法2: メタデータでの取得
        echo "<h3>方法2: メタデータでの取得</h3>";
        $tokyo_municipalities_meta = get_terms([
            'taxonomy' => 'grant_municipality',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'prefecture_slug',
                    'value' => $tokyo->slug,
                    'compare' => '='
                ]
            ]
        ]);
        
        if (is_wp_error($tokyo_municipalities_meta)) {
            echo "<p style='color: red;'>❌ エラー: " . $tokyo_municipalities_meta->get_error_message() . "</p>";
        } else {
            echo "<p>結果: " . count($tokyo_municipalities_meta) . "件</p>";
            if (count($tokyo_municipalities_meta) > 0) {
                echo "<ul>";
                for ($i = 0; $i < min(3, count($tokyo_municipalities_meta)); $i++) {
                    echo "<li>{$tokyo_municipalities_meta[$i]->name}</li>";
                }
                echo "</ul>";
            }
        }
        
    } else {
        echo "<p>東京都が見つかりません。</p>";
    }
}

// 5. AJAX関数の存在確認
echo "<h2>5. AJAX関数の存在確認</h2>";

$ajax_functions = [
    'gi_ajax_get_municipalities_for_prefecture',
    'gi_ajax_get_municipalities_for_prefectures'
];

foreach ($ajax_functions as $func) {
    if (function_exists($func)) {
        echo "<p>✅ {$func} 関数が存在します</p>";
    } else {
        echo "<p style='color: red;'>❌ {$func} 関数が見つかりません</p>";
    }
}

// 6. WordPress AJAX アクションの確認
echo "<h2>6. WordPress AJAX アクションの確認</h2>";

global $wp_filter;

$ajax_actions = [
    'wp_ajax_gi_get_municipalities_for_prefecture',
    'wp_ajax_nopriv_gi_get_municipalities_for_prefecture',
    'wp_ajax_gi_get_municipalities_for_prefectures', 
    'wp_ajax_nopriv_gi_get_municipalities_for_prefectures'
];

foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "<p>✅ {$action} アクションが登録されています</p>";
    } else {
        echo "<p style='color: red;'>❌ {$action} アクションが登録されていません</p>";
    }
}

// 7. 簡単な修正アクション
echo "<h2>7. 問題の診断結果</h2>";

$issues = [];
$solutions = [];

if (empty($prefectures) || count($prefectures) == 0) {
    $issues[] = "都道府県データが存在しません";
    $solutions[] = "gi_initialize_all_municipalities() 関数を実行して初期データを作成してください";
}

if (empty($municipalities) || count($municipalities) == 0) {
    $issues[] = "市町村データが存在しません";  
    $solutions[] = "gi_initialize_all_municipalities() 関数を実行して初期データを作成してください";
}

if (!function_exists('gi_ajax_get_municipalities_for_prefecture')) {
    $issues[] = "AJAX処理関数が見つかりません";
    $solutions[] = "inc/ajax-functions.php ファイルが正しく読み込まれているか確認してください";
}

if (empty($issues)) {
    echo "<div style='color: green; font-weight: bold;'>✅ 重大な問題は見つかりませんでした。</div>";
    echo "<p>フロントエンドでの動作確認とJavaScriptでのAJAX呼び出しを確認してください。</p>";
} else {
    echo "<div style='color: red; font-weight: bold;'>❌ 以下の問題が見つかりました:</div>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ul>";
    
    echo "<div style='color: blue; font-weight: bold;'>💡 推奨される解決方法:</div>";
    echo "<ul>";
    foreach ($solutions as $solution) {
        echo "<li>{$solution}</li>";
    }
    echo "</ul>";
}

echo "<h2>8. 次のステップ</h2>";
echo "<ol>";
echo "<li>このページの結果をスクリーンショットで保存</li>";
echo "<li>問題が見つかった場合は、推奨解決方法を実行</li>";
echo "<li>ブラウザの開発者ツールでAJAXリクエストを確認</li>";
echo "<li>フロントエンドでの都道府県選択→市町村取得の動作をテスト</li>";
echo "</ol>";

// CSS for better display
echo "<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #2c3e50; margin-top: 30px; }
h1 { border-bottom: 2px solid #3498db; padding-bottom: 10px; }
ul { background: #f8f9fa; padding: 15px; border-left: 4px solid #3498db; }
li { margin: 5px 0; }
p { margin: 10px 0; }
</style>";
?>