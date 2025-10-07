<?php
/**
 * 都道府県レベル助成金の市町村自動設定デバッグスクリプト
 * 
 * このスクリプトで以下を確認：
 * 1. 都道府県タームの存在確認
 * 2. 市町村タームの存在確認
 * 3. prefecture-level市町村タームの存在確認
 * 4. 都道府県レベル助成金の市町村設定状況
 */

// WordPress環境の読み込み（WordPressルートディレクトリに配置する場合）
// require_once('./wp-config.php');

echo "<h1>都道府県・市町村データ診断</h1>\n";

// 1. 都道府県タームの確認
$prefectures = get_terms([
    'taxonomy' => 'grant_prefecture',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
]);

echo "<h2>1. 都道府県タームの状況</h2>\n";
if (!empty($prefectures) && !is_wp_error($prefectures)) {
    echo "<p>✅ 都道府県タームが " . count($prefectures) . " 件見つかりました</p>\n";
    echo "<ul>\n";
    foreach (array_slice($prefectures, 0, 5) as $pref) {
        echo "<li>{$pref->name} (slug: {$pref->slug}, posts: {$pref->count})</li>\n";
    }
    if (count($prefectures) > 5) {
        echo "<li>... 他 " . (count($prefectures) - 5) . " 件</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p>❌ 都道府県タームが見つかりません</p>\n";
    echo "<p><strong>対処法:</strong> WordPressの管理画面 → ツール → データ最適化 で初期化を実行してください</p>\n";
}

// 2. 市町村タームの確認
$municipalities = get_terms([
    'taxonomy' => 'grant_municipality',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
]);

echo "<h2>2. 市町村タームの状況</h2>\n";
if (!empty($municipalities) && !is_wp_error($municipalities)) {
    echo "<p>✅ 市町村タームが " . count($municipalities) . " 件見つかりました</p>\n";
    
    // prefecture-levelタームをカウント
    $prefecture_level_count = 0;
    $regular_municipality_count = 0;
    $municipalities_without_meta = 0;
    
    foreach ($municipalities as $muni) {
        if (strpos($muni->slug, '-prefecture-level') !== false) {
            $prefecture_level_count++;
        } else {
            $regular_municipality_count++;
        }
        
        $prefecture_slug = get_term_meta($muni->term_id, 'prefecture_slug', true);
        if (empty($prefecture_slug)) {
            $municipalities_without_meta++;
        }
    }
    
    echo "<ul>\n";
    echo "<li>都道府県レベルターム: {$prefecture_level_count} 件</li>\n";
    echo "<li>通常の市町村ターム: {$regular_municipality_count} 件</li>\n";
    echo "<li>都道府県メタデータなし: {$municipalities_without_meta} 件</li>\n";
    echo "</ul>\n";
} else {
    echo "<p>❌ 市町村タームが見つかりません</p>\n";
    echo "<p><strong>対処法:</strong> WordPressの管理画面 → ツール → データ最適化 で初期化を実行してください</p>\n";
}

// 3. 都道府県レベル助成金の確認
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

echo "<h2>3. 都道府県レベル助成金の状況</h2>\n";
if ($grants_query->have_posts()) {
    $total_grants = $grants_query->found_posts;
    $grants_with_municipalities = 0;
    $grants_without_municipalities = 0;
    
    while ($grants_query->have_posts()) {
        $grants_query->the_post();
        $post_id = get_the_ID();
        
        $municipalities = wp_get_post_terms($post_id, 'grant_municipality');
        if (!empty($municipalities) && !is_wp_error($municipalities)) {
            $grants_with_municipalities++;
        } else {
            $grants_without_municipalities++;
        }
    }
    wp_reset_postdata();
    
    echo "<p>都道府県レベル助成金: {$total_grants} 件</p>\n";
    echo "<ul>\n";
    echo "<li>✅ 市町村設定済み: {$grants_with_municipalities} 件</li>\n";
    echo "<li>❌ 市町村未設定: {$grants_without_municipalities} 件</li>\n";
    echo "</ul>\n";
    
    if ($grants_without_municipalities > 0) {
        echo "<p><strong>⚠️ 問題:</strong> {$grants_without_municipalities} 件の都道府県レベル助成金で市町村が設定されていません</p>\n";
        echo "<p><strong>対処法:</strong> 以下を実行してください：</p>\n";
        echo "<ol>\n";
        echo "<li>WordPressの管理画面 → ツール → データ最適化 で初期化を実行</li>\n";
        echo "<li>既存の助成金を一括更新（下記の一括修正機能を使用）</li>\n";
        echo "</ol>\n";
    }
} else {
    echo "<p>❌ 都道府県レベル助成金が見つかりません</p>\n";
}

// 4. 一括修正機能の提案
echo "<h2>4. 一括修正の実行</h2>\n";
echo "<p>問題が見つかった場合、以下のボタンで一括修正を実行できます：</p>\n";

?>

<form method="post" style="margin: 20px 0;">
    <input type="hidden" name="action" value="fix_prefecture_municipalities">
    <?php wp_nonce_field('fix_prefecture_municipalities_nonce'); ?>
    <p>
        <input type="submit" class="button button-primary" value="🔧 都道府県レベル助成金の市町村を一括修正" 
               onclick="return confirm('都道府県レベル助成金の市町村設定を一括で修正します。実行しますか？');">
    </p>
    <p><small>この操作は既存のデータを更新しますが、手動で設定された市町村は保持されます。</small></p>
</form>

<?php

// 一括修正処理
if (isset($_POST['action']) && $_POST['action'] === 'fix_prefecture_municipalities' && 
    wp_verify_nonce($_POST['_wpnonce'], 'fix_prefecture_municipalities_nonce')) {
    
    echo "<h3>🔧 一括修正を実行中...</h3>\n";
    
    // 1. まず都道府県・市町村データを初期化
    if (function_exists('gi_initialize_all_municipalities')) {
        echo "<p>Step 1: 都道府県・市町村データの初期化...</p>\n";
        $init_result = gi_initialize_all_municipalities();
        if ($init_result['success']) {
            echo "<p>✅ 初期化完了: 作成 {$init_result['total_created']} 件, 更新 {$init_result['total_updated']} 件</p>\n";
        } else {
            echo "<p>❌ 初期化に失敗しました</p>\n";
        }
    }
    
    // 2. 都道府県レベル助成金の市町村を一括設定
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
    
    $fixed_count = 0;
    $error_count = 0;
    
    echo "<p>Step 2: 都道府県レベル助成金の市町村自動設定...</p>\n";
    
    if ($grants_query->have_posts()) {
        while ($grants_query->have_posts()) {
            $grants_query->the_post();
            $post_id = get_the_ID();
            
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
                                'description' => $prefecture->name . 'レベルの助成金'
                            ]
                        );
                        
                        if (!is_wp_error($result)) {
                            $municipality_term_ids[] = $result['term_id'];
                            // メタデータ設定
                            add_term_meta($result['term_id'], 'prefecture_slug', $prefecture->slug);
                            add_term_meta($result['term_id'], 'prefecture_name', $prefecture->name);
                        }
                    } else {
                        $municipality_term_ids[] = $pref_muni_term->term_id;
                        // メタデータがなければ追加
                        if (!get_term_meta($pref_muni_term->term_id, 'prefecture_slug', true)) {
                            add_term_meta($pref_muni_term->term_id, 'prefecture_slug', $prefecture->slug);
                            add_term_meta($pref_muni_term->term_id, 'prefecture_name', $prefecture->name);
                        }
                    }
                }
                
                // 市町村を設定（既存の手動選択とマージ）
                if (!empty($municipality_term_ids)) {
                    $existing_munis = wp_get_post_terms($post_id, 'grant_municipality', ['fields' => 'ids']);
                    if (!is_wp_error($existing_munis)) {
                        $all_muni_ids = array_unique(array_merge($existing_munis, $municipality_term_ids));
                        wp_set_post_terms($post_id, $all_muni_ids, 'grant_municipality', false);
                        $fixed_count++;
                    } else {
                        wp_set_post_terms($post_id, $municipality_term_ids, 'grant_municipality', false);
                        $fixed_count++;
                    }
                } else {
                    $error_count++;
                }
            } else {
                $error_count++;
            }
        }
        wp_reset_postdata();
    }
    
    echo "<p>✅ 一括修正完了: 修正 {$fixed_count} 件, エラー {$error_count} 件</p>\n";
    
    if ($fixed_count > 0) {
        echo "<div style='background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0;'>";
        echo "<strong>✅ 修正完了:</strong> {$fixed_count} 件の都道府県レベル助成金に市町村が自動設定されました。";
        echo "</div>";
    }
}

echo "<h2>5. 確認方法</h2>\n";
echo "<p>修正後は以下で確認してください：</p>\n";
echo "<ul>\n";
echo "<li>WordPressの管理画面 → 助成金 → 助成金一覧 で市町村が設定されているか確認</li>\n";
echo "<li>フロントエンドの助成金検索で都道府県選択時に市町村が表示されるか確認</li>\n";
echo "<li>助成金詳細ページで適用地域が正しく表示されるか確認</li>\n";
echo "</ul>\n";

echo "<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 40px; }
h1 { color: #23282d; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
h2 { color: #0073aa; margin-top: 30px; }
ul, ol { margin: 15px 0; }
li { margin: 5px 0; }
.button { background: #0073aa; color: white; padding: 10px 15px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
.button:hover { background: #005a87; }
.button-primary { background: #00a0d2; }
</style>";
?>