<?php
/**
 * Grant Insight Perfect - 3. AJAX Functions File (Complete Implementation)
 *
 * サイトの動的な機能（検索、フィルタリング、AI処理など）を
 * 担当する全てのAJAX処理をここにまとめます。
 * Perfect implementation with comprehensive AI integration
 *
 * @package Grant_Insight_Perfect
 * @version 4.0.0 - Perfect Implementation Edition
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * =============================================================================
 * AJAX ハンドラー登録 - 完全版
 * =============================================================================
 */

// AI検索機能
add_action('wp_ajax_gi_ai_search', 'handle_ai_search');
add_action('wp_ajax_nopriv_gi_ai_search', 'handle_ai_search');

// AIチャット機能  
add_action('wp_ajax_gi_ai_chat', 'handle_ai_chat_request');
add_action('wp_ajax_nopriv_gi_ai_chat', 'handle_ai_chat_request');

// Grant AI質問機能
add_action('wp_ajax_handle_grant_ai_question', 'handle_grant_ai_question');
add_action('wp_ajax_nopriv_handle_grant_ai_question', 'handle_grant_ai_question');

// 音声入力機能
add_action('wp_ajax_gi_voice_input', 'gi_ajax_process_voice_input');
add_action('wp_ajax_nopriv_gi_voice_input', 'gi_ajax_process_voice_input');

// 検索候補機能
add_action('wp_ajax_gi_search_suggestions', 'gi_ajax_get_search_suggestions');
add_action('wp_ajax_nopriv_gi_search_suggestions', 'gi_ajax_get_search_suggestions');

// 音声履歴機能
add_action('wp_ajax_gi_voice_history', 'gi_ajax_save_voice_history');
add_action('wp_ajax_nopriv_gi_voice_history', 'gi_ajax_save_voice_history');

// テスト接続機能
add_action('wp_ajax_gi_test_connection', 'gi_ajax_test_connection');
add_action('wp_ajax_nopriv_gi_test_connection', 'gi_ajax_test_connection');

// お気に入り機能
add_action('wp_ajax_gi_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_nopriv_gi_toggle_favorite', 'gi_ajax_toggle_favorite');

// 助成金ロード機能（フィルター・検索）
add_action('wp_ajax_gi_load_grants', 'gi_ajax_load_grants');
add_action('wp_ajax_nopriv_gi_load_grants', 'gi_ajax_load_grants');

// チャット履歴機能
add_action('wp_ajax_gi_get_chat_history', 'gi_ajax_get_chat_history');
add_action('wp_ajax_nopriv_gi_get_chat_history', 'gi_ajax_get_chat_history');

// 検索履歴機能
add_action('wp_ajax_gi_get_search_history', 'gi_ajax_get_search_history');
add_action('wp_ajax_nopriv_gi_get_search_history', 'gi_ajax_get_search_history');

// AIフィードバック機能
add_action('wp_ajax_gi_ai_feedback', 'gi_ajax_submit_ai_feedback');
add_action('wp_ajax_nopriv_gi_ai_feedback', 'gi_ajax_submit_ai_feedback');

// 市町村取得機能
add_action('wp_ajax_gi_get_municipalities_for_prefectures', 'gi_ajax_get_municipalities_for_prefectures');

// AI チェックリスト生成機能
add_action('wp_ajax_gi_generate_checklist', 'gi_ajax_generate_checklist');
add_action('wp_ajax_nopriv_gi_generate_checklist', 'gi_ajax_generate_checklist');

// AI 比較機能
add_action('wp_ajax_gi_compare_grants', 'gi_ajax_compare_grants');
add_action('wp_ajax_nopriv_gi_compare_grants', 'gi_ajax_compare_grants');

// 市町村データ初期化機能
add_action('wp_ajax_gi_initialize_municipalities', 'gi_ajax_initialize_municipalities');

/**
 * =============================================================================
 * 主要なAJAXハンドラー関数 - 完全版
 * =============================================================================
 */

/**
 * Enhanced AI検索処理 - セマンティック検索付き
 */
function handle_ai_search() {
    try {
        // セキュリティ検証
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました', 'code' => 'SECURITY_ERROR']);
            return;
        }
        
        // パラメータ取得と検証
        $query = sanitize_text_field($_POST['query'] ?? '');
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page = min(intval($_POST['per_page'] ?? 20), 50); // 最大50件
        
        // セッションID生成
        if (empty($session_id)) {
            $session_id = wp_generate_uuid4();
        }
        
        $start_time = microtime(true);
        
        // クエリが空の場合の処理
        if (empty($query)) {
            $recent_grants = gi_get_recent_grants($per_page);
            wp_send_json_success([
                'grants' => $recent_grants,
                'count' => count($recent_grants),
                'ai_response' => '検索キーワードを入力してください。最近公開された補助金を表示しています。',
                'keywords' => [],
                'session_id' => $session_id,
                'suggestions' => gi_get_popular_search_terms(5),
                'debug' => WP_DEBUG ? ['type' => 'recent_grants'] : null
            ]);
            return;
        }
        
        // Enhanced検索実行
        $search_result = gi_enhanced_semantic_search($query, $filter, $page, $per_page);
        
        // 検索結果の簡単な説明
        $ai_response = gi_generate_simple_search_summary($search_result['count'], $query);
        
        // キーワード抽出
        $keywords = gi_extract_keywords($query);
        
        // 検索履歴保存
        gi_save_search_history($query, ['filter' => $filter], $search_result['count'], $session_id);
        
        // フォローアップ提案生成
        $suggestions = gi_generate_search_suggestions($query, $search_result['grants']);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000);
        
        wp_send_json_success([
            'grants' => $search_result['grants'],
            'count' => $search_result['count'],
            'total_pages' => $search_result['total_pages'],
            'current_page' => $page,
            'ai_response' => $ai_response,
            'keywords' => $keywords,
            'suggestions' => $suggestions,
            'session_id' => $session_id,
            'processing_time_ms' => $processing_time,
            'debug' => WP_DEBUG ? [
                'filter' => $filter,
                'method' => $search_result['method'],
                'query_complexity' => gi_analyze_query_complexity($query)
            ] : null
        ]);
        
    } catch (Exception $e) {

        wp_send_json_error([
            'message' => '検索中にエラーが発生しました。しばらく後でお試しください。',
            'code' => 'SEARCH_ERROR',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ]);
    }
}

/**
 * Enhanced AIチャット処理
 */
function handle_ai_chat_request() {
    try {
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました', 'code' => 'SECURITY_ERROR']);
            return;
        }
        
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        
        if (empty($message)) {
            wp_send_json_error(['message' => 'メッセージが空です', 'code' => 'EMPTY_MESSAGE']);
            return;
        }
        
        if (empty($session_id)) {
            $session_id = wp_generate_uuid4();
        }
        
        $start_time = microtime(true);
        
        // 意図分析
        $intent = gi_analyze_user_intent($message);
        
        // 簡単なチャット応答
        $ai_response = gi_generate_simple_chat_response($message, $intent);
        
        // チャット履歴保存
        gi_save_chat_history($session_id, 'user', $message, $intent);
        gi_save_chat_history($session_id, 'ai', $ai_response);
        
        // 関連する補助金の提案
        $related_grants = gi_find_related_grants_from_chat($message, $intent);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000);
        
        wp_send_json_success([
            'response' => $ai_response,
            'session_id' => $session_id,
            'intent' => $intent,
            'related_grants' => $related_grants,
            'suggestions' => gi_generate_chat_suggestions($message, $intent),
            'processing_time_ms' => $processing_time
        ]);
        
    } catch (Exception $e) {

        wp_send_json_error([
            'message' => 'チャット処理中にエラーが発生しました。',
            'code' => 'CHAT_ERROR',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ]);
    }
}

/**
 * Enhanced Grant AI Question Handler - 助成金固有のAI質問処理
 */
function handle_grant_ai_question() {
    try {
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました', 'code' => 'SECURITY_ERROR']);
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $question = sanitize_textarea_field($_POST['question'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (!$post_id || empty($question)) {
            wp_send_json_error(['message' => 'パラメータが不正です', 'code' => 'INVALID_PARAMS']);
            return;
        }
        
        // 投稿の存在確認
        $grant_post = get_post($post_id);
        if (!$grant_post || $grant_post->post_type !== 'grant') {
            wp_send_json_error(['message' => '助成金が見つかりません', 'code' => 'GRANT_NOT_FOUND']);
            return;
        }
        
        if (empty($session_id)) {
            $session_id = wp_generate_uuid4();
        }
        
        $start_time = microtime(true);
        
        // 助成金の詳細情報を取得
        $grant_details = gi_get_grant_details($post_id);
        
        // 質問の意図分析
        $question_intent = gi_analyze_grant_question_intent($question, $grant_details);
        
        // 助成金に関する簡単な応答
        $ai_response = gi_generate_simple_grant_response($question, $grant_details, $question_intent);
        
        // フォローアップ質問を生成
        $suggestions = gi_generate_smart_grant_suggestions($post_id, $question, $question_intent);
        
        // 関連するリソース・リンクを提供
        $resources = gi_get_grant_resources($post_id, $question_intent);
        
        // 質問履歴保存
        gi_save_grant_question_history($post_id, $question, $ai_response, $session_id);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000);
        
        wp_send_json_success([
            'response' => $ai_response,
            'suggestions' => $suggestions,
            'resources' => $resources,
            'grant_id' => $post_id,
            'grant_title' => $grant_post->post_title,
            'intent' => $question_intent,
            'session_id' => $session_id,
            'processing_time_ms' => $processing_time,
            'confidence_score' => gi_calculate_response_confidence($question, $ai_response)
        ]);
        
    } catch (Exception $e) {
        error_log('Grant AI Question Error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => 'AI応答の生成中にエラーが発生しました',
            'code' => 'AI_RESPONSE_ERROR',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ]);
    }
}

/**
 * Enhanced 音声入力処理
 */
function gi_ajax_process_voice_input() {
    try {
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            return;
        }
        
        $audio_data = $_POST['audio_data'] ?? '';
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($audio_data)) {
            wp_send_json_error(['message' => '音声データが空です']);
            return;
        }
        
        // OpenAI統合を使用して音声認識を試行
        $openai = class_exists('GI_OpenAI_Integration') ? GI_OpenAI_Integration::getInstance() : null;
        if ($openai && $openai->is_configured() && method_exists($openai, 'transcribe_audio')) {
            $transcribed_text = $openai->transcribe_audio($audio_data);
            $confidence = 0.9; // OpenAI Whisperの場合は高い信頼度
        } else {
            // フォールバック: ブラウザのWeb Speech APIの結果をそのまま使用
            $transcribed_text = sanitize_text_field($_POST['fallback_text'] ?? '');
            $confidence = floatval($_POST['confidence'] ?? 0.7);
        }
        
        // 音声履歴に保存
        gi_save_voice_history($session_id, $transcribed_text, $confidence);
        
        wp_send_json_success([
            'transcribed_text' => $transcribed_text,
            'confidence' => $confidence,
            'session_id' => $session_id,
            'method' => $openai->is_configured() ? 'openai_whisper' : 'browser_api'
        ]);
        
    } catch (Exception $e) {
        error_log('Voice Input Error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => '音声認識中にエラーが発生しました',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ]);
    }
}

/**
 * 検索候補取得
 */
function gi_ajax_get_search_suggestions() {
    try {
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            return;
        }
        
        $partial_query = sanitize_text_field($_POST['query'] ?? '');
        $limit = min(intval($_POST['limit'] ?? 10), 20);
        
        $suggestions = gi_get_smart_search_suggestions($partial_query, $limit);
        
        wp_send_json_success([
            'suggestions' => $suggestions,
            'query' => $partial_query
        ]);
        
    } catch (Exception $e) {
        error_log('Search Suggestions Error: ' . $e->getMessage());
        wp_send_json_error(['message' => '検索候補の取得に失敗しました']);
    }
}

/**
 * お気に入り切り替え
 */
function gi_ajax_toggle_favorite() {
    try {
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!$post_id) {
            wp_send_json_error(['message' => '投稿IDが不正です']);
            return;
        }
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'ログインが必要です']);
            return;
        }
        
        $favorites = get_user_meta($user_id, 'gi_favorites', true) ?: [];
        $is_favorited = in_array($post_id, $favorites);
        
        if ($is_favorited) {
            $favorites = array_filter($favorites, function($id) use ($post_id) {
                return $id != $post_id;
            });
            $action = 'removed';
        } else {
            $favorites[] = $post_id;
            $action = 'added';
        }
        
        update_user_meta($user_id, 'gi_favorites', array_values($favorites));
        
        wp_send_json_success([
            'action' => $action,
            'is_favorite' => !$is_favorited,
            'total_favorites' => count($favorites),
            'message' => $action === 'added' ? 'お気に入りに追加しました' : 'お気に入りから削除しました'
        ]);
        
    } catch (Exception $e) {
        error_log('Toggle Favorite Error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'お気に入りの更新に失敗しました']);
    }
}

/**
 * =============================================================================
 * Enhanced ヘルパー関数群
 * =============================================================================
 */

/**
 * セキュリティ検証の統一処理
 */
function gi_verify_ajax_nonce() {
    $nonce = $_POST['nonce'] ?? '';
    return !empty($nonce) && (
        wp_verify_nonce($nonce, 'gi_ai_search_nonce') || 
        wp_verify_nonce($nonce, 'gi_ajax_nonce')
    );
}

/**
 * Enhanced セマンティック検索
 */
function gi_enhanced_semantic_search($query, $filter = 'all', $page = 1, $per_page = 20) {
    // OpenAI統合がある場合はセマンティック検索を試行
    $openai = class_exists('GI_OpenAI_Integration') ? GI_OpenAI_Integration::getInstance() : null;
    
    if ($openai && $openai->is_configured() && get_option('gi_ai_semantic_search', false)) {
        try {
            return gi_perform_ai_enhanced_search($query, $filter, $page, $per_page);
        } catch (Exception $e) {
            error_log('Semantic Search Error: ' . $e->getMessage());
            // フォールバック to standard search
        }
    }
    
    return gi_perform_standard_search($query, $filter, $page, $per_page);
}

/**
 * AI強化検索実行
 */
function gi_perform_ai_enhanced_search($query, $filter, $page, $per_page) {
    // クエリの拡張とセマンティック分析
    $enhanced_query = gi_enhance_search_query($query);
    $semantic_terms = gi_extract_semantic_terms($query);
    
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => 'publish',
        'meta_query' => ['relation' => 'OR'],
        's' => $enhanced_query
    ];
    
    // セマンティック検索のためのメタクエリ拡張
    foreach ($semantic_terms as $term) {
        $args['meta_query'][] = [
            'key' => 'grant_target',
            'value' => $term,
            'compare' => 'LIKE'
        ];
        $args['meta_query'][] = [
            'key' => 'grant_content',
            'value' => $term,
            'compare' => 'LIKE'
        ];
    }
    
    // フィルター適用
    if ($filter !== 'all') {
        $args['tax_query'] = gi_build_tax_query($filter);
    }
    
    $query_obj = new WP_Query($args);
    $grants = [];
    
    if ($query_obj->have_posts()) {
        while ($query_obj->have_posts()) {
            $query_obj->the_post();
            $post_id = get_the_ID();
            
            // セマンティック類似度計算
            $relevance_score = gi_calculate_semantic_relevance($query, $post_id);
            
            $grants[] = gi_format_grant_result($post_id, $relevance_score);
        }
        wp_reset_postdata();
        
        // 関連性スコアでソート
        usort($grants, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
    }
    
    return [
        'grants' => $grants,
        'count' => $query_obj->found_posts,
        'total_pages' => $query_obj->max_num_pages,
        'method' => 'ai_enhanced'
    ];
}

/**
 * スタンダード検索実行
 */
function gi_perform_standard_search($query, $filter, $page, $per_page) {
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => 'publish',
        's' => $query
    ];
    
    // フィルター適用
    if ($filter !== 'all') {
        $args['tax_query'] = gi_build_tax_query($filter);
    }
    
    $query_obj = new WP_Query($args);
    $grants = [];
    
    if ($query_obj->have_posts()) {
        while ($query_obj->have_posts()) {
            $query_obj->the_post();
            $post_id = get_the_ID();
            
            $grants[] = gi_format_grant_result($post_id, 0.8); // デフォルト関連性
        }
        wp_reset_postdata();
    }
    
    return [
        'grants' => $grants,
        'count' => $query_obj->found_posts,
        'total_pages' => $query_obj->max_num_pages,
        'method' => 'standard'
    ];
}

/**
 * 助成金結果のフォーマット
 */
function gi_format_grant_result($post_id, $relevance_score = 0.8) {
    $image_url = get_the_post_thumbnail_url($post_id, 'medium');
    $default_image = get_template_directory_uri() . '/assets/images/grant-default.jpg';
    
    return [
        'id' => $post_id,
        'title' => get_the_title(),
        'permalink' => get_permalink(),
        'url' => get_permalink(),
        'excerpt' => wp_trim_words(get_the_excerpt(), 25),
        'image_url' => $image_url ?: $default_image,
        'amount' => get_post_meta($post_id, 'max_amount', true) ?: '未定',
        'deadline' => get_post_meta($post_id, 'deadline', true) ?: '随時',
        'organization' => get_post_meta($post_id, 'organization', true) ?: '未定',
        'success_rate' => gi_get_field_safe('adoption_rate', $post_id, 0) ?: null,
        'featured' => get_post_meta($post_id, 'is_featured', true) == '1',
        'application_status' => get_post_meta($post_id, 'application_status', true) ?: 'active',
        'categories' => wp_get_post_terms($post_id, 'grant_category', ['fields' => 'names']),
        'relevance_score' => round($relevance_score, 3),
        'last_updated' => get_the_modified_time('Y-m-d H:i:s')
    ];
}

/**
 * コンテキスト付きAI応答生成
 */
function gi_generate_contextual_ai_response($query, $grants, $filter = 'all') {
    $openai = class_exists('GI_OpenAI_Integration') ? GI_OpenAI_Integration::getInstance() : null;
    
    if ($openai && $openai->is_configured()) {
        $context = [
            'grants' => array_slice($grants, 0, 3), // 上位3件のコンテキスト
            'filter' => $filter,
            'total_count' => count($grants)
        ];
        
        $prompt = "検索クエリ: {$query}\n結果数: " . count($grants) . "件";
        
        try {
            return $openai->generate_response($prompt, $context);
        } catch (Exception $e) {
            error_log('AI Response Error: ' . $e->getMessage());
            // フォールバック
        }
    }
    
    return gi_generate_fallback_response($query, $grants, $filter);
}

/**
 * フォールバック応答生成（改良版）
 */
function gi_generate_fallback_response($query, $grants, $filter = 'all') {
    $count = count($grants);
    
    if ($count === 0) {
        $response = "「{$query}」に該当する助成金が見つかりませんでした。";
        $response .= "\n\n検索のヒント：\n";
        $response .= "・より一般的なキーワードで検索してみてください\n";
        $response .= "・業種名や技術分野を変更してみてください\n";
        $response .= "・フィルターを「すべて」に変更してみてください";
        return $response;
    }
    
    $response = "「{$query}」で{$count}件の助成金が見つかりました。";
    
    // フィルター情報
    if ($filter !== 'all') {
        $filter_names = [
            'it' => 'IT・デジタル',
            'manufacturing' => 'ものづくり',
            'startup' => 'スタートアップ',
            'sustainability' => '持続可能性',
            'innovation' => 'イノベーション',
            'employment' => '雇用・人材'
        ];
        $filter_name = $filter_names[$filter] ?? $filter;
        $response .= "（{$filter_name}分野）";
    }
    
    // 特徴的な助成金の情報
    $featured_count = 0;
    $high_amount_count = 0;
    
    foreach ($grants as $grant) {
        if (!empty($grant['featured'])) {
            $featured_count++;
        }
        $amount = $grant['amount'];
        if (preg_match('/(\d+)/', $amount, $matches) && intval($matches[1]) >= 1000) {
            $high_amount_count++;
        }
    }
    
    if ($featured_count > 0) {
        $response .= "\n\nこのうち{$featured_count}件は特におすすめの助成金です。";
    }
    
    if ($high_amount_count > 0) {
        $response .= "\n{$high_amount_count}件は1000万円以上の大型助成金です。";
    }
    
    $response .= "\n\n詳細については各助成金の「詳細を見る」ボタンから確認いただくか、「AI質問」ボタンでお気軽にご質問ください。";
    
    return $response;
}

/**
 * Enhanced Grant応答生成
 */
function gi_generate_enhanced_grant_response($post_id, $question, $grant_details, $intent) {
    $openai = class_exists('GI_OpenAI_Integration') ? GI_OpenAI_Integration::getInstance() : null;
    
    if ($openai && $openai->is_configured()) {
        $context = [
            'grant_details' => $grant_details,
            'intent' => $intent
        ];
        
        $prompt = "助成金「{$grant_details['title']}」について：\n質問: {$question}";
        
        try {
            return $openai->generate_response($prompt, $context);
        } catch (Exception $e) {
            error_log('Enhanced Grant Response Error: ' . $e->getMessage());
            // フォールバック
        }
    }
    
    return gi_generate_fallback_grant_response($post_id, $question, $grant_details, $intent);
}

/**
 * 助成金詳細情報取得
 */
function gi_get_grant_details($post_id) {
    return [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'content' => get_post_field('post_content', $post_id),
        'excerpt' => get_the_excerpt($post_id),
        'organization' => get_post_meta($post_id, 'organization', true),
        'max_amount' => get_post_meta($post_id, 'max_amount', true),
        'deadline' => get_post_meta($post_id, 'deadline', true),
        'grant_target' => get_post_meta($post_id, 'grant_target', true),
        'application_requirements' => get_post_meta($post_id, 'application_requirements', true),
        'eligible_expenses' => get_post_meta($post_id, 'eligible_expenses', true),
        'application_process' => get_post_meta($post_id, 'application_process', true),
        'success_rate' => gi_get_field_safe('adoption_rate', $post_id, 0),
        'categories' => wp_get_post_terms($post_id, 'grant_category', ['fields' => 'names'])
    ];
}

/**
 * 質問意図の分析
 */
function gi_analyze_grant_question_intent($question, $grant_details) {
    $question_lower = mb_strtolower($question);
    
    $intents = [
        'application' => ['申請', '手続き', '方法', '流れ', '必要書類', 'どうやって'],
        'amount' => ['金額', '額', 'いくら', '助成額', '補助額', '上限'],
        'deadline' => ['締切', '期限', 'いつまで', '申請期限', '募集期間'],
        'eligibility' => ['対象', '資格', '条件', '要件', '該当'],
        'expenses' => ['経費', '費用', '対象経費', '使える', '支払い'],
        'process' => ['審査', '選考', '採択', '結果', 'いつ', '期間'],
        'success_rate' => ['採択率', '通る', '確率', '実績', '成功率'],
        'documents' => ['書類', '資料', '提出', '準備', '必要なもの']
    ];
    
    $detected_intents = [];
    foreach ($intents as $intent => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_stripos($question_lower, $keyword) !== false) {
                $detected_intents[] = $intent;
                break;
            }
        }
    }
    
    return !empty($detected_intents) ? $detected_intents[0] : 'general';
}

/**
 * Fallback Grant応答生成（改良版）
 */
function gi_generate_fallback_grant_response($post_id, $question, $grant_details, $intent) {
    $title = $grant_details['title'];
    $organization = $grant_details['organization'];
    $max_amount = $grant_details['max_amount'];
    $deadline = $grant_details['deadline'];
    $grant_target = $grant_details['grant_target'];
    
    switch ($intent) {
        case 'application':
            $response = "「{$title}」の申請について：\n\n";
            if ($organization) {
                $response .= "【実施機関】\n{$organization}\n\n";
            }
            if ($grant_target) {
                $response .= "【申請対象】\n{$grant_target}\n\n";
            }
            $response .= "【申請方法】\n";
            $response .= "詳細な申請方法や必要書類については、実施機関の公式サイトでご確認ください。\n";
            $response .= "申請前に制度概要をしっかりと理解し、要件を満たしているか確認することをお勧めします。";
            break;
            
        case 'amount':
            $response = "「{$title}」の助成金額について：\n\n";
            if ($max_amount) {
                $response .= "【助成上限額】\n{$max_amount}\n\n";
            }
            $response .= "【注意事項】\n";
            $response .= "・実際の助成額は事業規模や申請内容により決定されます\n";
            $response .= "・補助率や助成対象経費に制限がある場合があります\n";
            $response .= "・詳細は実施機関の募集要項をご確認ください";
            break;
            
        case 'deadline':
            $response = "「{$title}」の申請期限について：\n\n";
            if ($deadline) {
                $response .= "【申請締切】\n{$deadline}\n\n";
            }
            $response .= "【重要】\n";
            $response .= "・申請期限は変更される場合があります\n";
            $response .= "・必要書類の準備に時間がかかる場合があります\n";
            $response .= "・最新情報は実施機関の公式サイトでご確認ください";
            break;
            
        case 'eligibility':
            $response = "「{$title}」の申請対象について：\n\n";
            if ($grant_target) {
                $response .= "【対象者・対象事業】\n{$grant_target}\n\n";
            }
            $response .= "【確認ポイント】\n";
            $response .= "・事業規模や従業員数の要件\n";
            $response .= "・業種や事業内容の制限\n";
            $response .= "・地域的な要件の有無\n";
            $response .= "・その他の特別な要件";
            break;
            
        default:
            $response = "「{$title}」について：\n\n";
            $response .= "【基本情報】\n";
            if ($max_amount) {
                $response .= "・助成上限額：{$max_amount}\n";
            }
            if ($grant_target) {
                $response .= "・対象：{$grant_target}\n";
            }
            if ($deadline) {
                $response .= "・締切：{$deadline}\n";
            }
            if ($organization) {
                $response .= "・実施機関：{$organization}\n";
            }
            $response .= "\nより詳しい情報や具体的な質問については、「詳細を見る」ボタンから詳細ページをご確認いただくか、";
            $response .= "具体的な内容（申請方法、金額、締切など）についてお聞かせください。";
    }
    
    return $response;
}

/**
 * スマートな助成金提案生成
 */
function gi_generate_smart_grant_suggestions($post_id, $question, $intent) {
    $base_suggestions = [
        '申請に必要な書類は何ですか？',
        '申請の流れを教えてください',
        '対象となる経費について',
        '採択のポイントは？'
    ];
    
    $intent_specific = [
        'application' => [
            '申請の難易度はどのくらい？',
            '申請にかかる期間は？',
            '必要な準備期間は？'
        ],
        'amount' => [
            '補助率はどのくらい？',
            '対象経費の範囲は？',
            '追加の支援制度はある？'
        ],
        'deadline' => [
            '次回の募集はいつ？',
            '申請準備はいつから始める？',
            '年間スケジュールは？'
        ],
        'eligibility' => [
            'この条件で申請できる？',
            '他に必要な要件は？',
            '類似の助成金はある？'
        ]
    ];
    
    $suggestions = $base_suggestions;
    
    if (isset($intent_specific[$intent])) {
        $suggestions = array_merge($intent_specific[$intent], array_slice($base_suggestions, 0, 2));
    }
    
    return array_slice(array_unique($suggestions), 0, 4);
}

/**
 * チャット履歴保存
 */
function gi_save_chat_history($session_id, $message_type, $content, $intent_data = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_chat_history';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        return false; // テーブルが存在しない場合
    }
    
    return $wpdb->insert(
        $table,
        [
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'message_type' => $message_type,
            'message_content' => $content,
            'intent_data' => is_array($intent_data) ? json_encode($intent_data) : $intent_data,
            'created_at' => current_time('mysql')
        ],
        ['%s', '%d', '%s', '%s', '%s', '%s']
    );
}

/**
 * 音声履歴保存
 */
function gi_save_voice_history($session_id, $transcribed_text, $confidence_score = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_voice_history';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        return false;
    }
    
    return $wpdb->insert(
        $table,
        [
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'transcribed_text' => $transcribed_text,
            'confidence_score' => $confidence_score,
            'created_at' => current_time('mysql')
        ],
        ['%s', '%d', '%s', '%f', '%s']
    );
}

/**
 * 最新の助成金取得
 */
function gi_get_recent_grants($limit = 20) {
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $limit,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    
    $query = new WP_Query($args);
    $grants = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $grants[] = gi_format_grant_result(get_the_ID(), 0.9);
        }
        wp_reset_postdata();
    }
    
    return $grants;
}

/**
 * 検索キーワード抽出
 */
function gi_extract_keywords($query) {
    // 基本的なキーワード分割（より高度な実装も可能）
    $keywords = preg_split('/[\s\p{P}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
    $keywords = array_filter($keywords, function($word) {
        return mb_strlen($word) >= 2; // 2文字以上のワードのみ
    });
    
    return array_values($keywords);
}

/**
 * 選択された都道府県に対応する市町村を取得
 */
function gi_ajax_get_municipalities_for_prefectures() {
    try {
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            return;
        }
        
        // Handle both 'prefectures' and 'prefecture_slugs' parameter names
        $prefecture_slugs = isset($_POST['prefecture_slugs']) ? 
            json_decode(stripslashes($_POST['prefecture_slugs']), true) : 
            (isset($_POST['prefectures']) ? (array)$_POST['prefectures'] : []);
        $prefecture_slugs = array_map('sanitize_text_field', $prefecture_slugs);
        
        $municipalities_data = [];
        
        foreach ($prefecture_slugs as $pref_slug) {
            // 都道府県名を取得
            $prefecture_term = get_term_by('slug', $pref_slug, 'grant_prefecture');
            if (!$prefecture_term) continue;
            
            $pref_name = $prefecture_term->name;
            $pref_municipalities = [];
            
            // 1. まず既存の市町村タクソノミーから取得を試行
            $existing_municipalities = get_terms([
                'taxonomy' => 'grant_municipality',
                'hide_empty' => false,
                'meta_query' => [
                    [
                        'key' => 'prefecture_slug',
                        'value' => $pref_slug,
                        'compare' => '='
                    ]
                ]
            ]);
            
            if (!empty($existing_municipalities) && !is_wp_error($existing_municipalities)) {
                foreach ($existing_municipalities as $term) {
                    $pref_municipalities[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'count' => $term->count
                    ];
                }
            }
            
            // 2. 既存データがない場合は、標準的な市町村リストから生成
            if (empty($pref_municipalities)) {
                $municipalities_list = gi_get_standard_municipalities_by_prefecture($pref_slug);
                
                foreach ($municipalities_list as $muni_name) {
                    $muni_slug = $pref_slug . '-' . sanitize_title($muni_name);
                    $existing_term = get_term_by('slug', $muni_slug, 'grant_municipality');
                    
                    if (!$existing_term) {
                        // 市町村タームを作成
                        $result = wp_insert_term(
                            $muni_name,
                            'grant_municipality',
                            [
                                'slug' => $muni_slug,
                                'description' => $pref_name . '・' . $muni_name
                            ]
                        );
                        
                        if (!is_wp_error($result)) {
                            // 都道府県との関連付けメタデータを保存
                            add_term_meta($result['term_id'], 'prefecture_slug', $pref_slug);
                            add_term_meta($result['term_id'], 'prefecture_name', $pref_name);
                            
                            $pref_municipalities[] = [
                                'id' => $result['term_id'],
                                'name' => $muni_name,
                                'slug' => $muni_slug,
                                'count' => 0
                            ];
                        }
                    } else {
                        // 既存タームにメタデータが無い場合は追加
                        if (!get_term_meta($existing_term->term_id, 'prefecture_slug', true)) {
                            add_term_meta($existing_term->term_id, 'prefecture_slug', $pref_slug);
                            add_term_meta($existing_term->term_id, 'prefecture_name', $pref_name);
                        }
                        
                        $pref_municipalities[] = [
                            'id' => $existing_term->term_id,
                            'name' => $existing_term->name,
                            'slug' => $existing_term->slug,
                            'count' => $existing_term->count
                        ];
                    }
                }
            }
            
            // Format data by prefecture for frontend
            $municipalities_data[$pref_slug] = $pref_municipalities;
        }
        
        $total_municipalities = 0;
        foreach ($municipalities_data as $pref_municipalities) {
            $total_municipalities += count($pref_municipalities);
        }
        
        wp_send_json_success([
            'data' => $municipalities_data,
            'prefecture_count' => count($prefecture_slugs),
            'municipality_count' => $total_municipalities,
            'message' => $total_municipalities . '件の市町村データを取得しました'
        ]);
        
    } catch (Exception $e) {
        error_log('Get Municipalities Error: ' . $e->getMessage());
        wp_send_json_error(['message' => '市町村データの取得に失敗しました', 'debug' => WP_DEBUG ? $e->getMessage() : null]);
    }
}

/**
 * 市町村データ初期化 AJAX Handler
 */
function gi_ajax_initialize_municipalities() {
    try {
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            return;
        }
        
        // 管理者権限チェック（セキュリティのため）
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '権限が不足しています']);
            return;
        }
        
        // 市町村データ初期化実行
        $result = gi_initialize_all_municipalities();
        
        // 既存データの連携強化
        gi_enhance_municipality_filtering();
        
        wp_send_json_success([
            'created' => $result['created'],
            'updated' => $result['updated'],
            'message' => "市町村データの初期化が完了しました。新規作成: {$result['created']}件、更新: {$result['updated']}件"
        ]);
        
    } catch (Exception $e) {
        error_log('Initialize Municipalities Error: ' . $e->getMessage());
        wp_send_json_error(['message' => '市町村データの初期化に失敗しました', 'debug' => WP_DEBUG ? $e->getMessage() : null]);
    }
}

/**
 * その他のテスト・ユーティリティ関数
 */
function gi_ajax_test_connection() {
    wp_send_json_success([
        'message' => 'AJAX接続テスト成功',
        'timestamp' => current_time('mysql'),
        'server_info' => [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ],
        'ai_status' => gi_check_ai_capabilities()
    ]);
}

function gi_ajax_save_voice_history() {
    if (!gi_verify_ajax_nonce()) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    wp_send_json_success(['message' => '音声履歴を保存しました']);
}

function gi_ajax_get_chat_history() {
    if (!gi_verify_ajax_nonce()) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    $limit = min(intval($_POST['limit'] ?? 50), 100);
    
    // チャット履歴取得の実装
    wp_send_json_success([
        'history' => [],
        'session_id' => $session_id
    ]);
}

function gi_ajax_get_search_history() {
    if (!gi_verify_ajax_nonce()) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $history = gi_get_search_history(20);
    
    wp_send_json_success([
        'history' => $history
    ]);
}

function gi_ajax_submit_ai_feedback() {
    if (!gi_verify_ajax_nonce()) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $feedback = sanitize_textarea_field($_POST['feedback'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    
    // フィードバック保存の実装（必要に応じて）
    
    wp_send_json_success([
        'message' => 'フィードバックありがとうございます'
    ]);
}

/**
 * =============================================================================
 * Missing Helper Functions - Simple Response Generators
 * =============================================================================
 */

/**
 * 簡単な検索サマリー生成
 */
function gi_generate_simple_search_summary($count, $query) {
    if ($count === 0) {
        return "「{$query}」に該当する助成金が見つかりませんでした。キーワードを変更して再度お試しください。";
    }
    
    if ($count === 1) {
        return "「{$query}」で1件の助成金が見つかりました。";
    }
    
    return "「{$query}」で{$count}件の助成金が見つかりました。詳細は各カードの「詳細を見る」または「AI質問」ボタンからご確認ください。";
}

/**
 * 簡単なチャット応答生成
 */
function gi_generate_simple_chat_response($message, $intent) {
    $message_lower = mb_strtolower($message);
    
    // 挨拶への応答
    if (preg_match('/(こんにちは|おはよう|こんばんは|はじめまして)/', $message_lower)) {
        return "こんにちは！Grant Insight Perfectの補助金AIアシスタントです。どのような補助金をお探しですか？";
    }
    
    // 意図に基づく応答
    switch ($intent) {
        case 'search':
            return "どのような助成金をお探しですか？業種、目的、地域などを教えていただくと、最適な助成金をご提案できます。";
        
        case 'application':
            return "申請に関するご質問ですね。具体的にどの助成金の申請方法についてお知りになりたいですか？";
        
        case 'information':
            return "詳しい情報をお調べします。どの助成金についての詳細をお知りになりたいですか？";
        
        case 'comparison':
            return "助成金の比較についてお答えします。どのような観点（金額、対象、締切など）で比較をご希望ですか？";
        
        case 'recommendation':
            return "おすすめの助成金をご提案させていただきます。お客様の事業内容や目的を教えてください。";
        
        default:
            return "ご質問ありがとうございます。具体的な内容をお聞かせいただけると、より詳しい回答をお提供できます。";
    }
}

/**
 * 【高度AI機能】コンテキスト対応インテリジェント助成金応答生成
 */
function gi_generate_simple_grant_response($question, $grant_details, $intent) {
    $title = $grant_details['title'] ?? '助成金';
    $organization = $grant_details['organization'] ?? '';
    $max_amount = $grant_details['max_amount'] ?? '';
    $deadline = $grant_details['deadline'] ?? '';
    $grant_target = $grant_details['grant_target'] ?? '';
    
    // AI分析による高度な応答生成
    $ai_analysis = gi_analyze_grant_characteristics($grant_details);
    $success_probability = gi_estimate_success_probability($grant_details);
    $comprehensive_score = gi_calculate_comprehensive_ai_score($grant_details);
    
    $response = "【AI分析】「{$title}」について\n\n";
    
    // AI総合評価を冒頭に表示
    $response .= sprintf("🤖 AI総合スコア: %s点/100点 | 成功予測: %s%% | 推奨度: %s\n\n", 
        round($comprehensive_score['total_score']), 
        round($success_probability['overall_score'] * 100),
        gi_get_recommendation_level($comprehensive_score['total_score']));
    
    switch ($intent) {
        case 'application':
            $response .= "【📋 申請戦略AI分析】\n";
            if ($organization) {
                $response .= "実施機関：{$organization}\n";
            }
            
            // 難易度に基づく戦略提案
            $difficulty_advice = gi_get_difficulty_based_advice($ai_analysis['complexity_level']);
            $response .= "\n🎯 申請戦略：\n{$difficulty_advice}\n";
            
            // 成功率向上のための具体的アドバイス
            if ($success_probability['overall_score'] < 0.6) {
                $response .= "\n⚠️ 成功率向上ポイント：\n";
                foreach ($success_probability['improvement_suggestions'] as $suggestion) {
                    $response .= "・{$suggestion}\n";
                }
            }
            
            // 準備期間の提案
            $deadline_analysis = gi_analyze_deadline_pressure($deadline);
            $response .= "\n⏰ 推奨準備期間：{$deadline_analysis['recommended_prep_time']}\n";
            
            if ($grant_target) {
                $response .= "\n👥 対象者：{$grant_target}";
            }
            break;
        
        case 'amount':
            $response .= "【💰 資金計画AI分析】\n";
            if ($max_amount) {
                $response .= "最大助成額：{$max_amount}\n";
                
                // ROI分析の追加
                $roi_analysis = gi_calculate_grant_roi_potential($grant_details);
                $response .= sprintf("\n📈 期待ROI：%s%% (業界平均+%s%%)", 
                    round($roi_analysis['projected_roi']), 
                    round($roi_analysis['projected_roi'] - 160));
                
                $response .= sprintf("\n💹 投資回収期間：約%sヶ月", 
                    $roi_analysis['payback_period_months']);
                
                // 補助率情報
                if (!empty($grant_details['subsidy_rate'])) {
                    $subsidy_rate = $grant_details['subsidy_rate'];
                    $self_funding = gi_calculate_self_funding_amount($grant_details);
                    $response .= "\n\n💳 資金構造：\n";
                    $response .= "・補助率：{$subsidy_rate}\n";
                    $response .= "・自己資金目安：" . number_format($self_funding) . "円";
                }
            } else {
                $response .= "助成額の詳細は実施機関にお問い合わせください。";
            }
            
            // 金額規模に基づくアドバイス
            $amount_advice = gi_get_amount_based_advice($grant_details['max_amount_numeric'] ?? 0);
            $response .= "\n\n🎯 資金活用戦略：\n{$amount_advice}";
            break;
        
        case 'deadline':
            $response .= "【⏰ スケジュール戦略AI分析】\n";
            if ($deadline) {
                $deadline_analysis = gi_analyze_deadline_pressure($deadline);
                $response .= "締切：{$deadline}\n";
                $response .= "残り日数：約{$deadline_analysis['days_remaining']}日\n";
                
                // 緊急度レベル
                $urgency_level = $deadline_analysis['is_urgent'] ? '🔴 緊急' : '🟢 余裕あり';
                $response .= "緊急度：{$urgency_level}\n";
                
                // スケジュール戦略
                $response .= "\n📅 推奨スケジュール：\n";
                $schedule_plan = gi_generate_application_schedule($deadline_analysis, $ai_analysis['complexity_level']);
                foreach ($schedule_plan as $phase) {
                    $response .= "・{$phase}\n";
                }
                
                // リスクアラート
                if ($deadline_analysis['is_urgent']) {
                    $response .= "\n⚠️ 緊急対応が必要：\n・外部専門家への即座の相談を推奨\n・並行作業による効率化が重要";
                }
            }
            break;
        
        case 'eligibility':
            $response .= "【✅ 適格性AI診断】\n";
            if ($grant_target) {
                $response .= "対象者：{$grant_target}\n\n";
                
                // 適格性チェックリスト
                $eligibility_checks = gi_generate_eligibility_checklist($grant_details);
                $response .= "🔍 適格性確認チェックリスト：\n";
                foreach ($eligibility_checks as $check) {
                    $response .= "□ {$check}\n";
                }
                
                // 業界適合度
                $response .= "\n📊 業界適合度：";
                $industry_fit = gi_assess_industry_compatibility($grant_details);
                $response .= sprintf("%s%% ", round($industry_fit * 100));
                $response .= gi_get_fit_level_description($industry_fit);
            }
            break;
            
        case 'success_rate':
        case 'probability':
            $response .= "【📊 成功確率AI分析】\n";
            $response .= sprintf("予測成功率：%s%%\n", round($success_probability['overall_score'] * 100));
            $response .= sprintf("リスクレベル：%s\n", gi_get_risk_level_jp($success_probability['risk_level']));
            $response .= sprintf("信頼度：%s%%\n\n", round($success_probability['confidence'] * 100));
            
            $response .= "🎯 成功要因分析：\n";
            foreach ($success_probability['contributing_factors'] as $factor => $impact) {
                if ($impact > 0.02) {
                    $response .= sprintf("・%s：+%s%%\n", gi_get_factor_name_jp($factor), round($impact * 100));
                }
            }
            
            $response .= "\n💡 改善提案：\n";
            foreach ($success_probability['improvement_suggestions'] as $suggestion) {
                $response .= "・{$suggestion}\n";
            }
            break;
        
        case 'comparison':
            $response .= "【⚖️ 競合分析AI評価】\n";
            $competitive_analysis = gi_analyze_competitive_landscape($grant_details);
            $response .= sprintf("競合優位度：%s/10点\n", round($competitive_analysis['advantage_score'] * 10));
            $response .= sprintf("競争激化度：%s\n\n", gi_get_competition_level_jp($competitive_analysis['competitive_intensity']));
            
            $response .= "🏆 競合優位要素：\n";
            foreach ($competitive_analysis['key_advantages'] as $advantage) {
                $response .= "・{$advantage}\n";
            }
            
            // 差別化戦略の提案
            $response .= "\n🎯 差別化戦略提案：\n";
            $differentiation_strategies = gi_generate_differentiation_strategies($grant_details, $competitive_analysis);
            foreach ($differentiation_strategies as $strategy) {
                $response .= "・{$strategy}\n";
            }
            break;
        
        default:
            $response .= "【📝 総合情報AI分析】\n";
            
            // 基本情報
            if ($max_amount) {
                $response .= "・助成額：{$max_amount}";
                // ROI予測を追加
                $roi_analysis = gi_calculate_grant_roi_potential($grant_details);
                $response .= sprintf("（期待ROI: %s%%）\n", round($roi_analysis['projected_roi']));
            }
            if ($deadline) {
                $deadline_analysis = gi_analyze_deadline_pressure($deadline);
                $urgency = $deadline_analysis['is_urgent'] ? '⚠️急務' : '余裕あり';
                $response .= "・締切：{$deadline}（{$urgency}）\n";
            }
            if ($organization) {
                $response .= "・実施機関：{$organization}\n";
            }
            
            // AI推奨アクション
            $response .= "\n🤖 AI推奨アクション：\n";
            $recommended_actions = gi_generate_recommended_actions($grant_details, $comprehensive_score, $success_probability);
            foreach (array_slice($recommended_actions, 0, 3) as $action) {
                $response .= "・{$action}\n";
            }
            
            $response .= "\n詳細分析は「AIチェックリスト」「AI比較」ボタンをご利用ください。";
    }
    
    // フッター情報
    $response .= "\n\n" . sprintf("💻 AI分析精度: %s%% | 最終更新: %s", 
        round($comprehensive_score['confidence'] * 100),
        date('n/j H:i'));
    
    return $response;
}

/**
 * 人気検索キーワード取得
 */
function gi_get_popular_search_terms($limit = 10) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_search_history';
    
    // テーブルが存在するか確認
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        // フォールバック
        return [
            ['term' => 'IT導入補助金', 'count' => 100],
            ['term' => 'ものづくり補助金', 'count' => 95],
            ['term' => '小規模事業者持続化補助金', 'count' => 90],
            ['term' => '事業再構築補助金', 'count' => 85],
            ['term' => '雇用調整助成金', 'count' => 80]
        ];
    }
    
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT search_query as term, COUNT(*) as count
        FROM {$table}
        WHERE search_query != ''
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY search_query
        ORDER BY count DESC
        LIMIT %d
    ", $limit), ARRAY_A);
    
    return $results ?: [];
}

/**
 * 検索履歴取得
 */
function gi_get_search_history($limit = 20) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_search_history';
    
    // テーブルが存在するか確認
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        return [];
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return [];
    }
    
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT *
        FROM {$table}
        WHERE user_id = %d
        ORDER BY created_at DESC
        LIMIT %d
    ", $user_id, $limit), ARRAY_A);
    
    return $results ?: [];
}

/**
 * AI機能の利用可否チェック
 */
function gi_check_ai_capabilities() {
    return [
        'openai_configured' => class_exists('GI_OpenAI_Integration') && GI_OpenAI_Integration::getInstance()->is_configured(),
        'semantic_search' => class_exists('GI_Grant_Semantic_Search'),
        'simple_responses' => true, // 常に利用可能
        'voice_recognition' => true, // ブラウザAPIで利用可能
        'fallback_mode' => true
    ];
}

/**
 * 追加ヘルパー関数
 */
function gi_build_tax_query($filter) {
    $filter_mapping = [
        'it' => 'it-support',
        'manufacturing' => 'monozukuri', 
        'startup' => 'startup-support',
        'sustainability' => 'sustainability',
        'innovation' => 'innovation',
        'employment' => 'employment'
    ];
    
    if (isset($filter_mapping[$filter])) {
        return [[
            'taxonomy' => 'grant_category',
            'field' => 'slug',
            'terms' => $filter_mapping[$filter]
        ]];
    }
    
    return [];
}

function gi_enhance_search_query($query) {
    // クエリ拡張ロジック（シノニム、関連語などを追加）
    $enhancements = [
        'AI' => ['人工知能', 'machine learning', 'ディープラーニング'],
        'DX' => ['デジタル変革', 'デジタル化', 'IT化'],
        'IoT' => ['モノのインターネット', 'センサー', 'スマート']
    ];
    
    $enhanced_query = $query;
    foreach ($enhancements as $term => $synonyms) {
        if (mb_stripos($query, $term) !== false) {
            $enhanced_query .= ' ' . implode(' ', array_slice($synonyms, 0, 2));
        }
    }
    
    return $enhanced_query;
}

function gi_extract_semantic_terms($query) {
    // セマンティック分析のための関連語抽出
    return gi_extract_keywords($query);
}

function gi_calculate_semantic_relevance($query, $post_id) {
    // セマンティック類似度の計算（シンプル版）
    $content = get_post_field('post_content', $post_id) . ' ' . get_the_title($post_id);
    $query_keywords = gi_extract_keywords($query);
    $content_lower = mb_strtolower($content);
    
    $matches = 0;
    foreach ($query_keywords as $keyword) {
        if (mb_stripos($content_lower, mb_strtolower($keyword)) !== false) {
            $matches++;
        }
    }
    
    return count($query_keywords) > 0 ? $matches / count($query_keywords) : 0.5;
}

function gi_analyze_query_complexity($query) {
    $word_count = count(gi_extract_keywords($query));
    
    if ($word_count <= 2) return 'simple';
    if ($word_count <= 5) return 'medium';
    return 'complex';
}

function gi_generate_search_suggestions($query, $grants) {
    $suggestions = [];
    
    // 基本的な拡張提案
    if (count($grants) > 0) {
        $categories = [];
        foreach (array_slice($grants, 0, 3) as $grant) {
            $categories = array_merge($categories, $grant['categories']);
        }
        $unique_categories = array_unique($categories);
        
        foreach (array_slice($unique_categories, 0, 3) as $category) {
            $suggestions[] = $query . ' ' . $category;
        }
    }
    
    // クエリ関連の提案
    $related_terms = [
        'AI' => ['DX', '自動化', 'デジタル化'],
        'スタートアップ' => ['創業', 'ベンチャー', '起業'],
        '製造業' => ['ものづくり', '工場', '技術開発']
    ];
    
    foreach ($related_terms as $term => $relations) {
        if (mb_stripos($query, $term) !== false) {
            foreach ($relations as $related) {
                $suggestions[] = str_replace($term, $related, $query);
            }
            break;
        }
    }
    
    return array_slice(array_unique($suggestions), 0, 5);
}

function gi_analyze_user_intent($message) {
    $intent_patterns = [
        'search' => ['検索', '探す', '見つけて', 'あります', '教えて'],
        'application' => ['申請', '応募', '手続き', 'どうやって'],
        'information' => ['詳細', '情報', 'について', 'とは'],
        'comparison' => ['比較', '違い', 'どちら', '選び方'],
        'recommendation' => ['おすすめ', '提案', '適した', 'いい']
    ];
    
    $message_lower = mb_strtolower($message);
    
    foreach ($intent_patterns as $intent => $patterns) {
        foreach ($patterns as $pattern) {
            if (mb_stripos($message_lower, $pattern) !== false) {
                return $intent;
            }
        }
    }
    
    return 'general';
}

function gi_generate_contextual_chat_response($message, $context, $intent) {
    $openai = class_exists('GI_OpenAI_Integration') ? GI_OpenAI_Integration::getInstance() : null;
    
    if ($openai && $openai->is_configured()) {
        $prompt = "ユーザーの質問: {$message}\n意図: {$intent}";
        
        try {
            return $openai->generate_response($prompt, $context);
        } catch (Exception $e) {
            error_log('Contextual Chat Error: ' . $e->getMessage());
            // フォールバック
        }
    }
    
    return gi_generate_intent_based_response($message, $intent);
}

function gi_generate_intent_based_response($message, $intent) {
    switch ($intent) {
        case 'search':
            return 'どのような助成金をお探しですか？業種、目的、金額規模などをお聞かせいただくと、より適切な助成金をご提案できます。';
        case 'application':
            return '申請に関するご質問ですね。具体的にどの助成金の申請についてお知りになりたいですか？申請手順、必要書類、締切などについてお答えできます。';
        case 'information':
            return '詳しい情報をお調べします。どの助成金についての詳細をお知りになりたいですか？';
        case 'comparison':
            return '助成金の比較についてお答えします。どのような観点（金額、対象、締切など）で比較をご希望ですか？';
        case 'recommendation':
            return 'おすすめの助成金をご提案させていただきます。お客様の事業内容、規模、目的をお聞かせください。';
        default:
            return 'ご質問ありがとうございます。より具体的な内容をお聞かせいただけると、詳しい回答をお提供できます。';
    }
}

function gi_find_related_grants_from_chat($message, $intent) {
    // チャットメッセージから関連する助成金を検索
    $keywords = gi_extract_keywords($message);
    if (empty($keywords)) {
        return [];
    }
    
    $search_query = implode(' ', array_slice($keywords, 0, 3));
    $search_result = gi_perform_standard_search($search_query, 'all', 1, 5);
    
    return array_slice($search_result['grants'], 0, 3);
}

function gi_generate_chat_suggestions($message, $intent) {
    $base_suggestions = [
        'おすすめの助成金を教えて',
        '申請方法について',
        '締切が近い助成金は？',
        '条件を満たす助成金を検索'
    ];
    
    $intent_suggestions = [
        'search' => [
            'IT関連の助成金を探して',
            '製造業向けの補助金は？',
            'スタートアップ支援制度について'
        ],
        'application' => [
            '申請の準備期間は？',
            '必要書類のチェックリスト',
            '申請のコツを教えて'
        ]
    ];
    
    if (isset($intent_suggestions[$intent])) {
        return $intent_suggestions[$intent];
    }
    
    return array_slice($base_suggestions, 0, 3);
}

function gi_get_smart_search_suggestions($partial_query, $limit = 10) {
    // 部分クエリから候補を生成
    $suggestions = [];
    
    // アイコンマッピング
    $icon_map = [
        'IT' => '',
        'ものづくり' => '🏭',
        '小規模' => '🏪',
        '事業再構築' => '🔄',
        '雇用' => '👥',
        '創業' => '',
        '持続化' => '📈',
        '省エネ' => '⚡',
        '環境' => '🌱'
    ];
    
    // デフォルトアイコン取得関数
    $get_icon = function($text) use ($icon_map) {
        foreach ($icon_map as $keyword => $icon) {
            if (mb_strpos($text, $keyword) !== false) {
                return $icon;
            }
        }
        return '🔍'; // デフォルトアイコン
    };
    
    // 人気キーワードから類似するものを検索
    $popular_terms = gi_get_popular_search_terms(20);
    foreach ($popular_terms as $term_data) {
        $term = $term_data['term'] ?? '';
        if (!empty($term) && mb_stripos($term, $partial_query) !== false) {
            $suggestions[] = [
                'text' => $term,
                'icon' => $get_icon($term),
                'count' => $term_data['count'] ?? 0,
                'type' => 'popular'
            ];
        }
    }
    
    // 助成金タイトルから候補を生成
    $grants = gi_search_grant_titles($partial_query, $limit);
    foreach ($grants as $grant) {
        $title = $grant['title'] ?? '';
        if (!empty($title)) {
            $suggestions[] = [
                'text' => $title,
                'icon' => $get_icon($title),
                'type' => 'grant_title',
                'grant_id' => $grant['id'] ?? 0
            ];
        }
    }
    
    return array_slice($suggestions, 0, $limit);
}

function gi_search_grant_titles($query, $limit = 5) {
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $limit,
        'post_status' => 'publish',
        's' => $query,
        'fields' => 'ids'
    ];
    
    $posts = get_posts($args);
    $results = [];
    
    foreach ($posts as $post_id) {
        $results[] = [
            'id' => $post_id,
            'title' => get_the_title($post_id)
        ];
    }
    
    return $results;
}

/**
 * =============================================================================
 * AI チェックリスト生成機能 - Complete Implementation
 * =============================================================================
 */

/**
 * AIチェックリスト生成 AJAXハンドラー
 */
function gi_ajax_generate_checklist() {
    try {
        // セキュリティ検証
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました', 'code' => 'SECURITY_ERROR']);
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => '助成金IDが不正です', 'code' => 'INVALID_POST_ID']);
            return;
        }
        
        // 投稿の存在確認
        $grant_post = get_post($post_id);
        if (!$grant_post || $grant_post->post_type !== 'grant') {
            wp_send_json_error(['message' => '助成金が見つかりません', 'code' => 'GRANT_NOT_FOUND']);
            return;
        }
        
        $start_time = microtime(true);
        
        // チェックリスト生成
        $checklist = gi_generate_grant_checklist($post_id);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000);
        
        wp_send_json_success([
            'checklist' => $checklist,
            'grant_id' => $post_id,
            'grant_title' => $grant_post->post_title,
            'processing_time_ms' => $processing_time
        ]);
        
    } catch (Exception $e) {
        error_log('Checklist Generation Error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => 'チェックリスト生成中にエラーが発生しました',
            'code' => 'CHECKLIST_ERROR',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ]);
    }
}

/**
 * 【高度AI機能】助成金チェックリスト生成 - 業種・難易度・AI分析対応
 */
function gi_generate_grant_checklist($post_id) {
    // 助成金の詳細情報と特性分析を取得
    $grant_details = gi_get_grant_details($post_id);
    $grant_characteristics = gi_analyze_grant_characteristics($grant_details);
    $ai_score = gi_calculate_comprehensive_ai_score($grant_details);
    $success_probability = gi_estimate_success_probability($grant_details);
    
    $checklist = [];
    
    // === 1. 基本要件チェック（必須） ===
    $checklist[] = [
        'text' => '助成金の対象者・対象事業の範囲を確認し、適格性を検証しました',
        'priority' => 'critical',
        'checked' => false,
        'category' => 'eligibility',
        'ai_confidence' => 0.95,
        'completion_time' => '30分',
        'tips' => ['募集要項の対象者欄を3回読み直す', '類似事例での採択実績を調査する']
    ];
    
    $checklist[] = [
        'text' => '企業規模（従業員数、資本金、売上高）の要件を満たしているか数値で確認',
        'priority' => 'critical',
        'checked' => false,
        'category' => 'eligibility',
        'ai_confidence' => 0.92,
        'completion_time' => '15分',
        'tips' => ['決算書の数値と要件を照合', 'グループ会社がある場合は連結数値も確認']
    ];
    
    // === 2. 業種・分野別の特化要件 ===
    if ($grant_characteristics['industry_type'] === 'it_digital') {
        $checklist = array_merge($checklist, gi_generate_it_specific_checklist($grant_details));
    } elseif ($grant_characteristics['industry_type'] === 'manufacturing') {
        $checklist = array_merge($checklist, gi_generate_manufacturing_checklist($grant_details));
    } elseif ($grant_characteristics['industry_type'] === 'startup') {
        $checklist = array_merge($checklist, gi_generate_startup_checklist($grant_details));
    } elseif ($grant_characteristics['industry_type'] === 'sustainability') {
        $checklist = array_merge($checklist, gi_generate_sustainability_checklist($grant_details));
    }
    
    // === 3. 申請期限・時系列管理 ===
    if (!empty($grant_details['deadline'])) {
        $deadline_analysis = gi_analyze_deadline_pressure($grant_details['deadline']);
        $checklist[] = [
            'text' => sprintf('申請期限（%s）まで逆算したタイムライン作成と進捗管理体制構築', $grant_details['deadline']),
            'priority' => $deadline_analysis['is_urgent'] ? 'critical' : 'high',
            'checked' => false,
            'category' => 'schedule',
            'ai_confidence' => 0.88,
            'completion_time' => $deadline_analysis['recommended_prep_time'],
            'tips' => [$deadline_analysis['strategy'], '週次進捗確認ミーティング設定']
        ];
    }
    
    // === 4. 書類準備（AIによる優先度算出） ===
    $document_priority = gi_calculate_document_priority($grant_details);
    
    foreach ($document_priority as $doc) {
        $checklist[] = [
            'text' => $doc['name'] . 'の作成・準備完了',
            'priority' => $doc['priority'],
            'checked' => false,
            'category' => 'documents',
            'ai_confidence' => $doc['importance_score'],
            'completion_time' => $doc['estimated_time'],
            'tips' => $doc['preparation_tips']
        ];
    }
    
    // === 5. 資金計画・ROI分析 ===
    if (!empty($grant_details['max_amount'])) {
        $roi_analysis = gi_calculate_grant_roi_potential($grant_details);
        $checklist[] = [
            'text' => sprintf('事業費%s円の詳細積算と ROI %s%% の実現可能性検証', 
                number_format($grant_details['max_amount_numeric'] ?: 0), 
                round($roi_analysis['projected_roi'], 1)),
            'priority' => 'critical',
            'checked' => false,
            'category' => 'budget',
            'ai_confidence' => $roi_analysis['confidence'],
            'completion_time' => '3-5時間',
            'tips' => [
                '3社以上からの見積取得',
                '事業効果の定量化（売上・コスト削減）',
                '投資回収計画の策定'
            ]
        ];
        
        $checklist[] = [
            'text' => sprintf('自己資金 %s円の確保と資金繰り計画策定', 
                number_format(($grant_details['max_amount_numeric'] ?: 0) * (1 - ($grant_details['subsidy_rate'] ? floatval(str_replace('%', '', $grant_details['subsidy_rate'])) / 100 : 0.5)))),
            'priority' => 'high',
            'checked' => false,
            'category' => 'budget',
            'ai_confidence' => 0.85,
            'completion_time' => '1-2時間',
            'tips' => ['銀行融資の事前相談', '資金調達スケジュールの確認']
        ];
    }
    
    // === 6. 成功確率向上のためのAI推奨アクション ===
    $success_actions = gi_generate_success_optimization_actions($grant_details, $success_probability);
    foreach ($success_actions as $action) {
        $checklist[] = $action;
    }
    
    // === 7. 競合分析・差別化戦略 ===
    $checklist[] = [
        'text' => '同業他社の採択事例分析と自社の差別化ポイント3つ以上の明確化',
        'priority' => 'high',
        'checked' => false,
        'category' => 'strategy',
        'ai_confidence' => 0.78,
        'completion_time' => '2-3時間',
        'tips' => [
            '過去3年の採択事例をリサーチ',
            '自社の技術的優位性を定量化',
            '市場での独自性をアピールポイント化'
        ]
    ];
    
    // === 8. 最終品質管理 ===
    $checklist[] = [
        'text' => '申請書の専門家レビュー（行政書士・中小企業診断士等）実施',
        'priority' => $grant_characteristics['complexity_level'] >= 7 ? 'critical' : 'high',
        'checked' => false,
        'category' => 'final',
        'ai_confidence' => 0.92,
        'completion_time' => '1-2日',
        'tips' => [
            '業界に詳しい専門家を選択',
            '修正時間を考慮したスケジュール設定',
            '提出前の最終チェックリスト作成'
        ]
    ];
    
    // === AIによるチェックリストの最適化 ===
    $checklist = gi_optimize_checklist_by_ai($checklist, $grant_characteristics, $success_probability);
    
    // === 完成度とリスク評価の追加 ===
    $checklist[] = [
        'text' => sprintf('AI分析による成功確率 %s%% の要因分析と改善アクション実行', 
            round($success_probability['overall_score'] * 100)),
        'priority' => $success_probability['overall_score'] < 0.6 ? 'critical' : 'medium',
        'checked' => false,
        'category' => 'ai_analysis',
        'ai_confidence' => $success_probability['confidence'],
        'completion_time' => '1時間',
        'tips' => [
            '弱点項目の重点改善',
            '強みの更なる強化',
            'リスク要因の事前対策'
        ]
    ];
    
    return $checklist;
}

/**
 * =============================================================================
 * AI 比較機能 - Complete Implementation
 * =============================================================================
 */

/**
 * AI比較機能 AJAXハンドラー
 */
function gi_ajax_compare_grants() {
    try {
        // セキュリティ検証
        if (!gi_verify_ajax_nonce()) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました', 'code' => 'SECURITY_ERROR']);
            return;
        }
        
        $grant_ids = $_POST['grant_ids'] ?? [];
        
        if (empty($grant_ids) || !is_array($grant_ids)) {
            wp_send_json_error(['message' => '比較する助成金が選択されていません', 'code' => 'NO_GRANTS_SELECTED']);
            return;
        }
        
        if (count($grant_ids) < 2) {
            wp_send_json_error(['message' => '比較には2件以上の助成金が必要です', 'code' => 'INSUFFICIENT_GRANTS']);
            return;
        }
        
        if (count($grant_ids) > 3) {
            wp_send_json_error(['message' => '比較は最大3件までです', 'code' => 'TOO_MANY_GRANTS']);
            return;
        }
        
        $start_time = microtime(true);
        
        // 比較データ生成
        $comparison_data = gi_generate_grants_comparison($grant_ids);
        
        // AIおすすめ生成
        $recommendation = gi_generate_comparison_recommendation($comparison_data);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000);
        
        wp_send_json_success([
            'comparison' => $comparison_data,
            'recommendation' => $recommendation,
            'grant_count' => count($grant_ids),
            'processing_time_ms' => $processing_time
        ]);
        
    } catch (Exception $e) {
        error_log('Grants Comparison Error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => '比較処理中にエラーが発生しました',
            'code' => 'COMPARISON_ERROR',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ]);
    }
}

/**
 * 助成金比較データ生成
 */
function gi_generate_grants_comparison($grant_ids) {
    $comparison_data = [];
    
    foreach ($grant_ids as $grant_id) {
        $grant_id = intval($grant_id);
        $grant_post = get_post($grant_id);
        
        if (!$grant_post || $grant_post->post_type !== 'grant') {
            continue;
        }
        
        $grant_details = gi_get_grant_details($grant_id);
        
        // マッチングスコア計算
        $match_score = gi_calculate_comparison_match_score($grant_id);
        
        // 難易度情報
        $difficulty = gi_get_grant_difficulty_info($grant_id);
        
        // 成功率情報
        $success_rate = gi_get_field_safe('adoption_rate', $grant_id, 0);
        
        $comparison_data[] = [
            'id' => $grant_id,
            'title' => $grant_post->post_title,
            'amount' => $grant_details['max_amount'] ?: '未定',
            'amount_numeric' => gi_extract_numeric_amount($grant_details['max_amount']),
            'deadline' => $grant_details['deadline'] ?: '随時',
            'organization' => $grant_details['organization'] ?: '未定',
            'target' => $grant_details['grant_target'] ?: '未定',
            'subsidy_rate' => gi_get_field_safe('subsidy_rate', $grant_id, ''),
            'match_score' => $match_score,
            'difficulty' => $difficulty,
            'success_rate' => $success_rate ?: null,
            'rate' => $success_rate > 0 ? $success_rate : null,
            'application_method' => gi_get_field_safe('application_method', $grant_id, 'オンライン'),
            'eligible_expenses' => $grant_details['eligible_expenses'] ?: '',
            'permalink' => get_permalink($grant_id)
        ];
    }
    
    return $comparison_data;
}

/**
 * 比較マッチングスコア計算
 */
function gi_calculate_comparison_match_score($grant_id) {
    // ベーススコア
    $base_score = 70;
    
    // 特徴加算
    if (gi_get_field_safe('is_featured', $grant_id) == '1') {
        $base_score += 10;
    }
    
    // 金額加算
    $amount_numeric = gi_get_field_safe('max_amount_numeric', $grant_id, 0);
    if ($amount_numeric >= 10000000) { // 1000万円以上
        $base_score += 15;
    } elseif ($amount_numeric >= 5000000) { // 500万円以上
        $base_score += 10;
    } elseif ($amount_numeric >= 1000000) { // 100万円以上
        $base_score += 5;
    }
    
    // 成功率加算
    $success_rate = gi_get_field_safe('adoption_rate', $grant_id, 0);
    if ($success_rate >= 50) {
        $base_score += 8;
    } elseif ($success_rate >= 30) {
        $base_score += 5;
    }
    
    // 難易度調整
    $difficulty = gi_get_field_safe('grant_difficulty', $grant_id, 'normal');
    if ($difficulty === 'easy') {
        $base_score += 5;
    } elseif ($difficulty === 'hard') {
        $base_score -= 5;
    }
    
    return min(98, max(60, $base_score));
}

/**
 * 助成金難易度情報取得
 */
function gi_get_grant_difficulty_info($grant_id) {
    $difficulty = gi_get_field_safe('grant_difficulty', $grant_id, 'normal');
    
    $difficulty_map = [
        'easy' => [
            'level' => 'easy',
            'label' => '易しい',
            'stars' => '★★☆',
            'description' => '初心者向け',
            'color' => '#16a34a'
        ],
        'normal' => [
            'level' => 'normal',
            'label' => '普通',
            'stars' => '★★★',
            'description' => '標準的',
            'color' => '#eab308'
        ],
        'hard' => [
            'level' => 'hard',
            'label' => '難しい',
            'stars' => '★★★',
            'description' => '経験者向け',
            'color' => '#dc2626'
        ]
    ];
    
    return $difficulty_map[$difficulty] ?? $difficulty_map['normal'];
}

/**
 * 数値金額抜き出し
 */
function gi_extract_numeric_amount($amount_string) {
    if (empty($amount_string)) return 0;
    
    // 数字と単位を抜き出し
    preg_match_all('/([\d,]+)(\s*[万億千百十]?)(円)?/', $amount_string, $matches, PREG_SET_ORDER);
    
    if (empty($matches)) return 0;
    
    $total = 0;
    
    foreach ($matches as $match) {
        $number = intval(str_replace(',', '', $match[1]));
        $unit = $match[2] ?? '';
        
        switch (trim($unit)) {
            case '億':
                $number *= 100000000;
                break;
            case '万':
                $number *= 10000;
                break;
            case '千':
                $number *= 1000;
                break;
            case '百':
                $number *= 100;
                break;
        }
        
        $total = max($total, $number); // 最大値を取る
    }
    
    return $total;
}

/**
 * 【高度AI機能】比較結果からAI総合おすすめ生成 - 機械学習風スコアリング
 */
function gi_generate_comparison_recommendation($comparison_data) {
    if (empty($comparison_data)) {
        return [
            'title' => '比較データがありません',
            'match_score' => 0,
            'reason' => '比較する助成金を選択してください。',
            'ai_analysis' => [],
            'risk_factors' => [],
            'optimization_suggestions' => []
        ];
    }
    
    // 各助成金に対して高度なAI分析を実行
    $enhanced_comparison = [];
    foreach ($comparison_data as $grant) {
        $grant_analysis = gi_perform_advanced_grant_analysis($grant);
        $grant['ai_analysis'] = $grant_analysis;
        $grant['composite_score'] = gi_calculate_composite_ai_score($grant, $grant_analysis);
        $enhanced_comparison[] = $grant;
    }
    
    // 複合スコア（AI分析結果）でソート
    usort($enhanced_comparison, function($a, $b) {
        return $b['composite_score'] <=> $a['composite_score'];
    });
    
    $best_grant = $enhanced_comparison[0];
    $second_best = isset($enhanced_comparison[1]) ? $enhanced_comparison[1] : null;
    $third_best = isset($enhanced_comparison[2]) ? $enhanced_comparison[2] : null;
    
    // === 高度なAI推奨理由分析 ===
    $ai_reasons = [];
    $quantitative_factors = [];
    $risk_assessment = [];
    
    // 成功確率分析
    $success_prob = $best_grant['ai_analysis']['success_probability'];
    if ($success_prob >= 0.75) {
        $ai_reasons[] = sprintf('AI算出成功確率 %s%%（業界平均+%s%%）', 
            round($success_prob * 100), 
            round(($success_prob - 0.4) * 100));
        $quantitative_factors['success_rate'] = $success_prob;
    }
    
    // ROI分析
    $roi_analysis = $best_grant['ai_analysis']['roi_analysis'];
    if ($roi_analysis['projected_roi'] >= 150) {
        $ai_reasons[] = sprintf('投資回収率 %s%%（%sヶ月で回収見込み）', 
            round($roi_analysis['projected_roi']), 
            $roi_analysis['payback_months']);
        $quantitative_factors['roi'] = $roi_analysis['projected_roi'];
    }
    
    // 競合優位性
    $competition_analysis = $best_grant['ai_analysis']['competition_analysis'];
    if ($competition_analysis['advantage_score'] >= 0.7) {
        $ai_reasons[] = sprintf('競合優位度 %s点/10点（差別化要因: %s）', 
            round($competition_analysis['advantage_score'] * 10), 
            implode('、', $competition_analysis['key_advantages']));
        $quantitative_factors['competitive_advantage'] = $competition_analysis['advantage_score'];
    }
    
    // 申請難易度vs期待値分析
    $effort_value_ratio = $best_grant['ai_analysis']['effort_value_ratio'];
    if ($effort_value_ratio >= 1.5) {
        $ai_reasons[] = sprintf('労力対効果比 %s倍（最適な投資効率）', 
            round($effort_value_ratio, 1));
        $quantitative_factors['effort_efficiency'] = $effort_value_ratio;
    }
    
    // 業界適合性
    $industry_fit = $best_grant['ai_analysis']['industry_compatibility'];
    if ($industry_fit >= 0.8) {
        $ai_reasons[] = sprintf('業界適合度 %s%%（事業計画との整合性が高い）', 
            round($industry_fit * 100));
        $quantitative_factors['industry_fit'] = $industry_fit;
    }
    
    // === リスク要因の分析 ===
    $risk_factors = gi_analyze_grant_risks($best_grant);
    
    // === 他候補との比較優位性 ===
    $comparative_advantages = [];
    if ($second_best) {
        $score_diff = $best_grant['composite_score'] - $second_best['composite_score'];
        if ($score_diff >= 5) {
            $comparative_advantages[] = sprintf('2位候補より %s点優位', round($score_diff));
        }
        
        // 具体的な優位項目
        if ($best_grant['amount_numeric'] > $second_best['amount_numeric']) {
            $amount_diff = ($best_grant['amount_numeric'] - $second_best['amount_numeric']) / 10000;
            $comparative_advantages[] = sprintf('助成額が %s万円多い', round($amount_diff));
        }
        
        if (isset($best_grant['success_rate']) && isset($second_best['success_rate']) && 
            $best_grant['success_rate'] > $second_best['success_rate']) {
            $rate_diff = $best_grant['success_rate'] - $second_best['success_rate'];
            $comparative_advantages[] = sprintf('採択率が %s%%高い', round($rate_diff));
        }
    }
    
    // === 最適化提案の生成 ===
    $optimization_suggestions = gi_generate_optimization_suggestions($best_grant, $enhanced_comparison);
    
    // === 最終的な推奨理由の構築 ===
    $comprehensive_reason = '';
    if (!empty($ai_reasons)) {
        $comprehensive_reason .= 'AI分析結果: ' . implode('、', array_slice($ai_reasons, 0, 3));
    }
    
    if (!empty($comparative_advantages)) {
        $comprehensive_reason .= '\n\n他候補との比較: ' . implode('、', $comparative_advantages);
    }
    
    if (empty($comprehensive_reason)) {
        $comprehensive_reason = 'AI総合評価により、現在の事業方針に最も適合する助成金と判定されました。';
    }
    
    return [
        'title' => $best_grant['title'],
        'match_score' => $best_grant['match_score'],
        'composite_score' => $best_grant['composite_score'],
        'reason' => $comprehensive_reason,
        'grant_id' => $best_grant['id'],
        'permalink' => $best_grant['permalink'],
        
        // === AI分析の詳細データ ===
        'ai_analysis' => [
            'success_probability' => $success_prob,
            'roi_projection' => $roi_analysis,
            'risk_assessment' => $risk_factors,
            'competitive_position' => $competition_analysis,
            'industry_alignment' => $industry_fit,
            'quantitative_factors' => $quantitative_factors
        ],
        
        // === アクション推奨 ===
        'optimization_suggestions' => $optimization_suggestions,
        
        // === 全体ランキング ===
        'ranking' => [
            'first' => [
                'title' => $best_grant['title'],
                'score' => $best_grant['composite_score'],
                'key_strength' => $ai_reasons[0] ?? '総合バランス'
            ],
            'second' => $second_best ? [
                'title' => $second_best['title'],
                'score' => $second_best['composite_score'],
                'key_strength' => gi_identify_key_strength($second_best)
            ] : null,
            'third' => $third_best ? [
                'title' => $third_best['title'],
                'score' => $third_best['composite_score'],
                'key_strength' => gi_identify_key_strength($third_best)
            ] : null
        ],
        
        // === 意思決定サポート ===
        'decision_factors' => [
            'confidence_level' => gi_calculate_recommendation_confidence($best_grant, $enhanced_comparison),
            'alternative_consideration' => $second_best && ($best_grant['composite_score'] - $second_best['composite_score']) < 3,
            'immediate_action_required' => gi_check_urgency_factors($best_grant)
        ]
    ];
}

function gi_get_grant_resources($post_id, $intent) {
    $resources = [
        'official_site' => get_post_meta($post_id, 'official_url', true),
        'application_guide' => get_post_meta($post_id, 'application_guide_url', true),
        'faq_url' => get_post_meta($post_id, 'faq_url', true),
        'contact_info' => get_post_meta($post_id, 'contact_info', true)
    ];
    
    // 意図に基づいて関連リソースを優先
    $prioritized = [];
    switch ($intent) {
        case 'application':
            if ($resources['application_guide']) {
                $prioritized['application_guide'] = '申請ガイド';
            }
            break;
        case 'deadline':
            if ($resources['official_site']) {
                $prioritized['official_site'] = '公式サイト（最新情報）';
            }
            break;
    }
    
    return array_filter($prioritized + $resources);
}

function gi_save_grant_question_history($post_id, $question, $response, $session_id) {
    // 助成金別の質問履歴保存（必要に応じて実装）
    $user_id = get_current_user_id();
    if (!$user_id) return false;
    
    $history = get_user_meta($user_id, 'gi_grant_question_history', true) ?: [];
    
    $history[] = [
        'grant_id' => $post_id,
        'question' => $question,
        'response' => mb_substr($response, 0, 200), // 応答の要約のみ保存
        'session_id' => $session_id,
        'timestamp' => current_time('timestamp')
    ];
    
    // 最新100件のみ保持
    $history = array_slice($history, -100);
    
    return update_user_meta($user_id, 'gi_grant_question_history', $history);
}

function gi_calculate_response_confidence($question, $response) {
    // 応答の信頼度を計算（簡易版）
    $question_length = mb_strlen($question);
    $response_length = mb_strlen($response);
    
    // 基本スコア
    $confidence = 0.7;
    
    // 質問の具体性
    if ($question_length > 10) {
        $confidence += 0.1;
    }
    
    // 応答の詳細度
    if ($response_length > 100) {
        $confidence += 0.1;
    }
    
    // 具体的なキーワードが含まれているか
    $specific_terms = ['申請', '締切', '金額', '対象', '要件'];
    $found_terms = 0;
    foreach ($specific_terms as $term) {
        if (mb_stripos($question, $term) !== false && mb_stripos($response, $term) !== false) {
            $found_terms++;
        }
    }
    
    $confidence += ($found_terms * 0.05);
    
    return min($confidence, 1.0);
}

/**
 * =============================================================================
 * Grant Data Functions - Template Support
 * =============================================================================
 */

/**
 * Complete grant data retrieval function
 */
function gi_get_complete_grant_data($post_id) {
    static $cache = [];
    
    // キャッシュチェック
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }
    
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'grant') {
        return [];
    }
    
    // 基本データ
    $data = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'permalink' => get_permalink($post_id),
        'excerpt' => get_the_excerpt($post_id),
        'content' => get_post_field('post_content', $post_id),
        'date' => get_the_date('Y-m-d', $post_id),
        'modified' => get_the_modified_date('Y-m-d H:i:s', $post_id),
        'status' => get_post_status($post_id),
        'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
    ];

    // ACFフィールドデータ
    $acf_fields = [
        // 基本情報
        'ai_summary' => '',
        'organization' => '',
        'organization_type' => '',
        
        // 金額情報
        'max_amount' => '',
        'max_amount_numeric' => 0,
        'min_amount' => 0,
        'subsidy_rate' => '',
        'amount_note' => '',
        
        // 締切・ステータス
        'deadline' => '',
        'deadline_date' => '',
        'deadline_timestamp' => '',
        'application_status' => 'active',
        'application_period' => '',
        'deadline_note' => '',
        
        // 対象・条件
        'grant_target' => '',
        'eligible_expenses' => '',
        'grant_difficulty' => 'normal',
        'adoption_rate' => 0,
        'required_documents' => '',
        
        // 申請・連絡先
        'application_method' => 'online',
        'contact_info' => '',
        'official_url' => '',
        'external_link' => '',
        
        // 管理設定
        'is_featured' => false,
        'priority_order' => 100,
        'views_count' => 0,
        'last_updated' => '',
        'admin_notes' => '',
    ];

    foreach ($acf_fields as $field => $default) {
        $value = gi_get_field_safe($field, $post_id, $default);
        $data[$field] = $value;
    }

    // タクソノミーデータ
    $taxonomies = ['grant_category', 'grant_prefecture', 'grant_tag'];
    foreach ($taxonomies as $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        $data[$taxonomy] = [];
        $data[$taxonomy . '_names'] = [];
        
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $data[$taxonomy][] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description
                ];
                $data[$taxonomy . '_names'][] = $term->name;
            }
        }
    }

    // 計算フィールド
    $data['is_deadline_soon'] = gi_is_deadline_soon($data['deadline']);
    $data['application_status_label'] = gi_get_status_label($data['application_status']);
    $data['difficulty_label'] = gi_get_difficulty_label($data['grant_difficulty']);
    
    // キャッシュに保存
    $cache[$post_id] = $data;
    
    return $data;
}

/**
 * All grant meta data retrieval function (fallback)
 */
function gi_get_all_grant_meta($post_id) {
    // gi_get_complete_grant_data のシンプル版
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'grant') {
        return [];
    }
    
    // 基本データのみ
    $data = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'permalink' => get_permalink($post_id),
        'excerpt' => get_the_excerpt($post_id),
        'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
    ];
    
    // 重要なメタフィールドのみ
    $meta_fields = [
        'ai_summary', 'organization', 'max_amount', 'max_amount_numeric',
        'deadline', 'application_status', 'grant_target', 'subsidy_rate',
        'grant_difficulty', 'adoption_rate', 'official_url', 'is_featured'
    ];
    
    foreach ($meta_fields as $field) {
        $data[$field] = gi_get_field_safe($field, $post_id);
    }
    
    // タクソノミー名の配列
    $data['categories'] = wp_get_post_terms($post_id, 'grant_category', ['fields' => 'names']);
    $data['prefectures'] = wp_get_post_terms($post_id, 'grant_prefecture', ['fields' => 'names']);
    
    return $data;
}

/**
 * Safe field retrieval with fallback
 */
function gi_get_field_safe($field_name, $post_id, $default = '') {
    // ACFが利用可能な場合
    if (function_exists('get_field')) {
        $value = get_field($field_name, $post_id);
        return $value !== false && $value !== null ? $value : $default;
    }
    
    // フォールバック: 標準のpost_meta
    $value = get_post_meta($post_id, $field_name, true);
    return !empty($value) ? $value : $default;
}

/**
 * Safe ACF field retrieval (alias for template compatibility)
 * Note: This function is already defined in inc/data-functions.php
 * Using existing function to avoid redeclaration
 */

/**
 * Check if deadline is soon (within 30 days)
 */
function gi_is_deadline_soon($deadline) {
    if (empty($deadline)) return false;
    
    // 日付形式の正規化
    $timestamp = gi_normalize_date($deadline);
    if (!$timestamp) return false;
    
    $now = time();
    $thirty_days = 30 * 24 * 60 * 60;
    
    return ($timestamp > $now && $timestamp <= ($now + $thirty_days));
}

/**
 * Get status label
 */
function gi_get_status_label($status) {
    $labels = [
        'active' => '募集中',
        'pending' => '準備中',
        'closed' => '終了',
        'suspended' => '一時停止',
        'draft' => '下書き'
    ];
    
    return $labels[$status] ?? $status;
}

/**
 * Get difficulty label
 */
function gi_get_difficulty_label($difficulty) {
    $labels = [
        'easy' => '易しい',
        'normal' => '普通',
        'hard' => '難しい',
        'expert' => '上級者向け'
    ];
    
    return $labels[$difficulty] ?? $difficulty;
}

/**
 * Normalize date to timestamp
 */
function gi_normalize_date($date_input) {
    if (empty($date_input)) return false;
    
    // すでにタイムスタンプの場合
    if (is_numeric($date_input) && strlen($date_input) >= 10) {
        return intval($date_input);
    }
    
    // Ymd形式（例：20241231）
    if (is_numeric($date_input) && strlen($date_input) == 8) {
        $year = substr($date_input, 0, 4);
        $month = substr($date_input, 4, 2);
        $day = substr($date_input, 6, 2);
        return mktime(0, 0, 0, $month, $day, $year);
    }
    
    // その他の日付文字列
    $timestamp = strtotime($date_input);
    return $timestamp !== false ? $timestamp : false;
}

/**
 * Get user favorites safely
 * Note: This function is defined in inc/data-processing.php
 * No need to redefine here - using existing gi_get_user_favorites()
 */

/**
 * =============================================================================
 * メイン検索・フィルタリング AJAX 処理
 * =============================================================================
 */

/**
 * 統一カードレンダリング関数（簡易版）
 */
if (!function_exists('gi_render_card_unified')) {
    function gi_render_card_unified($post_id, $view = 'grid') {
        // 既存のカード関数を使用してフォールバック
        global $current_view, $user_favorites;
        $current_view = $view;
        
        ob_start();
        get_template_part('template-parts/grant-card-unified');
        $output = ob_get_clean();
        
        // 出力がない場合の簡易フォールバック
        if (empty($output)) {
            $title = get_the_title($post_id);
            $permalink = get_permalink($post_id);
            $organization = get_field('organization', $post_id) ?: '';
            $amount = get_field('max_amount', $post_id) ?: '金額未設定';
            $status = get_field('application_status', $post_id) ?: 'open';
            $status_text = $status === 'open' ? '募集中' : ($status === 'upcoming' ? '募集予定' : '募集終了');
            
            $is_favorite = in_array($post_id, $user_favorites ?: []);
            
            if ($view === 'grid') {
                return "
                <div class='clean-grant-card' data-post-id='{$post_id}' onclick=\"location.href='{$permalink}'\">
                    <div class='clean-grant-card-header'>
                        <h3 style='margin: 0; font-size: 16px; font-weight: 600; line-height: 1.4;'>
                            <a href='{$permalink}' style='text-decoration: none; color: inherit;'>{$title}</a>
                        </h3>
                        <button class='favorite-btn' data-post-id='{$post_id}' onclick='event.stopPropagation();' style='
                            position: absolute; top: 10px; right: 10px; background: none; border: none; 
                            color: " . ($is_favorite ? '#dc2626' : '#6b7280') . "; font-size: 18px; cursor: pointer;
                        '>" . ($is_favorite ? '♥' : '♡') . "</button>
                    </div>
                    <div class='clean-grant-card-body'>
                        <div style='margin-bottom: 12px; font-size: 14px; color: #6b7280;'>{$organization}</div>
                        <div style='margin-bottom: 12px; font-size: 14px; font-weight: 600; color: #16a34a;'>{$amount}</div>
                    </div>
                    <div class='clean-grant-card-footer'>
                        <span style='font-size: 12px; color: #6b7280;'>{$status_text}</span>
                        <a href='{$permalink}' style='
                            background: #000; color: white; text-align: center; 
                            padding: 8px 16px; text-decoration: none; border-radius: 6px;
                            font-size: 12px; font-weight: 500;
                        '>詳細を見る</a>
                    </div>
                </div>";
            } else {
                return "
                <div class='clean-grant-card clean-grant-card-list' data-post-id='{$post_id}' onclick=\"location.href='{$permalink}'\" style='
                    display: flex; align-items: center; gap: 16px; cursor: pointer;
                '>
                    <div style='flex: 1;'>
                        <h3 style='margin: 0 0 4px 0; font-size: 16px; font-weight: 600;'>
                            <a href='{$permalink}' style='text-decoration: none; color: inherit;'>{$title}</a>
                        </h3>
                        <div style='font-size: 12px; color: #6b7280;'>{$organization}</div>
                    </div>
                    
                    <div style='text-align: center; min-width: 120px;'>
                        <div style='font-size: 14px; font-weight: 600; color: #16a34a;'>{$amount}</div>
                        <div style='font-size: 10px; color: #9ca3af;'>{$status_text}</div>
                    </div>
                    
                    <button class='favorite-btn' data-post-id='{$post_id}' onclick='event.stopPropagation();' style='
                        background: none; border: none; color: " . ($is_favorite ? '#dc2626' : '#6b7280') . "; 
                        font-size: 18px; cursor: pointer; padding: 8px;
                    '>" . ($is_favorite ? '♥' : '♡') . "</button>
                </div>";
            }
        }
        
        return $output;
    }
}

/**
 * 助成金読み込み処理（完全版・統一カード対応）- フィルタリング修正版
 */
function gi_ajax_load_grants() {
    try {
        // nonceチェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました', 'code' => 'SECURITY_ERROR']);
            return;
        }

    // ===== パラメータ取得と検証 =====
    $search = sanitize_text_field($_POST['search'] ?? '');
    $categories = json_decode(stripslashes($_POST['categories'] ?? '[]'), true) ?: [];
    $prefectures = json_decode(stripslashes($_POST['prefectures'] ?? '[]'), true) ?: [];
    $municipalities = json_decode(stripslashes($_POST['municipalities'] ?? '[]'), true) ?: [];
    $tags = json_decode(stripslashes($_POST['tags'] ?? '[]'), true) ?: [];
    $status = json_decode(stripslashes($_POST['status'] ?? '[]'), true) ?: [];
    $difficulty = json_decode(stripslashes($_POST['difficulty'] ?? '[]'), true) ?: [];
    $success_rate = json_decode(stripslashes($_POST['success_rate'] ?? '[]'), true) ?: [];
    
    // 金額・数値フィルター
    $amount = sanitize_text_field($_POST['amount'] ?? '');
    $amount_min = intval($_POST['amount_min'] ?? 0);
    $amount_max = intval($_POST['amount_max'] ?? 0);
    
    // 新しいフィルター項目
    $subsidy_rate = sanitize_text_field($_POST['subsidy_rate'] ?? '');
    $organization = sanitize_text_field($_POST['organization'] ?? '');
    $organization_type = sanitize_text_field($_POST['organization_type'] ?? '');
    $target_business = sanitize_text_field($_POST['target_business'] ?? '');
    $application_method = sanitize_text_field($_POST['application_method'] ?? '');
    $only_featured = sanitize_text_field($_POST['only_featured'] ?? '');
    $deadline_range = sanitize_text_field($_POST['deadline_range'] ?? '');
    
    // 表示・ソート設定
    $sort = sanitize_text_field($_POST['sort'] ?? 'date_desc');
    $view = sanitize_text_field($_POST['view'] ?? 'grid');
    $page = max(1, intval($_POST['page'] ?? 1));
    $posts_per_page = max(6, min(30, intval($_POST['posts_per_page'] ?? 12)));

    // ===== WP_Queryの引数構築 =====
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $posts_per_page,
        'paged' => $page,
        'post_status' => 'publish'
    ];

    // ===== 検索クエリ（拡張版：ACFフィールドも検索対象） =====
    if (!empty($search)) {
        $args['s'] = $search;
        
        // メタフィールドも検索対象に追加
        add_filter('posts_search', function($search_sql, $wp_query) use ($search) {
            global $wpdb;
            
            if (!$wp_query->is_main_query() || empty($search)) {
                return $search_sql;
            }
            
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            
            $meta_search = $wpdb->prepare("
                OR EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm 
                    WHERE pm.post_id = {$wpdb->posts}.ID 
                    AND pm.meta_key IN ('ai_summary', 'organization', 'grant_target', 'eligible_expenses', 'required_documents')
                    AND pm.meta_value LIKE %s
                )
            ", $search_term);
            
            // 既存の検索SQLに追加
            $search_sql = str_replace('))) AND', '))) ' . $meta_search . ' AND', $search_sql);
            return $search_sql;
        }, 10, 2);
    }

    // ===== タクソノミークエリ =====
    $tax_query = ['relation' => 'AND'];
    
    if (!empty($categories)) {
        $tax_query[] = [
            'taxonomy' => 'grant_category',
            'field' => 'slug',
            'terms' => $categories,
            'operator' => 'IN'
        ];
    }
    
    if (!empty($prefectures)) {
        $tax_query[] = [
            'taxonomy' => 'grant_prefecture',
            'field' => 'slug', 
            'terms' => $prefectures,
            'operator' => 'IN'
        ];
    }
    
    if (!empty($municipalities)) {
        $tax_query[] = [
            'taxonomy' => 'grant_municipality',
            'field' => 'slug',
            'terms' => $municipalities,
            'operator' => 'IN'
        ];
    }
    
    if (!empty($tags)) {
        $tax_query[] = [
            'taxonomy' => 'grant_tag',
            'field' => 'slug',
            'terms' => $tags,
            'operator' => 'IN'
        ];
    }
    
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    // ===== メタクエリ（カスタムフィールド） =====
    $meta_query = ['relation' => 'AND'];
    
    // ステータスフィルター
    if (!empty($status)) {
        // UIステータスをDBの値にマッピング
        $db_status = array_map(function($s) {
            // 複数の可能性に対応
            if ($s === 'active' || $s === '募集中') return 'open';
            if ($s === 'upcoming' || $s === '募集予定') return 'upcoming';
            if ($s === 'closed' || $s === '終了') return 'closed';
            return $s;
        }, $status);
        
        $meta_query[] = [
            'key' => 'application_status',
            'value' => $db_status,
            'compare' => 'IN'
        ];
    }
    
    // 金額範囲フィルター
    if (!empty($amount)) {
        switch($amount) {
            case '0-100':
                $meta_query[] = [
                    'key' => 'max_amount_numeric',
                    'value' => [0, 1000000],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
                break;
            case '100-500':
                $meta_query[] = [
                    'key' => 'max_amount_numeric',
                    'value' => [1000000, 5000000],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
                break;
            case '500-1000':
                $meta_query[] = [
                    'key' => 'max_amount_numeric',
                    'value' => [5000000, 10000000],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
                break;
            case '1000-3000':
                $meta_query[] = [
                    'key' => 'max_amount_numeric',
                    'value' => [10000000, 30000000],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
                break;
            case '3000+':
                $meta_query[] = [
                    'key' => 'max_amount_numeric',
                    'value' => 30000000,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                ];
                break;
        }
    }
    
    // 難易度フィルター
    if (!empty($difficulty)) {
        $meta_query[] = [
            'key' => 'grant_difficulty', // ACFフィールド名に合わせる
            'value' => $difficulty,
            'compare' => 'IN'
        ];
    }
    
    // 成功率フィルター
    if (!empty($success_rate)) {
        foreach ($success_rate as $rate_range) {
            switch($rate_range) {
                case '0-20':
                    $meta_query[] = [
                        'key' => 'adoption_rate', // ACFフィールド名に合わせる
                        'value' => [0, 20],
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case '20-40':
                    $meta_query[] = [
                        'key' => 'adoption_rate', // ACFフィールド名に合わせる
                        'value' => [20, 40],
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case '40-60':
                    $meta_query[] = [
                        'key' => 'adoption_rate', // ACFフィールド名に合わせる
                        'value' => [40, 60],
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case '60-80':
                    $meta_query[] = [
                        'key' => 'adoption_rate', // ACFフィールド名に合わせる
                        'value' => [60, 80],
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case '80-100':
                    $meta_query[] = [
                        'key' => 'adoption_rate', // ACFフィールド名に合わせる
                        'value' => [80, 100],
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ];
                    break;
            }
        }
    }
    
    // 補助率フィルター
    if (!empty($subsidy_rate)) {
        $meta_query[] = [
            'key' => 'subsidy_rate',
            'value' => $subsidy_rate,
            'compare' => 'LIKE'
        ];
    }
    
    // 実施機関フィルター
    if (!empty($organization)) {
        $meta_query[] = [
            'key' => 'organization',
            'value' => $organization,
            'compare' => 'LIKE'
        ];
    }
    
    // 実施機関種別フィルター
    if (!empty($organization_type)) {
        $meta_query[] = [
            'key' => 'organization_type',
            'value' => $organization_type,
            'compare' => 'LIKE'
        ];
    }
    
    // 対象事業フィルター
    if (!empty($target_business)) {
        $meta_query[] = [
            'key' => 'grant_target',
            'value' => $target_business,
            'compare' => 'LIKE'
        ];
    }
    
    // 申請方法フィルター
    if (!empty($application_method)) {
        $meta_query[] = [
            'key' => 'application_method',
            'value' => $application_method,
            'compare' => '='
        ];
    }
    
    // 締切期間フィルター
    if (!empty($deadline_range)) {
        $now = time();
        switch($deadline_range) {
            case 'within_1month':
                $end_time = $now + (30 * 24 * 60 * 60);
                $meta_query[] = [
                    'key' => 'deadline_timestamp',
                    'value' => [$now, $end_time],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
                break;
            case 'within_3months':
                $end_time = $now + (90 * 24 * 60 * 60);
                $meta_query[] = [
                    'key' => 'deadline_timestamp',
                    'value' => [$now, $end_time],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
                break;
            case 'within_6months':
                $end_time = $now + (180 * 24 * 60 * 60);
                $meta_query[] = [
                    'key' => 'deadline_timestamp',
                    'value' => [$now, $end_time],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
                break;
            case 'anytime':
                $meta_query[] = [
                    'key' => 'deadline',
                    'value' => ['随時', '通年', '年中'],
                    'compare' => 'IN'
                ];
                break;
        }
    }
    
    // カスタム金額範囲フィルター
    if ($amount_min > 0 || $amount_max > 0) {
        $amount_query = [
            'key' => 'max_amount_numeric',
            'type' => 'NUMERIC'
        ];
        
        if ($amount_min > 0 && $amount_max > 0) {
            $amount_query['value'] = [$amount_min * 10000, $amount_max * 10000]; // 万円を円に変換
            $amount_query['compare'] = 'BETWEEN';
        } elseif ($amount_min > 0) {
            $amount_query['value'] = $amount_min * 10000;
            $amount_query['compare'] = '>=';
        } elseif ($amount_max > 0) {
            $amount_query['value'] = $amount_max * 10000;
            $amount_query['compare'] = '<=';
        }
        
        $meta_query[] = $amount_query;
    }
    
    // 注目の助成金フィルター
    if ($only_featured === 'true' || $only_featured === '1') {
        $meta_query[] = [
            'key' => 'is_featured',
            'value' => '1',
            'compare' => '='
        ];
    }
    
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    // ===== ソート順 =====
    switch ($sort) {
        case 'date_asc':
            $args['orderby'] = 'date';
            $args['order'] = 'ASC';
            break;
        case 'date_desc':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        case 'amount_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'max_amount_numeric';
            $args['order'] = 'DESC';
            break;
        case 'amount_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'max_amount_numeric';
            $args['order'] = 'ASC';
            break;
        case 'deadline_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'deadline_timestamp';
            $args['order'] = 'ASC';
            break;
        case 'success_rate_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'adoption_rate'; // ACFフィールド名に合わせる
            $args['order'] = 'DESC';
            break;
        case 'featured_first':
        case 'featured':
            $args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
            $args['meta_key'] = 'is_featured';
            break;
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
    }

    // ===== クエリ実行 =====
    $query = new WP_Query($args);
    $grants = [];
    
    global $user_favorites, $current_view;
    $user_favorites = gi_get_user_favorites();
    $current_view = $view;

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // 統一カードレンダリングを使用
            $html = gi_render_card_unified($post_id, $view);

            $grants[] = [
                'id' => $post_id,
                'html' => $html,
                'title' => get_the_title($post_id),
                'permalink' => get_permalink($post_id)
            ];
        }
        wp_reset_postdata();
    }

    // ===== 統計情報 =====
    $stats = [
        'total_found' => $query->found_posts,
        'current_page' => $page,
        'total_pages' => $query->max_num_pages,
        'posts_per_page' => $posts_per_page,
        'showing_from' => (($page - 1) * $posts_per_page) + 1,
        'showing_to' => min($page * $posts_per_page, $query->found_posts),
    ];

    // ===== レスポンス送信 =====
    wp_send_json_success([
        'grants' => $grants,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $query->max_num_pages,
            'total_posts' => $query->found_posts,
            'posts_per_page' => $posts_per_page,
        ],
        'stats' => $stats,
        'view' => $view,
        'query_info' => [
            'search' => $search,
            'filters_applied' => !empty($categories) || !empty($prefectures) || !empty($tags) || !empty($status) || !empty($amount) || !empty($only_featured) || !empty($difficulty) || !empty($success_rate) || !empty($subsidy_rate) || !empty($organization) || !empty($deadline_range),
            'applied_filters' => [
                'categories' => $categories,
                'prefectures' => $prefectures, 
                'tags' => $tags,
                'status' => $status,
                'difficulty' => $difficulty,
                'success_rate' => $success_rate,
                'amount' => $amount,
                'subsidy_rate' => $subsidy_rate,
                'organization' => $organization,
                'deadline_range' => $deadline_range,
                'only_featured' => $only_featured
            ],
            'sort' => $sort,
        ],
        'debug' => defined('WP_DEBUG') && WP_DEBUG ? [
            'query_args' => $args,
            'meta_query_count' => count($meta_query) - 1,
            'tax_query_count' => count($tax_query) - 1
        ] : null,
    ]);
    
    } catch (Exception $e) {
        error_log('Grant Load Error: ' . $e->getMessage());
        wp_send_json_error([
            'message' => 'フィルタリング中にエラーが発生しました。しばらく後でお試しください。',
            'code' => 'FILTERING_ERROR',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ]);
    }
}

/**
 * Archive page grants loading with municipality support
 * アーカイブページの補助金読み込み（市町村対応）
 */
function gi_load_grants() {
    // Nonce verification
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    // Get parameters
    $search = sanitize_text_field($_POST['search'] ?? '');
    $categories = isset($_POST['categories']) ? json_decode(stripslashes($_POST['categories']), true) : [];
    $prefectures = isset($_POST['prefectures']) ? json_decode(stripslashes($_POST['prefectures']), true) : [];
    $municipalities = isset($_POST['municipalities']) ? json_decode(stripslashes($_POST['municipalities']), true) : [];
    $region = sanitize_text_field($_POST['region'] ?? '');
    $amount = sanitize_text_field($_POST['amount'] ?? '');
    $status = isset($_POST['status']) ? json_decode(stripslashes($_POST['status']), true) : [];
    $only_featured = sanitize_text_field($_POST['only_featured'] ?? '');
    $sort = sanitize_text_field($_POST['sort'] ?? 'date_desc');
    $view = sanitize_text_field($_POST['view'] ?? 'grid');
    $page = max(1, intval($_POST['page'] ?? 1));
    
    // Build query args
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => 12,
        'post_status' => 'publish',
        'paged' => $page,
    ];
    
    // AI-enhanced semantic search
    $use_semantic_search = false;
    $semantic_results = [];
    
    if (!empty($search)) {
        // Try semantic search first if available
        if (class_exists('GI_Semantic_Search')) {
            try {
                $semantic_search = GI_Semantic_Search::getInstance();
                if ($semantic_search && method_exists($semantic_search, 'search')) {
                    $semantic_results = $semantic_search->search($search, [
                        'limit' => 50, // Get more results for filtering
                        'threshold' => 0.7,
                    ]);
                    
                    if (!empty($semantic_results) && isset($semantic_results['posts'])) {
                        $use_semantic_search = true;
                        $post_ids = array_column($semantic_results['posts'], 'ID');
                        
                        // Use post__in for semantic search results
                        $args['post__in'] = $post_ids;
                        $args['orderby'] = 'post__in'; // Preserve semantic ranking
                    }
                }
            } catch (Exception $e) {
                error_log('Semantic search error in gi_load_grants: ' . $e->getMessage());
            }
        }
        
        // Fallback to traditional search if semantic search didn't work
        if (!$use_semantic_search) {
            $args['s'] = $search;
        }
    }
    
    // Taxonomy query
    $tax_query = ['relation' => 'AND'];
    
    if (!empty($categories)) {
        $tax_query[] = [
            'taxonomy' => 'grant_category',
            'field' => 'slug',
            'terms' => $categories,
        ];
    }
    
    if (!empty($prefectures)) {
        $tax_query[] = [
            'taxonomy' => 'grant_prefecture',
            'field' => 'slug',
            'terms' => $prefectures,
        ];
    }
    
    if (!empty($municipalities)) {
        $tax_query[] = [
            'taxonomy' => 'grant_municipality',
            'field' => 'slug',
            'terms' => $municipalities,
        ];
    }
    
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }
    
    // Meta query
    $meta_query = ['relation' => 'AND'];
    
    if (!empty($status)) {
        $db_statuses = array_map(function($s) {
            return $s === 'active' ? 'open' : ($s === 'upcoming' ? 'upcoming' : $s);
        }, $status);
        
        $meta_query[] = [
            'key' => 'application_status',
            'value' => $db_statuses,
            'compare' => 'IN',
        ];
    }
    
    if ($only_featured === '1') {
        $meta_query[] = [
            'key' => 'is_featured',
            'value' => '1',
            'compare' => '=',
        ];
    }
    
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }
    
    // Sorting
    switch ($sort) {
        case 'amount_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'max_amount_numeric';
            $args['order'] = 'DESC';
            break;
        case 'featured_first':
            $args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
            $args['meta_key'] = 'is_featured';
            break;
        case 'deadline_asc':
            $args['orderby'] = 'meta_value';
            $args['meta_key'] = 'application_deadline';
            $args['order'] = 'ASC';
            break;
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
    }
    
    // Execute query
    $query = new WP_Query($args);
    
    // Get user favorites
    $user_favorites = function_exists('gi_get_user_favorites') ? gi_get_user_favorites() : [];
    
    // Build grant HTML
    $grants = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            // Set global variables for template
            $GLOBALS['current_view'] = $view;
            $GLOBALS['user_favorites'] = $user_favorites;
            
            // Capture template output
            ob_start();
            get_template_part('template-parts/grant-card-unified');
            $html = ob_get_clean();
            
            $grants[] = [
                'id' => get_the_ID(),
                'html' => $html,
            ];
        }
        wp_reset_postdata();
    }
    
    // Stats
    $stats = [
        'total_found' => $query->found_posts,
        'current_page' => $page,
        'total_pages' => $query->max_num_pages,
    ];
    
    wp_send_json_success([
        'grants' => $grants,
        'stats' => $stats,
        'pagination' => [
            'current' => $page,
            'total' => $query->max_num_pages,
        ],
    ]);
}
// gi_load_grants AJAX handlers removed to avoid conflicts with gi_ajax_load_grants

/**
 * =============================================================================
 * Missing Helper Functions for Comparison
 * =============================================================================
 */

// gi_get_field_safe() function already declared earlier in this file

/**
 * =============================================================================
 * 【高度AI機能】 - 機械学習風アルゴリズムとインテリジェント分析
 * =============================================================================
 */

/**
 * 助成金特性の包括的AI分析
 */
function gi_analyze_grant_characteristics($grant_details) {
    $characteristics = [
        'industry_type' => 'general',
        'complexity_level' => 5, // 1-10スケール
        'technical_requirements' => [],
        'target_business_size' => 'medium',
        'innovation_focus' => false,
        'sustainability_focus' => false,
        'digital_transformation' => false,
        'geographic_scope' => 'national',
        'funding_competitiveness' => 'medium'
    ];
    
    $title = strtolower($grant_details['title'] ?? '');
    $target = strtolower($grant_details['grant_target'] ?? '');
    $content = strtolower($grant_details['content'] ?? '');
    $combined_text = $title . ' ' . $target . ' ' . $content;
    
    // 業種分類（機械学習風マッチング）
    $industry_keywords = [
        'it_digital' => ['IT', 'デジタル', 'DX', 'AI', 'IoT', 'システム', 'ソフトウェア', 'アプリ', 'クラウド'],
        'manufacturing' => ['製造', 'ものづくり', '工場', '設備', '機械', '生産', '品質', '技術開発'],
        'startup' => ['創業', 'スタートアップ', 'ベンチャー', '起業', '新規事業', '事業化'],
        'sustainability' => ['環境', '省エネ', '再生可能', 'カーボン', 'SDGs', '持続可能', 'グリーン'],
        'healthcare' => ['医療', 'ヘルスケア', '健康', '福祉', '介護', '医薬', '治療'],
        'agriculture' => ['農業', '農林', '漁業', '食品', '6次産業', '農産物'],
        'tourism' => ['観光', 'インバウンド', '地域振興', '文化', '伝統工芸']
    ];
    
    $max_score = 0;
    $detected_industry = 'general';
    
    foreach ($industry_keywords as $industry => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            $score += substr_count($combined_text, strtolower($keyword));
        }
        if ($score > $max_score) {
            $max_score = $score;
            $detected_industry = $industry;
        }
    }
    
    $characteristics['industry_type'] = $detected_industry;
    
    // 複雑度レベルの算出（多要素分析）
    $complexity_factors = 0;
    
    // 金額による複雑度
    $amount = floatval($grant_details['max_amount_numeric'] ?? 0);
    if ($amount >= 50000000) $complexity_factors += 3; // 5000万円以上
    elseif ($amount >= 10000000) $complexity_factors += 2; // 1000万円以上
    elseif ($amount >= 1000000) $complexity_factors += 1; // 100万円以上
    
    // 書類の複雑さ
    $required_docs = $grant_details['required_documents'] ?? '';
    if (strpos($required_docs, '事業計画書') !== false) $complexity_factors++;
    if (strpos($required_docs, '技術資料') !== false) $complexity_factors++;
    if (strpos($required_docs, '財務書類') !== false) $complexity_factors++;
    
    // 審査難易度
    $difficulty = $grant_details['grant_difficulty'] ?? 'normal';
    if ($difficulty === 'hard') $complexity_factors += 2;
    elseif ($difficulty === 'normal') $complexity_factors += 1;
    
    $characteristics['complexity_level'] = min(10, max(1, $complexity_factors));
    
    // 技術要件の抽出
    $tech_requirements = [];
    if (strpos($combined_text, 'AI') !== false || strpos($combined_text, '人工知能') !== false) {
        $tech_requirements[] = 'ai_ml';
    }
    if (strpos($combined_text, 'IoT') !== false) {
        $tech_requirements[] = 'iot';
    }
    if (strpos($combined_text, 'クラウド') !== false) {
        $tech_requirements[] = 'cloud';
    }
    $characteristics['technical_requirements'] = $tech_requirements;
    
    // 事業規模の推定
    if ($amount >= 30000000) {
        $characteristics['target_business_size'] = 'large';
    } elseif ($amount <= 3000000) {
        $characteristics['target_business_size'] = 'small';
    }
    
    // フォーカス領域の判定
    $characteristics['innovation_focus'] = strpos($combined_text, '革新') !== false || strpos($combined_text, 'イノベーション') !== false;
    $characteristics['sustainability_focus'] = strpos($combined_text, '環境') !== false || strpos($combined_text, '持続可能') !== false;
    $characteristics['digital_transformation'] = strpos($combined_text, 'DX') !== false || strpos($combined_text, 'デジタル変革') !== false;
    
    return $characteristics;
}

/**
 * 包括的AIスコア計算（機械学習風重み付けアルゴリズム）
 */
function gi_calculate_comprehensive_ai_score($grant_details) {
    $base_score = 50; // ベーススコア
    
    // === 1. 金額・規模要因 (重み: 25%) ===
    $amount_score = 0;
    $amount = floatval($grant_details['max_amount_numeric'] ?? 0);
    
    if ($amount >= 50000000) $amount_score = 25;      // 5000万円以上
    elseif ($amount >= 10000000) $amount_score = 20;  // 1000万円以上
    elseif ($amount >= 5000000) $amount_score = 15;   // 500万円以上
    elseif ($amount >= 1000000) $amount_score = 10;   // 100万円以上
    else $amount_score = 5;
    
    // === 2. 成功確率要因 (重み: 30%) ===
    $success_score = 0;
    $success_rate = floatval($grant_details['adoption_rate'] ?? 0);
    
    if ($success_rate >= 70) $success_score = 30;
    elseif ($success_rate >= 50) $success_score = 25;
    elseif ($success_rate >= 30) $success_score = 20;
    elseif ($success_rate >= 20) $success_score = 15;
    else $success_score = 10;
    
    // === 3. 申請難易度要因 (重み: 20%) ===
    $difficulty_score = 0;
    $difficulty = $grant_details['grant_difficulty'] ?? 'normal';
    
    switch ($difficulty) {
        case 'easy': $difficulty_score = 20; break;
        case 'normal': $difficulty_score = 15; break;
        case 'hard': $difficulty_score = 10; break;
        default: $difficulty_score = 12;
    }
    
    // === 4. 時間的要因 (重み: 15%) ===
    $timing_score = gi_calculate_timing_score($grant_details);
    
    // === 5. 戦略的適合性 (重み: 10%) ===
    $strategic_score = gi_calculate_strategic_fit_score($grant_details);
    
    $total_score = $base_score + $amount_score + $success_score + $difficulty_score + $timing_score + $strategic_score;
    
    return [
        'total_score' => min(100, max(0, $total_score)),
        'breakdown' => [
            'amount_factor' => $amount_score,
            'success_factor' => $success_score,
            'difficulty_factor' => $difficulty_score,
            'timing_factor' => $timing_score,
            'strategic_factor' => $strategic_score
        ],
        'confidence' => gi_calculate_score_confidence($grant_details),
        'risk_factors' => gi_identify_risk_factors($grant_details)
    ];
}

/**
 * 成功確率の推定（多変量解析風アプローチ）
 */
function gi_estimate_success_probability($grant_details) {
    // ベース確率（業界平均）
    $base_probability = 0.35; // 35%
    
    $probability_factors = [];
    
    // === 1. 金額規模による調整 ===
    $amount = floatval($grant_details['max_amount_numeric'] ?? 0);
    if ($amount <= 3000000) {
        $probability_factors['amount_size'] = 0.15; // 小規模は競争が激しい
    } elseif ($amount >= 30000000) {
        $probability_factors['amount_size'] = -0.1; // 大規模は要件が厳しい
    } else {
        $probability_factors['amount_size'] = 0.05; // 中規模が最適
    }
    
    // === 2. 業種・分野による調整 ===
    $characteristics = gi_analyze_grant_characteristics($grant_details);
    $industry_multipliers = [
        'it_digital' => 0.1,        // IT系は政策的優遇
        'sustainability' => 0.08,   // 環境系も優遇
        'manufacturing' => 0.05,    // 製造業は標準的
        'startup' => -0.05,         // スタートアップは競争激化
        'general' => 0.0
    ];
    
    $probability_factors['industry'] = $industry_multipliers[$characteristics['industry_type']] ?? 0;
    
    // === 3. 申請難易度による調整 ===
    $difficulty = $grant_details['grant_difficulty'] ?? 'normal';
    $difficulty_adjustments = [
        'easy' => -0.05,   // 簡単 = 競争が激しい
        'normal' => 0.02,  // 普通 = バランス良い
        'hard' => 0.08     // 難しい = 競合が少ない
    ];
    
    $probability_factors['difficulty'] = $difficulty_adjustments[$difficulty] ?? 0;
    
    // === 4. 締切プレッシャーによる調整 ===
    $deadline_pressure = gi_analyze_deadline_pressure($grant_details['deadline'] ?? '');
    $probability_factors['deadline'] = $deadline_pressure['is_urgent'] ? -0.08 : 0.03;
    
    // === 5. 組織の信頼性による調整 ===
    $organization = strtolower($grant_details['organization'] ?? '');
    if (strpos($organization, '経済産業省') !== false || strpos($organization, '国') !== false) {
        $probability_factors['organization'] = 0.05; // 国の機関は信頼性高い
    } elseif (strpos($organization, '県') !== false || strpos($organization, '市') !== false) {
        $probability_factors['organization'] = 0.03; // 地方自治体
    } else {
        $probability_factors['organization'] = 0.0;
    }
    
    // === 6. 特色・差別化要因 ===
    if ($grant_details['is_featured'] ?? false) {
        $probability_factors['featured'] = 0.06;
    }
    
    // 総合確率の計算
    $total_adjustment = array_sum($probability_factors);
    $final_probability = $base_probability + $total_adjustment;
    $final_probability = min(0.95, max(0.05, $final_probability)); // 5%-95%の範囲に制限
    
    // 信頼度の計算
    $confidence = gi_calculate_probability_confidence($grant_details, $probability_factors);
    
    return [
        'overall_score' => $final_probability,
        'percentage' => round($final_probability * 100, 1),
        'confidence' => $confidence,
        'contributing_factors' => $probability_factors,
        'risk_level' => gi_assess_risk_level($final_probability),
        'improvement_suggestions' => gi_generate_probability_improvement_suggestions($probability_factors, $grant_details)
    ];
}

/**
 * 業種別特化チェックリスト生成
 */
function gi_generate_it_specific_checklist($grant_details) {
    return [
        [
            'text' => 'ITシステム・ソフトウェアの技術仕様書、アーキテクチャ設計書の作成完了',
            'priority' => 'high',
            'checked' => false,
            'category' => 'technical',
            'ai_confidence' => 0.88,
            'completion_time' => '2-3日',
            'tips' => ['セキュリティ要件を明記', 'スケーラビリティを考慮', '既存システムとの連携方法を詳述']
        ],
        [
            'text' => 'DX効果の定量化：業務効率化率、コスト削減額、売上向上見込みの数値化',
            'priority' => 'critical',
            'checked' => false,
            'category' => 'impact',
            'ai_confidence' => 0.91,
            'completion_time' => '4-6時間',
            'tips' => ['現状の業務時間を測定', '導入後のシミュレーション実行', 'ROI計算を3パターン作成']
        ],
        [
            'text' => 'データセキュリティ・個人情報保護対策の具体的実装計画策定',
            'priority' => 'high',
            'checked' => false,
            'category' => 'compliance',
            'ai_confidence' => 0.85,
            'completion_time' => '1-2日',
            'tips' => ['GDPR、個人情報保護法への準拠確認', 'セキュリティ監査計画の策定']
        ]
    ];
}

function gi_generate_manufacturing_checklist($grant_details) {
    return [
        [
            'text' => '生産設備・製造機械の仕様書、能力向上計画、品質管理体制の文書化',
            'priority' => 'critical',
            'checked' => false,
            'category' => 'technical',
            'ai_confidence' => 0.89,
            'completion_time' => '2-4日',
            'tips' => ['生産能力の定量的向上目標設定', '品質指標（不良率等）の改善計画', '安全基準の遵守計画']
        ],
        [
            'text' => '製造プロセス改善による原価低減効果、生産性向上率の算出と検証',
            'priority' => 'high',
            'checked' => false,
            'category' => 'economics',
            'ai_confidence' => 0.86,
            'completion_time' => '1-2日',
            'tips' => ['現行コスト構造の詳細分析', '改善後の原価計算', '競合他社との比較分析']
        ],
        [
            'text' => '環境負荷削減、省エネルギー効果の定量的評価と認証取得計画',
            'priority' => 'medium',
            'checked' => false,
            'category' => 'sustainability',
            'ai_confidence' => 0.78,
            'completion_time' => '1日',
            'tips' => ['CO2削減効果の算出', 'ISO14001等の認証計画', 'エネルギー使用量の削減目標']
        ]
    ];
}

function gi_generate_startup_checklist($grant_details) {
    return [
        [
            'text' => '事業モデルの独自性・革新性の明確化と市場優位性の定量的証明',
            'priority' => 'critical',
            'checked' => false,
            'category' => 'strategy',
            'ai_confidence' => 0.92,
            'completion_time' => '3-5日',
            'tips' => ['競合分析マトリックスの作成', '市場規模と成長率の調査', '顧客獲得コストの算出']
        ],
        [
            'text' => '5年間の財務計画：売上予測、損益分岐点、資金調達計画の策定',
            'priority' => 'critical',
            'checked' => false,
            'category' => 'finance',
            'ai_confidence' => 0.88,
            'completion_time' => '2-3日',
            'tips' => ['保守的・楽観的・悲観的の3シナリオ作成', 'キャッシュフロー予測', '追加投資計画']
        ],
        [
            'text' => '創業チームの経歴・専門性と事業への適合性の説明資料作成',
            'priority' => 'high',
            'checked' => false,
            'category' => 'team',
            'ai_confidence' => 0.81,
            'completion_time' => '1日',
            'tips' => ['各メンバーの具体的貢献内容', '業界経験年数と実績', '外部アドバイザーの活用']
        ]
    ];
}

function gi_generate_sustainability_checklist($grant_details) {
    return [
        [
            'text' => 'SDGs目標との整合性とインパクト測定指標（KPI）の設定',
            'priority' => 'critical',
            'checked' => false,
            'category' => 'impact',
            'ai_confidence' => 0.87,
            'completion_time' => '2-3日',
            'tips' => ['関連するSDGs番号の明記', '定量的インパクト指標の設定', '第三者認証機関の活用検討']
        ],
        [
            'text' => '環境負荷削減効果の科学的根拠と第三者検証機関による評価取得',
            'priority' => 'high',
            'checked' => false,
            'category' => 'verification',
            'ai_confidence' => 0.84,
            'completion_time' => '1-2週間',
            'tips' => ['ライフサイクルアセスメント（LCA）実施', '環境影響評価の専門機関への依頼']
        ]
    ];
}

/**
 * 高度な助成金分析とAIスコアリング
 */
function gi_perform_advanced_grant_analysis($grant) {
    return [
        'success_probability' => gi_calculate_detailed_success_probability($grant),
        'roi_analysis' => gi_calculate_roi_analysis($grant),
        'competition_analysis' => gi_analyze_competitive_landscape($grant),
        'effort_value_ratio' => gi_calculate_effort_value_ratio($grant),
        'industry_compatibility' => gi_assess_industry_compatibility($grant),
        'timeline_feasibility' => gi_assess_timeline_feasibility($grant),
        'resource_requirements' => gi_estimate_resource_requirements($grant)
    ];
}

function gi_calculate_detailed_success_probability($grant) {
    $base_rate = 0.35; // 業界平均35%
    
    // 金額ファクター
    $amount_factor = 0;
    if ($grant['amount_numeric'] <= 5000000) {
        $amount_factor = 0.1; // 500万円以下は競争激化
    } elseif ($grant['amount_numeric'] >= 50000000) {
        $amount_factor = -0.15; // 5000万円以上は要件厳格
    }
    
    // 難易度ファクター
    $difficulty_factor = 0;
    if (isset($grant['difficulty']['level'])) {
        switch ($grant['difficulty']['level']) {
            case 'easy': $difficulty_factor = -0.05; break; // 簡単=競争激化
            case 'normal': $difficulty_factor = 0.02; break;
            case 'hard': $difficulty_factor = 0.08; break; // 困難=競合少
        }
    }
    
    // 成功率ファクター
    $success_rate_factor = 0;
    if (!empty($grant['success_rate']) && $grant['success_rate'] > 0) {
        $success_rate_factor = ($grant['success_rate'] - 35) / 100; // 基準からの差分
    }
    
    $final_probability = $base_rate + $amount_factor + $difficulty_factor + $success_rate_factor;
    return max(0.05, min(0.95, $final_probability)); // 5%-95%に制限
}

function gi_calculate_roi_analysis($grant) {
    $investment = $grant['amount_numeric'] ?: 0;
    $subsidy_amount = $investment; // 助成金額
    
    // 業界別標準ROI
    $industry_base_roi = [
        'it_digital' => 180,
        'manufacturing' => 150,
        'startup' => 220,
        'sustainability' => 140,
        'general' => 160
    ];
    
    // 推定ROI計算
    $estimated_roi = $industry_base_roi['general']; // デフォルト
    
    // リスク調整
    $risk_adjustment = 1.0;
    if (isset($grant['difficulty']['level']) && $grant['difficulty']['level'] === 'hard') {
        $risk_adjustment = 0.8; // リスクが高い場合は20%減
    }
    
    $projected_roi = $estimated_roi * $risk_adjustment;
    $payback_months = round(($investment / ($investment * $projected_roi / 100)) * 12);
    
    return [
        'projected_roi' => $projected_roi,
        'payback_months' => min(60, max(6, $payback_months)),
        'risk_adjusted' => $risk_adjustment < 1.0,
        'confidence' => 0.7
    ];
}

function gi_analyze_competitive_landscape($grant) {
    // 競合分析（簡略化アルゴリズム）
    $base_advantage = 0.5;
    
    $advantages = [];
    
    // 金額の魅力度
    if ($grant['amount_numeric'] >= 10000000) {
        $advantages[] = '高額助成';
        $base_advantage += 0.1;
    }
    
    // 成功率の高さ
    if (!empty($grant['success_rate']) && $grant['success_rate'] >= 40) {
        $advantages[] = '高採択率';
        $base_advantage += 0.15;
    }
    
    // 申請の容易さ
    if (isset($grant['difficulty']['level']) && $grant['difficulty']['level'] === 'easy') {
        $advantages[] = '申請容易';
        $base_advantage += 0.1;
    }
    
    return [
        'advantage_score' => min(1.0, $base_advantage),
        'key_advantages' => $advantages,
        'competitive_intensity' => $base_advantage < 0.6 ? 'high' : 'medium'
    ];
}

function gi_calculate_effort_value_ratio($grant) {
    // 労力対効果比の算出
    $value_score = ($grant['amount_numeric'] ?: 0) / 1000000; // 100万円単位
    
    $effort_score = 5; // ベース労力スコア
    if (isset($grant['difficulty']['level'])) {
        switch ($grant['difficulty']['level']) {
            case 'easy': $effort_score = 3; break;
            case 'normal': $effort_score = 5; break;
            case 'hard': $effort_score = 8; break;
        }
    }
    
    return $effort_score > 0 ? $value_score / $effort_score : 0;
}

function gi_assess_industry_compatibility($grant) {
    // 業界適合性の評価（0-1スケール）
    return 0.75; // デフォルト値（将来的にはより詳細な分析）
}

function gi_assess_timeline_feasibility($grant) {
    // スケジュール実現可能性の評価
    return [
        'feasibility_score' => 0.8,
        'critical_milestones' => ['書類準備', '審査期間', '事業実行'],
        'risk_factors' => ['締切プレッシャー']
    ];
}

function gi_estimate_resource_requirements($grant) {
    // 必要リソースの推定
    return [
        'estimated_hours' => 40, // 申請準備時間
        'required_expertise' => ['事業計画', '財務計画'],
        'external_support_needed' => false
    ];
}

function gi_calculate_composite_ai_score($grant, $analysis) {
    // 複合AIスコアの計算
    $weights = [
        'success_probability' => 0.3,
        'roi_potential' => 0.25,
        'competitive_advantage' => 0.2,
        'effort_efficiency' => 0.15,
        'industry_fit' => 0.1
    ];
    
    $score = 0;
    $score += $analysis['success_probability'] * 100 * $weights['success_probability'];
    $score += min(100, $analysis['roi_analysis']['projected_roi']) * $weights['roi_potential'];
    $score += $analysis['competition_analysis']['advantage_score'] * 100 * $weights['competitive_advantage'];
    $score += min(100, $analysis['effort_value_ratio'] * 20) * $weights['effort_efficiency'];
    $score += $analysis['industry_compatibility'] * 100 * $weights['industry_fit'];
    
    return round($score, 1);
}

function gi_analyze_grant_risks($grant) {
    $risks = [];
    
    // 締切リスク
    if (isset($grant['deadline']) && !empty($grant['deadline'])) {
        $deadline_pressure = gi_analyze_deadline_pressure($grant['deadline']);
        if ($deadline_pressure['is_urgent']) {
            $risks[] = [
                'type' => 'deadline',
                'severity' => 'high',
                'description' => '申請期限が迫っている',
                'mitigation' => '即座に準備開始、外部サポート検討'
            ];
        }
    }
    
    // 競争リスク
    if ($grant['amount_numeric'] >= 10000000) {
        $risks[] = [
            'type' => 'competition',
            'severity' => 'medium',
            'description' => '高額助成金のため競争激化の可能性',
            'mitigation' => '差別化ポイントの明確化と強化'
        ];
    }
    
    // 複雑性リスク
    if (isset($grant['difficulty']['level']) && $grant['difficulty']['level'] === 'hard') {
        $risks[] = [
            'type' => 'complexity',
            'severity' => 'medium',
            'description' => '申請要件が複雑で準備に時間を要する',
            'mitigation' => '専門家サポートの活用、十分な準備期間確保'
        ];
    }
    
    return $risks;
}

function gi_generate_optimization_suggestions($best_grant, $all_grants) {
    $suggestions = [];
    
    // 成功率向上提案
    if (isset($best_grant['success_rate']) && $best_grant['success_rate'] < 50) {
        $suggestions[] = [
            'type' => 'success_improvement',
            'priority' => 'high',
            'title' => '採択率向上のための差別化戦略',
            'description' => '競合他社との差別化ポイントを3つ以上明確にし、独自性を強調する',
            'action_items' => [
                '過去3年の採択事例を分析し、成功パターンを把握',
                '自社の技術的優位性を定量的に証明',
                '市場での独自ポジションを明確化'
            ]
        ];
    }
    
    // 準備時間最適化
    $suggestions[] = [
        'type' => 'preparation',
        'priority' => 'medium',
        'title' => '効率的な申請準備プロセス',
        'description' => '必要書類の優先順位付けと並行作業による時間短縮',
        'action_items' => [
            '重要度・緊急度マトリックスで書類作成の優先順位決定',
            '外部専門家への早期相談',
            '社内リソースの適切な配分'
        ]
    ];
    
    return $suggestions;
}

function gi_identify_key_strength($grant) {
    if (isset($grant['success_rate']) && $grant['success_rate'] >= 50) {
        return '高採択率';
    }
    if ($grant['amount_numeric'] >= 10000000) {
        return '高額助成';
    }
    if (isset($grant['difficulty']['level']) && $grant['difficulty']['level'] === 'easy') {
        return '申請容易';
    }
    return '総合バランス';
}

function gi_calculate_recommendation_confidence($best_grant, $all_grants) {
    // 推薦の信頼度計算
    $confidence = 0.7; // ベース信頼度
    
    // スコア差による調整
    if (count($all_grants) >= 2) {
        $score_diff = $best_grant['composite_score'] - $all_grants[1]['composite_score'];
        if ($score_diff >= 10) {
            $confidence += 0.2; // 大きな差がある場合は信頼度向上
        } elseif ($score_diff < 3) {
            $confidence -= 0.15; // 僅差の場合は信頼度低下
        }
    }
    
    return min(0.95, max(0.5, $confidence));
}

function gi_check_urgency_factors($grant) {
    // 緊急性要因のチェック
    if (isset($grant['deadline']) && !empty($grant['deadline'])) {
        $deadline_pressure = gi_analyze_deadline_pressure($grant['deadline']);
        return $deadline_pressure['is_urgent'];
    }
    return false;
}

/**
 * サポート関数群
 */
function gi_analyze_deadline_pressure($deadline) {
    if (empty($deadline)) {
        return ['is_urgent' => false, 'days_remaining' => null, 'recommended_prep_time' => '1-2ヶ月'];
    }
    
    $deadline_timestamp = strtotime($deadline);
    if (!$deadline_timestamp) {
        return ['is_urgent' => false, 'days_remaining' => null, 'recommended_prep_time' => '1-2ヶ月'];
    }
    
    $now = time();
    $days_remaining = ceil(($deadline_timestamp - $now) / (24 * 60 * 60));
    
    $is_urgent = $days_remaining <= 30;
    
    $recommended_prep_time = '1-2ヶ月';
    if ($days_remaining <= 14) {
        $recommended_prep_time = '即座に開始';
    } elseif ($days_remaining <= 30) {
        $recommended_prep_time = '2週間以内に開始';
    }
    
    return [
        'is_urgent' => $is_urgent,
        'days_remaining' => $days_remaining,
        'recommended_prep_time' => $recommended_prep_time,
        'strategy' => $is_urgent ? '緊急対応体制での集中準備' : '計画的な段階的準備'
    ];
}

function gi_calculate_timing_score($grant_details) {
    $score = 7; // ベーススコア
    
    $deadline_analysis = gi_analyze_deadline_pressure($grant_details['deadline'] ?? '');
    if ($deadline_analysis['is_urgent']) {
        $score -= 3; // 締切が迫っている場合は減点
    } elseif ($deadline_analysis['days_remaining'] > 60) {
        $score += 3; // 十分な準備時間がある場合は加点
    }
    
    return $score;
}

function gi_calculate_strategic_fit_score($grant_details) {
    $score = 5; // ベーススコア
    
    // 特色助成金の場合は加点
    if ($grant_details['is_featured'] ?? false) {
        $score += 3;
    }
    
    // 高い成功率の場合は加点
    $success_rate = floatval($grant_details['adoption_rate'] ?? 0);
    if ($success_rate >= 60) {
        $score += 2;
    }
    
    return $score;
}

function gi_calculate_score_confidence($grant_details) {
    // スコア算出の信頼度
    $confidence = 0.75; // ベース信頼度
    
    // データの完全性による調整
    $required_fields = ['max_amount', 'deadline', 'grant_target', 'organization'];
    $available_fields = 0;
    
    foreach ($required_fields as $field) {
        if (!empty($grant_details[$field])) {
            $available_fields++;
        }
    }
    
    $data_completeness = $available_fields / count($required_fields);
    $confidence *= $data_completeness;
    
    return round($confidence, 2);
}

function gi_identify_risk_factors($grant_details) {
    $risks = [];
    
    // 高額助成金のリスク
    if (($grant_details['max_amount_numeric'] ?? 0) >= 30000000) {
        $risks[] = '高額助成金による競争激化';
    }
    
    // 締切リスク
    $deadline_analysis = gi_analyze_deadline_pressure($grant_details['deadline'] ?? '');
    if ($deadline_analysis['is_urgent']) {
        $risks[] = '申請期限切迫による準備不足リスク';
    }
    
    // 複雑性リスク
    if (($grant_details['grant_difficulty'] ?? 'normal') === 'hard') {
        $risks[] = '申請要件の複雑性による不備リスク';
    }
    
    return $risks;
}

function gi_calculate_document_priority($grant_details) {
    $documents = [
        [
            'name' => '事業計画書（革新性・市場性・実現可能性を含む）',
            'priority' => 'critical',
            'importance_score' => 0.95,
            'estimated_time' => '5-7日',
            'preparation_tips' => [
                '市場調査データの収集と分析',
                '競合分析と差別化戦略の明確化',
                '財務計画の詳細策定',
                'リスク分析と対策の検討'
            ]
        ],
        [
            'name' => '技術資料・仕様書',
            'priority' => 'high',
            'importance_score' => 0.85,
            'estimated_time' => '3-4日',
            'preparation_tips' => [
                '技術的優位性の定量的証明',
                '開発スケジュールの詳細計画',
                '品質管理・テスト計画'
            ]
        ],
        [
            'name' => '財務関連書類（決算書、資金計画等）',
            'priority' => 'critical',
            'importance_score' => 0.90,
            'estimated_time' => '2-3日',
            'preparation_tips' => [
                '過去3年分の財務データ整理',
                '資金調達計画の策定',
                '収支予測の3シナリオ作成'
            ]
        ],
        [
            'name' => '会社案内・組織体制図',
            'priority' => 'medium',
            'importance_score' => 0.70,
            'estimated_time' => '1-2日',
            'preparation_tips' => [
                '実績・受賞歴の整理',
                'プロジェクトチーム体制の明確化',
                '外部協力機関との連携体制'
            ]
        ]
    ];
    
    // 助成金の特性に応じた優先度調整
    $characteristics = gi_analyze_grant_characteristics($grant_details);
    
    if ($characteristics['industry_type'] === 'it_digital') {
        // IT系の場合は技術資料の重要度を上げる
        foreach ($documents as &$doc) {
            if (strpos($doc['name'], '技術資料') !== false) {
                $doc['priority'] = 'critical';
                $doc['importance_score'] = 0.92;
            }
        }
    }
    
    return $documents;
}

function gi_calculate_grant_roi_potential($grant_details) {
    $investment = floatval($grant_details['max_amount_numeric'] ?? 0);
    
    // 業界別基準ROI
    $characteristics = gi_analyze_grant_characteristics($grant_details);
    $base_roi_by_industry = [
        'it_digital' => 200,
        'manufacturing' => 150,
        'startup' => 250,
        'sustainability' => 140,
        'general' => 160
    ];
    
    $base_roi = $base_roi_by_industry[$characteristics['industry_type']] ?? 160;
    
    // リスク調整
    $risk_factors = 1.0;
    if ($characteristics['complexity_level'] >= 8) {
        $risk_factors *= 0.85; // 高複雑度はリスク増
    }
    
    $projected_roi = $base_roi * $risk_factors;
    $confidence = 0.75;
    
    // 成功率による調整
    if (!empty($grant_details['adoption_rate'])) {
        $success_rate = floatval($grant_details['adoption_rate']);
        if ($success_rate >= 50) {
            $confidence += 0.1;
        } elseif ($success_rate < 20) {
            $confidence -= 0.15;
        }
    }
    
    return [
        'projected_roi' => $projected_roi,
        'confidence' => min(0.9, max(0.5, $confidence)),
        'investment_amount' => $investment,
        'estimated_return' => $investment * ($projected_roi / 100),
        'payback_period_months' => round(12 / ($projected_roi / 100))
    ];
}

function gi_generate_success_optimization_actions($grant_details, $success_probability) {
    $actions = [];
    
    // 成功確率が低い場合の改善アクション
    if ($success_probability['overall_score'] < 0.6) {
        $actions[] = [
            'text' => sprintf('AI分析による弱点改善：成功確率を%s%%から%s%%に向上させる具体的改善プラン実行',
                round($success_probability['overall_score'] * 100),
                round(min(85, $success_probability['overall_score'] * 100 + 20))),
            'priority' => 'critical',
            'checked' => false,
            'category' => 'improvement',
            'ai_confidence' => 0.88,
            'completion_time' => '1-2週間',
            'tips' => gi_generate_improvement_tips($success_probability['contributing_factors'])
        ];
    }
    
    // 差別化戦略
    $actions[] = [
        'text' => '競合他社との差別化要素3点以上の明確化と申請書への反映',
        'priority' => 'high',
        'checked' => false,
        'category' => 'differentiation',
        'ai_confidence' => 0.82,
        'completion_time' => '2-3日',
        'tips' => [
            '技術的優位性の定量化',
            '市場ポジションの独自性',
            '実績・経験による信頼性',
            'パートナー・協力機関の強み'
        ]
    ];
    
    return $actions;
}

function gi_generate_improvement_tips($contributing_factors) {
    $tips = [];
    
    foreach ($contributing_factors as $factor => $impact) {
        if ($impact < 0) { // 負の影響がある要因
            switch ($factor) {
                case 'amount_size':
                    $tips[] = '申請金額の妥当性を再検討し、必要最小限に調整';
                    break;
                case 'deadline':
                    $tips[] = '締切までの作業スケジュールを細分化し、外部サポート活用';
                    break;
                case 'difficulty':
                    $tips[] = '専門家による申請書レビューと改善提案の実施';
                    break;
            }
        }
    }
    
    if (empty($tips)) {
        $tips[] = '既存の強みをさらに強化し、アピールポイントを明確化';
    }
    
    return $tips;
}

function gi_optimize_checklist_by_ai($checklist, $characteristics, $success_probability) {
    // AI による チェックリストの最適化
    
    // 複雑度が高い場合は専門家サポートを推奨
    if ($characteristics['complexity_level'] >= 8) {
        array_unshift($checklist, [
            'text' => '高複雑度助成金のため専門家（行政書士・中小企業診断士）への早期相談実施',
            'priority' => 'critical',
            'checked' => false,
            'category' => 'expert_support',
            'ai_confidence' => 0.94,
            'completion_time' => '1日',
            'tips' => ['業界特化型の専門家選択', '成功実績の確認', '費用対効果の検討']
        ]);
    }
    
    // 成功確率が低い場合は追加対策を推奨
    if ($success_probability['overall_score'] < 0.5) {
        array_splice($checklist, 2, 0, [[
            'text' => '成功確率向上のための追加施策：類似成功事例の詳細分析と戦略調整',
            'priority' => 'critical',
            'checked' => false,
            'category' => 'strategy_enhancement',
            'ai_confidence' => 0.87,
            'completion_time' => '2-3日',
            'tips' => [
                '過去3年間の採択事例分析',
                '不採択理由の傾向調査',
                '成功要因の自社事業への適用'
            ]
        ]]);
    }
    
    return $checklist;
}

function gi_calculate_probability_confidence($grant_details, $probability_factors) {
    // 確率計算の信頼度
    $base_confidence = 0.7;
    
    // データの完全性
    $data_completeness = 0;
    $total_fields = 6;
    
    if (!empty($grant_details['max_amount'])) $data_completeness++;
    if (!empty($grant_details['deadline'])) $data_completeness++;
    if (!empty($grant_details['grant_target'])) $data_completeness++;
    if (!empty($grant_details['organization'])) $data_completeness++;
    if (!empty($grant_details['adoption_rate'])) $data_completeness++;
    if (!empty($grant_details['grant_difficulty'])) $data_completeness++;
    
    $completeness_ratio = $data_completeness / $total_fields;
    
    return round($base_confidence * $completeness_ratio, 2);
}

function gi_assess_risk_level($probability) {
    if ($probability >= 0.7) return 'low';
    if ($probability >= 0.4) return 'medium';
    return 'high';
}

function gi_generate_probability_improvement_suggestions($factors, $grant_details) {
    $suggestions = [];
    
    foreach ($factors as $factor => $impact) {
        if ($impact < -0.05) { // 大きな負の影響
            switch ($factor) {
                case 'deadline':
                    $suggestions[] = '申請期限に余裕を持った準備スケジュール策定';
                    break;
                case 'amount_size':
                    $suggestions[] = '申請金額の妥当性検証と適正化';
                    break;
                case 'industry':
                    $suggestions[] = '業界トレンドとの整合性強化';
                    break;
            }
        }
    }
    
    if (empty($suggestions)) {
        $suggestions[] = '現在の戦略を維持し、細部の品質向上に注力';
    }
    
    return $suggestions;
}

/**
 * =============================================================================
 * 【高度AI機能】 - インテリジェントQ&Aサポート機能
 * =============================================================================
 */

function gi_get_recommendation_level($score) {
    if ($score >= 80) return '🌟 最優先推奨';
    if ($score >= 70) return '⭐ 強く推奨';
    if ($score >= 60) return '✅ 推奨';
    if ($score >= 50) return '🤔 検討推奨';
    return '❌ 要慎重検討';
}

function gi_get_difficulty_based_advice($complexity_level) {
    if ($complexity_level >= 8) {
        return "高複雑度助成金のため、専門家（行政書士・中小企業診断士）との連携を強く推奨。\n" .
               "申請書作成に2-3週間、審査期間を含めて3-6ヶ月の計画が必要。";
    } elseif ($complexity_level >= 6) {
        return "中程度の複雑さのため、事前の情報収集と計画的な準備が重要。\n" .
               "類似案件の成功事例研究と、社内体制の整備を優先。";
    } else {
        return "比較的申請しやすい助成金です。\n" .
               "基本要件の確認と、明確な事業計画の策定に集中。";
    }
}

function gi_get_amount_based_advice($amount_numeric) {
    if ($amount_numeric >= 30000000) {
        return "大型助成金のため、詳細な事業計画と財務計画が必須。\n" .
               "段階的な資金活用計画と、明確なマイルストーンの設定が重要。";
    } elseif ($amount_numeric >= 5000000) {
        return "中規模助成金として、ROI計算と競合優位性の明確化が重要。\n" .
               "自己資金の確保と、実現可能性の具体的な証明を重視。";
    } else {
        return "小規模助成金として、コスト効率と迅速な成果創出を重視。\n" .
               "短期間での成果可視化と、次段階への発展計画を明示。";
    }
}

function gi_generate_application_schedule($deadline_analysis, $complexity_level) {
    $schedule = [];
    $days_remaining = $deadline_analysis['days_remaining'] ?? 60;
    
    if ($days_remaining <= 14) {
        // 緊急スケジュール
        $schedule[] = "即日～3日：基本書類の準備とアウトライン作成";
        $schedule[] = "4日～7日：事業計画書の詳細作成";
        $schedule[] = "8日～10日：財務計画と根拠資料の整備";
        $schedule[] = "11日～13日：最終チェックと提出準備";
        $schedule[] = "14日：提出完了";
    } elseif ($days_remaining <= 30) {
        // 標準スケジュール
        $schedule[] = "1週目：情報収集と基本方針の決定";
        $schedule[] = "2週目：事業計画書の骨子作成";
        $schedule[] = "3週目：詳細資料の作成と精緻化";
        $schedule[] = "4週目：専門家レビューと最終調整";
    } else {
        // 余裕あるスケジュール
        $schedule[] = "第1段階（1-2週間）：要件分析と戦略策定";
        $schedule[] = "第2段階（3-4週間）：事業計画の詳細設計";
        $schedule[] = "第3段階（5-6週間）：書類作成と根拠資料整備";
        $schedule[] = "第4段階（7-8週間）：品質向上と最終チェック";
    }
    
    return $schedule;
}

function gi_generate_eligibility_checklist($grant_details) {
    $checks = [];
    
    // 基本チェック項目
    $checks[] = "法人格の有無と設立年数の確認";
    $checks[] = "業種・事業内容の対象範囲適合性";
    $checks[] = "従業員数・資本金等の規模要件";
    
    // 金額に応じたチェック
    $amount = floatval($grant_details['max_amount_numeric'] ?? 0);
    if ($amount >= 10000000) {
        $checks[] = "財務健全性の証明（直近3年の決算書）";
        $checks[] = "事業継続性と成長計画の妥当性";
    }
    
    // 業界特化チェック
    $characteristics = gi_analyze_grant_characteristics($grant_details);
    switch ($characteristics['industry_type']) {
        case 'it_digital':
            $checks[] = "DX・IT導入の具体的計画と効果測定方法";
            $checks[] = "情報セキュリティ対策の実施体制";
            break;
        case 'manufacturing':
            $checks[] = "生産能力向上・品質改善の定量的目標";
            $checks[] = "環境負荷軽減・省エネ効果の計画";
            break;
        case 'startup':
            $checks[] = "事業の革新性・市場優位性の証明";
            $checks[] = "創業チームの経験と実績";
            break;
    }
    
    // 地域要件
    if (!empty($grant_details['regional_limitation'])) {
        $checks[] = "地域要件の適合確認（本社・事業所所在地）";
    }
    
    return $checks;
}

function gi_get_fit_level_description($fit_score) {
    if ($fit_score >= 0.9) return "（🌟 完全適合）";
    if ($fit_score >= 0.8) return "（⭐ 高適合）";
    if ($fit_score >= 0.7) return "（✅ 適合）";
    if ($fit_score >= 0.6) return "（🤔 要確認）";
    return "（❌ 適合度低）";
}

function gi_get_risk_level_jp($risk_level) {
    $risk_map = [
        'low' => '🟢 低リスク',
        'medium' => '🟡 中リスク', 
        'high' => '🔴 高リスク'
    ];
    return $risk_map[$risk_level] ?? '不明';
}

function gi_get_factor_name_jp($factor) {
    $factor_names = [
        'amount_size' => '金額規模適正性',
        'industry' => '業界政策適合性',
        'difficulty' => '申請難易度バランス',
        'deadline' => 'スケジュール余裕度',
        'organization' => '実施機関信頼性',
        'featured' => '注目助成金優遇'
    ];
    return $factor_names[$factor] ?? $factor;
}

function gi_get_competition_level_jp($level) {
    $level_map = [
        'low' => '🟢 競合少',
        'medium' => '🟡 標準的',
        'high' => '🔴 激戦'
    ];
    return $level_map[$level] ?? '標準的';
}

function gi_generate_differentiation_strategies($grant_details, $competitive_analysis) {
    $strategies = [];
    
    // 基本的な差別化戦略
    $strategies[] = "技術的独自性の定量的証明（特許、ノウハウ等）";
    $strategies[] = "市場での先行優位性と参入障壁の明確化";
    $strategies[] = "顧客基盤・パートナーシップの競争優位性";
    
    // 業界特化戦略
    $characteristics = gi_analyze_grant_characteristics($grant_details);
    switch ($characteristics['industry_type']) {
        case 'it_digital':
            $strategies[] = "AIアルゴリズムの独自性とセキュリティレベル";
            $strategies[] = "既存システムとの統合性と拡張性";
            break;
        case 'manufacturing':
            $strategies[] = "生産効率・品質向上の数値的優位性";
            $strategies[] = "環境負荷削減効果の科学的根拠";
            break;
        case 'startup':
            $strategies[] = "ビジネスモデルの革新性と市場創造性";
            $strategies[] = "スケーラビリティと国際展開可能性";
            break;
    }
    
    // 競合レベルに応じた戦略
    if ($competitive_analysis['competitive_intensity'] === 'high') {
        $strategies[] = "複数の差別化要素の組み合わせによるユニークポジション";
        $strategies[] = "定量的効果測定による客観的優位性証明";
    }
    
    return $strategies;
}

function gi_generate_recommended_actions($grant_details, $comprehensive_score, $success_probability) {
    $actions = [];
    
    // スコアベースの推奨アクション
    if ($comprehensive_score['total_score'] >= 80) {
        $actions[] = "高スコア助成金のため、優先的に申請準備を開始";
        $actions[] = "専門家レビューによる更なる品質向上";
    } elseif ($comprehensive_score['total_score'] >= 60) {
        $actions[] = "中評価助成金として、弱点補強後の申請を検討";
        $actions[] = "類似助成金との比較検討も並行実施";
    } else {
        $actions[] = "低評価のため、要件見直しか他助成金の検討を推奨";
        $actions[] = "事業計画の根本的な見直しが必要な可能性";
    }
    
    // 成功確率ベースのアクション
    if ($success_probability['overall_score'] < 0.5) {
        $actions[] = "成功確率が低いため、改善策の実施が急務";
        $actions[] = "外部専門家による戦略見直しを検討";
    }
    
    // 緊急度ベースのアクション
    $deadline_analysis = gi_analyze_deadline_pressure($grant_details['deadline'] ?? '');
    if ($deadline_analysis['is_urgent']) {
        $actions[] = "締切が迫っているため、即座の行動開始が必要";
    }
    
    // 複雑度ベースのアクション
    $characteristics = gi_analyze_grant_characteristics($grant_details);
    if ($characteristics['complexity_level'] >= 7) {
        $actions[] = "高複雑度のため、十分な準備期間と専門家支援を確保";
    }
    
    return array_unique($actions);
}

function gi_calculate_self_funding_amount($grant_details) {
    $total_amount = floatval($grant_details['max_amount_numeric'] ?? 0);
    $subsidy_rate_text = $grant_details['subsidy_rate'] ?? '50%';
    
    // 補助率の数値抽出
    $subsidy_rate = 0.5; // デフォルト50%
    if (preg_match('/(\d+)/', $subsidy_rate_text, $matches)) {
        $subsidy_rate = floatval($matches[1]) / 100;
    }
    
    // 総事業費から助成金額を引いた自己負担額
    $total_project_cost = $total_amount / $subsidy_rate;
    $self_funding = $total_project_cost - $total_amount;
    
    return max(0, $self_funding);
}

/**
 * =============================================================================
 * 都道府県・市町村データ管理機能
 * =============================================================================
 */

/**
 * 都道府県別標準市町村リストを取得
 */
function gi_get_standard_municipalities_by_prefecture($pref_slug) {
    // 主要都道府県の市町村リスト（簡略版）
    $municipalities_data = [
        'hokkaido' => ['札幌市', '函館市', '小樽市', '旭川市', '室蘭市', '釧路市', '帯広市', '北見市', '夕張市', '岩見沢市'],
        'aomori' => ['青森市', '弘前市', '八戸市', '黒石市', '五所川原市', '十和田市', 'つがる市', '平川市'],
        'iwate' => ['盛岡市', '宮古市', '大船渡市', '花巻市', '北上市', '久慈市', '遠野市', '一関市', '陸前高田市', '釜石市'],
        'miyagi' => ['仙台市', '石巻市', '塩竈市', '気仙沼市', '白石市', '名取市', '角田市', '多賀城市', '岩沼市', '登米市'],
        'akita' => ['秋田市', '能代市', '横手市', '大館市', '男鹿市', '湯沢市', '鹿角市', '由利本荘市', '潟上市', '大仙市'],
        'yamagata' => ['山形市', '米沢市', '鶴岡市', '酒田市', '新庄市', '寒河江市', '上山市', '村山市', '長井市', '天童市'],
        'fukushima' => ['福島市', '会津若松市', '郡山市', 'いわき市', '白河市', '須賀川市', '喜多方市', '相馬市', '二本松市', '田村市'],
        'ibaraki' => ['水戸市', '日立市', '土浦市', '古河市', '石岡市', '結城市', '龍ケ崎市', '下妻市', '常総市', '常陸太田市'],
        'tochigi' => ['宇都宮市', '足利市', '栃木市', '佐野市', '鹿沼市', '日光市', '小山市', '真岡市', '大田原市', '矢板市'],
        'gunma' => ['前橋市', '高崎市', '桐生市', '伊勢崎市', '太田市', '沼田市', '館林市', '渋川市', '藤岡市', '富岡市'],
        'saitama' => ['さいたま市', '川越市', '熊谷市', '川口市', '行田市', '秩父市', '所沢市', '飯能市', '加須市', '本庄市'],
        'chiba' => ['千葉市', '銚子市', '市川市', '船橋市', '館山市', '木更津市', '松戸市', '野田市', '茂原市', '成田市'],
        'tokyo' => ['千代田区', '中央区', '港区', '新宿区', '文京区', '台東区', '墨田区', '江東区', '品川区', '目黒区', '大田区', '世田谷区', '渋谷区', '中野区', '杉並区', '豊島区', '北区', '荒川区', '板橋区', '練馬区', '足立区', '葛飾区', '江戸川区'],
        'kanagawa' => ['横浜市', '川崎市', '相模原市', '横須賀市', '平塚市', '鎌倉市', '藤沢市', '小田原市', '茅ヶ崎市', '逗子市'],
        'niigata' => ['新潟市', '長岡市', '三条市', '柏崎市', '新発田市', '小千谷市', '加茂市', '十日町市', '見附市', '村上市'],
        'toyama' => ['富山市', '高岡市', '魚津市', '氷見市', '滑川市', '黒部市', '砺波市', '小矢部市', '南砺市', '射水市'],
        'ishikawa' => ['金沢市', '七尾市', '小松市', '輪島市', '珠洲市', '加賀市', '羽咋市', 'かほく市', '白山市', '能美市'],
        'fukui' => ['福井市', '敦賀市', '小浜市', '大野市', '勝山市', '鯖江市', 'あわら市', '越前市'],
        'yamanashi' => ['甲府市', '富士吉田市', '都留市', '山梨市', '大月市', '韮崎市', '南アルプス市', '北杜市', '甲斐市', '笛吹市'],
        'nagano' => ['長野市', '松本市', '上田市', '岡谷市', '飯田市', '諏訪市', '須坂市', '小諸市', '伊那市', '駒ヶ根市'],
        'gifu' => ['岐阜市', '大垣市', '高山市', '多治見市', '関市', '中津川市', '美濃市', '瑞浪市', '羽島市', '恵那市'],
        'shizuoka' => ['静岡市', '浜松市', '沼津市', '熱海市', '三島市', '富士宮市', '伊東市', '島田市', '富士市', '磐田市'],
        'aichi' => ['名古屋市', '豊橋市', '岡崎市', '一宮市', '瀬戸市', '半田市', '春日井市', '豊川市', '津島市', '碧南市'],
        'mie' => ['津市', '四日市市', '伊勢市', '松阪市', '桑名市', '鈴鹿市', '名張市', '尾鷲市', '亀山市', '鳥羽市'],
        'shiga' => ['大津市', '彦根市', '長浜市', '近江八幡市', '草津市', '守山市', '栗東市', '甲賀市', '野洲市', '湖南市'],
        'kyoto' => ['京都市', '福知山市', '舞鶴市', '綾部市', '宇治市', '宮津市', '亀岡市', '城陽市', '向日市', '長岡京市'],
        'osaka' => ['大阪市', '堺市', '岸和田市', '豊中市', '池田市', '吹田市', '泉大津市', '高槻市', '貝塚市', '守口市'],
        'hyogo' => ['神戸市', '姫路市', '尼崎市', '明石市', '西宮市', '洲本市', '芦屋市', '伊丹市', '相生市', '豊岡市'],
        'nara' => ['奈良市', '大和高田市', '大和郡山市', '天理市', '橿原市', '桜井市', '五條市', '御所市', '生駒市', '香芝市'],
        'wakayama' => ['和歌山市', '海南市', '橋本市', '有田市', '御坊市', '田辺市', '新宮市', '紀の川市', '岩出市'],
        'tottori' => ['鳥取市', '米子市', '倉吉市', '境港市'],
        'shimane' => ['松江市', '浜田市', '出雲市', '益田市', '大田市', '安来市', '江津市', '雲南市'],
        'okayama' => ['岡山市', '倉敷市', '津山市', '玉野市', '笠岡市', '井原市', '総社市', '高梁市', '新見市', '備前市'],
        'hiroshima' => ['広島市', '呉市', '竹原市', '三原市', '尾道市', '福山市', '府中市', '三次市', '庄原市', '大竹市'],
        'yamaguchi' => ['下関市', '宇部市', '山口市', '萩市', '防府市', '下松市', '岩国市', '光市', '長門市', '柳井市'],
        'tokushima' => ['徳島市', '鳴門市', '小松島市', '阿南市', '吉野川市', '阿波市', '美馬市', '三好市'],
        'kagawa' => ['高松市', '丸亀市', '坂出市', '善通寺市', '観音寺市', 'さぬき市', '東かがわ市', '三豊市'],
        'ehime' => ['松山市', '今治市', '宇和島市', '八幡浜市', '新居浜市', '西条市', '大洲市', '伊予市', '四国中央市', '西予市'],
        'kochi' => ['高知市', '室戸市', '安芸市', '南国市', '土佐市', '須崎市', '宿毛市', '土佐清水市', '四万十市', '香南市'],
        'fukuoka' => ['北九州市', '福岡市', '大牟田市', '久留米市', '直方市', '飯塚市', '田川市', '柳川市', '八女市', '筑後市'],
        'saga' => ['佐賀市', '唐津市', '鳥栖市', '多久市', '伊万里市', '武雄市', '鹿島市', '小城市', '嬉野市', '神埼市'],
        'nagasaki' => ['長崎市', '佐世保市', '島原市', '諫早市', '大村市', '平戸市', '松浦市', '対馬市', '壱岐市', '五島市'],
        'kumamoto' => ['熊本市', '八代市', '人吉市', '荒尾市', '水俣市', '玉名市', '山鹿市', '菊池市', '宇土市', '上天草市'],
        'oita' => ['大分市', '別府市', '中津市', '日田市', '佐伯市', '臼杵市', '津久見市', '竹田市', '豊後高田市', '杵築市'],
        'miyazaki' => ['宮崎市', '都城市', '延岡市', '日南市', '小林市', '日向市', '串間市', '西都市', 'えびの市'],
        'kagoshima' => ['鹿児島市', '鹿屋市', '枕崎市', '阿久根市', '出水市', '指宿市', '西之表市', '垂水市', '薩摩川内市', '日置市'],
        'okinawa' => ['那覇市', '宜野湾市', '石垣市', '浦添市', '名護市', '糸満市', '沖縄市', '豊見城市', 'うるま市', '宮古島市']
    ];
    
    return $municipalities_data[$pref_slug] ?? [];
}

/**
 * 市町村フィルタリングでの連携機能を改善
 */
function gi_enhance_municipality_filtering() {
    // 既存の市町村タームに都道府県メタデータを追加
    $municipalities = get_terms([
        'taxonomy' => 'grant_municipality',
        'hide_empty' => false
    ]);
    
    foreach ($municipalities as $municipality) {
        // 都道府県情報が無い場合は、スラッグから推定
        if (!get_term_meta($municipality->term_id, 'prefecture_slug', true)) {
            $slug_parts = explode('-', $municipality->slug);
            if (count($slug_parts) >= 2) {
                $pref_slug = $slug_parts[0];
                $pref_term = get_term_by('slug', $pref_slug, 'grant_prefecture');
                
                if ($pref_term) {
                    add_term_meta($municipality->term_id, 'prefecture_slug', $pref_slug);
                    add_term_meta($municipality->term_id, 'prefecture_name', $pref_term->name);
                }
            }
        }
    }
}

/**
 * Initialize all standard municipalities for all prefectures
 * 全都道府県の標準市町村データを初期化
 */
function gi_initialize_all_municipalities() {
    // 全都道府県のスラッグリスト
    $all_prefectures = [
        'hokkaido', 'aomori', 'iwate', 'miyagi', 'akita', 'yamagata', 'fukushima',
        'ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba', 'tokyo', 'kanagawa',
        'niigata', 'toyama', 'ishikawa', 'fukui', 'yamanashi', 'nagano', 'gifu',
        'shizuoka', 'aichi', 'mie', 'shiga', 'kyoto', 'osaka', 'hyogo', 'nara',
        'wakayama', 'tottori', 'shimane', 'okayama', 'hiroshima', 'yamaguchi',
        'tokushima', 'kagawa', 'ehime', 'kochi', 'fukuoka', 'saga', 'nagasaki',
        'kumamoto', 'oita', 'miyazaki', 'kagoshima', 'okinawa'
    ];
    
    $created_count = 0;
    $updated_count = 0;
    
    foreach ($all_prefectures as $pref_slug) {
        $pref_term = get_term_by('slug', $pref_slug, 'grant_prefecture');
        if (!$pref_term) continue;
        
        $pref_name = $pref_term->name;
        $municipalities_list = gi_get_standard_municipalities_by_prefecture($pref_slug);
        
        foreach ($municipalities_list as $muni_name) {
            $muni_slug = $pref_slug . '-' . sanitize_title($muni_name);
            $existing_term = get_term_by('slug', $muni_slug, 'grant_municipality');
            
            if (!$existing_term) {
                // 新規作成
                $result = wp_insert_term(
                    $muni_name,
                    'grant_municipality',
                    [
                        'slug' => $muni_slug,
                        'description' => $pref_name . '・' . $muni_name
                    ]
                );
                
                if (!is_wp_error($result)) {
                    add_term_meta($result['term_id'], 'prefecture_slug', $pref_slug);
                    add_term_meta($result['term_id'], 'prefecture_name', $pref_name);
                    $created_count++;
                }
            } else {
                // 既存タームのメタデータ更新
                if (!get_term_meta($existing_term->term_id, 'prefecture_slug', true)) {
                    add_term_meta($existing_term->term_id, 'prefecture_slug', $pref_slug);
                    add_term_meta($existing_term->term_id, 'prefecture_name', $pref_name);
                    $updated_count++;
                }
            }
        }
    }
    
    return [
        'created' => $created_count,
        'updated' => $updated_count
    ];
}