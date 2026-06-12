<?php
get_header();

// 1. Определяем контекст: зашли по прямому URL поста (/performer/slug/) или по старому архиву автора
if (is_singular('performer')) {
    $post_id   = get_queried_object_id();
    $main_post = get_post($post_id);
    $user_id   = $main_post ? $main_post->post_author : 0;
} else {
    $user_id   = get_queried_object_id();
    $main_post = Joyvia_Profile_Manager::get_main_profile($user_id);
    if ($main_post) {
        $post_id = $main_post->ID;
    }
}

// Профиль виден всем только если ACF profile_status = 'published'.
// Сам автор может смотреть свой профиль в любом статусе (превью).
$acf_status = $main_post ? (get_field('profile_status', $main_post->ID) ?: 'draft') : '';
$is_owner   = ($main_post && get_current_user_id() === (int)$user_id);

if (empty($user_id) || empty($main_post) || ($acf_status !== 'published' && !$is_owner)) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part(404);
    exit;
}

// 3. Базовые данные пользователя
$first_name = get_user_meta($user_id, 'first_name', true);
$last_name  = get_user_meta($user_id, 'last_name', true);

// 4. Данные из поста (ACF и мета)
$avatarka = get_field('avatarka', $post_id);
$fallback = '/wp-content/themes/ava.svg';

$rejting = get_field('rejting', $post_id) ?: '0.0';
$kolvooczenok = get_field('kol-vo_oczenok', $post_id) ?: '0';

// Последний визит (остается в user_meta)
$last_login_ts = get_user_meta($user_id, 'last_login', true);
$now = current_time('timestamp');
$last_login = $last_login_ts ? custom_human_time_diff((is_numeric($last_login_ts) ? $last_login_ts : strtotime($last_login_ts)), $now) : 'В сети был(а) давно';

// Город и районы
$gorod_terms = get_field('gorod', $post_id);
$city_name = '';
$region_names = [];
if (!empty($gorod_terms) && is_array($gorod_terms)) {
    foreach ($gorod_terms as $t) {
        $term_id = is_object($t) ? $t->term_id : (int)$t;
        $term_obj = get_term($term_id, 'city');
        if (!$term_obj || is_wp_error($term_obj)) continue;
        if ($term_obj->parent == 0) $city_name = $term_obj->name;
        else $region_names[] = $term_obj->name;
    }
}

// О себе
$o_sebe = get_field('o_sebe', $post_id);
$o_sebe_short = '';
$show_more = false;
if (!empty($o_sebe)) {
    $o_sebe_text = wp_strip_all_tags(wp_kses($o_sebe, ['strong' => [], 'em' => [], 'ul' => [], 'ol' => [], 'li' => [], 'br' => [], 'p' => []]));
    if (mb_strlen($o_sebe_text) > 120) {
        $o_sebe_short = mb_strimwidth($o_sebe_text, 0, 120, '...');
        $show_more = true;
    } else {
        $o_sebe_short = $o_sebe_text;
    }
}

// Пакеты услуг
$service_packages = get_post_meta($post_id, 'service_packages_data', true);
if (!is_array($service_packages)) $service_packages = [];

$min_price = null;
if (!empty($service_packages)) {
    foreach ($service_packages as $pkg) {
        $price_val = (float)$pkg['price'];
        if ($price_val > 0 && ($min_price === null || $price_val < $min_price)) {
            $min_price = $price_val;
        }
    }
    usort($service_packages, function($a, $b) { return (float)$b['price'] <=> (float)$a['price']; });
}
$packages_top = array_slice($service_packages, 0, 3);

?>

<div class="wrapper--author">
    <div>
        <section class="section section--author-card">
            <div class="author-card__photo">
                <?php if ($avatarka && isset($avatarka['url'])): ?>
                    <img src="<?= esc_url($avatarka['url']) ?>" alt="Фото">
                <?php else: ?>
                    <img src="<?= esc_url($fallback) ?>" alt="Фото">
                <?php endif; ?>
            </div>
            <div class="author-card__content">
                <h1 class="author-card__name"><?= esc_html($first_name . ' ' . $last_name) ?></h1>
                
<?php if ($is_owner && $acf_status !== 'published'): ?>
    <div style="background:#fff3cd; color:#856404; padding:8px 12px; border-radius:4px; margin-bottom:15px; font-size:13px; display:inline-block;">
        <?php
        $owner_msgs = [
            'draft'       => 'Профиль ещё не опубликован — его не видно в каталоге.',
            'pending'     => 'Профиль на модерации и пока не виден в каталоге.',
            'rejected'    => 'Профиль отклонён модератором. Исправьте и отправьте повторно.',
            'deactivated' => 'Профиль скрыт вами и не виден в каталоге.',
        ];
        echo esc_html($owner_msgs[$acf_status] ?? 'Профиль не опубликован.');
        ?>
    </div>
<?php endif; ?>

                <div class="author-card__city">
                    <p>
                        <?= esc_html($city_name) ?>
                        <?php if (!empty($region_names)): ?>
                            <span class="author-card__regions"><?= esc_html(implode(', ', $region_names)) ?></span>
                        <?php endif; ?>
                    </p>
                    <p><?= esc_html($last_login) ?></p>
                </div>
                
                <div class="author-card__field author-card__field--rating">
                    <span class="author-card__value">
                        <span class="author-card__rating-icon" style="color:#ffc107;">★</span>
                        <span class="author-card__rating-score"><?= esc_html($rejting) ?></span>
                        <span class="author-card__rating-count">(<?= esc_html($kolvooczenok) ?> оценок)</span>
                    </span>
                </div>
                
                <?php if (!empty($o_sebe)): ?>
                    <div class="author-card__field author-card__field--description">
                        <p class="author-card__text"><?= esc_html($o_sebe_short) ?></p>
                        <?php if ($show_more): ?><a href="#moreinfo">Подробнее</a><?php endif; ?>
                        
                        <?php if (!empty($service_packages)): ?>
                            <section class="section section--author-pricemore nopc">
                                <div class="pricemore">
                                    <div class="pricemore__list">
                                        <?php foreach ($packages_top as $pkg): ?>
                                            <div class="pricemore__item">
                                                <span class="pricemore__name"><?= esc_html($pkg['name']) ?></span>
                                                <span class="pricemore__price">от <?= number_format((float)$pkg['price'], 0, '', ' ') ?> ₽</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="pricemore__more"><a href="#uslugi">Подробнее о пакетах услуг</a></div>
                                    <div class="pricemore__btn"><button class="zayavka" onclick="openPopup('zayavka')">Оставить заявку</button></div>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php
        // Статистика (Опыт)
        $stats = [];
        if ($rejting) $stats[] = ['value' => number_format((float)$rejting, 1), 'label' => 'рейтинг'];
        if ($kolvooczenok) $stats[] = ['value' => $kolvooczenok, 'label' => 'отзывов'];
        if ($opyt = get_field('opyt_raboty', $post_id)) {
            $stats[] = ['value' => apply_filters('joyvia_exp_label', $opyt), 'label' => 'опыт'];
        }
        
        if (!empty($stats)): ?>
            <section class="section section--author-stats">
                <div class="author-stats">
                    <?php foreach ($stats as $stat): ?>
                        <div class="author-stats__item">
                            <span class="author-stats__value"><?= esc_html($stat['value']) ?></span>
                            <span class="author-stats__label"><?= esc_html($stat['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php 
        // Таксономии (Теги профиля)
        $event_terms = wp_get_object_terms($post_id, 'event');
        $spec_terms  = wp_get_object_terms($post_id, 'specialization');
        $skill_terms = wp_get_object_terms($post_id, 'skill');

        $sections = [
            ['title' => 'Работает на мероприятиях', 'terms' => $event_terms, 'extra_class' => ' author-tags__list--events'],
            ['title' => 'Специализация', 'terms' => $spec_terms, 'extra_class' => ''],
            ['title' => 'Навыки', 'terms' => $skill_terms, 'extra_class' => '']
        ];

        if (array_filter($sections, fn($s) => !empty($s['terms']) && !is_wp_error($s['terms']))): ?>
            <section class="section section--author-tags">
                <?php foreach ($sections as $section): if (empty($section['terms']) || is_wp_error($section['terms'])) continue; ?>
                    <div class="author-tags__block">
                        <h2 class="author-tags__title"><?= esc_html($section['title']) ?></h2>
                        <div class="author-tags__list<?= esc_attr($section['extra_class']) ?>">
                            <?php foreach ($section['terms'] as $term): ?>
                                <span class="author-tags__tag"><?= esc_html($term->name) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php 
        // Портфолио
        $all_media = [];
        $show_portfolio = get_field('pokazat_portfolio', $post_id) ?: ['Да']; 
        if (in_array('Да', (array)$show_portfolio)) {
            if ($videos = get_field('portfolio_videos', $post_id)) foreach ($videos as $v) $all_media[] = ['type' => 'video', 'data' => $v];
            if ($images = get_field('portfolio', $post_id)) foreach ($images as $i) $all_media[] = ['type' => 'image', 'data' => $i];
        }

        if (!empty($all_media)): ?>
            <section class="section section--author-portfolio">
                <h2>Портфолио</h2>
                <div class="portfolio-grid" id="portfolioGrid">
                    <?php foreach ($all_media as $index => $media): 
                        $is_video = $media['type'] === 'video';
                        $class = $is_video ? 'grid-item grid-item--video' : 'grid-item grid-item--image';
                        $srcp = $is_video ? $media['data']['url'] : ($media['data']['sizes']['medium'] ?? $media['data']['url']);
                        $full_url = $media['data']['url'];
                    ?>
                        <div class="<?= $class ?>" data-index="<?= $index ?>" data-full="<?= esc_url($full_url) ?>">
                            <?php if ($is_video): ?>
                                <video data-srcp="<?= esc_url($srcp) ?>" muted playsinline></video>
                                <div class="play-icon">
                                    <svg width="40" height="40" viewBox="0 0 79 79" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M39.2 78.4C49.5965 78.4 59.5672 74.27 66.9186 66.9186C74.27 59.5672 78.4 49.5965 78.4 39.2C78.4 28.8035 74.27 18.8328 66.9186 11.4814C59.5672 4.12999 49.5965 0 39.2 0C28.8035 0 18.8328 4.12999 11.4814 11.4814C4.12999 18.8328 0 28.8035 0 39.2C0 49.5965 4.12999 59.5672 11.4814 66.9186C18.8328 74.27 28.8035 78.4 39.2 78.4ZM37.0195 25.3232C36.2816 24.8309 35.4238 24.5481 34.5377 24.505C33.6517 24.462 32.7705 24.6603 31.9883 25.0788C31.2062 25.4973 30.5523 26.1203 30.0964 26.8814C29.6406 27.6424 29.3999 28.5129 29.4 29.4V49C29.3999 49.8871 29.6406 50.7576 30.0964 51.5186C30.5523 52.2797 31.2062 52.9027 31.9883 53.3212C32.7705 53.7397 33.6517 53.938 34.5377 53.895C35.4238 53.8519 36.2816 53.5691 37.0195 53.0768L51.7195 43.2768C52.3906 42.8293 52.9408 42.2231 53.3214 41.5119C53.702 40.8007 53.9011 40.0066 53.9011 39.2C53.9011 38.3934 53.702 37.5993 53.3214 36.8881C52.9408 36.1769 52.3906 35.5707 51.7195 35.1232L37.0195 25.3232Z" fill="white"/>
                                    </svg>
                                </div>
                            <?php else: ?>
                                <img data-srcp="<?= esc_url($srcp) ?>" alt="Портфолио">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="portfolio-popup" id="portfolioPopup">
                    <div class="portfolio-popup__content">
                        <div class="portfolio-popup__author-card">
                            <?php if ($avatarka && isset($avatarka['sizes']['medium'])): ?><img src="<?php echo esc_url($avatarka['sizes']['medium']); ?>" alt="Фото пользователя" width="<?php echo esc_attr($avatarka['sizes']['medium-width']); ?>" height="<?php echo esc_attr($avatarka['sizes']['medium-height']); ?>"><?php else: ?><img src="<?php echo esc_url($fallback); ?>" alt="Фото пользователя"><?php endif; ?>
                            <div>
                                <div class="portfolio-popup__author-name"><?php echo esc_html($first_name . ' ' . $last_name); ?></div>
                                <div class="portfolio-popup__author-rate">
                                    <span class="author-card__rating-icon" style="color:#ffc107;">★</span>
                                    <span class="author-card__rating-score"><?php echo esc_html($rejting); ?></span>
                                    <span class="author-card__rating-count">(<?php echo esc_html($kolvooczenok); ?> оценок)</span>
                                </div>
                            </div>
                        </div>
                        <button class="portfolio-popup__close" aria-label="Закрыть">&times;</button>
                        <div class="portfolio-popup__counter"><span id="popupCurrent">1</span> / <span id="popupTotal">0</span></div>
                        <button class="portfolio-popup__arrow portfolio-popup__arrow--prev"><span>&#10094;</span></button>
                        <div class="portfolio-popup__slider"></div>
                        <button class="portfolio-popup__arrow portfolio-popup__arrow--next"><span>&#10095;</span></button>
                        <div class="portfolio-popup__dots"></div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const gridItems = document.querySelectorAll('.portfolio-grid .grid-item');
                    let maxCells = 8;

                    function renderGrid() {
                        let cells = 0, hiddenCount = 0, lastVisibleIndex = -1;
                        gridItems.forEach((item, idx) => {
                            let cost = item.classList.contains('grid-item--video') ? 4 : 1;
                            let overlay = item.querySelector('.show-more-overlay');
                            if(overlay) overlay.remove();

                            if (cells + cost <= maxCells) {
                                item.style.display = 'block';
                                cells += cost;
                                lastVisibleIndex = idx;
                                let media = item.querySelector('img, video');
                                if (media && media.dataset.srcp && !media.src) media.src = media.dataset.srcp;
                            } else {
                                item.style.display = 'none';
                                hiddenCount++;
                            }
                        });

                        if (hiddenCount > 0 && lastVisibleIndex !== -1) {
                            let overlay = document.createElement('div');
                            overlay.className = 'show-more-overlay';
                            overlay.textContent = `+ ${hiddenCount} фото`;
                            overlay.onclick = function(e) { e.stopPropagation(); maxCells += 8; renderGrid(); };
                            gridItems[lastVisibleIndex].appendChild(overlay);
                        }
                    }
                    if (gridItems.length > 0) renderGrid();

                    const popup = document.getElementById('portfolioPopup');
                    if (!popup) return;
                    const popupSlider = popup.querySelector('.portfolio-popup__slider'), dotsContainer = popup.querySelector('.portfolio-popup__dots');
                    const counterCurrent = document.getElementById('popupCurrent'), counterTotal = document.getElementById('popupTotal');
                    let currentIndex = 0;
                    const popupMedia = [], dots = [];

                    gridItems.forEach((item, index) => {
                        const isVideo = item.classList.contains('grid-item--video');
                        const sourceMedia = item.querySelector('img, video');
                        const fullUrl = item.dataset.full || (sourceMedia ? sourceMedia.dataset.srcp : '');

                        let mediaEl = document.createElement(isVideo ? 'video' : 'img');
                        mediaEl.dataset.src = fullUrl;
                        mediaEl.className = 'popup-media-item';
                        if (isVideo) { mediaEl.controls = true; mediaEl.playsInline = true; }
                        if (index === 0) mediaEl.classList.add('active');
                        
                        popupSlider.appendChild(mediaEl);
                        popupMedia.push(mediaEl);
                        item.addEventListener('click', () => openPopup(index));

                        const dot = document.createElement('span');
                        dot.className = 'portfolio-popup__dot' + (index === 0 ? ' active' : '');
                        dot.addEventListener('click', () => { currentIndex = index; updatePopup(); });
                        dotsContainer.appendChild(dot);
                        dots.push(dot);
                    });

                    if (counterTotal) counterTotal.textContent = popupMedia.length;

                    function loadImage(index) {
                        if (!popupMedia[index]) return;
                        const media = popupMedia[index];
                        if (!media.src && media.dataset.src) media.src = media.dataset.src;
                    }

                    function openPopup(index) {
                        currentIndex = index; loadImage(currentIndex); updatePopup();
                        popup.classList.add('active'); document.body.style.overflow = 'hidden';
                    }

                    function closePopup() {
                        popup.classList.remove('active'); document.body.style.overflow = '';
                        popupMedia.forEach(media => { if (media.tagName === 'VIDEO') media.pause(); });
                    }

                    function updatePopup() {
                        popupMedia.forEach((media, i) => {
                            media.classList.toggle('active', i === currentIndex);
                            if (i === currentIndex && media.tagName === 'VIDEO') media.play();
                            else if (media.tagName === 'VIDEO') media.pause();
                        });
                        dots.forEach((dot, i) => dot.classList.toggle('active', i === currentIndex));
                        if (counterCurrent) counterCurrent.textContent = currentIndex + 1;
                        loadImage(currentIndex);
                        if (currentIndex + 1 < popupMedia.length) loadImage(currentIndex + 1);
                        if (currentIndex - 1 >= 0) loadImage(currentIndex - 1);
                    }

                    popup.querySelector('.portfolio-popup__close').addEventListener('click', closePopup);
                    popup.querySelector('.portfolio-popup__arrow--prev').addEventListener('click', () => { currentIndex = (currentIndex - 1 + popupMedia.length) % popupMedia.length; updatePopup(); });
                    popup.querySelector('.portfolio-popup__arrow--next').addEventListener('click', () => { currentIndex = (currentIndex + 1) % popupMedia.length; updatePopup(); });

                    document.addEventListener('keydown', e => {
                        if (popup.classList.contains('active')) {
                            if (e.key === 'Escape') closePopup();
                            if (e.key === 'ArrowLeft') { currentIndex = (currentIndex - 1 + popupMedia.length) % popupMedia.length; updatePopup(); }
                            if (e.key === 'ArrowRight') { currentIndex = (currentIndex + 1) % popupMedia.length; updatePopup(); }
                        }
                    });
                });
                </script>
            </section>
        <?php endif; ?>

        <?php if (!empty($o_sebe)): ?>
            <section class="section section--author-moreinfo" id="moreinfo">
                <h2>Описание</h2>
                <div><?= wp_kses($o_sebe, ['strong' => [], 'em' => [], 'ul' => [], 'ol' => [], 'li' => [], 'br' => [], 'p' => []]) ?></div>
                <span class="section--author-opentxt nopc-notablet">Читать ещё</span>
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const toggleBtn = document.querySelector(".section--author-opentxt"), content = document.querySelector(".section--author-moreinfo > div");
                    if (toggleBtn && content) {
                        toggleBtn.addEventListener("click", function() {
                            content.classList.toggle("go-open");
                            toggleBtn.textContent = content.classList.contains("go-open") ? "Скрыть" : "Читать ещё";
                        });
                    }
                });
                </script>
            </section>
        <?php endif; ?>

        <?php if (!empty($service_packages)): $total = count($service_packages); ?>
            <section class="section section--author-uslugi" id="uslugi">
                <h2>Пакеты услуг</h2>
                <div class="uslugi-list">
                    <?php foreach ($service_packages as $i => $pkg): 
                        $nazvanie = $pkg['name'];
                        $opisanie_lines = preg_split('/\r\n|\r|\n/', trim($pkg['desc']));
                        $short_text = count($opisanie_lines) >= 4 
                            ? implode("<br>", array_slice($opisanie_lines, 0, 3)) . '...' 
                            : implode("<br>", $opisanie_lines);
                        
                        $pkg_tags = [];
                        if (!empty($pkg['parent_id'])) {
                            $term = get_term($pkg['parent_id']);
                            if ($term && !is_wp_error($term)) $pkg_tags[] = $term->name;
                        }
                    ?>
                        <div class="usluga-item <?= ($i >= 3) ? 'hidden-usluga' : '' ?>">
                            <div class="usluga-header">
                                <h3><?= esc_html($nazvanie) ?></h3>
                                <span class="usluga-price">от <?= number_format((float)$pkg['price'], 0, '', ' ') ?> ₽</span>
                            </div>
                            <div class="usluga-opisanie">
                                <p class="opisanie-short"><?= $short_text ?></p>
                                <?php if (count($opisanie_lines) >= 4): ?>
                                    <p class="opisanie-full" style="display:none;"><?= nl2br(esc_html(trim($pkg['desc']))) ?></p>
                                    <button class="toggle-opisanie">Читать ещё</button>
                                <?php endif; ?>
                                <?php if (!empty($pkg_tags)): ?>
                                    <div class="usluga-tags author-tags__list" style="margin-top:15px; gap:8px;">
                                        <?php foreach (array_unique($pkg_tags) as $tag_name): ?>
                                            <span class="author-tags__tag"><?= esc_html($tag_name) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($total > 3): ?><button class="show-more">Ещё <?= $total - 3 ?> услуг</button><?php endif; ?>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const section = document.querySelector('.section--author-uslugi');
                    if (!section) return;
                    section.addEventListener('click', function(e) {
                        if (e.target.classList.contains('toggle-opisanie')) {
                            let parent = e.target.closest('.usluga-opisanie');
                            if (!parent) return;
                            let shortText = parent.querySelector('.opisanie-short'), fullText = parent.querySelector('.opisanie-full');
                            if (fullText && (fullText.style.display === 'none' || fullText.style.display === '')) {
                                fullText.style.display = 'block'; shortText.style.display = 'none'; e.target.textContent = 'Скрыть';
                            } else if (fullText) {
                                fullText.style.display = 'none'; shortText.style.display = 'block'; e.target.textContent = 'Читать ещё';
                            }
                        }
                        if (e.target.classList.contains('show-more')) {
                            let hiddenItems = section.querySelectorAll('.hidden-usluga');
                            if (e.target.classList.contains('hidemore')) {
                                hiddenItems.forEach(el => el.style.display = 'none');
                                e.target.textContent = 'Ещё ' + hiddenItems.length + ' услуг';
                                e.target.classList.remove('hidemore');
                            } else {
                                hiddenItems.forEach(el => el.style.display = 'block');
                                e.target.textContent = 'Скрыть';
                                e.target.classList.add('hidemore');
                            }
                        }
                    });
                });
                </script>
            </section>
        <?php endif; ?>

        <?php
$comments = get_comments([
    'post_id' => get_option('reviews_post_id'), 
    'status' => 'approve', 
    'type' => 'review', 
    'meta_query' => [['key' => 'author_id', 'value' => $user_id, 'compare' => '=']], 
    'orderby' => 'date', 
    'order' => 'DESC'
]);
?>
<section class="section section--author-reviews" id="reviews">
    <h2>Отзывы (<?= count($comments) ?>)</h2>
    <?php if (!empty($comments)): ?>
        <div class="reviews-list">
            <?php foreach ($comments as $index => $comment): $rating = get_comment_meta($comment->comment_ID, 'rating', true); ?>
                <div class="review-item" itemprop="review" itemscope itemtype="https://schema.org/Review" <?= $index >= 4 ? 'style="display: none;"' : '' ?>>
                    <div class="review-header">
                        <div class="reviewer-info">
                            <span class="reviewer-name" itemprop="author" itemscope itemtype="https://schema.org/Person">
                                <span itemprop="name"><?= esc_html($comment->comment_author) ?></span>
                            </span>
                            <span class="review-date">
                                <meta itemprop="datePublished" content="<?= esc_attr(mysql2date('c', $comment->comment_date)) ?>">
                                <?= esc_html(mysql2date('d.m.Y', $comment->comment_date)) ?>
                            </span>
                        </div>
                        <div class="review-rating" itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                            <meta itemprop="ratingValue" content="<?= esc_attr($rating) ?>">
                            <meta itemprop="bestRating" content="5">
                            <meta itemprop="worstRating" content="1">
                            <?php for ($i = 1; $i <= 5; $i++): ?><span class="star <?= $i <= $rating ? 'filled' : '' ?>">★</span><?php endfor; ?>
                        </div>
                    </div>
                    <div class="review-text" itemprop="reviewBody"><?= nl2br(esc_html($comment->comment_content)) ?></div>
                    <span class="review-expand-btn" onclick="this.previousElementSibling.classList.add('opentextreview'); this.remove();">Раскрыть текст</span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($comments) > 4): ?>
            <button type="button" class="show-all-reviews-btn" onclick="document.querySelectorAll('#reviews .review-item').forEach(function(el) { el.style.display = ''; }); this.remove();">Все отзывы</button>
        <?php endif; ?>
    <?php else: ?>
        <p class="no-reviews">Пока нет отзывов. Будьте первыми!</p>
    <?php endif; ?>

            
            <!-- Форма отзыва -->
            <div class="review-form-container">
                <h3>Оставить отзыв</h3>
                <div id="review-message" class="review-message" style="display: none;"></div>
                <form class="review-form" id="review-form">
                    <input type="hidden" name="action" value="submit_review">
                    <input type="hidden" name="author_id" value="<?= $user_id ?>">
                    <?php wp_nonce_field('submit_review_nonce', 'review_nonce'); ?>
                    <div class="form-row">
                        <div class="form-field"><label for="reviewer_name">Имя *</label><input type="text" id="reviewer_name" name="reviewer_name" required maxlength="100"></div>
                        <div class="form-field"><label for="reviewer_email">E-mail *</label><input type="email" id="reviewer_email" name="reviewer_email" required></div>
                    </div>
                    <div class="form-field rating-block">
                        <label for="rating">Рейтинг *</label>
                        <div class="rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>"><label for="star<?= $i ?>" title="<?= $i ?> звезд">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="review_text">Отзыв *</label>
                        <textarea id="review_text" name="review_text" rows="5" maxlength="500" required placeholder="Расскажите о вашем опыте работы с исполнителем..."></textarea>
                        <div class="char-counter"><span id="char-count">0</span>/500 символов</div>
                    </div>
                    <div class="form-field"><div class="g-recaptcha" data-sitekey="6LcpBLgrAAAAAKlx4zTjeFZa5cLmWcNprkbIQ7Sh"></div></div>
                    <button type="submit" class="submit-review-btn"><span class="btn-text">Отправить отзыв</span><span class="btn-loader" style="display: none;">Отправка...</span></button>
                </form>
            </div>
        </section>

        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('review_text'), charCount = document.getElementById('char-count');
            if (textarea && charCount) {
                textarea.addEventListener('input', function() {
                    const count = this.value.length; charCount.textContent = count;
                    charCount.style.color = count > 450 ? '#dc3545' : (count > 400 ? '#ffc107' : '#666');
                });
            }
            const reviewForm = document.getElementById('review-form'), messageDiv = document.getElementById('review-message');
            const submitBtn = document.querySelector('.submit-review-btn'), btnText = document.querySelector('.btn-text'), btnLoader = document.querySelector('.btn-loader');
            
            function showMessage(text, type) {
                messageDiv.textContent = text; messageDiv.className = 'review-message ' + type; messageDiv.style.display = 'block'; messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (type === 'success') setTimeout(() => messageDiv.style.display = 'none', 5000);
            }

            if (reviewForm) {
                reviewForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!document.getElementById('reviewer_name').value.trim() || !document.getElementById('reviewer_email').value.trim() || !document.getElementById('review_text').value.trim()) return showMessage('Пожалуйста, заполните все обязательные поля', 'error');
                    if (!document.querySelector('input[name="rating"]:checked')) return showMessage('Пожалуйста, выберите рейтинг', 'error');
                    if (document.getElementById('review_text').value.trim().length < 10) return showMessage('Отзыв должен содержать не менее 10 символов', 'error');
                    const recaptcha = grecaptcha.getResponse();
                    if (!recaptcha) return showMessage('Пожалуйста, подтвердите, что вы не робот', 'error');
                    
                    submitBtn.disabled = true; btnText.style.display = 'none'; btnLoader.style.display = 'inline';
                    const formData = new FormData(reviewForm); formData.append('g-recaptcha-response', recaptcha);
                    
                    fetch('<?= admin_url('admin-ajax.php') ?>', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) { showMessage('Спасибо за отзыв! Он будет опубликован после модерации.', 'success'); reviewForm.reset(); grecaptcha.reset(); charCount.textContent = '0'; }
                        else showMessage(data.data || 'Произошла ошибка при отправке отзыва', 'error');
                    })
                    .catch(() => showMessage('Произошла ошибка при отправке отзыва', 'error'))
                    .finally(() => { submitBtn.disabled = false; btnText.style.display = 'inline'; btnLoader.style.display = 'none'; });
                });
            }
        });
        </script>

        <?php
        // Похожие исполнители (Адаптировано под CPT performer)
        $prof_ids = wp_get_object_terms($post_id, 'profession', ['fields' => 'ids']);
        if (!empty($prof_ids) && !is_wp_error($prof_ids)) {
            $similar_query = new WP_Query([
                'post_type'      => 'performer',
                'post_status'    => 'publish',
                'posts_per_page' => 4,
                'post__not_in'   => [$post_id],
                'orderby'        => 'rand',
                'tax_query'      => [
                    [
                        'taxonomy' => 'profession',
                        'field'    => 'term_id',
                        'terms'    => $prof_ids
                    ]
                ]
            ]);

            if ($similar_query->have_posts()): ?>
                <section class="section section--author-other" itemscope itemtype="https://schema.org/ItemList">
                    <meta itemprop="name" content="Похожие исполнители">
                    <h2>Похожие исполнители</h2>
                    <div class="authors-grid" id="similar-authors-grid">
                        <?php 
                        while ($similar_query->have_posts()) {
                            $similar_query->the_post();
                            set_query_var('executor', $post);
                            get_template_part('template/executor');
                        }
                        wp_reset_postdata();
                        ?>
                    </div>
                </section>
            <?php endif; 
        }
        ?>
    </div>

    <!-- Правая панель (Sticky price) -->
    <?php if (!empty($service_packages)): ?>
        <section class="section section--author-pricemore">
            <div class="pricemore">
                <?php if ($min_price !== null): ?>
                    <div class="pricemore__minprice">от <?= number_format($min_price, 0, '', ' ') ?> ₽</div>
                <?php endif; ?>
                <div class="pricemore__list">
                    <?php foreach ($packages_top as $pkg): ?>
                        <div class="pricemore__item">
                            <span class="pricemore__name"><?= esc_html($pkg['name']) ?></span>
                            <span class="pricemore__price">от <?= number_format((float)$pkg['price'], 0, '', ' ') ?> ₽</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="pricemore__more"><a href="#uslugi">Подробнее о пакетах услуг</a></div>
				<div class="pricemore__btn"><button class="zayavka" onclick="openPopup('zayavka')">Оставить заявку</button> <div class="add-favorite" data-id="<?= esc_attr($post_id) ?>"></div></div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php joyvia_schema_render_performer($post_id); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const favBtn = document.querySelector('.add-favorite');
    if (!favBtn) return;
    const postId = favBtn.dataset.id;
    let favs = JSON.parse(localStorage.getItem('joyvia_favorites') || '[]');
    if (favs.includes(postId)) {
        favBtn.classList.add('ready');
    }
    favBtn.addEventListener('click', () => {
        favs = JSON.parse(localStorage.getItem('joyvia_favorites') || '[]');
        const index = favs.indexOf(postId);
        if (index > -1) {
            favs.splice(index, 1);
            favBtn.classList.remove('ready');
        } else {
            favs.push(postId);
            favBtn.classList.add('ready');
        }
        localStorage.setItem('joyvia_favorites', JSON.stringify(favs));
    });
});
</script>

<?php get_footer(); ?>