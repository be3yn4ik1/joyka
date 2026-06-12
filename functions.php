<?php
require_once get_stylesheet_directory() . '/inc/joyvia-entities.php';
require_once get_stylesheet_directory() . '/inc/joyvia-catalog-sync.php';
require_once get_stylesheet_directory() . '/inc/joyvia-schema.php';
require_once get_stylesheet_directory() . '/inc/joyvia-admin-performers.php';


//*************** AJAX: Загрузка избранных исполнителей
add_action('wp_ajax_joyvia_get_favorites_ajax', 'joyvia_get_favorites_ajax_callback');
add_action('wp_ajax_nopriv_joyvia_get_favorites_ajax', 'joyvia_get_favorites_ajax_callback');
function joyvia_get_favorites_ajax_callback() {
    $ids_raw = isset($_POST['ids']) ? json_decode(stripslashes($_POST['ids']), true) : [];
    if (!is_array($ids_raw) || empty($ids_raw)) {  wp_send_json_error(['message' => 'Массив ID пуст или невалиден']); }
    $ids = array_filter(array_map('intval', $ids_raw));
    if (empty($ids)) { wp_send_json_error(['message' => 'После очистки не осталось валидных ID']);  }
    $fav_query = new WP_Query([
        'post_type'      => 'performer',
        'post_status'    => 'any', 
        'post__in'       => $ids,
        'orderby'        => 'post__in', 
        'posts_per_page' => -1,
    ]);
    $html = '';
    if ($fav_query->have_posts()) {
        ob_start();
        $position = 1;
        while ($fav_query->have_posts()) {
            $fav_query->the_post();
            global $post; 
            set_query_var('executor', $post);
            set_query_var('item_position', $position++);
            get_template_part('template/executor');
        }
        wp_reset_postdata();
        $html = ob_get_clean();
    }
    wp_send_json_success(['html' => $html, 'found_posts' => $fav_query->found_posts]);
}

//****************Этот код будет искать скилы специи, у которых в поле ACF profession_link указан ID выбранной пользователем профессии.
add_action('wp_ajax_get_prof_data', 'joyvia_get_prof_data');
add_action('wp_ajax_nopriv_get_prof_data', 'joyvia_get_prof_data');
function joyvia_get_prof_data() {
    $prof_id = intval($_POST['prof_id']);
    // Получаем Специализации
    $specs = get_terms([
        'taxonomy' => 'specialization',
        'hide_empty' => false,
        'meta_query' => [[ 'key' => 'profession_link', 'value' => $prof_id, 'compare' => '='
        ]]
    ]);
    // Получаем Навыки
    $skills = get_terms([
        'taxonomy' => 'skill',
        'hide_empty' => false,
        'meta_query' => [[ 'key' => 'profession_link', 'value' => $prof_id, 'compare' => '='
        ]]
    ]);
    ob_start();
    if (!empty($specs)) {
        foreach ($specs as $term) {
            echo '<label class="spec-skill-label"><input type="checkbox" name="specializations[]" value="'.$term->term_id.'"><div class="spec-skill-card">'.esc_html($term->name).'</div></label>';
        }
    }
    $specs_html = ob_get_clean();
    ob_start();
    if (!empty($skills)) {
        foreach ($skills as $term) {
            echo '<label class="spec-skill-label"><input type="checkbox" name="skills[]" value="'.$term->term_id.'"><div class="spec-skill-card">'.esc_html($term->name).'</div></label>';
        }
    }
    $skills_html = ob_get_clean();
    wp_send_json_success(['specs' => $specs_html, 'skills' => $skills_html]);
}


//*************** Скрытие админ-панели и запрет входа в wp-admin для не-админов
add_filter('show_admin_bar', function($show) {
    if (!current_user_can('administrator')) { return false; }
    return $show;
});
add_action('admin_init', function() {
    if (defined('DOING_AJAX') && DOING_AJAX) { return; }
    if (!current_user_can('administrator')) { wp_safe_redirect(home_url()); exit; }
});


//*************** Регистрация и авторизация пользователей через AJAX (с reCAPTCHA)
add_action('wp_enqueue_scripts', 'joyvia_enqueue_auth_scripts');
function joyvia_enqueue_auth_scripts() {
    wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
    wp_enqueue_script('joyvia-auth', get_stylesheet_directory_uri() . '/js/auth.js', [], '1.1', true);
    wp_localize_script('joyvia-auth', 'joyvia_ajax', [
        'url'   => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('joyvia_auth_nonce')
    ]);
}

add_action('wp_ajax_nopriv_joyvia_auth_handler', 'joyvia_auth_process');
add_action('wp_ajax_joyvia_auth_handler', 'joyvia_auth_process');
function joyvia_auth_process() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'joyvia_auth_nonce')) {
        wp_send_json_error('Ошибка безопасности');
    }
    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $_POST['g-recaptcha-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);
    $body = json_decode(wp_remote_retrieve_body($response));
    if (!$body || !$body->success) {
        wp_send_json_error('Проверка капчи не пройдена');
    }
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $action_type = sanitize_text_field($_POST['action_type']);
    if (!is_email($email)) {
        wp_send_json_error('Неверный формат email');
    }
    if ($action_type === 'register') {
        if (email_exists($email)) {
            wp_send_json_error('Email уже зарегистрирован');
        }
        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_pass' => $password,
            'user_email' => $email,
            'role' => 'author'
        ]);
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        Joyvia_Profile_Manager::ensure_profile($user_id);
        joyvia_send_verification_email($user_id, $email);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        wp_send_json_success(['redirect' => '/profile-setup/']);
    } elseif ($action_type === 'login') {
        $user = wp_authenticate($email, $password);
        if (is_wp_error($user)) {
            wp_send_json_error('Неверный email или пароль');
        }
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        wp_send_json_success(['redirect' => '/profile-settings/']);
    }
    wp_die();
}

//*************** Обработка и сохранение отзывов (кастомный тип комментариев)
add_filter( 'rank_math/sitemap/enable_caching', '__return_false' );
add_action('wp_ajax_submit_review', 'handle_review_submission');
add_action('wp_ajax_nopriv_submit_review', 'handle_review_submission');
function handle_review_submission() {
    error_log('Review submission started');
    error_log('POST data: ' . print_r($_POST, true));
    if (!isset($_POST['review_nonce']) || !wp_verify_nonce($_POST['review_nonce'], 'submit_review_nonce')) {
        error_log('Nonce verification failed');
        wp_send_json_error('Ошибка безопасности');
        return;
    }
    error_log('Nonce verification passed');
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptcha_response)) {
        wp_send_json_error('Подтвердите, что вы не робот');
        return;
    }
    $recaptcha_secret = RECAPTCHA_SECRET_KEY;
    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret' => $recaptcha_secret,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);
    if (is_wp_error($response)) {
        wp_send_json_error('Ошибка проверки reCAPTCHA');
        return;
    }
    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body, true);
    if (!$result || !$result['success']) {
        wp_send_json_error('Проверка reCAPTCHA не пройдена');
        return;
    }
    $author_id = intval($_POST['author_id'] ?? 0);
    $reviewer_name = sanitize_text_field($_POST['reviewer_name'] ?? '');
    $reviewer_email = sanitize_email($_POST['reviewer_email'] ?? '');
    $review_text_raw = $_POST['review_text'] ?? '';
    $rating = intval($_POST['rating'] ?? 0);
    $review_text_raw = trim($review_text_raw);
    $review_length = mb_strlen($review_text_raw);
    error_log('Review text length before sanitization: ' . $review_length);
    if (empty($reviewer_name) || empty($reviewer_email) || empty($review_text_raw)) {
        wp_send_json_error('Заполните все обязательные поля');
        return;
    }
    if (!is_email($reviewer_email)) { wp_send_json_error('Введите корректный email');
        return;
    }
    if ($rating < 1 || $rating > 5) { wp_send_json_error('Выберите рейтинг от 1 до 5');
        return;
    }
    if ($review_length < 10) {  wp_send_json_error('Отзыв должен содержать не менее 10 символов');
        return;
    }
    if ($review_length > 500) { wp_send_json_error('Отзыв не должен превышать 500 символов');
        return;
    }
    if ($author_id <= 0) {  wp_send_json_error('Неверный ID автора');
        return;
    }
    $review_text = sanitize_textarea_field($review_text_raw);
    $reviews_post_id = get_option('reviews_post_id');
    if (!$reviews_post_id || !get_post($reviews_post_id)) {
        $post_data = [
            'post_title' => 'Отзывы пользователей',
            'post_content' => 'Служебная запись для хранения отзывов',
            'post_status' => 'private',
            'post_type' => 'page',
        ];
        $reviews_post_id = wp_insert_post($post_data);
        update_option('reviews_post_id', $reviews_post_id);
    }
    
    $comment_data = [
        'comment_post_ID' => $reviews_post_id,
        'comment_author' => $reviewer_name,
        'comment_author_email' => $reviewer_email,
        'comment_content' => $review_text,
        'comment_type' => 'review',
        'comment_approved' => 0,
        'comment_author_IP' => $_SERVER['REMOTE_ADDR'],
        'comment_agent' => $_SERVER['HTTP_USER_AGENT'],
        'comment_date' => current_time('mysql'),
        'comment_date_gmt' => current_time('mysql', 1),
    ];
    $comment_id = wp_insert_comment($comment_data);
    error_log('Comment insertion result: ' . $comment_id);
    if ($comment_id) {
        add_comment_meta($comment_id, 'author_id', $author_id);
        add_comment_meta($comment_id, 'rating', $rating);
        error_log('Comment created successfully with ID: ' . $comment_id);
        wp_send_json_success('Отзыв отправлен на модерацию');
    } else {
        error_log('Comment insertion failed');
        wp_send_json_error('Ошибка при сохранении отзыва');
    }
}


//*************** Кастомизация списка комментариев в админке (Колонки и фильтры для отзывов)
add_filter('manage_edit-comments_columns', 'add_comment_rating_column');
function add_comment_rating_column($columns) {
    $columns['rating'] = 'Рейтинг';
    $columns['author_id'] = 'Автор профиля';
    return $columns;
}

add_action('manage_comments_custom_column', 'show_comment_rating_column', 10, 2);
function show_comment_rating_column($column, $comment_id) {
    if ($column === 'rating') {
        $rating = get_comment_meta($comment_id, 'rating', true);
        if ($rating) {
            echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . ' (' . $rating . ')';
        }
    }
    if ($column === 'author_id') {
        $author_id = get_comment_meta($comment_id, 'author_id', true);
        if ($author_id) {
            $user = get_user_by('ID', $author_id);
            if ($user) {
                $first_name = get_user_meta($author_id, 'first_name', true);
                $last_name = get_user_meta($author_id, 'last_name', true);
                echo $first_name . ' ' . $last_name . ' (ID: ' . $author_id . ')';
            }
        }
    }
}

add_action('restrict_manage_comments', 'add_review_filter');
function add_review_filter() {
    $comment_type = isset($_GET['comment_type']) ? $_GET['comment_type'] : '';
    ?>
    <select name="comment_type">
        <option value="">Все типы</option>
        <option value="review" <?php selected($comment_type, 'review'); ?>>Отзывы</option>
        <option value="comment" <?php selected($comment_type, 'comment'); ?>>Обычные комментарии</option>
    </select>
    <?php
}

add_filter('pre_get_comments', 'filter_comments_by_type');
function filter_comments_by_type($query) {
    if (is_admin() && isset($_GET['comment_type']) && $_GET['comment_type'] === 'review') { $query->query_vars['type'] = 'review'; }
    return $query;
}

add_filter('comment_row_actions', 'modify_review_comment_actions', 10, 2);
function modify_review_comment_actions($actions, $comment) {
    if ($comment->comment_type === 'review') {
        $author_id = get_comment_meta($comment->comment_ID, 'author_id', true);
        if ($author_id) {
            $user = get_user_by('ID', $author_id);
            if ($user) {
                $first_name = get_user_meta($author_id, 'first_name', true);
                $last_name = get_user_meta($author_id, 'last_name', true);
                $actions['view_profile'] = '<a href="' . get_author_posts_url($author_id) . '">Профиль: ' . $first_name . ' ' . $last_name . '</a>';
            }
        }
    }
    return $actions;
}


//*************** Интеграция Contact Form 7 с Telegram
add_action('wpcf7_before_send_mail', 'joyvia_send_cf7_to_telegram', 10, 3);
function joyvia_send_cf7_to_telegram($contact_form, &$abort, $submission) {
    $worker_url = 'https://shrill-wildflower-a5b5.shisigrekk.workers.dev';
    $bot_token  = get_field('telegram_bot_token', 'option');
    $chat_id    = get_field('telegram_chat_id', 'option');
    if (empty($bot_token) || empty($chat_id)) return;
    $mail = $contact_form->prop('mail');
    $posted_data = $submission->get_posted_data();
    if (empty($mail['body'])) {
        $message = "Новая заявка:\n\n";
        foreach ($posted_data as $key => $value) {
            if (strpos($key, '_') !== 0) {
                $val = is_array($value) ? implode(', ', $value) : $value;
                $message .= "{$key}: {$val}\n";
            }
        }
    } else {
        $message = wpcf7_mail_replace_tags($mail['body']);
        $message = str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $message);
        $message = wp_strip_all_tags($message);
    }
    wp_remote_post("{$worker_url}/bot{$bot_token}/sendMessage", [
        'body' => [
            'chat_id' => $chat_id,
            'text'    => trim($message),
            'disable_web_page_preview' => true,
        ],
        'timeout'   => 15,
        'sslverify' => false
    ]);
}

//*************** Глобальное отключение jQuery и мусорных скриптов
function remove_jquery_globally() { if (!is_admin()) { wp_deregister_script('jquery');  wp_dequeue_script('jquery');  }  }
add_action('wp_enqueue_scripts', 'remove_jquery_globally', 100);
function remove_parent_footer_script() { remove_action('wp_footer', 'blankslate_footer');}
add_action('wp_enqueue_scripts', 'remove_parent_footer_script', 100);

//*************** Статус "Последний раз в сети" для профилей пользователей
function custom_human_time_diff($from, $to) {
    $diff = $to - $from;
    $map = [
        60              => 'В сети 1 мин. назад',
        5*60            => 'В сети 5 мин. назад',
        15*60           => 'В сети 15 мин. назад',
        30*60           => 'В сети 30 мин. назад',
        60*60           => 'В сети 1 ч. назад',
        3*60*60         => 'В сети 3 ч. назад',
        6*60*60         => 'В сети 6 ч. назад',
        12*60*60        => 'В сети 12 ч. назад',
        16*60*60        => 'В сети 16 ч. назад',
        24*60*60        => 'В сети 1 д. назад',
        2*24*60*60      => 'В сети 2 д. назад',
        3*24*60*60      => 'В сети 3 д. назад',
        4*24*60*60      => 'В сети 4 д. назад',
        5*24*60*60      => 'В сети 5 д. назад',
        6*24*60*60      => 'В сети 6 д. назад',
        7*24*60*60      => 'В сети 1 нед. назад',
        14*24*60*60     => 'В сети 2 нед. назад',
        21*24*60*60     => 'В сети 3 нед. назад',
    ];
    foreach ($map as $s => $label) {
        if ($diff < $s) return $label;
    }
    return 'В сети был(а) давно';
}
function update_last_visit() {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'last_login', current_time('timestamp'));
    }
}
add_action('init', 'update_last_visit');


//*************** AJAX: Загрузка авторов с фильтрацией по цене и пагинацией
add_action('wp_ajax_load_more_authors', 'load_more_authors_callback');
add_action('wp_ajax_nopriv_load_more_authors', 'load_more_authors_callback');
function load_more_authors_callback() {
    $offset = intval($_POST['offset']);
    $meta_query = isset($_POST['meta_query']) ? json_decode(stripslashes($_POST['meta_query']), true) : array();
    $current_user_id = get_current_user_id();
    $price_from = isset($_POST['price_from']) ? intval($_POST['price_from']) : 0;
    $price_to = isset($_POST['price_to']) ? intval($_POST['price_to']) : 0;
    if (!is_array($meta_query)) {
        $meta_query = array();
    }
    $base_args = array(
        'exclude' => array($current_user_id),
        'role__not_in' => array('Administrator'),
        'orderby' => 'meta_value_num',
        'meta_key' => 'rejting',
        'order' => 'DESC',
        'meta_query' => $meta_query
    );
    
    if ($price_from > 0 || $price_to > 0) {
        $all_users = get_users($base_args);
        $filtered_users = array();
        foreach ($all_users as $user) {
            $gruppa_uslug = get_field('gruppa_uslug', 'user_' . $user->ID);
            if ($gruppa_uslug && isset($gruppa_uslug['usluga_v_napravlenii']) && is_array($gruppa_uslug['usluga_v_napravlenii'])) {
                $user_has_matching_price = false;
                foreach ($gruppa_uslug['usluga_v_napravlenii'] as $usluga) {
                    $tovary = $usluga['privyazka_tovara_k_naprvleniyu'];
                    if (is_array($tovary)) {
                        foreach ($tovary as $product_post) {
                            $product = wc_get_product($product_post->ID);
                            if (!$product) continue;
                            $product_price = floatval($product->get_price());
                            // Проверяем соответствие цены фильтру
                            $price_matches = true;
                            if ($price_from > 0 && $product_price < $price_from) {
                                $price_matches = false;
                            }
                            if ($price_to > 0 && $product_price > $price_to) {
                                $price_matches = false;
                            }
                            
                            if ($price_matches) {
                                $user_has_matching_price = true;
                                break 2; // Выходим из обоих циклов
                            }
                        }
                    }
                }
                if ($user_has_matching_price) {
                    $filtered_users[] = $user;
                }
            }
        }
        $total = count($filtered_users);
        $users = array_slice($filtered_users, $offset, 12);
        
    } else {
        $args = array_merge($base_args, array(
            'number' => 12,
            'offset' => $offset
        ));
        
        $users = get_users($args);
        $total = count(get_users($base_args));
    }
    $html = '';
    if (!empty($users)) {
        foreach ($users as $user) {
            ob_start();
            set_query_var('executor', $user);
            get_template_part('template/executor');
            $html .= ob_get_clean();
        }
    }
    
    $has_more = ($offset + 12 < $total);
    
    wp_send_json(array(
        'html' => $html,
        'has_more' => $has_more,
        'total' => $total,
        'debug' => array(
            'offset' => $offset,
            'meta_query' => $meta_query,
            'found_users' => count($users),
            'price_from' => $price_from,
            'price_to' => $price_to
        )
    ));
}


//*************** AJAX: Загрузка похожих исполнителей по общим терминам (направлениям)
add_action('wp_ajax_load_more_similar_authors', 'load_more_similar_authors_callback');
add_action('wp_ajax_nopriv_load_more_similar_authors', 'load_more_similar_authors_callback');
function load_more_similar_authors_callback() {
    $offset = intval($_POST['offset']);
    $current_user_id = intval($_POST['current_user_id']);
    $current_terms = isset($_POST['current_terms']) ? json_decode(stripslashes($_POST['current_terms']), true) : array();
    
    // Валидация данных
    if (!$current_user_id || !is_array($current_terms) || empty($current_terms)) {
        wp_send_json_error('Неверные параметры');
        return;
    }
    
    // Находим всех похожих пользователей
    $similar_users = [];
    $all_users = get_users([
        'exclude' => [$current_user_id],
        'role__not_in' => ['Administrator'],
        'number' => -1,
    ]);
    
    foreach ($all_users as $u) {
        $other_terms = get_field('osnovnoe_napravlenie', 'user_' . $u->ID);
        if ($other_terms && is_array($other_terms)) {
            $common = array_intersect($current_terms, $other_terms);
            if (!empty($common)) {
                $similar_users[] = $u;
            }
        }
    }
    
    // Перемешиваем для случайного порядка
    shuffle($similar_users);
    
    // Берем нужную порцию
    $users_to_show = array_slice($similar_users, $offset, 4);
    
    $html = '';
    if (!empty($users_to_show)) {
        foreach ($users_to_show as $user) {
            ob_start();
            set_query_var('executor', $user);
            get_template_part('template/executor');
            $html .= ob_get_clean();
        }
    }
    
    $total_similar = count($similar_users);
    $has_more = ($offset + 4 < $total_similar);
    
    wp_send_json_success(array(
        'html' => $html,
        'has_more' => $has_more,
        'total' => $total_similar,
        'loaded' => count($users_to_show),
        'next_offset' => $offset + 4
    ));
}


//*************** Подключение основного JS файла с атрибутом defer
function my_scripts() {
    wp_register_script( 'executor',
        get_stylesheet_directory_uri() . '/js/js.js',
        [], '1.3',  true );
    wp_enqueue_script('executor');
    wp_localize_script('executor', 'load_more_authors_obj', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
}
add_action('wp_enqueue_scripts', 'my_scripts');

function add_defer_attribute($tag, $handle) {
    if ($handle === 'executor') { return str_replace(' src', ' defer src', $tag); }
    return $tag; 
}
add_filter('script_loader_tag', 'add_defer_attribute', 10, 2);


//*************** Переопределение шаблона для страницы профиля автора и CPT performer
add_filter('template_include', function($template){
  if (is_author() || is_singular('performer')) {
    $custom = locate_template('single-listing.php');
    if ($custom) { return $custom; }
  }
  return $template;
});


//*************** Шорткод: блок "Как мы работаем" (данные из ACF Options)
function kak_my_rabotaem_shortcode() {
    $group = get_field('kak_my_rabotaem', 'option');
    $data = [
        'pervyj' => [ 'zagolovok' => $group['pervyj_zagolovok'], 'tekst' => $group['pervyj_tekst_pod_zagolovkom'] ],
        'vtoroj' => [ 'zagolovok' => $group['vtoroj_zagolovok'], 'tekst' => $group['vtoroj_tekst_pod_zagolovkom'] ],
        'tretij' => [ 'zagolovok' => $group['tretij_zagolovok'], 'tekst' => $group['tretij_tekst_pod_zagolovkom'] ]
    ];
    ob_start(); ?>
    <div class="section--how-work">
        <h2 class="section--how-work__title">Как мы работаем</h2>
        <div class="section--how-work__grid">
            <?php $i = 1; foreach ($data as $key => $item): ?>
                <div class="section--how-work__card section--how-work__card_<?php echo $key; ?>">
                    <span class="section--how-work__number"><?php echo $i++; ?></span>
                    <h3 class="section--how-work__card-title"><?php echo $item['zagolovok']; ?></h3>
                    <div class="section--how-work__card-text"><?php echo $item['tekst']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="section--how-work__btn-check" onclick="openPopup('check')">Как выбрать хорошего исполнителя?</button>
    </div>
    <?php return ob_get_clean(); 
}
add_shortcode('kak_my_rabotaem', 'kak_my_rabotaem_shortcode');


//*************** Отключение автоматических обновлений и редактора Gutenberg
function remove_plugin_updates($value) { return null; }
add_filter('site_transient_update_plugins', 'remove_plugin_updates');
function remove_theme_updates($value) { return null; }
add_filter('site_transient_update_themes', 'remove_theme_updates');
add_filter( 'auto_update_plugin', '__return_false' );
add_filter( 'use_block_editor_for_post_type', '__return_false' );


//*************** Отключение лишнего шрифта Inter от WooCommerce
add_filter( 'wp_theme_json_data_theme', 'disable_inter_font', 100 );
function disable_inter_font( $theme_json ) {
    $theme_data = $theme_json->get_data();
    $font_data  = $theme_data['settings']['typography']['fontFamilies']['theme'] ?? array();
    $font_name = 'Inter';
    foreach ( $font_data as $font_key => $font ) {
        if ( isset( $font['name'] ) && $font['name'] === $font_name ) {
            unset($font_data[$font_key]); 
            $theme_json->update_with( array(
                'version'  => 1,
                'settings' => array(
                    'typography' => array(
                        'fontFamilies' => array(
                            'theme' => $font_data,
                        ),
                    ),
                ),
            ) );
            break;
        }
    }
    return $theme_json;
}


//*************** Кастомизация списка пользователей в админке: колонки "Город" и "Направление"
add_filter( 'manage_users_columns', 'add_gorod_column' );
function add_gorod_column( $columns ) {
    $columns['gorod'] = 'Город';
    $columns['osnovnoe_napravlenie'] = 'Основное направление';
    return $columns;
}

add_filter( 'manage_users_custom_column', 'show_custom_column_data', 10, 3 );
function show_custom_column_data( $value, $column_name, $user_id ) {
    if ( $column_name === 'gorod' ) {
        $term = get_field( 'gorod', 'user_' . $user_id );
        if ( $term && is_object($term) ) {
            return esc_html( $term->name );
        } elseif ( is_array($term) ) {
            return implode( ', ', wp_list_pluck( $term, 'name' ) );
        } else {
            return '—';
        }
    }
    if ( $column_name === 'osnovnoe_napravlenie' ) {
        $term_ids = get_field( 'osnovnoe_napravlenie', 'user_' . $user_id );
        if ( $term_ids ) {
            if ( is_array($term_ids) ) {
                $names = array();
                foreach ( $term_ids as $term_id ) {
                    $term = get_term( $term_id );
                    if ( $term && !is_wp_error( $term ) ) {
                        if ( $term->parent != 0 ) {
                            $parent_term = get_term( $term->parent );
                            if ( $parent_term && !is_wp_error( $parent_term ) ) {
                                $names[] = esc_html( $parent_term->name );
                            }
                        } else {
                            $names[] = esc_html( $term->name );
                        }
                    }
                }
                return !empty($names) ? implode( ', ', array_unique($names) ) : '—';
            } else {
                $term = get_term( $term_ids );
                if ( $term && !is_wp_error( $term ) ) {
                    if ( $term->parent != 0 ) {
                        $parent_term = get_term( $term->parent );
                        return ( $parent_term && !is_wp_error( $parent_term ) ) ? esc_html( $parent_term->name ) : '—';
                    }
                    return esc_html( $term->name );
                }
            }
        }
        return '—';
    }
    return $value;
}


//*************** Переопределение шаблона для магазина и категорий товаров
add_filter( 'template_include', 'joyvia_custom_templates', 20 );
function joyvia_custom_templates( $template ) {
    $new_template = get_stylesheet_directory() . '/template-catalog.php';
    if ( ( is_shop() || is_tax( 'product_cat' ) ) && file_exists( $new_template ) ) { return $new_template; }
    return $template;
}

//*************** Оптимизация: отключение Emoji, oEmbed и XML-RPC
add_filter('show_admin_bar', '__return_false');
function disable_wp_emojicons() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'disable_wp_emojicons');

function disable_embeds_code() {
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
    remove_filter('the_content', ['WP_oEmbed', 'autoembed'], 8);
}
add_action('init', 'disable_embeds_code');

add_filter('xmlrpc_enabled', '__return_false');


//*************** Подключение стилей родительской темы
if ( !defined( 'ABSPATH' ) ) exit;
// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:
if ( !function_exists( 'chld_thm_cfg_parent_css' ) ):
    function chld_thm_cfg_parent_css() {
        wp_enqueue_style( 'chld_thm_cfg_parent', trailingslashit( get_template_directory_uri() ) . 'style.css', array(  ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'chld_thm_cfg_parent_css', 10 );



/* =====================================================================
 * ===== ЯДРО УПРАВЛЕНИЯ ПРОФИЛЯМИ (CPT: performer) ====================
 * =====================================================================
 *
 * АРХИТЕКТУРА (ОБНОВЛЕННАЯ):
 *  - На одного пользователя — РОВНО ОДИН пост типа `performer`.
 *  - Источник истины статуса — ACF-поле `profile_status`:
 *      draft        → черновик (не опубликован)
 *      pending      → на модерации
 *      published    → опубликован, виден в каталоге
 *      rejected     → отклонен модератором
 *      deactivated  → скрыт пользователем
 *  - WP-статус (`post_status`) автоматически синхронизируется с ACF:
 *      published     → publish     (виден в каталоге)
 *      все остальные → draft       (НЕ виден в каталоге)
 *  - Никаких теневых копий, никаких дублей.
 *
 * ВАЖНО: Когда юзер редактирует УЖЕ ОПУБЛИКОВАННЫЙ профиль и отправляет
 * на повторную модерацию (шаг 5), профиль временно скрывается из каталога
 * до одобрения модератором. Юзер может «отозвать» правки кнопкой.
 * Это намеренный компромисс ради отсутствия дублей в БД.
 * ===================================================================== */

class Joyvia_Profile_Manager {

    /**
     * Получить (или создать) единственный профиль пользователя.
     * Используется при регистрации и в шаблонах профиля.
     */
    public static function ensure_profile($user_id) {
        $main = self::get_main_profile($user_id);
        if ($main) return $main->ID;

        // Отключаем хук синхронизации на время программного создания
        remove_action('save_post_performer', 'joyvia_master_sync_performer', 99);

        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name  = get_user_meta($user_id, 'last_name', true);

        $post_id = wp_insert_post([
            'post_type'   => 'performer',
            'post_title'  => trim($first_name . ' ' . $last_name) ?: 'Профиль ' . $user_id,
            'post_status' => 'draft', // соответствует profile_status='draft'
            'post_author' => $user_id,
        ]);

        if (!is_wp_error($post_id) && $post_id) {
            update_field('profile_status', 'draft', $post_id);
        }

        add_action('save_post_performer', 'joyvia_master_sync_performer', 99, 2);

        return $post_id;
    }

    /**
     * Удобный алиас, оставлен для обратной совместимости с шаблонами.
     * Возвращает ID единственного поста-профиля юзера.
     */
    public static function get_editable_post_id($user_id) {
        return self::ensure_profile($user_id);
    }

    /**
     * Найти основной (и единственный) пост-профиль юзера.
     * Учитываем все статусы, потому что при редактировании пост может быть
     * в любом из них.
     */
    public static function get_main_profile($user_id) {
        $posts = get_posts([
            'post_type'   => 'performer',
            'author'      => $user_id,
            'post_parent' => 0,
            'post_status' => ['publish', 'pending', 'draft', 'private', 'future'],
            'numberposts' => 1,
            'orderby'     => 'ID',
            'order'       => 'ASC' // самый старый — он же единственный
        ]);
        return !empty($posts) ? $posts[0] : false;
    }
}


/**
 * Маппинг ACF-статуса в WP-статус.
 */
function joyvia_acf_to_wp_status($acf_status) {
    return $acf_status === 'published' ? 'publish' : 'draft';
}


/**
 * ЕДИНЫЙ МАСТЕР-ОБРАБОТЧИК СИНХРОНИЗАЦИИ
 * Срабатывает на сохранение поста performer (в т.ч. админом из wp-admin).
 *
 * Логика:
 *  1. Берём актуальное значение `profile_status` (из POST или БД).
 *  2. Приводим WP `post_status` к нему.
 *  3. Если статус стал `published` — обновляем дату последней модерации.
 */
add_action('save_post_performer', 'joyvia_master_sync_performer', 99, 2);
function joyvia_master_sync_performer($post_id, $post) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;

    // Не вмешиваемся в служебные статусы. Иначе при отправке в корзину
    // (wp_trash_post меняет статус на 'trash' через wp_update_post) хук
    // вернул бы пост обратно — и исполнителя нельзя было бы удалить.
    if (in_array($post->post_status, ['trash', 'auto-draft', 'inherit'], true)) return;

    // Защита от рекурсии
    remove_action('save_post_performer', 'joyvia_master_sync_performer', 99);

    // Получаем ACF-статус: приоритет у POST (когда админ сохраняет форму),
    // иначе берём то, что уже в БД.
    $acf_status = isset($_POST['acf']['field_joyvia_profile_status'])
        ? sanitize_text_field($_POST['acf']['field_joyvia_profile_status'])
        : get_field('profile_status', $post_id);

    // Если поле пустое — выставляем draft и выходим
    if (empty($acf_status)) {
        update_field('profile_status', 'draft', $post_id);
        $acf_status = 'draft';
    }

    $target_wp_status = joyvia_acf_to_wp_status($acf_status);

    if ($post->post_status !== $target_wp_status) {
        wp_update_post([
            'ID'          => $post_id,
            'post_status' => $target_wp_status,
        ]);
    }

    // При публикации — обновляем дату модерации (если её ещё нет за сегодня)
    if ($acf_status === 'published') {
        update_field('last_approved_date', date_i18n('Ymd'), $post_id);
        // На всякий случай чистим причину отказа
        delete_field('rejection_reason', $post_id);
    }

    add_action('save_post_performer', 'joyvia_master_sync_performer', 99, 2);
}


/**
 * Когда админ нажимает «Опубликовать» в редакторе wp-admin
 * (т.е. меняет post_status на publish напрямую), синхронно
 * проставляем ACF-статус `published`. Это предохранитель на случай,
 * если админ забыл переключить ACF-поле.
 */
/**
 * При любом редактировании данных профиля юзером — отправляем на модерацию.
 * Вызывается из всех joyvia_save_stepN_cb после сохранения данных.
 *
 * Логика:
 *  - published / rejected / deactivated → pending (требуется новая модерация)
 *  - draft  → остаётся draft  (юзер ещё заполняет, до шага 5 не доходил)
 *  - pending → остаётся pending
 */
function joyvia_mark_for_remoderation($post_id) {
    $current = get_field('profile_status', $post_id) ?: 'draft';

    if ($current === 'draft' || $current === 'pending') return;

    update_field('profile_status', 'pending', $post_id);
    delete_field('rejection_reason', $post_id);

    remove_action('save_post_performer', 'joyvia_master_sync_performer', 99);
    wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
    add_action('save_post_performer', 'joyvia_master_sync_performer', 99, 2);
}

add_action('transition_post_status', 'joyvia_on_status_transition', 10, 3);
function joyvia_on_status_transition($new_status, $old_status, $post) {
    if ($post->post_type !== 'performer') return;
    if ($new_status === $old_status) return;

    $acf_status = get_field('profile_status', $post->ID);

    if ($new_status === 'publish' && $acf_status !== 'published') {
        update_field('profile_status', 'published', $post->ID);
        update_field('last_approved_date', date_i18n('Ymd'), $post->ID);
        delete_field('rejection_reason', $post->ID);
    }
}


//*************** AJAX: Загрузка аватарки -------------------------
add_action('wp_ajax_joyvia_upload_avatar', 'joyvia_upload_avatar_cb');
function joyvia_upload_avatar_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('Ошибка авторизации');
    if (empty($_FILES['avatar'])) wp_send_json_error('Файл не получен');
    
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    
    $att_id = media_handle_upload('avatar', 0);
    if (is_wp_error($att_id)) wp_send_json_error($att_id->get_error_message());
    
    $post_id = Joyvia_Profile_Manager::ensure_profile($uid);
    update_field('avatarka', $att_id, $post_id);
    
    wp_send_json_success(['url' => wp_get_attachment_image_url($att_id, 'medium'), 'id' => $att_id]);
}
 
//*************** AJAX: Сохранение шага 1 (о себе) ----------------
add_action('wp_ajax_joyvia_save_step1', 'joyvia_save_step1_cb');
function joyvia_save_step1_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');

    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
    $email      = sanitize_email($_POST['email'] ?? '');
    $city_id    = intval($_POST['city_id'] ?? 0);
    $regions    = array_map('intval', (array)($_POST['regions'] ?? []));
    $opyt       = sanitize_text_field($_POST['opyt'] ?? '');
    $o_sebe     = sanitize_textarea_field($_POST['o_sebe'] ?? '');

    $post_id = Joyvia_Profile_Manager::ensure_profile($uid);

    // Имя/фамилия в user_meta
    update_user_meta($uid, 'first_name', $first_name);
    update_user_meta($uid, 'last_name',  $last_name);

    // Email — если поменялся
    if ($email && $email !== wp_get_current_user()->user_email && is_email($email)) {
        wp_update_user(['ID' => $uid, 'user_email' => $email]);
        joyvia_send_verification_email($uid, $email);
    }

    // Заголовок поста = ФИО
    wp_update_post([
        'ID'         => $post_id,
        'post_title' => trim($first_name . ' ' . $last_name) ?: 'Профиль ' . $uid,
    ]);

    // Город + районы (нативная таксономия + ACF)
    $city_terms = array_filter(array_merge([$city_id], $regions));
    wp_set_object_terms($post_id, $city_terms, 'city', false);
    update_field('gorod', $city_terms, $post_id);

    update_field('opyt_raboty', $opyt, $post_id);
    update_field('o_sebe',      $o_sebe, $post_id);

    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $telegram = sanitize_text_field($_POST['telegram'] ?? '');

    // Контакты в ACF
    update_field('kontaktnye_dannye', [
        'kontakt' => array_filter([
            $phone    ? ['chto_eto' => 'Телефон',  'ssylka_na_kontakt' => $phone]    : null,
            $email    ? ['chto_eto' => 'Email',     'ssylka_na_kontakt' => $email]    : null,
            $telegram ? ['chto_eto' => 'Telegram',  'ssylka_na_kontakt' => $telegram] : null,
        ])
    ], $post_id);

    // Удаление / Загрузка нового аватара прямо в шаге 1
    if (isset($_POST['delete_avatar']) && $_POST['delete_avatar'] === '1') {
        delete_field('avatarka', $post_id);
    }
    if (!empty($_FILES['avatar']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attachment_id = media_handle_upload('avatar', 0);
        if (!is_wp_error($attachment_id)) {
            update_field('avatarka', $attachment_id, $post_id);
        }
    }

    // Сохраняем прогресс шагов за юзером (не за постом)
    $cur = intval(get_user_meta($uid, 'profile_setup_current_step', true));
    if ($cur < 1) update_user_meta($uid, 'profile_setup_current_step', 1);

    joyvia_mark_for_remoderation($post_id);

    wp_send_json_success();
}
 
//*************** AJAX: Сохранение шага 2 (профессия и события) --
add_action('wp_ajax_joyvia_save_step2', 'joyvia_save_step2_cb');
function joyvia_save_step2_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');
    
    $post_id = Joyvia_Profile_Manager::ensure_profile($uid);
    $prof_id = intval($_POST['profession_id'] ?? 0);
    $events  = array_map('intval', (array)($_POST['events'] ?? []));

    // Профессия
    if ($prof_id) {
        wp_set_object_terms($post_id, [$prof_id], 'profession', false);
        update_field('selected_profession_id_acf', $prof_id, $post_id);
    }

    // События
    wp_set_object_terms($post_id, $events, 'event', false);
    update_field('vybrannye_meropriyatiya', $events, $post_id);

    $event_extra = json_decode(wp_unslash($_POST['event_extra'] ?? '{}'), true);
    if (!is_array($event_extra)) $event_extra = [];
    update_post_meta($post_id, 'selected_event_extra', $event_extra);

    $cur = intval(get_user_meta($uid, 'profile_setup_current_step', true));
    if ($cur < 2) {
        update_user_meta($uid, 'profile_setup_current_step', 2);
        update_field('profile_setup_current_step_acf', 2, $post_id);
    }

    joyvia_mark_for_remoderation($post_id);

    wp_send_json_success();
}

//*************** AJAX: Сохранение шага 3 (специализации/навыки) -
add_action('wp_ajax_joyvia_save_step3', 'joyvia_save_step3_cb');
function joyvia_save_step3_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');

    $post_id = Joyvia_Profile_Manager::ensure_profile($uid);
    $specs   = array_map('intval', (array)($_POST['specs']  ?? []));
    $skills  = array_map('intval', (array)($_POST['skills'] ?? []));

    // Нативная привязка WP
    wp_set_object_terms($post_id, $specs, 'specialization', false);
    wp_set_object_terms($post_id, $skills, 'skill', false);

    // Дублируем в ACF
    update_field('vybrannye_speczializaczii', $specs,  $post_id);
    update_field('vybrannye_navyki',          $skills, $post_id);

    $cur = intval(get_user_meta($uid, 'profile_setup_current_step', true));
    if ($cur < 3) update_user_meta($uid, 'profile_setup_current_step', 3);

    joyvia_mark_for_remoderation($post_id);
 
    wp_send_json_success();
}

//*************** AJAX: Сохранение шага 4 (пакеты услуг) ---------
add_action('wp_ajax_joyvia_save_step4', 'joyvia_save_step4_cb');
function joyvia_save_step4_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');
 
    $post_id = Joyvia_Profile_Manager::ensure_profile($uid);
    $pkgs = json_decode(wp_unslash($_POST['packages'] ?? '[]'), true);
    if (!is_array($pkgs)) $pkgs = [];
 
    $clean = array_map(fn($p) => [
        'type'      => sanitize_text_field($p['type']      ?? ''),
        'parent_id' => sanitize_text_field($p['parent_id'] ?? ''),
        'index'     => intval($p['index'] ?? 0),
        'name'      => sanitize_text_field($p['name']      ?? ''),
        'price'     => intval($p['price'] ?? 0),
        'desc'      => sanitize_textarea_field($p['desc']  ?? ''),
    ], $pkgs);
 
    update_post_meta($post_id, 'service_packages_data', $clean);
 
    // Формируем массив для ACF repeater service_packages
    if (function_exists('update_field')) {
        update_field('service_packages', array_map(fn($p) => [
            'p_type'      => $p['type'],
            'p_parent_id' => $p['parent_id'],
            'p_name'      => $p['name'],
            'p_price'     => $p['price'],
            'p_desc'      => $p['desc'],
        ], $clean), $post_id);
    }
 
    $cur = intval(get_user_meta($uid, 'profile_setup_current_step', true));
    if ($cur < 4) update_user_meta($uid, 'profile_setup_current_step', 4);

    joyvia_mark_for_remoderation($post_id);
 
    wp_send_json_success();
}

//*************** AJAX: Сохранение шага 5 и публикация ----------
add_action('wp_ajax_joyvia_save_step5', 'joyvia_save_step5_cb');
function joyvia_save_step5_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');
    
    $post_id = Joyvia_Profile_Manager::ensure_profile($uid);
    
    // Видео ссылки
    $vl = json_decode(wp_unslash($_POST['video_links'] ?? '[]'), true);
    if (!is_array($vl)) $vl = [];
    $clean_links = array_values(array_filter(array_map('esc_url_raw', $vl)));
    
    $acf_video_links = [];
    foreach ($clean_links as $link) { $acf_video_links[] = ['video_url' => $link]; }
    update_field('portfolio_video_links_acf', $acf_video_links, $post_id);
    
    // Соцсети
    $soc = json_decode(wp_unslash($_POST['social'] ?? '{}'), true);
    if (!is_array($soc)) $soc = [];
    $social_data = [
        'instagram' => esc_url_raw($soc['instagram'] ?? ''),
        'vk'        => esc_url_raw($soc['vk']        ?? ''),
        'website'   => esc_url_raw($soc['website']   ?? ''),
        'other'     => esc_url_raw($soc['other']      ?? ''),
    ];
    update_field('portfolio_social_acf', $social_data, $post_id);
    
    // Отправляем профиль на модерацию: ACF=pending, post_status=draft
    update_field('profile_status', 'pending', $post_id);
    // Чистим причину отказа на случай повторной отправки после rejected
    delete_field('rejection_reason', $post_id);
    // post_status синхронизируется хуком, но подстрахуемся
    if (get_post_status($post_id) !== 'draft') {
        remove_action('save_post_performer', 'joyvia_master_sync_performer', 99);
        wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
        add_action('save_post_performer', 'joyvia_master_sync_performer', 99, 2);
    }

    wp_send_json_success(['redirect' => home_url('/profile-settings/')]);
}
 
//*************** AJAX: Загрузка / Удаление фото и видео (Портфолио) -----
function joyvia_handle_media_upload($type_key, $field_name, $is_video = false) {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');
    if (empty($_FILES[$type_key])) wp_send_json_error('Файл не получен');
    
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    
    $att_id = media_handle_upload($type_key, 0);
    if (is_wp_error($att_id)) wp_send_json_error($att_id->get_error_message());
 
    $post_id = Joyvia_Profile_Manager::ensure_profile($uid);
    $portfolio = get_field($field_name, $post_id) ?: [];
    
    // Нормализация массива ACF
    if (is_array($portfolio) && !empty($portfolio) && isset($portfolio[0]) && is_array($portfolio[0])) {
        $ids = array_column($portfolio, 'ID');
    } else {
        $ids = array_map('intval', (array)$portfolio);
    }
    
    if (!in_array($att_id, $ids)) {
        $ids[] = $att_id;
        update_field($field_name, $ids, $post_id);
    }
    
    $url = $is_video ? wp_get_attachment_url($att_id) : wp_get_attachment_image_url($att_id, 'medium');
    wp_send_json_success(['id' => $att_id, 'url' => $url]);
}

function joyvia_handle_media_delete($field_name) {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');
    $del_id   = intval($_POST['attachment_id'] ?? 0);
    $post_id  = Joyvia_Profile_Manager::ensure_profile($uid);
    $portfolio = get_field($field_name, $post_id) ?: [];
    
    if (is_array($portfolio) && !empty($portfolio) && isset($portfolio[0]) && is_array($portfolio[0])) {
        $ids = array_filter(array_column($portfolio, 'ID'), fn($id) => $id !== $del_id);
    } else {
        $ids = array_filter(array_map('intval', (array)$portfolio), fn($id) => $id !== $del_id);
    }
    update_field($field_name, array_values($ids), $post_id);
    wp_send_json_success();
}

add_action('wp_ajax_joyvia_upload_portfolio_photo', function() { joyvia_handle_media_upload('photo', 'portfolio', false); });
add_action('wp_ajax_joyvia_delete_portfolio_photo', function() { joyvia_handle_media_delete('portfolio'); });
add_action('wp_ajax_joyvia_upload_portfolio_video', function() { joyvia_handle_media_upload('video', 'portfolio_videos', true); });
add_action('wp_ajax_joyvia_delete_portfolio_video', function() { joyvia_handle_media_delete('portfolio_videos'); });
 
//*************** AJAX: Загрузка специализаций/навыков по профессии
//                (расширенная версия с сохранённым состоянием) ---
add_action('wp_ajax_get_prof_data_setup', 'joyvia_get_prof_data_setup_cb');
add_action('wp_ajax_nopriv_get_prof_data_setup', 'joyvia_get_prof_data_setup_cb');
function joyvia_get_prof_data_setup_cb() {
    $prof_id     = intval($_POST['prof_id'] ?? 0);
    $saved_specs = array_map('intval', (array)($_POST['saved_specs'] ?? []));
    $saved_skills = array_map('intval', (array)($_POST['saved_skills'] ?? []));
 
    $specs  = get_terms(['taxonomy' => 'specialization', 'hide_empty' => false, 'meta_query' => [['key' => 'profession_link', 'value' => $prof_id, 'compare' => '=']]]);
    $skills = get_terms(['taxonomy' => 'skill',          'hide_empty' => false, 'meta_query' => [['key' => 'profession_link', 'value' => $prof_id, 'compare' => '=']]]);
 
    ob_start();
    if (!empty($specs) && !is_wp_error($specs)) {
        foreach ($specs as $term) {
            $chk = in_array($term->term_id, $saved_specs) ? ' checked' : '';
            echo '<label class="spec-skill-label"><input type="checkbox" name="specializations[]" value="' . esc_attr($term->term_id) . '"' . $chk . '><div class="spec-skill-card">' . esc_html($term->name) . '</div></label>';
        }
    } else {
        echo '<p class="f14 c646 mt10">Нет специализаций для этой профессии</p>';
    }
    $specs_html = ob_get_clean();
 
    ob_start();
    if (!empty($skills) && !is_wp_error($skills)) {
        foreach ($skills as $term) {
            $chk = in_array($term->term_id, $saved_skills) ? ' checked' : '';
            echo '<label class="spec-skill-label"><input type="checkbox" name="skills[]" value="' . esc_attr($term->term_id) . '"' . $chk . '><div class="spec-skill-card">' . esc_html($term->name) . '</div></label>';
        }
    } else {
        echo '<p class="f14 c646 mt10">Нет навыков для этой профессии</p>';
    }
    $skills_html = ob_get_clean();
 
    wp_send_json_success(['specs' => $specs_html, 'skills' => $skills_html]);
}


//*************** AJAX: Изменение статуса (Отозвать модерацию / Скрыть / Опубликовать) -----
/**
 * Допустимые переходы (инициатор — пользователь)
 */
add_action('wp_ajax_joyvia_change_profile_status', 'joyvia_change_profile_status_cb');
function joyvia_change_profile_status_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_status_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');
    
    $new_status = sanitize_text_field($_POST['new_status']);
    $allowed    = ['draft', 'deactivated', 'published'];
    if (!in_array($new_status, $allowed, true)) wp_send_json_error('Недопустимый статус');

    $main_post = Joyvia_Profile_Manager::get_main_profile($uid);
    if (!$main_post) wp_send_json_error('Профиль не найден');

    $current = get_field('profile_status', $main_post->ID);

    // Валидация переходов
    $valid_transitions = [
        'pending'   => ['draft'],
        'published' => ['deactivated'],
    ];
    if (!isset($valid_transitions[$current]) || !in_array($new_status, $valid_transitions[$current], true)) {
        wp_send_json_error('Этот переход статуса запрещён');
    }

    update_field('profile_status', $new_status, $main_post->ID);

    $target_wp_status = joyvia_acf_to_wp_status($new_status);

    remove_action('save_post_performer', 'joyvia_master_sync_performer', 99);
    wp_update_post(['ID' => $main_post->ID, 'post_status' => $target_wp_status]);
    add_action('save_post_performer', 'joyvia_master_sync_performer', 99, 2);

    wp_send_json_success();
}


//*************** AJAX: Удаление аккаунта -----------------------
add_action('wp_ajax_joyvia_delete_account', 'joyvia_delete_account_cb');
function joyvia_delete_account_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce')) {  wp_send_json_error('Ошибка безопасности');  }
    $uid = get_current_user_id();
    if (!$uid) { wp_send_json_error('Ошибка авторизации'); }
    require_once ABSPATH . 'wp-admin/includes/user.php';

    $main_post = Joyvia_Profile_Manager::get_main_profile($uid);
    if ($main_post) {
        wp_delete_post($main_post->ID, true);
    }

    $user_attachments = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'author'         => $uid,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    foreach ($user_attachments as $att_id) {
        wp_delete_attachment($att_id, true);
    }

    if (wp_delete_user($uid)) {
        wp_logout();
        wp_send_json_success();
    } else {
        wp_send_json_error('Ошибка при удалении аккаунта из базы данных');
    }
}


//*************** Удаление профиля-исполнителя при удалении пользователя -----------------------
// Тип CPT performer регистрируется так, что WP не удаляет его автоматически
// вместе с пользователем. Поэтому при удалении юзера (в т.ч. из wp-admin/users.php)
// явно подчищаем его профиль(и) и загруженные им вложения.
add_action('delete_user', 'joyvia_cleanup_performer_on_user_delete', 10, 1);
function joyvia_cleanup_performer_on_user_delete($user_id) {
    $user_id = (int) $user_id;
    if (!$user_id) return;

    $performers = get_posts([
        'post_type'      => 'performer',
        'author'         => $user_id,
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    // Снимаем мастер-хук, чтобы он не вмешивался в удаление
    remove_action('save_post_performer', 'joyvia_master_sync_performer', 99);
    foreach ($performers as $pid) {
        wp_delete_post($pid, true);
    }
    add_action('save_post_performer', 'joyvia_master_sync_performer', 99, 2);

    $attachments = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    foreach ($attachments as $att_id) {
        wp_delete_attachment($att_id, true);
    }
}

//*************** Регистрация CPT для страниц каталога
add_action( 'init', 'joyvia_register_catalog_page_cpt' );
function joyvia_register_catalog_page_cpt() {
    register_post_type( 'catalog_page', [
        'labels' => [
            'name'               => 'Страницы каталога',
            'singular_name'      => 'Страница каталога',
            'add_new'            => 'Создать страницу',
            'add_new_item'       => 'Создать страницу каталога',
            'edit_item'          => 'Редактировать страницу',
            'new_item'           => 'Новая страница',
            'view_item'          => 'Посмотреть страницу',
            'search_items'       => 'Найти страницу',
            'not_found'          => 'Страницы не найдены',
            'menu_name'          => 'Каталоги',
        ],
        // ВАЖНО: Делаем публичным для ядра (чтобы не было 404), но без системных ссылок
        'public'              => true, 
        'publicly_queryable'  => true,
        'exclude_from_search' => true,
        'show_in_nav_menus'   => false,
        'rewrite'             => false, // Запрещаем WP генерировать /catalog_page/slug
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 22,
        'menu_icon'           => 'dashicons-networking',
        'supports'            => ['title'],
        'has_archive'         => false,
    ] );
}

/**
 * ГЕНЕРАЦИЯ URL ПРИ СОХРАНЕНИИ СТРАНИЦЫ КАТАЛОГА
 * Хук ACF срабатывает после сохранения полей поста (priority 20)
 */
add_action('acf/save_post', 'joyvia_generate_catalog_url', 20);
function joyvia_generate_catalog_url($post_id) {
    if (get_post_type($post_id) !== 'catalog_page') return;

    $type = get_field('page_type', $post_id);
    $url = '';

    $prof_id     = get_field('catalog_profession', $post_id);
    $event_id    = get_field('catalog_event', $post_id);
    $subevent_id = get_field('catalog_subevent', $post_id);
    $spec_id     = get_field('catalog_spec', $post_id);

    $prof_slug     = $prof_id ? get_term($prof_id)->slug : '';
    $event_slug    = $event_id ? get_term($event_id)->slug : '';
    $subevent_slug = $subevent_id ? get_term($subevent_id)->slug : '';
    $spec_slug     = $spec_id ? get_term($spec_id)->slug : '';

    switch ($type) {
        case 'hub_event':
            $url = $event_slug;
            break;
        case 'hub_subevent':
            $url = $event_slug . '/' . $subevent_slug;
            break;
        case 'event_prof':
            $url = $event_slug . '/' . $prof_slug;
            break;
        case 'subevent_prof':
            $url = $event_slug . '/' . $subevent_slug . '/' . $prof_slug;
            break;
        case 'spec_prof':
            $url = $prof_slug . '/' . $spec_slug;
            break;
        case 'prof_only':
            $url = $prof_slug;
            break;
    }

    $url = trim($url, '/');
    if (empty($url)) return;

    $old_url = get_post_meta($post_id, 'generated_url', true);
    update_post_meta($post_id, 'generated_url', $url);
	error_log('[joyvia-url-save] post_id=' . $post_id . ' url=' . $url . ' verify=' . get_post_meta($post_id, 'generated_url', true));

    if (!empty($old_url) && $old_url !== $url) {
        update_post_meta($post_id, 'generated_url', $url);
        
        if (!empty($old_url)) {
            $redirects = get_post_meta($post_id, '_old_urls', true);
            if (!is_array($redirects)) $redirects = [];
            if (!in_array($old_url, $redirects)) {
                $redirects[] = $old_url;
                update_post_meta($post_id, '_old_urls', $redirects);
            }
        }
    }
}
//*************** Кнопка "Открыть страницу" рядом с полем generated_url в админке
add_action('acf/render_field/key=field_cat_generated_url', function($field) {
    $url = $field['value'];
    if (empty($url)) return;
    $full_url = home_url('/' . trim($url, '/') . '/');
    echo '<a href="' . esc_url($full_url) . '" target="_blank" style="display:inline-block;margin-top:8px;padding:6px 14px;background:#2271b1;color:#fff;border-radius:4px;text-decoration:none;font-size:13px;">
        Открыть страницу →
    </a>';
});

//************ Кастомизация вывода страниц в каталоге
add_filter('display_post_states', 'joyvia_catalog_page_url_state', 10, 2);
function joyvia_catalog_page_url_state($states, $post) {
    if ($post->post_type === 'catalog_page') {
        $url = get_post_meta($post->ID, 'generated_url', true);
        if (!empty($url)) {
            $full_url = home_url('/' . trim($url, '/') . '/');
            $states['joyvia_url'] = sprintf(
                '</span><a href="%1$s" target="_blank" style="display:block; width:max-content; margin-top:6px; padding:2px 8px; border:1px solid #2c8a3e; border-radius:4px; color:#2c8a3e; font-size:12px; font-weight:normal; text-decoration:none;">%1$s</a><span style="display:none;">',
                esc_url($full_url)
            );
        }
    }
    return $states;
}

add_filter('manage_edit-catalog_page_columns', 'joyvia_admin_catalog_page_columns');
function joyvia_admin_catalog_page_columns($columns) {
    $new = [];
    foreach ($columns as $k => $v) {
        if ($k === 'date') continue;
        $new[$k] = $v;
        if ($k === 'title') {
            $new['joyvia_type']     = 'Тип страницы';
            $new['joyvia_indexing'] = 'Индексация';
        }
    }
    $new['date'] = 'Дата';
    return $new;
}

add_action('manage_catalog_page_posts_custom_column', 'joyvia_admin_catalog_page_column_content', 10, 2);
function joyvia_admin_catalog_page_column_content($column, $post_id) {
    if ($column === 'joyvia_type') {
        $type = get_post_meta($post_id, 'page_type', true);
        $type_labels = [
            'prof_only'     => 'Профессия',
            'event_prof'    => 'Событие + Профессия',
            'subevent_prof' => 'Подсобытие + Профессия',
            'spec_prof'     => 'Спец-ть + Профессия',
            'hub_event'     => 'Хаб (Событие)',
            'hub_subevent'  => 'Хаб (Подсобытие)'
        ];
        
        $label = isset($type_labels[$type]) ? $type_labels[$type] : ($type ?: '—');
        echo '<span style="color:#555; font-size:13px;">' . esc_html($label) . '</span>';
    }

    if ($column === 'joyvia_indexing') {
        $allow = (int) get_post_meta($post_id, 'allow_indexing', true);
        if ($allow === 1) {
            echo '<span style="color:#2c8a3e; font-weight:600;">Да</span>';
        } else {
            echo '<span style="color:#a02020; font-weight:600;">Нет</span>';
        }
    }
}
add_action('edit_form_top', 'joyvia_dynamic_catalog_hint');
function joyvia_dynamic_catalog_hint($post) {
    if ($post->post_type !== 'catalog_page') return;

    $type = get_field('page_type', $post->ID);
    $event_id = get_field('catalog_event', $post->ID);
    $subevent_id = get_field('catalog_subevent', $post->ID);

    $message = '';
    $color = '#d63638'; 

    if ($type === 'hub_event' && $event_id) {
        $exists = get_posts([
            'post_type' => 'catalog_page',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'page_type', 'value' => 'event_prof'],
                ['key' => 'catalog_event', 'value' => $event_id]
            ],
            'fields' => 'ids',
            'posts_per_page' => 1
        ]);
        if (empty($exists)) {
            $message = 'Внимание: на этой странице не будет карточек профессий. Создайте хотя бы одну страницу <b>«Событие + Профессия»</b> для этого события.';
        }
    } elseif ($type === 'hub_subevent' && $subevent_id) {
        $exists = get_posts([
            'post_type' => 'catalog_page',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'page_type', 'value' => 'subevent_prof'],
                ['key' => 'catalog_subevent', 'value' => $subevent_id]
            ],
            'fields' => 'ids',
            'posts_per_page' => 1
        ]);
        if (empty($exists)) {
            $message = 'Внимание: на этой странице не будет карточек профессий. Создайте хотя бы одну страницу <b>«Подсобытие + Профессия»</b> для этого подсобытия.';
        }
    } elseif (in_array($type, ['event_prof', 'subevent_prof']) && $event_id) {
        $is_sub = ($type === 'subevent_prof' && $subevent_id);
        $hub_type = $is_sub ? 'hub_subevent' : 'hub_event';
        $meta_key = $is_sub ? 'catalog_subevent' : 'catalog_event';
        $meta_val = $is_sub ? $subevent_id : $event_id;

        $hub_exists = get_posts([
            'post_type' => 'catalog_page',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'page_type', 'value' => $hub_type],
                ['key' => $meta_key, 'value' => $meta_val]
            ],
            'fields' => 'ids',
            'posts_per_page' => 1
        ]);

        if (empty($hub_exists)) {
            $label = ($hub_type === 'hub_event') ? 'Хаб события' : 'Хаб подсобытия';
            $message = "Внимание: для этой страницы не найден опубликованный <b>{$label}</b>. Исполнители будут выводиться здесь, но пользователи не смогут найти эту страницу через навигацию на сайте.";
            $color = '#dba617'; 
        }
    }

    if ($message) {
        printf(
            '<div class="notice notice-warning inline" style="margin: 20px 0 0 0; border-left-color: %s; background: #fff5f5;">
                <p style="color: %s; font-size: 14px;">%s</p>
            </div>',
            $color, $color, $message
        );
    }
}

// 1. Убираем "Удалить" (Корзина) из массовых действий и добавляем "Удалить навсегда"
add_filter('bulk_actions-edit-catalog_page', 'joyvia_catalog_page_bulk_actions');
function joyvia_catalog_page_bulk_actions($actions) {
    unset($actions['trash']);
    $actions['delete'] = 'Удалить навсегда';
    return $actions;
}

// 2. Меняем ссылку при наведении на строку в таблице каталогов
add_filter('post_row_actions', 'joyvia_catalog_page_row_actions', 10, 2);
function joyvia_catalog_page_row_actions($actions, $post) {
    if ($post->post_type === 'catalog_page') {
        unset($actions['trash']); // Убираем стандартную ссылку корзины
        
        // Добавляем прямую ссылку на безвозвратное удаление
        if (current_user_can('delete_post', $post->ID)) {
            $delete_url = get_delete_post_link($post->ID, '', true); // true = force delete
            $actions['delete'] = sprintf(
                '<a href="%s" class="submitdelete" aria-label="%s" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_attr(sprintf('Удалить навсегда «%s»', $post->post_title)),
                'Вы уверены, что хотите безвозвратно удалить эту страницу?',
                '<span style="color:#a02020;">Удалить навсегда</span>'
            );
        }
    }
    return $actions;
}

// 3. Форсируем ссылку удаления везде (в т.ч. внутри редактора страницы)
add_filter('get_delete_post_link', 'joyvia_catalog_page_force_delete_link', 10, 3);
function joyvia_catalog_page_force_delete_link($link, $post_id, $force_delete) {
    if (!$force_delete && get_post_type($post_id) === 'catalog_page') {
        // Перегенерируем ссылку с параметром force_delete = true
        return get_delete_post_link($post_id, '', true);
    }
    return $link;
}

// 4. Визуально меняем текст красной кнопки внутри редактора (с "В корзину" на "Удалить навсегда")
add_action('admin_footer-post.php', 'joyvia_catalog_page_delete_text_js');
function joyvia_catalog_page_delete_text_js() {
    global $post;
    if ($post && $post->post_type === 'catalog_page') {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var deleteBtn = document.querySelector('#delete-action .submitdelete');
            if (deleteBtn) {
                deleteBtn.textContent = 'Удалить навсегда';
                deleteBtn.addEventListener('click', function(e) {
                    if (!confirm('Вы уверены, что хотите безвозвратно удалить эту страницу каталога?')) {
                        e.preventDefault();
                    }
                });
            }
        });
        </script>
        <?php
    }
}

/**
 * БРОНЕБОЙНЫЙ РОУТЕР КАТАЛОГА
 * Работает на самом низком уровне ядра WP, перехватывая запрос до Rank Math
 */
 

// 1. Регистрируем внутреннюю переменную запроса
add_filter('query_vars', function($vars) {
    $vars[] = 'joyvia_catalog_id';
    $vars[] = 'navyk';
    return $vars;
});

// 2. Перехватываем URL и назначаем ID поста
add_action('parse_request', 'joyvia_catalog_parse_request', 1);
function joyvia_catalog_parse_request($wp) {
    if (is_admin() || defined('DOING_AJAX')) return;
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if (empty($path)) return;
    global $wpdb;

    // 1. СНАЧАЛА ищем актуальную страницу (ПРИОРИТЕТ)
    $page_query = $wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = 'generated_url' AND meta_value = %s
        LIMIT 1
    ", $path);
    
    $page_id = $wpdb->get_var($page_query);

    if ($page_id && get_post_status($page_id) === 'publish') {
        $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (substr($request_uri, -1) !== '/') {
            $query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
            wp_redirect(home_url('/' . $path . '/' . $query_string), 301);
            exit;
        }

        // КРИТИЧЕСКИ ВАЖНО: Очищаем то, что "надумал" WP, и жестко указываем ID нашего поста
        $wp->query_vars = []; 
        $wp->query_vars['joyvia_catalog_id'] = $page_id;
        return; // Нашли страницу, прерываем функцию, редиректы не нужны
    }

    // 2. ЕСЛИ актуальной страницы нет, проверяем историю (_old_urls) для редиректа
    $redirect_query = $wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_old_urls' AND meta_value LIKE %s
        LIMIT 1
    ", '%' . $wpdb->esc_like('"' . $path . '"') . '%');
    
    $redirect_post_id = $wpdb->get_var($redirect_query);

    if ($redirect_post_id) {
        $new_url = get_post_meta($redirect_post_id, 'generated_url', true);
        if ($new_url && $new_url !== $path) {
            $query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
            wp_redirect(home_url('/' . $new_url . '/' . $query_string), 301);
            exit;
        }
    }
}

// 3. Конфигурируем основной запрос WP
add_action('pre_get_posts', 'joyvia_catalog_pre_get_posts');
function joyvia_catalog_pre_get_posts($query) {
    if ($query->is_main_query() && $query->get('joyvia_catalog_id')) {
        $query->set('p', $query->get('joyvia_catalog_id'));
        $query->set('post_type', 'catalog_page');
        $query->is_404 = false; // Блокируем системный 404
    }
}

// 4. Подключаем шаблон и отдаем статус 200
add_filter('template_include', 'joyvia_catalog_template_include', 99);
function joyvia_catalog_template_include($template) {
    if (get_query_var('joyvia_catalog_id')) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
        
        $new_template = locate_template('template-catalog.php');
        if ($new_template) return $new_template;
    }
    return $template;
}

// Отключаем canonical redirect для страниц каталога
add_filter('redirect_canonical', 'joyvia_disable_canonical_for_catalog', 10, 2);
function joyvia_disable_canonical_for_catalog($redirect_url, $requested_url) {
    if (get_query_var('joyvia_catalog_id')) {
        return false;
    }
    return $redirect_url;
}

function my_admin_inline_css() {
    echo '<style> .post-type-performer span.inline.hide-if-no-js, .post-type-performer span.trash { display: none;}
        span.select2-selection.select2-selection--multiple { height: 100%;  display: flex;   }
		li.select2-selection__choice.ui-sortable-handle { padding-left: 5px!important;  padding-right: 5px!important;}
    </style>';
}
add_action('admin_head', 'my_admin_inline_css');
//*************** Отключение Permalink Manager и стандартных ссылок WP для CPT catalog_page
add_action('add_meta_boxes', function() {
    // Удаляем метабокс плагина Permalink Manager
    remove_meta_box('permalink-manager', 'catalog_page', 'normal');
    remove_meta_box('permalink-manager', 'catalog_page', 'advanced');
}, 99);

add_action('admin_head', function() {
    global $post_type;
    if ($post_type === 'catalog_page') {
        echo '<style> #edit-slug-box, .permalink-manager-edit-uri-box { display: none !important; }  </style>';
    }
});


function get_morpher_declension($term_id, $case_str) {
    $term = get_term($term_id);
    if (!$term || is_wp_error($term)) return '';
    $parts = explode('_', $case_str);
    $case = $parts[0];
    $is_plural = isset($parts[1]) && $parts[1] === 'мн';
    $meta_key = 'morpher_name_' . mb_strtolower($case, 'UTF-8') . ($is_plural ? '_plural' : '');
    $cached = get_term_meta($term_id, $meta_key, true);
    if (!empty($cached)) return $cached;
    $error_transient = 'morpher_err_' . $term_id;
    if (get_transient($error_transient)) return $term->name;
    $tokens = defined('MORPHER_TOKENS') ? MORPHER_TOKENS : [];
    $token = !empty($tokens) ? $tokens[array_rand($tokens)] : '';
    $url = 'https://ws3.morpher.ru/russian/declension?s=' . urlencode($term->name) . '&format=json&token=' . $token;
    $response = wp_remote_get($url, ['timeout' => 5]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        set_transient($error_transient, true, HOUR_IN_SECONDS);
        return $term->name;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data['Р'])) {
        $case_list = ['И', 'Р', 'Д', 'В', 'Т', 'П', 'М'];
    foreach ($case_list as $c) {if (!empty($data[$c])) {  update_term_meta($term_id, 'morpher_name_' . mb_strtolower($c, 'UTF-8'), $data[$c]);  }  }
        if (!empty($data['множественное'])) {
            foreach ($case_list as $c) {
                if (!empty($data['множественное'][$c])) {  update_term_meta($term_id, 'morpher_name_' . mb_strtolower($c, 'UTF-8') . '_plural', $data['множественное'][$c]);  }
            }
        }
        if ($is_plural && !empty($data['множественное'][$case])) return $data['множественное'][$case];
        return !empty($data[$case]) ? $data[$case] : $term->name;
    }
    set_transient($error_transient, true, HOUR_IN_SECONDS);
    return $term->name;
}



//Подтверждение почты
add_action('init', 'joyvia_verify_email_handler');
function joyvia_verify_email_handler() {
    if (isset($_GET['verificate']) && !empty($_GET['verificate'])) {
        $hash = sanitize_text_field($_GET['verificate']);
        $users = get_users(['meta_key' => 'email_verification_hash', 'meta_value' => $hash, 'number' => 1]);
        if (!empty($users)) {
            update_user_meta($users[0]->ID, 'email_verified', 1);
            delete_user_meta($users[0]->ID, 'email_verification_hash');
            wp_safe_redirect(home_url('/profile-settings/?verified=1'));
            exit;
        }
    }
}
function joyvia_send_verification_email($user_id, $email) {
    $hash = wp_generate_password(24, false);
    update_user_meta($user_id, 'email_verification_hash', $hash);
    update_user_meta($user_id, 'email_verified', 0);
    $verify_link = home_url('/profile-settings/?verificate=' . $hash);
    $subject = 'Подтверждение почты на Joyvia';
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $message = '<div style="font-family: sans-serif; color: #333;">';
    $message .= '<p style="font-size:32px; font-weight:600; margin-bottom:0px;">Добро пожаловать на сайт Joyvia.</p>';
    $message .= '<p>Для верификации вашей почты, пожалуйста перейдите по ссылке:</p>';
    $message .= '<a href="' . esc_url($verify_link) . '" style="display: inline-block; text-decoration: none; border: none; background: #4c32e1; border-radius: 20px; color: #fff; font-size: 15px; font-weight: 500; padding: 20px 23px;">Подтвердить почту</a>';
    $message .= '</div>';
    wp_mail($email, $subject, $message, $headers);
}
add_action('wp_ajax_joyvia_resend_verification', 'joyvia_resend_verification_cb');
function joyvia_resend_verification_cb() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'joyvia_profile_nonce') || !($uid = get_current_user_id())) wp_send_json_error('auth');
    $user = get_user_by('id', $uid);
    joyvia_send_verification_email($uid, $user->user_email);
    wp_send_json_success();
}
add_action('show_user_profile', 'joyvia_edit_user_email_status');
add_action('edit_user_profile', 'joyvia_edit_user_email_status');
function joyvia_edit_user_email_status($user) {
    $is_verified = get_user_meta($user->ID, 'email_verified', true);
    $text = ($is_verified == 1) ? 'Подтвержден' : 'Не подтвержден';
    $color = ($is_verified == 1) ? '#2c8a3e' : '#d63638';
    ?>
    <h3>Верификация Email (Joyvia)</h3>
    <table class="form-table"><tr><th>Статус почты</th><td><span style="color: <?php echo $color; ?>; font-weight: 600; font-size: 14px;"><?php echo $text; ?></span></td></tr></table>
    <?php
}

//Вывод профессии в навыках и специализации
add_filter('manage_edit-specialization_columns', 'joyvia_add_profession_column_to_terms', 15);
add_filter('manage_edit-skill_columns', 'joyvia_add_profession_column_to_terms', 15);
function joyvia_add_profession_column_to_terms($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'name') {
            $new_columns['joyvia_profession'] = 'Профессия';
        }
    }
    return $new_columns;
}
add_filter('manage_specialization_custom_column', 'joyvia_populate_profession_column_for_terms', 10, 3);
add_filter('manage_skill_custom_column', 'joyvia_populate_profession_column_for_terms', 10, 3);
function joyvia_populate_profession_column_for_terms($content, $column_name, $term_id) {
    if ($column_name === 'joyvia_profession') {
        $prof_id = get_term_meta($term_id, 'profession_link', true);
        if ($prof_id) {
            $prof_term = get_term($prof_id, 'profession');
            if ($prof_term && !is_wp_error($prof_term)) {
                $edit_link = get_edit_term_link($prof_id, 'profession', 'performer');
                return sprintf('<a href="%s" style="font-weight: 500;">%s</a>', esc_url($edit_link), esc_html($prof_term->name));
            }
        }
        return '<span style="color: #a02020;">—</span>';
    }
    return $content;
}