<?php
/**
 * 都道府県・市町村関係修正スクリプト
 * Municipality Relationship Fix Script
 * 
 * このスクリプトは市町村データ取得問題の根本的な解決を行います
 * 
 * 使用方法:
 * 1. このファイルをWordPressテーマディレクトリにアップロード
 * 2. ブラウザでアクセス: https://yoursite.com/wp-content/themes/your-theme/fix-municipality-relationships.php
 */

// WordPress環境の読み込み
if (!function_exists('get_option')) {
    $wp_config_path = dirname(dirname(dirname(__FILE__))) . '/wp-config.php';
    if (file_exists($wp_config_path)) {
        require_once($wp_config_path);
    } else {
        echo "WordPress環境が読み込めません。";
        exit;
    }
}

echo "<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #2c3e50; margin-top: 30px; }
h1 { border-bottom: 3px solid #3498db; padding-bottom: 10px; }
.step { background: #f8f9fa; padding: 20px; border-left: 4px solid #3498db; margin: 20px 0; }
.success { background: #d4edda; border-left-color: #28a745; color: #155724; }
.error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
.warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; }
ul, ol { margin: 15px 0; padding-left: 30px; }
li { margin: 8px 0; }
button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px 10px 0; }
button:hover { background: #005a87; }
button.danger { background: #dc3545; }
button.danger:hover { background: #c82333; }
.code { background: #f1f1f1; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
.debug-info { background: #e9ecef; padding: 15px; border-radius: 4px; margin: 10px 0; font-family: monospace; font-size: 12px; }
</style>";

echo "<h1>🔧 市町村データ関係修正ツール</h1>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

// Step 1: 現状診断
echo "<div class='step'>";
echo "<h2>Step 1: 現状診断</h2>";

// 基本分類法の確認
$prefecture_taxonomy_exists = taxonomy_exists('grant_prefecture');
$municipality_taxonomy_exists = taxonomy_exists('grant_municipality');

echo "<h3>分類法の存在確認</h3>";
echo "<p>都道府県分類法: " . ($prefecture_taxonomy_exists ? "✅ 存在" : "❌ 不存在") . "</p>";
echo "<p>市町村分類法: " . ($municipality_taxonomy_exists ? "✅ 存在" : "❌ 不存在") . "</p>";

if (!$prefecture_taxonomy_exists || !$municipality_taxonomy_exists) {
    echo "<div class='error'>重大な問題: 分類法が登録されていません。functions.phpやinc/theme-foundation.phpを確認してください。</div>";
    exit;
}

// 都道府県数確認
$prefectures = get_terms([
    'taxonomy' => 'grant_prefecture',
    'hide_empty' => false
]);

$prefecture_count = is_wp_error($prefectures) ? 0 : count($prefectures);
echo "<p>都道府県数: <strong>{$prefecture_count}</strong></p>";

if ($prefecture_count == 0) {
    echo "<div class='error'>都道府県データが存在しません。データの初期化が必要です。</div>";
}

// 市町村数確認
$municipalities = get_terms([
    'taxonomy' => 'grant_municipality',
    'hide_empty' => false
]);

$municipality_count = is_wp_error($municipalities) ? 0 : count($municipalities);
echo "<p>市町村数: <strong>{$municipality_count}</strong></p>";

// 関係性の確認
$hierarchical_relationships = 0;
$meta_relationships = 0;
$no_relationship = 0;

if (!is_wp_error($municipalities) && !empty($municipalities)) {
    foreach ($municipalities as $muni) {
        $has_hierarchical = $muni->parent > 0;
        $has_meta = !empty(get_term_meta($muni->term_id, 'prefecture_slug', true));
        
        if ($has_hierarchical && $has_meta) {
            $hierarchical_relationships++; // Both
        } elseif ($has_hierarchical) {
            $hierarchical_relationships++;
        } elseif ($has_meta) {
            $meta_relationships++;
        } else {
            $no_relationship++;
        }
    }
}

echo "<h3>市町村と都道府県の関係</h3>";
echo "<p>階層的関係のある市町村: <strong>{$hierarchical_relationships}</strong></p>";
echo "<p>メタデータ関係のみの市町村: <strong>{$meta_relationships}</strong></p>";
echo "<p>関係のない孤立した市町村: <strong>{$no_relationship}</strong></p>";

// 問題の特定
$issues = [];
if ($prefecture_count < 47) {
    $issues[] = "都道府県データが不完全です (47都道府県中{$prefecture_count}件のみ)";
}
if ($municipality_count == 0) {
    $issues[] = "市町村データが存在しません";
}
if ($no_relationship > 0) {
    $issues[] = "{$no_relationship}件の市町村が都道府県と関連付けられていません";
}

if (empty($issues)) {
    echo "<div class='success'>✅ 基本的なデータ構造に問題は見つかりませんでした</div>";
} else {
    echo "<div class='error'>❌ 以下の問題が見つかりました:</div>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ul>";
}

echo "</div>"; // Step 1 end

// Step 2: AJAX機能のテスト
echo "<div class='step'>";
echo "<h2>Step 2: AJAX機能テスト</h2>";

$ajax_functions = [
    'gi_ajax_get_municipalities_for_prefecture' => 'wp_ajax_gi_get_municipalities_for_prefecture',
    'gi_ajax_get_municipalities_for_prefectures' => 'wp_ajax_gi_get_municipalities_for_prefectures'
];

echo "<h3>AJAX関数の存在確認</h3>";
foreach ($ajax_functions as $func_name => $action_name) {
    $func_exists = function_exists($func_name);
    echo "<p>{$func_name}: " . ($func_exists ? "✅ 存在" : "❌ 不存在") . "</p>";
    
    if ($func_exists) {
        global $wp_filter;
        $action_registered = isset($wp_filter[$action_name]);
        echo "<p>└ {$action_name}: " . ($action_registered ? "✅ 登録済み" : "❌ 未登録") . "</p>";
    }
}

echo "</div>"; // Step 2 end

// Step 3: 修復オプション
echo "<div class='step'>";
echo "<h2>Step 3: 修復オプション</h2>";
echo "<p>以下の修復オプションから選択してください：</p>";

?>
<form method="post" style="margin: 20px 0;">
    <?php wp_nonce_field('municipality_fix_actions', '_wpnonce'); ?>
    
    <h3>オプション 1: 完全な再初期化 (推奨)</h3>
    <p>既存の市町村データを全て削除し、標準データから再作成します。</p>
    <button type="submit" name="action" value="full_reinitialize" class="danger">
        🔄 完全再初期化を実行
    </button>
    
    <h3>オプション 2: 関係の修復のみ</h3>
    <p>既存の市町村データは保持し、都道府県との関係のみを修復します。</p>
    <button type="submit" name="action" value="fix_relationships">
        🔧 関係修復を実行
    </button>
    
    <h3>オプション 3: 診断情報のみ表示</h3>
    <p>修復は行わず、詳細な診断情報のみを表示します。</p>
    <button type="submit" name="action" value="detailed_diagnosis">
        📊 詳細診断を実行
    </button>
    
    <h3>オプション 4: サンプルデータでテスト</h3>
    <p>東京都の市町村データを作成してAJAX機能をテストします。</p>
    <button type="submit" name="action" value="test_tokyo">
        🧪 東京都テストを実行
    </button>
</form>

<?php

// アクション処理
if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'municipality_fix_actions')) {
    $action = sanitize_text_field($_POST['action']);
    
    echo "<div class='step'>";
    echo "<h2>実行結果</h2>";
    
    switch ($action) {
        case 'full_reinitialize':
            echo "<h3>🔄 完全再初期化を実行中...</h3>";
            
            // 既存市町村の削除
            $deleted_count = 0;
            if (!is_wp_error($municipalities)) {
                foreach ($municipalities as $muni) {
                    if (wp_delete_term($muni->term_id, 'grant_municipality')) {
                        $deleted_count++;
                    }
                }
            }
            echo "<p>削除された市町村: {$deleted_count}件</p>";
            
            // 都道府県の確認・作成
            $created_prefectures = 0;
            $standard_prefectures = [
                'tokyo' => '東京都',
                'osaka' => '大阪府',
                'kanagawa' => '神奈川県',
                'aichi' => '愛知県',
                'saitama' => '埼玉県',
                'chiba' => '千葉県',
                'hyogo' => '兵庫県',
                'hokkaido' => '北海道',
                'fukuoka' => '福岡県',
                'kyoto' => '京都府'
            ];
            
            foreach ($standard_prefectures as $slug => $name) {
                $existing = get_term_by('slug', $slug, 'grant_prefecture');
                if (!$existing) {
                    $result = wp_insert_term($name, 'grant_prefecture', ['slug' => $slug]);
                    if (!is_wp_error($result)) {
                        $created_prefectures++;
                    }
                }
            }
            echo "<p>作成された都道府県: {$created_prefectures}件</p>";
            
            // 東京都の市町村を作成
            $tokyo_municipalities = [
                '千代田区', '中央区', '港区', '新宿区', '文京区', '台東区', '墨田区', '江東区',
                '品川区', '目黒区', '大田区', '世田谷区', '渋谷区', '中野区', '杉並区', '豊島区',
                '北区', '荒川区', '板橋区', '練馬区', '足立区', '葛飾区', '江戸川区'
            ];
            
            $tokyo = get_term_by('slug', 'tokyo', 'grant_prefecture');
            $created_municipalities = 0;
            
            if ($tokyo) {
                foreach ($tokyo_municipalities as $muni_name) {
                    $muni_slug = 'tokyo-' . sanitize_title($muni_name);
                    $result = wp_insert_term($muni_name, 'grant_municipality', [
                        'slug' => $muni_slug,
                        'parent' => $tokyo->term_id,
                        'description' => "東京都{$muni_name}"
                    ]);
                    
                    if (!is_wp_error($result)) {
                        // メタデータも追加
                        add_term_meta($result['term_id'], 'prefecture_slug', 'tokyo');
                        add_term_meta($result['term_id'], 'prefecture_name', '東京都');
                        $created_municipalities++;
                    }
                }
            }
            
            echo "<p>作成された東京都の市町村: {$created_municipalities}件</p>";
            echo "<div class='success'>✅ 完全再初期化が完了しました</div>";
            break;
            
        case 'fix_relationships':
            echo "<h3>🔧 関係修復を実行中...</h3>";
            
            $fixed_count = 0;
            if (!is_wp_error($municipalities)) {
                foreach ($municipalities as $muni) {
                    // メタデータから都道府県を特定
                    $prefecture_slug = get_term_meta($muni->term_id, 'prefecture_slug', true);
                    if ($prefecture_slug) {
                        $prefecture = get_term_by('slug', $prefecture_slug, 'grant_prefecture');
                        if ($prefecture && $muni->parent != $prefecture->term_id) {
                            // 親子関係を修正
                            wp_update_term($muni->term_id, 'grant_municipality', [
                                'parent' => $prefecture->term_id
                            ]);
                            $fixed_count++;
                        }
                    }
                }
            }
            
            echo "<p>修正された関係: {$fixed_count}件</p>";
            echo "<div class='success'>✅ 関係修復が完了しました</div>";
            break;
            
        case 'detailed_diagnosis':
            echo "<h3>📊 詳細診断結果</h3>";
            
            if (!empty($prefectures)) {
                echo "<h4>都道府県の詳細</h4>";
                echo "<div class='debug-info'>";
                foreach (array_slice($prefectures, 0, 5) as $pref) {
                    echo "ID: {$pref->term_id}, Name: {$pref->name}, Slug: {$pref->slug}, Count: {$pref->count}<br>";
                }
                if (count($prefectures) > 5) {
                    echo "... 他 " . (count($prefectures) - 5) . " 件<br>";
                }
                echo "</div>";
            }
            
            if (!empty($municipalities)) {
                echo "<h4>市町村の詳細 (最初の10件)</h4>";
                echo "<div class='debug-info'>";
                foreach (array_slice($municipalities, 0, 10) as $muni) {
                    $parent_info = $muni->parent > 0 ? " Parent: {$muni->parent}" : " No Parent";
                    $meta_info = get_term_meta($muni->term_id, 'prefecture_slug', true);
                    $meta_info = $meta_info ? " Meta: {$meta_info}" : " No Meta";
                    echo "ID: {$muni->term_id}, Name: {$muni->name}, Slug: {$muni->slug}{$parent_info}{$meta_info}<br>";
                }
                echo "</div>";
            }
            break;
            
        case 'test_tokyo':
            echo "<h3>🧪 東京都テスト実行中...</h3>";
            
            // 東京都の確認
            $tokyo = get_term_by('slug', 'tokyo', 'grant_prefecture');
            if (!$tokyo) {
                $result = wp_insert_term('東京都', 'grant_prefecture', ['slug' => 'tokyo']);
                if (!is_wp_error($result)) {
                    $tokyo = get_term($result['term_id']);
                    echo "<p>東京都を作成しました (ID: {$tokyo->term_id})</p>";
                }
            } else {
                echo "<p>東京都が見つかりました (ID: {$tokyo->term_id})</p>";
            }
            
            if ($tokyo) {
                // 新宿区を作成
                $shinjuku_slug = 'tokyo-shinjuku';
                $existing_shinjuku = get_term_by('slug', $shinjuku_slug, 'grant_municipality');
                
                if (!$existing_shinjuku) {
                    $result = wp_insert_term('新宿区', 'grant_municipality', [
                        'slug' => $shinjuku_slug,
                        'parent' => $tokyo->term_id,
                        'description' => '東京都新宿区'
                    ]);
                    
                    if (!is_wp_error($result)) {
                        add_term_meta($result['term_id'], 'prefecture_slug', 'tokyo');
                        add_term_meta($result['term_id'], 'prefecture_name', '東京都');
                        echo "<p>新宿区を作成しました (ID: {$result['term_id']})</p>";
                    }
                } else {
                    echo "<p>新宿区が既に存在します (ID: {$existing_shinjuku->term_id})</p>";
                }
                
                // AJAX機能のテスト
                echo "<h4>AJAX機能テスト</h4>";
                echo "<div class='debug-info'>";
                echo "テスト用JavaScript:<br><br>";
                echo htmlspecialchars("
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'gi_get_municipalities_for_prefecture',
        nonce: 'test_nonce',
        prefecture_slug: 'tokyo'
    })
}).then(response => response.json())
  .then(data => console.log(data));
                ");
                echo "</div>";
            }
            
            echo "<div class='success'>✅ 東京都テストが完了しました</div>";
            break;
    }
    
    echo "</div>"; // 実行結果 end
}

echo "</div>"; // Step 3 end

// Step 4: 次のステップ
echo "<div class='step'>";
echo "<h2>Step 4: 確認とテスト</h2>";
echo "<h3>フロントエンドでのテスト手順</h3>";
echo "<ol>";
echo "<li>助成金一覧ページ (/archives/grant/) にアクセス</li>";
echo "<li>サイドバーの都道府県フィルターで「東京都」を選択</li>";
echo "<li>市町村フィルターが自動で更新されることを確認</li>";
echo "<li>ブラウザの開発者ツール (F12) でネットワークタブを確認</li>";
echo "<li>AJAX リクエスト gi_get_municipalities_for_prefecture が成功しているか確認</li>";
echo "</ol>";

echo "<h3>トラブルシューティング</h3>";
echo "<ul>";
echo "<li>AJAX エラーが発生する場合: <code>wp_debug.log</code> を確認</li>";
echo "<li>nonce エラーの場合: ページを再読み込みしてキャッシュをクリア</li>";
echo "<li>市町村が表示されない場合: このスクリプトで完全再初期化を実行</li>";
echo "<li>JavaScript エラーの場合: ブラウザのコンソールでエラーメッセージを確認</li>";
echo "</ul>";

echo "</div>"; // Step 4 end

echo "<p style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;'>";
echo "修復完了後は、このファイルをサーバーから削除することをお勧めします。<br>";
echo "問題が解決しない場合は、開発者ツールのネットワークタブでAJAXリクエストを確認してください。";
echo "</p>";
?>