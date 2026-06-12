<?php
$executor = get_query_var('executor');
if (!$executor instanceof WP_Post) return;

$post_id  = $executor->ID;
$user_id  = (int) $executor->post_author;
$position = (int) get_query_var('item_position', 1);

$current_event_id = (int) get_query_var('current_event_id', 0);
$current_spec_id  = (int) get_query_var('current_spec_id', 0);

// Сброс фантомных фильтров (если они всё-таки пролезли из ACF)
$queried_obj_id = get_queried_object_id();
if (get_post_type($queried_obj_id) === 'catalog_page') {
    $page_type = get_field('page_type', $queried_obj_id);
    if (!in_array($page_type, ['event_prof', 'subevent_prof', 'hub_event', 'hub_subevent'])) {
        $current_event_id = 0;
    }
    if ($page_type !== 'spec_prof') {
        $current_spec_id = 0;
    }
}

$first_name = get_user_meta($user_id, 'first_name', true);
$last_name  = get_user_meta($user_id, 'last_name', true);
$full_name  = trim($first_name . ' ' . $last_name) ?: get_the_title($post_id);

$avatarka = get_field('avatarka', $post_id);

$rejting      = get_field('rejting', $post_id) ?: '0.0';
$kolvooczenok = get_field('kol-vo_oczenok', $post_id) ?: '0';

$last_login_ts = get_user_meta($user_id, 'last_login', true);
$last_login    = $last_login_ts
    ? custom_human_time_diff((int) $last_login_ts, current_time('timestamp'))
    : 'Был(а) давно';

$gorod_terms = get_field('gorod', $post_id);
$city_name   = '';
if (!empty($gorod_terms) && is_array($gorod_terms)) {
    foreach ($gorod_terms as $t) {
        $term_obj = get_term(is_object($t) ? $t->term_id : (int) $t, 'city');
        if ($term_obj && !is_wp_error($term_obj) && $term_obj->parent == 0) {
            $city_name = $term_obj->name;
            break;
        }
    }
}

$o_sebe       = get_field('o_sebe', $post_id);
$o_sebe_short = '';
if (!empty($o_sebe)) {
    $o_sebe_text = wp_strip_all_tags(wp_kses($o_sebe, ['strong' => [], 'em' => [], 'ul' => [], 'ol' => [], 'li' => [], 'br' => [], 'p' => []]));
    $o_sebe_short = mb_strlen($o_sebe_text) > 120 ? mb_strimwidth($o_sebe_text, 0, 120, '...') : $o_sebe_text;
}

$service_packages = get_post_meta($post_id, 'service_packages_data', true);
if (!is_array($service_packages)) $service_packages = [];

$valid_event_ids = [];
if ($current_event_id > 0) {
    $valid_event_ids[] = $current_event_id;
    $event_term = get_term($current_event_id, 'event');
    if ($event_term && !is_wp_error($event_term)) {
        if ($event_term->parent == 0) {
            // Если это родительское событие, добавляем все его подсобытия
            $children = get_term_children($current_event_id, 'event');
            if (!is_wp_error($children) && is_array($children)) {
                $valid_event_ids = array_merge($valid_event_ids, $children);
            }
        } else {
            // Если это подсобытие, пакеты родительского события тоже релевантны!
            $valid_event_ids[] = $event_term->parent;
        }
    }
}

$relevant_packages = [];
$other_packages    = [];
$min_price_overall = null;
$min_price_relevant = null;

foreach ($service_packages as $pkg) {
    $price = (int) ($pkg['price'] ?? 0);
    if ($price <= 0) continue;

    $pkg_parent = (int) ($pkg['parent_id'] ?? 0);
    $pkg_type   = $pkg['type'] ?? '';
    $pkg_name   = $pkg['name'] ?? '';

    if ($min_price_overall === null || $price < $min_price_overall) {
        $min_price_overall = $price;
    }

    $is_relevant = false;
    if ($current_event_id > 0 && $pkg_type === 'event' && in_array($pkg_parent, $valid_event_ids)) {
        $is_relevant = true;
    }
    if ($current_spec_id > 0 && $pkg_type === 'spec' && $pkg_parent === $current_spec_id) {
        $is_relevant = true;
    }

    $row = ['name' => $pkg_name, 'price' => $price];

    if ($is_relevant) {
        $relevant_packages[] = $row;
        if ($min_price_relevant === null || $price < $min_price_relevant) {
            $min_price_relevant = $price;
        }
    } else {
        $other_packages[] = $row;
    }
}

$has_context_filter = ($current_event_id > 0 || $current_spec_id > 0);

// ИСПРАВЛЕНИЕ: Fallback на общие пакеты, если релевантных нет вообще (чтобы карточка не была пустой)
if ($has_context_filter && !empty($relevant_packages)) {
    $display_packages = $relevant_packages;
    $final_price      = $min_price_relevant !== null ? $min_price_relevant : $min_price_overall;
} else {
    $display_packages = array_merge($relevant_packages, $other_packages);
    $final_price      = $min_price_overall;
}

$price_str   = $final_price ? 'от ' . number_format($final_price, 0, '.', ' ') . ' ₽' : 'По запросу';
$profile_url = get_permalink($post_id);
?>

<div class="executor-card" data-user-id="<?= esc_attr($post_id) ?>"
     itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">

    <meta itemprop="position" content="<?= esc_attr($position) ?>">

    <div class="executor-schema-wrapper" itemprop="item" itemscope itemtype="https://schema.org/LocalBusiness" style="display:contents;">

        <div class="executor-card-flex">
            <div class="executor-card__avatar">
                <?php if ($avatarka && !empty($avatarka['sizes']['large'])): ?>
                    <img src="<?= esc_url($avatarka['sizes']['large']) ?>"
                         alt="Фото исполнителя <?= esc_attr($full_name) ?>"
                         itemprop="image"
                         width="<?= esc_attr($avatarka['sizes']['large-width'] ?? '') ?>"
                         height="<?= esc_attr($avatarka['sizes']['large-height'] ?? '') ?>">
                <?php else: ?>
                    <img src="/wp-content/uploads/2025/08/user.webp" alt="Фото пользователя" itemprop="image">
                <?php endif; ?>
            </div>

            <div class="executor-card__name-star">
                <p>
                    <a href="<?= esc_url($profile_url) ?>" class="executor-card__name-link" itemprop="url">
                        <span itemprop="name"><?= esc_html($full_name) ?></span>
                    </a>
                </p>

                <div class="executor-card__city">
                    <?php if ($city_name): ?>
                        <span itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                            <span itemprop="addressLocality"><?= esc_html($city_name) ?></span>
                            <meta itemprop="addressCountry" content="RU">
                        </span>
                    <?php endif; ?>
                    <p><?= esc_html($last_login) ?></p>
                </div>

                <meta itemprop="priceRange" content="<?= esc_attr($price_str) ?>">

                <span class="author-card__value">
                    <span class="author-card__rating-icon"></span>
                    <span class="author-card__rating-score"><?= esc_html($rejting) ?></span>
                    <span class="author-card__rating-count">(<?= esc_html($kolvooczenok) ?> оценок)</span>
                </span>
            </div>
        </div>

        <p class="executor-card__text"><?= esc_html($o_sebe_short) ?></p>

        <?php if (!empty($display_packages)): ?>
            <div class="uslugi-list">
                <?php foreach ($display_packages as $i => $pkg): ?>
                    <div class="usluga-item <?= ($i >= 2) ? 'hidden-usluga' : '' ?>">
                        <div class="usluga-header">
                            <p><?= esc_html($pkg['name']) ?></p>
                            <span class="usluga-price">от <?= esc_html(number_format($pkg['price'], 0, '.', ' ')) ?> ₽</span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (count($display_packages) > 2): ?>
                    <button class="show-uslug">Ещё <?= count($display_packages) - 2 ?> услуг</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="executor-card__link">
            <a href="<?= esc_url($profile_url) ?>" class="btn">Подробнее</a>
        </div>

    </div>
</div>