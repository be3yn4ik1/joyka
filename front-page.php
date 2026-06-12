<?php
/* Template Name: Главная */

if (!defined('ABSPATH')) { exit; }

$stats_query = new WP_Query([
    'post_type'      => 'performer',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
]);

$count_performers = count($stats_query->posts);
$count_reviews = 0;
$count_rating_total = 0;
$count_rating = 0.0;
$performers_with_rating = 0;

if ($count_performers > 0) {
    foreach ($stats_query->posts as $pid) {
        $count_reviews += (int) get_post_meta($pid, 'kol-vo_oczenok', true);
        $current_rating = (float) get_post_meta($pid, 'rejting', true);
        
        if ($current_rating > 0) {
            $count_rating_total += $current_rating;
            $performers_with_rating++;
        }
    }
    
    if ($performers_with_rating > 0) {
        $count_rating = round($count_rating_total / $performers_with_rating, 1);
    }
}

get_header();

if (function_exists('joyvia_schema_render_homepage')) {
    joyvia_schema_render_homepage();
}

$catalog_pages = get_posts([
    'post_type'      => 'catalog_page',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'ASC',
]);

$prof_urls = [];
$event_urls = [];
$ordered_professions = [];
$ordered_events = [];

foreach ($catalog_pages as $cp) {
    $type = get_field('page_type', $cp->ID);
    $url = get_post_meta($cp->ID, 'generated_url', true);
    
    if ($type === 'prof_only') {
        $tid = get_field('catalog_profession', $cp->ID);
        if ($tid) {
            $prof_urls[$tid] = $url;
            $term = get_term($tid, 'profession');
            if ($term && !is_wp_error($term)) {
                $ordered_professions[] = [
                    'term' => $term,
                    'url'  => $url,
                ];
            }
        }
    } elseif ($type === 'hub_event') {
        $tid = get_field('catalog_event', $cp->ID);
        if ($tid) {
            $event_urls[$tid] = $url;
            $term = get_term($tid, 'event');
            if ($term && !is_wp_error($term)) {
                $ordered_events[] = [
                    'term' => $term,
                    'url'  => $url,
                ];
            }
        }
    }
}
?>

<section class="section section--mainpage-filter">
    <div class="section--mainpage-filter__h1content">
        <h1>Подбор исполнителей для мероприятий: <span>фото, видео, ведущие и не только</span></h1>
        <p>Фотографы, видеографы, ведущие — быстро, удобно, надёжно</p>
    </div>

    <div class="section-filter__filter-block">
        <div class="category-filter"><p>Выберите специализацию</p><span class="reset"></span>
            <?php
            $professions = get_terms([
                'taxonomy'   => 'profession',
                'hide_empty' => false,
                'parent'     => 0,
            ]);
            if (!empty($professions) && !is_wp_error($professions)) {
                echo '<div class="category-filter--select">';
                foreach ($professions as $profession) { 
                    if (isset($prof_urls[$profession->term_id])) {
                        echo '<div data-id="' . esc_attr($profession->slug) . '">' . esc_html($profession->name) . '</div>';  
                    }
                }
                echo '</div>';
            }
            ?>
        </div>
        <div class="gorod-filter"><p>Выберите город</p><span class="reset"></span>
            <?php  
            $cities = get_terms([ 
                'taxonomy'   => 'city', 
                'hide_empty' => false, 
                'parent'     => 0,  
            ]);
            if (!empty($cities) && !is_wp_error($cities)) {
                echo '<div class="gorod-filter--select">';
                foreach ($cities as $city) {  
                    echo '<div data-id="' . esc_attr($city->slug) . '">' . esc_html($city->name) . '</div>';  
                }
                echo '</div>';
            }
            ?>
        </div>
        <div class="data-filter"><p>Дата</p><span class="reset"></span><div class="data-filter--select"></div></div>
        <a href="#" class="go-filter">Найти</a>
    </div>

    <div class="catalog-counters-box">
        <div class="catalog-counters-box__item">
            <div class="catalog-counters-box__value"><span class="total-count-mobile"><?= esc_html($count_performers) ?></span><p>+</p></div>
            <div class="catalog-counters-box__label">исполнителей</div>
        </div>
        <div class="catalog-counters-box__divider"></div>
        <div class="catalog-counters-box__item">
            <div class="catalog-counters-box__value"><?= esc_html($count_reviews) ?></div>
            <div class="catalog-counters-box__label">отзывов</div>
        </div>
        <div class="catalog-counters-box__divider"></div>
        <div class="catalog-counters-box__item">
            <div class="catalog-counters-box__value starsvg"><?= esc_html(number_format($count_rating, 1, '.', '')) ?></div>
            <div class="catalog-counters-box__label">средний рейтинг</div>
        </div>
    </div>
</section>

<?php
if (!function_exists('joyvia_declension')) {
    function joyvia_declension($number, $titles) {
        $cases = array(2, 0, 1, 1, 1, 2);
        return $titles[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
    }
}
?>

<section class="section section--find">
    <div class="section--find_btns">
        <button class="section--find_btn" onclick="showFindTab('meropriyatie', this)">Выберите мероприятие<span>Знаю тип праздника</span></button>
        <button class="section--find_btn active" onclick="showFindTab('specialist', this)">Выберите специалиста<span>Знаю кого ищу</span></button>
    </div>
    <div class="section--find_meropriyatie" id="meropriyatie" style="display: none;">
        <?php 
        if (!empty($ordered_events)) {
            foreach ($ordered_events as $item) {
                $event = $item['term'];
                $link = home_url('/' . $item['url'] . '/');
                $icon_url = get_field('event_icon', 'event_' . $event->term_id);
                $count = $event->count;
                $count_text = $count . ' ' . joyvia_declension($count, ['исполнитель', 'исполнителя', 'исполнителей']);
                $bg_style = $icon_url ? 'background-image:url(' . esc_url(is_array($icon_url) ? $icon_url['url'] : $icon_url) . ');' : '';
                ?>
                <a href="<?= esc_url($link) ?>" style="<?= $bg_style ?>">
                    <p><?= esc_html($event->name) ?></p>
                    <span><?= esc_html($count_text) ?></span>
                </a>
                <?php
            }
        }
        ?>
    </div>
    <div class="section--find_specialist" id="specialist" style="display: grid;">
        <?php 
        $plural_names = array( 'Фотограф'  => 'Фотографы', 'Видеограф' => 'Видеографы', 'Ведущий'   => 'Ведущие', 'Аниматор'  => 'Аниматоры', 'Фокусник' => 'Фокусники', 'Диджей'    => 'Диджеи', 'Дед Мороз'  => 'Дед Морозы', 'Организатор' => 'Организаторы' );
        
        if (!empty($ordered_professions)) {
            foreach ($ordered_professions as $item) {
                $profession = $item['term'];
                $link = home_url('/' . $item['url'] . '/');
                $icon_url = get_field('profession_icon', 'profession_' . $profession->term_id);
                $count = $profession->count;
                $count_text = $count . ' ' . joyvia_declension($count, ['исполнитель', 'исполнителя', 'исполнителей']);
                $bg_style = $icon_url ? 'background-image:url(' . esc_url(is_array($icon_url) ? $icon_url['url'] : $icon_url) . ');' : '';
                $display_name = isset($plural_names[$profession->name]) ? $plural_names[$profession->name] : $profession->name;
                ?>
                <a href="<?= esc_url($link) ?>" style="<?= $bg_style ?>">
                    <p><?= esc_html($display_name) ?></p>
                    <span><?= esc_html($count_text) ?></span>
                </a>
                <?php
            }
        }
        ?>
    </div>
</section>

<script>
function showFindTab(tabId, btn) {
    document.getElementById('meropriyatie').style.display = 'none';
    document.getElementById('specialist').style.display = 'none';
    const btns = btn.closest('.section--find_btns').querySelectorAll('.section--find_btn');
    btns.forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).style.display = 'grid'; 
    btn.classList.add('active');
}
</script>

<?php
$current_user_id = get_current_user_id();
$admin_ids = get_users(array('role' => 'Administrator', 'fields' => 'ID'));
$exclude_authors = array_merge(array($current_user_id), $admin_ids);
$popular_query = new WP_Query(array(
    'post_type'      => 'performer',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'author__not_in' => $exclude_authors,
    'meta_query'     => array(
        array(
            'key'     => 'prioritet',
            'value'   => 5,
            'compare' => '=',
            'type'    => 'NUMERIC'
        )
    ),
    'orderby'        => 'rand'
));

if ($popular_query->have_posts()) : ?>
<section class="section section--mainpage-popular">
    <h2>Популярные исполнители</h2>
    <div class="authors-grid" id="authors-grid">
        <?php 
        foreach ($popular_query->posts as $performer_post) {
            set_query_var('executor', $performer_post); 
            get_template_part('template/executor'); 
        } 
        ?>
    </div>
    <div class="slider-viewport" id="slider-viewport" aria-hidden="true">
        <div class="slider-track" id="slider-track" role="list"></div>
    </div>
    <div class="slider-dots" id="slider-dots" aria-hidden="true"></div>
</section>
<script>
(function(){
const viewport = document.getElementById('slider-viewport');
const track = document.getElementById('slider-track');
const dotsWrap = document.getElementById('slider-dots');
const cards = Array.from(document.querySelectorAll('.authors-grid .executor-card'));
let currentIndex = 0;
let currentTranslate = 0;
let prevTranslate = 0;
let dragging = false;
let slidesCount = cards.length;
function buildSlides(){
    track.innerHTML = '';
    dotsWrap.innerHTML = '';
    cards.forEach((card, idx) => {
        track.appendChild(card);
        const dot = document.createElement('button');
        dot.className = 'slider-dot';
        dot.addEventListener('click', ()=>{ goTo(idx); });
        dotsWrap.appendChild(dot);
    });
    updateDots();
    setPositionByIndex();
    viewport.setAttribute('aria-hidden','false');
    dotsWrap.setAttribute('aria-hidden','false');
}
function updateDots(){  Array.from(dotsWrap.children).forEach((d,i)=> d.classList.toggle('active', i===currentIndex));}
function setPositionByIndex(){
    currentTranslate = - currentIndex * viewport.clientWidth;
    prevTranslate = currentTranslate;
    track.style.transform = `translateX(${currentTranslate}px)`;
    updateDots();
}
function goTo(index){
    currentIndex = Math.max(0, Math.min(index, slidesCount-1));
    track.style.transition = 'transform 350ms cubic-bezier(.22,.9,.2,1)';
    setPositionByIndex();
    track.addEventListener('transitionend', ()=>{track.style.transition=''}, {once:true});
}
function touchStart(e){ startX = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX; dragging = true; track.style.transition = ''; prevTranslate = currentTranslate;}
function touchMove(e){
    if(!dragging) return;
    const clientX = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
    const delta = clientX - startX;
    currentTranslate = prevTranslate + delta;
    track.style.transform = `translateX(${currentTranslate}px)`;
}
function touchEnd(){
    if(!dragging) return;
    dragging = false;
    const movedBy = currentTranslate - prevTranslate;
    if(movedBy < -50 && currentIndex < slidesCount-1) currentIndex++;
    if(movedBy > 50 && currentIndex > 0) currentIndex--;
    goTo(currentIndex);
}
function initSlider(){
    if(window.matchMedia('(max-width:500px)').matches){
        buildSlides();
        track.addEventListener('touchstart', touchStart, {passive:true});
        track.addEventListener('touchmove', touchMove, {passive:true});
        track.addEventListener('touchend', touchEnd);
        track.addEventListener('mousedown', touchStart);
        window.addEventListener('mousemove', touchMove);
        window.addEventListener('mouseup', touchEnd);
        window.addEventListener('resize', ()=>{ setPositionByIndex(); });
    }
}
window.addEventListener('load', initSlider);
window.addEventListener('resize', initSlider);
window.addEventListener('keydown', (e)=>{
    if(window.matchMedia('(max-width:500px)').matches){
        if(e.key==='ArrowLeft') goTo(currentIndex-1);
        if(e.key==='ArrowRight') goTo(currentIndex+1);
    }
});
})();
</script>
<?php 
endif;
wp_reset_postdata(); 
?>

<section class="section section--mainpage-work">
<?php echo do_shortcode( '[kak_my_rabotaem]' ); ?>
</section>

<section class="section section--mainpage-advantages">
  <div class="section--mainpage-advantages-wrap">
    <div class="advantage-card"><div class="advantage-icon"><img src="/wp-content/uploads/2026/04/frame.svg" alt="Проверенные исполнители"></div>
      <div class="advantage-content"><p class="advantage-title">Проверенные исполнители</p><span class="advantage-subtitle">Портфолио и реальные отзывы</span></div>
    </div>
    <div class="advantage-card"><div class="advantage-icon"><img src="/wp-content/uploads/2026/04/frame1.svg" alt="Быстрый ответ"></div>
      <div class="advantage-content"><p class="advantage-title">Быстрый ответ</p><span class="advantage-subtitle">Отвечают в течение нескольких часов</span></div>
    </div>
    <div class="advantage-card"><div class="advantage-icon"><img src="/wp-content/uploads/2026/04/frame2.svg" alt="Безопасно"></div>
      <div class="advantage-content"><p class="advantage-title">Безопасно</p><span class="advantage-subtitle">Платите только после праздника</span></div>
    </div>
  </div>
</section>

<?php if (trim(get_the_content())): ?><section class="section section--description"><?php the_content(); ?></section> <?php endif; ?>

<?php get_footer(); ?>