# 市町村自動入力機能の改善提案

## 現在の状況
- 都道府県を選択すると、同じ名前の市町村タームが自動生成される
- 例：「東京都」→「東京都」市町村タームが作成
- 実際の市町村（横浜市、名古屋市など）の詳細選択ができない

## 改善案

### Option 1: 真の市町村マスターデータ実装

#### 1. 市町村マスターデータの準備
```php
function gi_get_municipalities_by_prefecture($prefecture_slug) {
    $municipality_map = [
        'tokyo' => [
            '千代田区', '中央区', '港区', '新宿区', '文京区', '台東区',
            '墨田区', '江東区', '品川区', '目黒区', '大田区', '世田谷区',
            '渋谷区', '中野区', '杉並区', '豊島区', '北区', '荒川区',
            '板橋区', '練馬区', '足立区', '葛飾区', '江戸川区',
            '八王子市', '立川市', '武蔵野市', '三鷹市', '青梅市',
            // ... 他の東京都の市町村
        ],
        'kanagawa' => [
            '横浜市', '川崎市', '相模原市', '横須賀市', '平塚市',
            '鎌倉市', '藤沢市', '小田原市', '茅ヶ崎市', '逗子市',
            // ... 他の神奈川県の市町村
        ],
        'osaka' => [
            '大阪市', '堺市', '岸和田市', '豊中市', '池田市',
            '吹田市', '泉大津市', '高槻市', '貝塚市', '守口市',
            // ... 他の大阪府の市町村
        ],
        // ... 他の都道府県
    ];
    
    return $municipality_map[$prefecture_slug] ?? [];
}
```

#### 2. 自動入力ロジックの改良
```php
function gi_enhanced_sync_prefecture_to_municipality($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    $prefectures = wp_get_post_terms($post_id, 'grant_prefecture', ['fields' => 'all']);
    
    if (!empty($prefectures) && !is_wp_error($prefectures)) {
        foreach ($prefectures as $prefecture) {
            // 1. 都道府県レベルの市町村タームを作成
            gi_create_prefecture_level_municipality($prefecture);
            
            // 2. 該当する全市町村のタームを事前作成（実行は一回のみ）
            gi_create_all_municipalities_for_prefecture($prefecture);
        }
    }
}

function gi_create_all_municipalities_for_prefecture($prefecture) {
    $municipalities = gi_get_municipalities_by_prefecture($prefecture->slug);
    
    foreach ($municipalities as $municipality_name) {
        if (!term_exists($municipality_name, 'grant_municipality')) {
            wp_insert_term(
                $municipality_name,
                'grant_municipality',
                [
                    'slug' => sanitize_title($prefecture->slug . '-' . $municipality_name),
                    'description' => $prefecture->name . 'の' . $municipality_name,
                    'parent' => gi_get_prefecture_municipality_term_id($prefecture)
                ]
            );
        }
    }
}
```

#### 3. 管理画面での市町村選択UI強化
```javascript
// 都道府県選択時に該当市町村を表示
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('prefecture-checkbox')) {
        updateMunicipalityOptions();
    }
});

function updateMunicipalityOptions() {
    const selectedPrefectures = Array.from(document.querySelectorAll('.prefecture-checkbox:checked'))
        .map(cb => cb.value);
    
    // AJAX で該当市町村を取得して表示
    fetch(ajaxurl, {
        method: 'POST',
        body: new FormData(Object.entries({
            action: 'gi_get_municipalities_for_prefectures',
            prefectures: selectedPrefectures,
            nonce: gi_ajax.nonce
        }))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderMunicipalityOptions(data.data.municipalities);
        }
    });
}
```

### Option 2: 現在のシステムの最適化

#### 1. より明確な分類
```php
function gi_improved_sync_prefecture_to_municipality($post_id, $post, $update) {
    // 地域制限タイプを確認
    $regional_limitation = get_field('regional_limitation', $post_id);
    
    if ($regional_limitation === 'prefecture_only') {
        // 都道府県レベルの助成金として市町村タームを作成
        gi_sync_prefecture_level_municipalities($post_id);
    } elseif ($regional_limitation === 'municipality_only') {
        // 市町村レベルの助成金 - 手動選択を促す
        gi_prompt_municipality_selection($post_id);
    }
}
```

#### 2. フロントエンドでの改善
```javascript
// より明確なフィルタリング
function filterByMunicipalityType() {
    const municipalityType = document.querySelector('[name="municipality_type"]:checked').value;
    
    if (municipalityType === 'prefecture_level') {
        // 都道府県レベルの助成金のみ表示
        showPrefectureLevelGrants();
    } else if (municipalityType === 'municipality_level') {
        // 市町村レベルの助成金のみ表示
        showMunicipalityLevelGrants();
    }
}
```

## 推奨実装順序

### 第一段階：現システムの改善
1. 地域制限タイプによる分岐処理の実装
2. 管理画面での視覚的な区別の追加
3. フロントエンドでの明確な分類表示

### 第二段階：真の市町村システム（必要に応じて）
1. 市町村マスターデータの準備
2. 階層構造の実装（都道府県 > 市町村）
3. 管理画面UIの大幅改善
4. 検索・フィルター機能の強化

## 技術的な考慮点

### データベース設計
- 現在の`grant_municipality`タクソノミーを活用
- 階層構造（`hierarchical: true`）は既に設定済み
- 親子関係でデータ整理が可能

### パフォーマンス
- 市町村データの事前生成でクエリ最適化
- キャッシュ機能の活用
- 必要時のみデータロード

### ユーザビリティ
- 段階的な選択（都道府県→市町村）
- 検索機能による快適な選択体験
- 視覚的な分類表示

## 実装のメリット

1. **より正確な地域分類**
   - 都道府県レベルと市町村レベルの明確な区別
   - ユーザーにとってより分かりやすい検索・絞り込み

2. **データの正確性向上**
   - 助成金の実際の対象地域を正確に反映
   - 誤解を招く可能性の削減

3. **拡張性**
   - 将来的な地域データの追加が容易
   - 他の地域関連機能への応用可能

4. **SEO効果**
   - より詳細な地域ページの作成可能
   - 地域別の検索需要への対応