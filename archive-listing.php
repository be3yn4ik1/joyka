<?php /* Template Name: Шаблон Архив исполнителей */ get_header(); ?>

<section class="section section--archivepage-top">
    <?php if ( is_shop() ) : 
        $shop_page_id = get_option('woocommerce_shop_page_id');
        $h1 = get_field('h1', $shop_page_id);
        if ( $h1 ) : ?>
            <h1 class="archivepage-title"><?php echo esc_html( $h1 ); ?></h1>
        <?php else : ?>
            <h1 class="archivepage-title"><?php woocommerce_page_title(); ?></h1>
        <?php endif; ?>

    <?php elseif ( is_tax( 'product_cat' ) ) : 
        $term = get_queried_object();
        $h1 = get_field('h1', $term);
        if ( $h1 ) : ?>
            <h1 class="archivepage-title"><?php echo esc_html( $h1 ); ?></h1>
        <?php else : ?>
            <h1 class="archivepage-title"><?php echo esc_html( $term->name ); ?></h1>
        <?php endif; ?>

    <?php else : ?>
        <h1 class="archivepage-title"><?php the_archive_title(); ?></h1>
    <?php endif; ?>

    <?php
    if ( is_shop() ) {
        $shop_page_id = isset( $shop_page_id ) ? $shop_page_id : get_option('woocommerce_shop_page_id');
        $tekst_pod_h1 = get_field( 'tekst_pod_h1', $shop_page_id );
    } elseif ( is_tax( 'product_cat' ) ) {
        $term = isset( $term ) ? $term : get_queried_object();
        $tekst_pod_h1 = get_field( 'tekst_pod_h1', $term );
    } else {
        $tekst_pod_h1 = false;
    }

    if ( $tekst_pod_h1 ) : ?>
        <p class="archivepage-description"><?php echo esc_html( $tekst_pod_h1 ); ?></p>
    <?php else : ?>
        <p class="archivepage-description">Лучшие профессионалы по выгодным ценам</p>
    <?php endif; ?>






    <div class="section-filter__filter-mobile"> <div class="section-filter__filter-mobile-btn"><p>Фильтр</p><span></span></div>   <span class="sbros">Сбросить</span> </div>
    <div class="section-filter__filter-block">
        <div class="category-filter">
            <p>Выберите специализацию</p><span class="reset"></span>
            <?php 
            $parent_categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0 ) );
            if ( ! empty( $parent_categories ) && ! is_wp_error( $parent_categories ) ) { 
                echo '<div class="category-filter--select">'; 
                foreach ( $parent_categories as $category ) {   echo '<div data-id="' . esc_attr( $category->slug ) . '">' . esc_html( $category->name ) . '</div>'; } 
                echo '</div>'; 
            } 
            ?>			
        </div>
        <div class="gorod-filter">
            <p>Выберите город</p><span class="reset"></span>
            <?php 
            $cities = get_terms( array( 'taxonomy' => 'city', 'hide_empty' => false, 'parent' => 0 ) ); 
            if ( ! empty( $cities ) && ! is_wp_error( $cities ) ) { 
                echo '<div class="gorod-filter--select">'; 
                foreach ( $cities as $city ) { echo '<div data-id="' . esc_attr( $city->slug ) . '">' . esc_html( $city->name ) . '</div>';  }
                echo '</div>'; 
            } 
            ?>
        </div>
        <div class="data-filter"> <p>Дата</p><span class="reset"></span> <div class="data-filter--select"></div> </div>
        <div class="price-range"> <p>Цена, ₽</p>
            <label class="price-range__label">
                <input class="price-range__input price-range__input--from" type="number" aria-label="Минимальная цена" placeholder="1000">
                <span class="price-range__text">От</span>
            </label>
            <label class="price-range__label">
                <input class="price-range__input price-range__input--to" type="number" aria-label="Максимальная цена" placeholder="300 000">
                <span class="price-range__text">До</span>
            </label>
        </div>
        <span class="sbros">Сбросить</span>
        <a href="#" class="go-filter">Найти</a>
    </div>
</section>

<?php
// Проверяем, есть ли текущая категория
$price_from = isset($_GET['price_from']) ? intval($_GET['price_from']) : 0;
$price_to = isset($_GET['price_to']) ? intval($_GET['price_to']) : 0;

// Проверяем, есть ли текущая категория
$current_term_id = 0;
if ( is_tax( 'product_cat' ) ) {
    $term = get_queried_object();
    $current_term_id = $term->term_id;
    $meta_query = array(
        array(
            'key'     => 'osnovnoe_napravlenie',
            'value'   => '"' . $current_term_id . '"',
            'compare' => 'LIKE',
        )
    );
} else {
    $meta_query = array();
}

$current_user_id = get_current_user_id();
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$users_per_page = 12;

// Базовые аргументы запроса пользователей
$base_args = array(
    'exclude' => array($current_user_id),
    'role__not_in' => array('Administrator'),
    'orderby' => 'meta_value_num',
    'meta_key' => 'rejting',
    'order' => 'DESC',
    'meta_query' => $meta_query
);

// Если есть фильтр по цене, получаем всех пользователей и фильтруем их
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
    $total_users = count($filtered_users);
    $users = array_slice($filtered_users, ($paged - 1) * $users_per_page, $users_per_page);
    
} else {
    $args = array_merge($base_args, array(
        'number' => $users_per_page,
        'offset' => ($paged - 1) * $users_per_page
    ));
    
    $users = get_users($args);
    $total_users = count(get_users($base_args));
}
?>

<section class="section section--archivepage-authors">
    <div class="authors-grid" id="authors-grid"
         data-term-id="<?php echo esc_attr($current_term_id); ?>"
         data-users-per-page="<?php echo esc_attr($users_per_page); ?>"
         itemprop="mainEntity" itemscope itemtype="https://schema.org/ItemList">    
        <?php $list_name = isset($h1) && $h1 ? $h1 : get_the_archive_title();  ?>
        <meta itemprop="name" content="<?php echo esc_attr( strip_tags($list_name) ); ?>">
        <?php
        if (!empty($users)) {
            $item_position = 0; 
foreach ($users as $user) {
    $item_position++; 
    set_query_var('executor', $user);
    set_query_var('item_position', $item_position); 
    set_query_var('current_term_id', $current_term_id); 
    get_template_part('template/executor');
}
        } else { echo '<p>Исполнители не найдены в этой категории.</p>';  }
        ?>
    </div>
    <div class="pagination" id="pagination">
        <?php
        $max_pages = ceil($total_users / $users_per_page);
        if ($max_pages > 1) {
            echo '<div class="pagination-links">';
            for ($i = 1; $i <= $max_pages; $i++) {
                if ($i === $paged) {
                    echo '<span class="page-numbers current" aria-current="page" aria-label="Стр. ' . $i . '">' . $i . '</span>';
                } else {
                    echo '<a class="page-numbers" href="#" data-page="' . $i . '" aria-label="Стр. ' . $i . '">' . $i . '</a>';
                }
            }
            if ($paged > 1) {
                echo '<a class="page-numbers prev" href="#" data-page="' . ($paged - 1) . '" aria-label="Предыдущая страница">«</a>';
            }
            if ($paged < $max_pages) {
                echo '<a class="page-numbers next" href="#" data-page="' . ($paged + 1) . '" aria-label="Следующая страница">»</a>';
            }
            echo '</div>';
        }
        ?>
        <div class="pagination-count">
            <?php
            $current_count = min($paged * $users_per_page, $total_users);
            echo "$current_count из $total_users";
            ?>
        </div>
    </div>
</section>

<?php
if ( is_shop() ) :
    $content = apply_filters( 'the_content', get_post_field( 'post_content', wc_get_page_id( 'shop' ) ) );
    $content = preg_replace( '/<h1\b([^>]*)>(.*?)<\/h1>/is', '<h2$1>$2</h2>', $content );
?>
    <section class="section section--description"> 
		<div class="container">
            <?php echo $content; ?>
		</div>
    </section>
<?php elseif ( is_tax( 'product_cat' ) && term_description() ) :
    $term_description = term_description();
    $term_description = preg_replace( '/<h1\b([^>]*)>(.*?)<\/h1>/is', '<h2$1>$2</h2>', $term_description );
?>
    <section class="section section--description">
		<div class="container">
        <?php echo $term_description; ?>
        </div> 
    </section>
<?php endif; ?>


<script>
// Обновленный JavaScript для пагинации с поддержкой фильтра по цене
document.addEventListener('DOMContentLoaded', function() {
    const authorsGrid = document.querySelector('#authors-grid');
    const termId = authorsGrid.getAttribute('data-term-id');
    const usersPerPage = parseInt(authorsGrid.getAttribute('data-users-per-page'));
    const priceFromInput = document.querySelector('.price-range__input--from');
    const priceToInput = document.querySelector('.price-range__input--to');
    
    // Формируем meta_query на основе текущей категории
    let metaQuery = [];
    if (termId && termId !== '0') {
        metaQuery = [{
            'key': 'osnovnoe_napravlenie',
            'value': '"' + termId + '"',
            'compare': 'LIKE'
        }];
    }
    
    const paginationLinks = document.querySelectorAll('#pagination .pagination-links a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = parseInt(this.getAttribute('data-page'));
            loadAuthorsPage(page, usersPerPage, metaQuery);
        });
    });
    
    function loadAuthorsPage(page, usersPerPage, metaQuery) {
        const offset = (page - 1) * usersPerPage;
        const priceFrom = priceFromInput ? priceFromInput.value.trim() : '';
        const priceTo = priceToInput ? priceToInput.value.trim() : '';
        
        const formData = new URLSearchParams({
            action: 'load_more_authors',
            offset: offset,
            meta_query: JSON.stringify(metaQuery)
        });
        
        // Добавляем параметры цены, если они заданы
        if (priceFrom) {
            formData.append('price_from', priceFrom);
        }
        if (priceTo) {
            formData.append('price_to', priceTo);
        }
        
        fetch(load_more_authors_obj.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.html) {
                document.querySelector('#authors-grid').innerHTML = data.html;
                document.querySelector('.pagination-count').innerText = Math.min(page * usersPerPage, data.total) + ' из ' + data.total;
                updatePaginationLinks(page, Math.ceil(data.total / usersPerPage), usersPerPage, metaQuery);
                // Плавная прокрутка к #authors-grid с отступом 40px
                const authorsGrid = document.querySelector('#authors-grid');
                const offset = authorsGrid.getBoundingClientRect().top + window.pageYOffset - 40;
                window.scrollTo({ top: offset, behavior: 'smooth' });
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    function updatePaginationLinks(currentPage, maxPages, usersPerPage, metaQuery) {
        const paginationContainer = document.querySelector('.pagination-links');
        paginationContainer.innerHTML = '';
        let links = '';
        
        // Предыдущая страница
        if (currentPage > 1) {
            links += `<a class="page-numbers prev" href="#" data-page="${currentPage - 1}" aria-label="Предыдущая страница">«</a>`;
        }
        
        // Номера страниц
        for (let i = 1; i <= maxPages; i++) {
            if (i === currentPage) {
                links += `<span class="page-numbers current" aria-current="page" aria-label="Стр. ${i}">${i}</span>`;
            } else {
                links += `<a class="page-numbers" href="#" data-page="${i}" aria-label="Стр. ${i}">${i}</a>`;
            }
        }
        
        // Следующая страница
        if (currentPage < maxPages) {  
            links += `<a class="page-numbers next" href="#" data-page="${parseInt(currentPage) + 1}" aria-label="Следующая страница">»</a>`;   
        }
        
        paginationContainer.innerHTML = links;
        
        // Перепривязываем обработчики событий
        document.querySelectorAll('#pagination .pagination-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.getAttribute('data-page'));
                loadAuthorsPage(page, usersPerPage, metaQuery);
            });
        });
    }
});
</script>

<?php get_footer();