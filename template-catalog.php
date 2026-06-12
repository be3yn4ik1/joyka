<?php

if (!defined('ABSPATH')) exit;

$page_id     = get_queried_object_id();
$is_shop     = function_exists('is_shop') && is_shop();

$seo_h1      = '';
$subtitle    = '';
$seo_text    = '';
$prof_id     = 0;
$event_id    = 0;
$subevent_id = 0;
$spec_id     = 0;
$page_type   = '';
$counters_auto = 1;

if ($is_shop) {
    $page_type   = 'main_hub';
    $seo_h1      = 'Каталог исполнителей';
    $subtitle    = 'Найдите лучших специалистов для вашего мероприятия';
} else {
    $page_type   = get_field('page_type', $page_id);
    $seo_h1      = get_field('seo_h1', $page_id) ?: get_the_title($page_id);
    $subtitle    = get_field('subtitle', $page_id);
    $seo_text    = get_field('seo_text', $page_id);
    
    $prof_id     = (int) get_field('catalog_profession', $page_id);
    $event_id    = (int) get_field('catalog_event', $page_id);
    $subevent_id = (int) get_field('catalog_subevent', $page_id);
    $spec_id     = (int) get_field('catalog_spec', $page_id);
    $counters_auto = get_field('counters_auto', $page_id);

    // --- СБРОС ФАНТОМОВ ---
    // Жестко зануляем ID, если они не нужны для текущего типа страницы.
    if (!in_array($page_type, ['event_prof', 'subevent_prof', 'hub_event', 'hub_subevent'])) {
        $event_id = 0;
        $subevent_id = 0;
    }
    if ($page_type !== 'spec_prof') {
        $spec_id = 0;
    }
    // ----------------------
}

$breadcrumbs = [];
if ($page_type === 'prof_only' && $prof_id) {
    $breadcrumbs[] = ['name' => get_morpher_declension($prof_id, 'И_мн')];
} elseif ($page_type === 'event_prof' && $event_id && $prof_id) {
    $breadcrumbs[] = ['name' => get_morpher_declension($event_id, 'И_мн'), 'url' => home_url('/' . get_term($event_id)->slug . '/')];
    $breadcrumbs[] = ['name' => get_morpher_declension($prof_id, 'И_мн') . ' на ' . mb_strtolower(get_morpher_declension($event_id, 'В'), 'UTF-8')];
} elseif ($page_type === 'subevent_prof' && $event_id && $subevent_id && $prof_id) {
    $breadcrumbs[] = ['name' => get_morpher_declension($event_id, 'И_мн'), 'url' => home_url('/' . get_term($event_id)->slug . '/')];
    $breadcrumbs[] = ['name' => get_morpher_declension($subevent_id, 'И_мн'), 'url' => home_url('/' . get_term($event_id)->slug . '/' . get_term($subevent_id)->slug . '/')];
    $breadcrumbs[] = ['name' => get_morpher_declension($prof_id, 'И_мн') . ' на ' . mb_strtolower(get_morpher_declension($subevent_id, 'В'), 'UTF-8')];
} elseif ($page_type === 'spec_prof' && $prof_id && $spec_id) {
    $breadcrumbs[] = ['name' => get_morpher_declension($prof_id, 'И_мн'), 'url' => home_url('/' . get_term($prof_id)->slug . '/')];
    $breadcrumbs[] = ['name' => get_morpher_declension($spec_id, 'И_мн')];
} elseif ($page_type === 'hub_event' && $event_id) {
    $breadcrumbs[] = ['name' => get_morpher_declension($event_id, 'И_мн')];
} elseif ($page_type === 'hub_subevent' && $event_id && $subevent_id) {
    $breadcrumbs[] = ['name' => get_morpher_declension($event_id, 'И_мн'), 'url' => home_url('/' . get_term($event_id)->slug . '/')];
    $breadcrumbs[] = ['name' => get_morpher_declension($subevent_id, 'И_мн')];
}

$tax_query = ['relation' => 'AND'];
$base_tax_query = ['relation' => 'AND'];

// Формируем базовый запрос только из параметров страницы (без $_GET)
if ($prof_id) {
    $tax_query[] = ['taxonomy' => 'profession', 'field' => 'term_id', 'terms' => $prof_id];
    $base_tax_query[] = ['taxonomy' => 'profession', 'field' => 'term_id', 'terms' => $prof_id];
}
if (in_array($page_type, ['event_prof', 'subevent_prof', 'hub_event', 'hub_subevent']) && ($event_id || $subevent_id)) {
    $tax_query[] = ['taxonomy' => 'event', 'field' => 'term_id', 'terms' => $subevent_id ?: $event_id];
    $base_tax_query[] = ['taxonomy' => 'event', 'field' => 'term_id', 'terms' => $subevent_id ?: $event_id];
}
if (in_array($page_type, ['spec_prof']) && $spec_id) {
    $tax_query[] = ['taxonomy' => 'specialization', 'field' => 'term_id', 'terms' => $spec_id];
    $base_tax_query[] = ['taxonomy' => 'specialization', 'field' => 'term_id', 'terms' => $spec_id];
}

// Добавляем параметры из $_GET ТОЛЬКО в рабочий $tax_query (чтобы не резать списки фильтров)
if (!empty($_GET['active_spec'])) {
    $tax_query[] = ['taxonomy' => 'specialization', 'field' => 'slug', 'terms' => sanitize_text_field($_GET['active_spec'])];
}

if (!empty($_GET['district'])) {
    $tax_query[] = ['taxonomy' => 'city', 'field' => 'term_id', 'terms' => (int)$_GET['district']];
}

$filter_skill_slugs = [];
if (!empty($_GET['navyk'])) {
    $raw = sanitize_text_field(wp_unslash($_GET['navyk']));
    $filter_skill_slugs = array_filter(array_map('sanitize_key', explode(',', $raw)));
}
if (!empty($filter_skill_slugs)) {
    $skill_terms = get_terms([
        'taxonomy'   => 'skill',
        'slug'       => $filter_skill_slugs,
        'hide_empty' => false,
        'fields'     => 'ids',
    ]);
    if (!empty($skill_terms) && !is_wp_error($skill_terms)) {
        $tax_query[] = ['taxonomy' => 'skill', 'field' => 'term_id', 'terms' => $skill_terms, 'operator' => 'AND'];
    }
}

$meta_query = ['relation' => 'AND'];

if (!empty($_GET['experience'])) {
    $meta_query[] = ['key' => 'opyt_raboty', 'value' => sanitize_text_field($_GET['experience']), 'compare' => '='];
}

if (isset($_GET['rating_min']) && $_GET['rating_min'] !== '') {
    if ($_GET['rating_min'] === '0') {
        $meta_query[] = [
            'relation' => 'OR',
            ['key' => 'rejting', 'compare' => 'NOT EXISTS'],
            ['key' => 'rejting', 'value' => '', 'compare' => '='],
            ['key' => 'rejting', 'value' => '0', 'compare' => '=']
        ];
    } else {
        $meta_query[] = [
            'key'     => 'rejting', 
            'value'   => (float)$_GET['rating_min'], 
            'compare' => '>=', 
            'type'    => 'DECIMAL'
        ];
    }
}

if (!empty($_GET['price_min']) || !empty($_GET['price_max'])) {
    $price_q = ['key' => 'min_price', 'type' => 'NUMERIC'];
    if (!empty($_GET['price_min']) && !empty($_GET['price_max'])) {
        $price_q['value']   = [(int)$_GET['price_min'], (int)$_GET['price_max']];
        $price_q['compare'] = 'BETWEEN';
    } elseif (!empty($_GET['price_min'])) {
        $price_q['value']   = (int)$_GET['price_min'];
        $price_q['compare'] = '>=';
    } else {
        $price_q['value']   = (int)$_GET['price_max'];
        $price_q['compare'] = '<=';
    }
    $meta_query[] = $price_q;
}

$orderby_args = [];
if (!empty($_GET['sort_rating'])) {
    $orderby_args['orderby']  = 'meta_value_num';
    $orderby_args['meta_key'] = 'rejting';
    $orderby_args['order']    = $_GET['sort_rating'] === 'ASC' ? 'ASC' : 'DESC';
} else {
    $meta_query[] = [
        'relation' => 'OR',
        'prioritet_clause'  => ['key' => 'prioritet', 'compare' => 'EXISTS'],
        'prioritet_missing' => ['key' => 'prioritet', 'compare' => 'NOT EXISTS'],
    ];
    $orderby_args['orderby'] = ['prioritet_clause' => 'DESC', 'date' => 'DESC'];
}

if ($counters_auto || !empty($_GET)) {
    $stats_query = new WP_Query([
        'post_type'      => 'performer',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => $tax_query,
        'meta_query'     => $meta_query,
    ]);
    $count_performers = $stats_query->found_posts;
    
    if ($counters_auto) {
        $count_reviews = 0;
        $count_rating_total = 0;
        $performers_with_rating = 0;
        
        if ($count_performers > 0) {
            foreach ($stats_query->posts as $pid) {
                $count_reviews += (int) get_field('kol-vo_oczenok', $pid);
                $current_rating = (float) get_field('rejting', $pid);
                
                if ($current_rating > 0) {
                    $count_rating_total += $current_rating;
                    $performers_with_rating++;
                }
            }
            
            if ($performers_with_rating > 0) {
                $count_rating = round($count_rating_total / $performers_with_rating, 1);
            } else {
                $count_rating = 0.0;
            }
        } else {
            $count_rating = 0.0;
        }
    } else {
        $count_reviews = (int) get_field('count_reviews', $page_id);
        $count_rating  = (float) get_field('count_rating', $page_id);
    }
} else {
    $count_performers = (int) get_field('count_performers', $page_id);
    $count_reviews    = (int) get_field('count_reviews', $page_id);
    $count_rating     = (float) get_field('count_rating', $page_id);
}

global $wpdb;

$base_query = new WP_Query([
    'post_type'      => 'performer',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'tax_query'      => $base_tax_query,
]);

$performer_ids = $base_query->posts;
$in_posts = !empty($performer_ids) ? implode(',', array_map('intval', $performer_ids)) : '0';

$cities_query = get_terms([
    'taxonomy'   => 'city',
    'hide_empty' => true,
    'parent'     => 0
]);

$moscow_id = 0;
if(!is_wp_error($cities_query) && !empty($cities_query)) {
    foreach($cities_query as $city_term) {
        if(mb_stripos($city_term->name, 'москва') !== false) {
            $moscow_id = $city_term->term_id;
            break;
        }
    }
}

$districts = [];
$experiences = [];
$ratings = [];
$available_skills = [];
$has_novice = false;

if (!empty($performer_ids)) {
    if ($moscow_id > 0) {
        $districts = get_terms([
            'taxonomy'   => 'city',
            'hide_empty' => false,
            'parent'     => $moscow_id,
            'object_ids' => $performer_ids
        ]);
    }

    $experiences = $wpdb->get_col("
        SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta}
        WHERE meta_key = 'opyt_raboty' 
          AND meta_value != ''
          AND post_id IN ({$in_posts})
    ");
    
    $exp_order = ['Менее 1 года' => 1, '1-3 года' => 2, '3-5 лет' => 3, '5-10 лет' => 4, 'Более 10 лет' => 5];
    usort($experiences, function($a, $b) use ($exp_order) {
        $oa = $exp_order[$a] ?? 99;
        $ob = $exp_order[$b] ?? 99;
        return $oa <=> $ob;
    });

    $ratings = $wpdb->get_col("
        SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta}
        WHERE meta_key = 'rejting' 
          AND meta_value != '' 
          AND meta_value > 0
          AND post_id IN ({$in_posts})
        ORDER BY CAST(meta_value AS DECIMAL(10,2)) DESC
    ");

    $novice_count = $wpdb->get_var("
        SELECT COUNT(p.ID) 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rejting'
        WHERE p.ID IN ({$in_posts})
          AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')
    ");
    
    if ($novice_count > 0) {
        $has_novice = true;
    }

    // Получаем навыки, привязанные к текущим профилям и разрешенные для фильтра
    $available_skills = get_terms([
        'taxonomy'   => 'skill',
        'hide_empty' => false,
        'object_ids' => $performer_ids,
        'meta_query' => [
            [
                'key'     => 'is_filter',
                'value'   => '1',
                'compare' => '='
            ]
        ]
    ]);
    if (is_wp_error($available_skills)) {
        $available_skills = [];
    }
}

if (!empty($active_spec_slug)) {
    $args['tax_query'][] = [
        'taxonomy' => 'specialization',
        'field'    => 'slug',
        'terms'    => $active_spec_slug
    ];
}

get_header();

ob_start();
?>
<div class="catalog-filter-container">
    <div class="catalog-filter-mobile-trigger">
        <button type="button" class="btn-mobile-filter">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.125 8.125C9.16053 8.125 10 7.28553 10 6.25C10 5.21447 9.16053 4.375 8.125 4.375C7.08947 4.375 6.25 5.21447 6.25 6.25C6.25 7.28553 7.08947 8.125 8.125 8.125Z" stroke="#4C32E1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.125 15.625C14.1605 15.625 15 14.7855 15 13.75C15 12.7145 14.1605 11.875 13.125 11.875C12.0895 11.875 11.25 12.7145 11.25 13.75C11.25 14.7855 12.0895 15.625 13.125 15.625Z" stroke="#4C32E1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 6.25H16.875" stroke="#4C32E1" stroke-width="2" stroke-linejoin="round"/><path d="M3.125 6.25H6.25" stroke="#4C32E1" stroke-width="2" stroke-linejoin="round"/><path d="M15 13.75H16.875" stroke="#4C32E1" stroke-width="2" stroke-linejoin="round"/><path d="M3.125 13.75H11.25" stroke="#4C32E1" stroke-width="2" stroke-linejoin="round"/></svg>
            Фильтр <span class="filter-count-badge">0</span>
        </button>
        <button type="button" class="btn-filter-reset mobile-foot-reset hidden">Сбросить</button>
    </div>

    <div class="catalog-filter-wrapper">
        <div class="catalog-filter-mobile-head">
            <div class="title">Фильтр</div>
            <button type="button" class="btn-filter-close">&times;</button>
        </div>

        <div class="catalog-filter-fields">
            
            <?php 
            $sort_val = $_GET['sort_rating'] ?? '';
            $sort_label = 'По рейтингу';
            if ($sort_val === 'DESC') $sort_label = 'От 5 до 0';
            elseif ($sort_val === 'ASC') $sort_label = 'От 0 до 5';
            ?>
            <div class="filter-group custom-select-group">
                <input type="hidden" name="sort_rating" value="<?php echo esc_attr($sort_val); ?>" class="filter-input-hidden">
                <div class="custom-select-trigger filter-select" data-default="По рейтингу"><?php echo esc_html($sort_label); ?></div>
                <div class="filter-result">
                    <span data-value="">По рейтингу</span>
                    <span data-value="DESC" <?php echo $sort_val === 'DESC' ? 'class="selected"' : ''; ?>>От 5 до 0</span>
                    <span data-value="ASC" <?php echo $sort_val === 'ASC' ? 'class="selected"' : ''; ?>>От 0 до 5</span>
                </div>
            </div>

            <?php
            $district_val = $_GET['district'] ?? '';
            $district_label = 'Район Москвы';
            if ($district_val && !empty($districts)) {
                foreach ($districts as $d) {
                    if ((string)$d->term_id === (string)$district_val) {
                        $district_label = $d->name;
                        break;
                    }
                }
            }
            ?>
            <div class="filter-group custom-select-group">
                <input type="hidden" name="district" value="<?php echo esc_attr($district_val); ?>" class="filter-input-hidden">
                <div class="custom-select-trigger filter-select" data-default="Район Москвы"><?php echo esc_html($district_label); ?></div>
                <div class="filter-result">
                    <span data-value="">Район Москвы</span>
                    <?php if(!is_wp_error($districts) && !empty($districts)): ?>
                        <?php foreach($districts as $district): ?>
                            <span data-value="<?php echo esc_attr($district->term_id); ?>" <?php echo $district_val == $district->term_id ? 'class="selected"' : ''; ?>>
                                <?php echo esc_html($district->name); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="filter-group filter-price-group">
                <div class="price-dropdown-toggle">Цена</div>
                <div class="price-dropdown-menu">
                    <div class="price-input-wrap">
                        <span>От</span>
                        <input type="number" name="price_min" placeholder="3 000" class="filter-input-price" value="<?php echo esc_attr($_GET['price_min'] ?? ''); ?>">
                        <button type="button" class="clear-price">&times;</button>
                    </div>
                    <div class="price-input-wrap">
                        <span>До</span>
                        <input type="number" name="price_max" placeholder="300 000" class="filter-input-price" value="<?php echo esc_attr($_GET['price_max'] ?? ''); ?>">
                        <button type="button" class="clear-price">&times;</button>
                    </div>
                </div>
            </div>

            <?php 
            $exp_val = $_GET['experience'] ?? '';
            $exp_label = $exp_val ? $exp_val : 'Возраст / Опыт';
            ?>
            <div class="filter-group custom-select-group">
                <input type="hidden" name="experience" value="<?php echo esc_attr($exp_val); ?>" class="filter-input-hidden">
                <div class="custom-select-trigger filter-select" data-default="Возраст / Опыт"><?php echo esc_html($exp_label); ?></div>
                <div class="filter-result">
                    <span data-value="">Возраст / Опыт</span>
                    <?php 
                    if(!empty($experiences)) {
                        foreach ($experiences as $exp) {
                            $sel = ($exp_val === $exp) ? 'class="selected"' : '';
                            echo "<span data-value=\"".esc_attr($exp)."\" $sel>".esc_html($exp)."</span>";
                        }
                    }
                    ?>
                </div>
            </div>

            <?php
            $rat_val = $_GET['rating_min'] ?? '';
            $rat_label = 'Рейтинг';
            if ($rat_val === '0') {
                $rat_label = 'Без рейтинга / Новички';
            } elseif ($rat_val !== '') {
                $rat_label = 'от ' . str_replace('.', ',', $rat_val);
            }
            ?>
            <div class="filter-group custom-select-group">
                <input type="hidden" name="rating_min" value="<?php echo esc_attr($rat_val); ?>" class="filter-input-hidden">
                <div class="custom-select-trigger filter-select" data-default="Рейтинг"><?php echo esc_html($rat_label); ?></div>
                <div class="filter-result">
                    <span data-value="">Рейтинг</span>
                    <?php if($has_novice): ?>
                        <span data-value="0" <?php echo $rat_val === '0' ? 'class="selected"' : ''; ?>>Без рейтинга / Новички</span>
                    <?php endif; ?>
                    <?php if(!empty($ratings)): ?>
                        <?php foreach($ratings as $rating_val): ?>
                            <span data-value="<?php echo esc_attr($rating_val); ?>" <?php echo $rat_val === (string)$rating_val ? 'class="selected"' : ''; ?>>
                                от <?php echo esc_html(str_replace('.', ',', $rating_val)); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $skill_val = $_GET['navyk'] ?? '';
            $skill_label = 'Навыки';
            if ($skill_val && !empty($available_skills)) {
                foreach ($available_skills as $sk) {
                    if ($sk->slug === $skill_val) {
                        $skill_label = $sk->name;
                        break;
                    }
                }
            }
            ?>
            <div class="filter-group custom-select-group">
                <input type="hidden" name="navyk" value="<?php echo esc_attr($skill_val); ?>" class="filter-input-hidden">
                <div class="custom-select-trigger filter-select" data-default="Навыки"><?php echo esc_html($skill_label); ?></div>
                <div class="filter-result">
                    <?php if(!empty($available_skills)): ?>
                        <?php foreach($available_skills as $skill): ?>
                            <span data-value="<?php echo esc_attr($skill->slug); ?>" <?php echo $skill_val === $skill->slug ? 'class="selected"' : ''; ?>>
                                <?php echo esc_html($skill->name); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="button" class="btn-filter-reset desktop-reset hidden">Сбросить</button>
        </div>

        <div class="catalog-filter-mobile-foot">
            <button type="button" class="btn-apply-mobile">Показать исполнителей (<span class="total-count-mobile"><?php echo esc_html($count_performers); ?></span>)</button>
            <button type="button" class="btn-filter-reset mobile-foot-reset hidden">Сбросить</button>
        </div>
    </div>
</div>
<?php $filter_html = ob_get_clean(); ?>

<section class="section section--archivepage-top <?= in_array($page_type, ['hub_event', 'hub_subevent', 'main_hub']) ? 'is-hub' : '' ?>">
    <div class="breadcrumbs">
        <a href="<?= esc_url(home_url()) ?>">Главная</a>
        <?php foreach ($breadcrumbs as $crumb): ?>
            <span class="catalog-breadcrumbs__sep">/</span>
            <?php if (!empty($crumb['url'])): ?>
                <a href="<?= esc_url($crumb['url']) ?>"><?= esc_html($crumb['name']) ?></a>
            <?php else: ?>
                <span><?= esc_html($crumb['name']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <h1 class="archivepage-title"><?= esc_html($seo_h1) ?></h1>

    <?php if ($subtitle): ?>
        <p class="archivepage-description"><?= esc_html($subtitle) ?></p>
    <?php endif; ?>

    <div class="catalog-counters-box">
        <div class="catalog-counters-box__item">
            <div class="catalog-counters-box__value"><span class="total-count-mobile"><?= esc_html($count_performers) ?></span>+</div>
            <div class="catalog-counters-box__label">исполнителей</div>
        </div>
        <div class="catalog-counters-box__divider"></div>
        <div class="catalog-counters-box__item">
            <div class="catalog-counters-box__value"><?= esc_html($count_reviews) ?></div>
            <div class="catalog-counters-box__label">отзывов</div>
        </div>
        <div class="catalog-counters-box__divider"></div>
        <div class="catalog-counters-box__item">
            <div class="catalog-counters-box__value starsvg"><?= esc_html(number_format($count_rating, 1)) ?></div>
            <div class="catalog-counters-box__label">средний рейтинг</div>
        </div>
    </div>
</section>

<?php if (in_array($page_type, ['hub_event', 'hub_subevent', 'main_hub'])): ?>

    <section class="section section--hub-content">
        <div class="wrapwhite">
            <h2><?= $is_shop ? 'Выберите категорию специалистов' : esc_html('Кого ищете на ' . get_morpher_declension($subevent_id ?: $event_id, 'В')) ?></h2>
        <?php
        if ($is_shop) {
            $hub_pages = get_posts([
                'post_type'      => 'catalog_page',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_query'     => [
                    ['key' => 'page_type', 'value' => 'prof_only']
                ],
            ]);
        } else {
            $mq   = ['relation' => 'AND'];
            $mq[] = ['key' => 'page_type', 'value' => $subevent_id ? 'subevent_prof' : 'event_prof'];
            $mq[] = ['key' => 'catalog_event', 'value' => $event_id];
            if ($subevent_id) {
                $mq[] = ['key' => 'catalog_subevent', 'value' => $subevent_id];
            }

            $hub_pages = get_posts([
                'post_type'      => 'catalog_page',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_query'     => $mq,
            ]);
        }

        $active_professions = [];

        if (!empty($hub_pages)) {
            echo '<div class="dgrid section--find_specialist mt20">';
            foreach ($hub_pages as $h_page) {
                $h_prof_id   = get_field('catalog_profession', $h_page->ID);
                if (!$h_prof_id) continue;
                $h_prof_term = get_term($h_prof_id);
                if (is_wp_error($h_prof_term) || !$h_prof_term) continue;

                $h_url   = home_url('/' . ltrim(get_post_meta($h_page->ID, 'generated_url', true), '/') . '/');
                $h_icon  = get_field('profession_icon', $h_prof_term);

                $tax_query_count = ['relation' => 'AND', ['taxonomy' => 'profession', 'field' => 'term_id', 'terms' => $h_prof_id]];
                if (!$is_shop && ($subevent_id || $event_id)) {
                    $tax_query_count[] = ['taxonomy' => 'event', 'field' => 'term_id', 'terms' => $subevent_id ?: $event_id];
                }

                $p_count = new WP_Query([
                    'post_type'      => 'performer',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'tax_query'      => $tax_query_count,
                ]);

                if ($p_count->found_posts > 0) {
                    $active_professions[$h_prof_id] = ['term' => $h_prof_term, 'url' => $h_url];
                    ?>
<a href="<?= esc_url($h_url) ?>" style="background-image:url(<?= esc_url(is_array($h_icon) ? $h_icon['url'] : $h_icon) ?>)">
    <p><?= esc_html($h_prof_term->name) ?></p>
    <span><?= esc_html($p_count->found_posts) ?> исполнителей</span>
</a>
                    <?php
                }
            }
            echo '</div>';
        }
        ?>
</div>
        <?php if (!empty($active_professions)): ?>
            <h2 class="hub-popular__title">Популярные исполнители</h2>
            
            <?= $filter_html ?>

            <div class="authors-grid" id="authors-grid" itemprop="mainEntity" itemscope itemtype="https://schema.org/ItemList">
                <meta itemprop="name" content="<?= esc_attr($seo_h1) ?>">
                <?php 
                // Используем кастомный параметр cpaged вместо paged, чтобы избежать 404 от WooCommerce
                $paged = max(1, (int) ($_GET['cpaged'] ?? 1));

                $hub_query_args = array_merge([
                    'post_type'      => 'performer',
                    'post_status'    => 'publish',
                    'posts_per_page' => 6, // 6 карточек на страницу
                    'paged'          => $paged,
                    'tax_query'      => $tax_query,
                    'meta_query'     => $meta_query,
                ], $orderby_args);

                $hub_catalog_query = new WP_Query($hub_query_args);

                if ($hub_catalog_query->have_posts()) {
                    $position = 1;
                    while ($hub_catalog_query->have_posts()) {
                        $hub_catalog_query->the_post();
                        set_query_var('executor', $post);
                        if (!$is_shop) {
                            set_query_var('current_event_id', $subevent_id ?: $event_id);
                        }
                        set_query_var('item_position', $position++);
                        get_template_part('template/executor');
                    }
                    wp_reset_postdata();
                } else {
                    echo '<p class="catalog-empty" style="margin-top:20px;">К сожалению, исполнителей по вашему запросу не найдено.</p>';
                }
                ?>
            </div>
            
            <?php
            $max_pages   = $hub_catalog_query->max_num_pages;
            $total_found = $hub_catalog_query->found_posts;
            if ($max_pages > 1):
                $query_args_for_pagination = $_GET;
                unset($query_args_for_pagination['cpaged']);
                
                $base_url = home_url(wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
            ?>
            <div class="pagination" id="pagination">
                <div class="pagination-links">
                    <?php
                    echo paginate_links([
                        'base'      => $base_url . '%_%',
                        'format'    => '?cpaged=%#%',
                        'current'   => $paged,
                        'total'     => $max_pages,
                        'prev_text' => '«',
                        'next_text' => '»',
                        'type'      => 'plain',
                        'add_args'  => $query_args_for_pagination,
                    ]);
                    ?>
                </div>
                <div class="pagination-count">
                    <?= esc_html(min($paged * $hub_query_args['posts_per_page'], $total_found) . ' из ' . $total_found) ?>
                </div>
            </div>
            <?php else: ?>
                <div id="pagination" style="display:none;"></div>
            <?php endif; ?>
            
        <?php endif; ?>
        
        
     <?php if (!$is_shop && function_exists('joyvia_schema_render_hub')) joyvia_schema_render_hub($page_id, $page_type, $event_id, $subevent_id); ?>
        
    </section>

<?php else: ?>

    <section class="section section--archivepage-authors">

        <?= $filter_html ?>

        <?php if ($prof_id):
            $sibling_specs = get_terms([
                'taxonomy'   => 'specialization',
                'hide_empty' => false,
                'meta_query' => [['key' => 'profession_link', 'value' => $prof_id, 'compare' => '=']],
            ]);

            if (!empty($sibling_specs) && !is_wp_error($sibling_specs)):
                $prof_term   = get_term($prof_id);
                $is_prof_hub = ($page_type === 'prof_only' && empty($_GET['active_spec']));
        ?>
            <div class="catalog-specializations-block">
                <h3 class="catalog-specializations-block__title">
<?php
$active_spec_slug = isset($_GET['active_spec']) ? sanitize_text_field($_GET['active_spec']) : '';

$perf_args = [
    'post_type'      => 'performer',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'post_status'    => 'publish',
    'tax_query'      => ['relation' => 'AND']
];

if ($prof_id) {
    $perf_args['tax_query'][] = [
        'taxonomy' => 'profession',
        'field'    => 'term_id',
        'terms'    => $prof_id
    ];
}

if ($event_id) {
    $event_ids = [$event_id];
    $children = get_term_children($event_id, 'event');
    if (!is_wp_error($children) && !empty($children)) {
        $event_ids = array_merge($event_ids, $children);
    }
    $perf_args['tax_query'][] = [
        'taxonomy' => 'event',
        'field'    => 'term_id',
        'terms'    => $event_ids,
        'operator' => 'IN'
    ];
}

$perf_ids = get_posts($perf_args);

$available_specs = [];
if (!empty($perf_ids)) {
    $terms = wp_get_object_terms($perf_ids, 'specialization');
    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $term_prof_id = get_term_meta($term->term_id, 'profession_link', true);
            if ((int)$term_prof_id === (int)$prof_id) {
                $available_specs[$term->term_id] = $term;
            }
        }
    }
}

$prof_term = get_term($prof_id, 'profession');
$prof_name_plural = '';
if ($prof_term && !is_wp_error($prof_term)) {
    if (function_exists('get_morpher_declension')) {
        $declined = get_morpher_declension($prof_id, 'И_мн');
        $prof_name_plural = mb_strtolower($declined, 'UTF-8');
    } else {
        $prof_name_plural = mb_strtolower(get_field('plural_name', 'profession_' . $prof_id) ?: $prof_term->name, 'UTF-8');
    }
}
?>

    Другие специализации <?php echo esc_html(mb_strtolower(get_morpher_declension($prof_term->term_id, 'Р_мн'))); ?>
</h3>
                <div class="catalog-specializations-block__list" id="ajax-spec-list">
    <button type="button" 
        data-slug="" 
        class="catalog-spec-tag <?= empty($active_spec_slug) ? 'catalog-spec-tag--active' : '' ?>">
    Все <?= esc_html($prof_name_plural) ?>
</button>

    <?php if (!empty($available_specs)): ?>
        <?php foreach ($available_specs as $spec): ?>
            <button type="button" 
                    data-slug="<?= esc_attr($spec->slug) ?>" 
                    class="catalog-spec-tag <?= ($active_spec_slug === $spec->slug) ? 'catalog-spec-tag--active' : '' ?>">
                <?= esc_html($spec->name) ?>
            </button>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
            </div>
        <?php
            endif;
        endif;
        ?>

        <div class="authors-grid" id="authors-grid" itemprop="mainEntity" itemscope itemtype="https://schema.org/ItemList">
            <meta itemprop="name" content="<?= esc_attr($seo_h1) ?>">

            <?php
            // Аналогичная замена для страниц обычного каталога
            $paged = max(1, (int) ($_GET['cpaged'] ?? 1));

            $query_args = array_merge([
                'post_type'      => 'performer',
                'post_status'    => 'publish',
                'posts_per_page' => 6,
                'paged'          => $paged,
                'tax_query'      => $tax_query,
                'meta_query'     => $meta_query,
            ], $orderby_args);

            $catalog_query = new WP_Query($query_args);

            if (function_exists('joyvia_schema_render_catalog')) {
                joyvia_schema_render_catalog($page_id, $page_type, $prof_id, $event_id, $subevent_id, $spec_id, $catalog_query);
            }

            if ($catalog_query->have_posts()) {
                $position = 1;
                while ($catalog_query->have_posts()) {
                    $catalog_query->the_post();
                    set_query_var('executor', $post);
                    set_query_var('item_position', $position++);
                    set_query_var('current_event_id', $subevent_id ?: $event_id);
                    set_query_var('current_spec_id', $spec_id);
                    get_template_part('template/executor');
                }
                wp_reset_postdata();
            } else {
                echo '<p class="catalog-empty">К сожалению, исполнителей по вашему запросу не найдено.</p>';
            }
            ?>
        </div>

        <?php
        $max_pages   = $catalog_query->max_num_pages;
        $total_found = $catalog_query->found_posts;
        if ($max_pages > 1):
            $query_args_for_pagination = $_GET;
            unset($query_args_for_pagination['cpaged']);
            
            $base_url = home_url(wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        ?>
        <div class="pagination" id="pagination">
            <div class="pagination-links">
                <?php
                echo paginate_links([
                    'base'      => $base_url . '%_%',
                    'format'    => '?cpaged=%#%',
                    'current'   => $paged,
                    'total'     => $max_pages,
                    'prev_text' => '«',
                    'next_text' => '»',
                    'type'      => 'plain',
                    'add_args'  => $query_args_for_pagination,
                ]);
                ?>
            </div>
            <div class="pagination-count">
                <?= esc_html(min($paged * 6, $total_found) . ' из ' . $total_found) ?>
            </div>
        </div>
        <?php else: ?>
            <div id="pagination" style="display:none;"></div>
        <?php endif; ?>

    </section>

<?php endif; ?>

<?php if ($seo_text): ?>
    <section class="section section--description">
        <div class="container">
            <?= apply_filters('the_content', $seo_text) ?>
        </div>
    </section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterContainer = document.querySelector('.catalog-filter-container');
    const wrapper = filterContainer ? filterContainer.querySelector('.catalog-filter-wrapper') : null;
    const btnMobileFilter = filterContainer ? filterContainer.querySelector('.btn-mobile-filter') : null;
    const btnClose = filterContainer ? filterContainer.querySelector('.btn-filter-close') : null;
    const resetBtns = filterContainer ? filterContainer.querySelectorAll('.btn-filter-reset') : [];
    const inputs = filterContainer ? filterContainer.querySelectorAll('.filter-input-hidden, .filter-input-price') : [];
    const priceToggle = filterContainer ? filterContainer.querySelector('.price-dropdown-toggle') : null;
    const specButtons = document.querySelectorAll('#ajax-spec-list .catalog-spec-tag');
    const applyMobileBtn = filterContainer ? filterContainer.querySelector('.btn-apply-mobile') : null;
    const countBadges = filterContainer ? filterContainer.querySelectorAll('.filter-count-badge') : [];
    const mobileTotalCount = document.querySelectorAll('.total-count-mobile');
    const selectGroups = filterContainer ? filterContainer.querySelectorAll('.custom-select-group') : [];
    let isMobileOpen = false;

    const toggleMobileModal = (state) => {
        if (!wrapper) return;
        isMobileOpen = state;
        wrapper.classList.toggle('active', isMobileOpen);
        document.body.style.overflow = isMobileOpen ? 'hidden' : '';
    };

    if(btnMobileFilter) btnMobileFilter.addEventListener('click', () => toggleMobileModal(true));
    if(btnClose) btnClose.addEventListener('click', () => toggleMobileModal(false));

    if(priceToggle) {
        priceToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.filter-result.active').forEach(el => el.classList.remove('active'));
            this.parentElement.classList.toggle('open');
        });
    }

    selectGroups.forEach(group => {
        const trigger = group.querySelector('.custom-select-trigger');
        const dropdown = group.querySelector('.filter-result');
        const hiddenInput = group.querySelector('.filter-input-hidden');
        const options = dropdown.querySelectorAll('span');
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.filter-result.active').forEach(el => {
                if (el !== dropdown) el.classList.remove('active');
            });
            document.querySelectorAll('.filter-price-group.open').forEach(el => el.classList.remove('open'));
            dropdown.classList.toggle('active');
        });
        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const val = option.dataset.value;
                const text = option.textContent.trim();
                hiddenInput.value = val;
                trigger.textContent = text;
                options.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                dropdown.classList.remove('active');
                handleFilterChange();
            });
        });
    });

    document.addEventListener('click', (e) => {
        if(!e.target.closest('.custom-select-group')) {
            document.querySelectorAll('.filter-result.active').forEach(el => el.classList.remove('active'));
        }
        if(!e.target.closest('.filter-price-group')) {
            document.querySelectorAll('.filter-price-group.open').forEach(el => el.classList.remove('open'));
        }
    });

    const updateClearButtons = () => {
        if (!filterContainer) return;
        filterContainer.querySelectorAll('.price-input-wrap').forEach(wrap => {
            const input = wrap.querySelector('input');
            const clearBtn = wrap.querySelector('.clear-price');
            clearBtn.style.display = input.value.length > 0 ? 'block' : 'none';
        });
    };

    if (filterContainer) {
        filterContainer.querySelectorAll('.clear-price').forEach(btn => {
            btn.addEventListener('click', function() {
                this.previousElementSibling.value = '';
                updateClearButtons();
                handleFilterChange();
            });
        });
    }

    const updateActiveState = () => {
        let activeCount = 0;
        inputs.forEach(input => {
            if(input.value.trim() !== '') activeCount++;
        });

        resetBtns.forEach(btn => {
            if(activeCount > 0) btn.classList.remove('hidden');
            else btn.classList.add('hidden');
        });

        countBadges.forEach(badge => {
            badge.textContent = activeCount;
            badge.style.display = activeCount > 0 ? 'inline-flex' : 'none';
        });
    };

    const fetchResults = (targetUrl = null) => {
        let url;
        
        if (targetUrl) {
            url = new URL(targetUrl, window.location.origin);
        } else {
            url = new URL(window.location.href);
            // Удаляем наш новый параметр из URL
            url.searchParams.delete('cpaged');
            
            inputs.forEach(input => {
                if(input.value.trim() !== '') {
                    url.searchParams.set(input.name, input.value);
                } else {
                    url.searchParams.delete(input.name);
                }
            });

            let activeSpec = document.querySelector('#ajax-spec-list .catalog-spec-tag--active');
            if(activeSpec && activeSpec.dataset.slug) {
                url.searchParams.set('active_spec', activeSpec.dataset.slug);
            } else {
                url.searchParams.delete('active_spec');
            }
        }

        const gridContainer = document.getElementById('authors-grid');
        if(gridContainer) gridContainer.style.opacity = '0.4';

        fetch(url.toString())
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newGrid = doc.getElementById('authors-grid');
                const newPagination = doc.getElementById('pagination');
                const newCountStr = doc.querySelector('.total-count-mobile');
                const paginationContainer = document.getElementById('pagination');
                
                if(gridContainer && newGrid) {
                    gridContainer.innerHTML = newGrid.innerHTML;
                    gridContainer.style.opacity = '1';
                }
                
                if(paginationContainer) {
                    if(newPagination) {
                        paginationContainer.innerHTML = newPagination.innerHTML;
                        paginationContainer.style.display = '';
                    } else {
                        paginationContainer.innerHTML = '';
                        paginationContainer.style.display = 'none';
                    }
                }

                if(newCountStr) {
                    mobileTotalCount.forEach(el => el.textContent = newCountStr.textContent);
                }

                window.history.replaceState({}, '', url.toString());

                if (targetUrl && gridContainer) {
                    const rect = gridContainer.getBoundingClientRect();
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                    const offset = window.innerWidth <= 768 ? 60 : 100;
                    window.scrollTo({
                        top: rect.top + scrollTop - offset,
                        behavior: 'smooth'
                    });
                }
                
                if (typeof initUslugi === 'function') initUslugi();
            })
            .catch(error => {
                console.error('Ошибка загрузки:', error);
                if(gridContainer) gridContainer.style.opacity = '1';
            });
    };

    const handleFilterChange = () => {
        updateClearButtons();
        updateActiveState();
        fetchResults();
    };

    inputs.forEach(input => {
        if(input.type !== 'hidden') {
            input.addEventListener('change', handleFilterChange);
            input.addEventListener('input', updateClearButtons);
        }
    });

    if(applyMobileBtn) {
        applyMobileBtn.addEventListener('click', () => {
            toggleMobileModal(false);
        });
    }

    resetBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            inputs.forEach(input => input.value = '');
            
            selectGroups.forEach(group => {
                const trigger = group.querySelector('.custom-select-trigger');
                trigger.textContent = trigger.dataset.default;
                group.querySelectorAll('.filter-result span').forEach(span => span.classList.remove('selected'));
            });

            updateClearButtons();
            updateActiveState();
            fetchResults();
            if(window.innerWidth <= 991) toggleMobileModal(false);
        });
    });

    specButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            specButtons.forEach(b => b.classList.remove('catalog-spec-tag--active'));
            this.classList.add('catalog-spec-tag--active');
            fetchResults();
        });
    });

    document.addEventListener('click', (e) => {
        const paginationLink = e.target.closest('#pagination a.page-numbers');
        if (paginationLink) {
            e.preventDefault();
            fetchResults(paginationLink.href);
        }
    });

    updateClearButtons();
    updateActiveState();
});
</script>

<?php get_footer(); ?>