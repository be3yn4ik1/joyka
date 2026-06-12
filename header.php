<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php esc_attr( bloginfo( 'charset' ) ) ?>" >
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <?php wp_head(); ?>
    <meta name="format-detection" content="telephone=no">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100..900&display=swap" rel="stylesheet">
</head>
<body <?php body_class(); ?>>

<header class="header">
     <div class="header__wrap">
        <div class="header__logo">
			<a href="/"> <svg xmlns="http://www.w3.org/2000/svg" width="144" height="60" fill="none"><g fill="#4C32E1" clip-path="url(#a)"><path d="M19.767 13.381a3.2 3.2 0 0 1 2.639 2.644c.017.102.085.195.186.22a.2.2 0 0 0 .068.01c.025 0 .05 0 .067-.01.102-.025.17-.118.186-.22a3.2 3.2 0 0 1 2.639-2.644c.101-.017.194-.084.22-.186.008-.026.008-.042.008-.068q.002-.04-.008-.068c-.026-.101-.119-.17-.22-.186a3.2 3.2 0 0 1-2.639-2.644c-.017-.102-.084-.195-.186-.22C22.702 10 22.685 10 22.66 10q-.04-.002-.068.008c-.101.026-.17.12-.186.22a3.2 3.2 0 0 1-2.639 2.645c-.101.017-.194.085-.22.186-.008.026-.008.043-.008.068q-.002.04.008.068c.026.093.119.17.22.186M14.921 19.025a2.84 2.84 0 0 1 2.351 2.356.24.24 0 0 0 .161.195.2.2 0 0 0 .068.009c.025 0 .05 0 .067-.009.085-.025.144-.11.161-.195a2.85 2.85 0 0 1 2.351-2.356c.093-.016.178-.076.203-.17.008-.016.008-.041.008-.058s0-.043-.008-.06c-.025-.093-.11-.152-.203-.17a2.85 2.85 0 0 1-2.35-2.355.25.25 0 0 0-.128-.186.2.2 0 0 0-.101-.026.3.3 0 0 0-.093.017.24.24 0 0 0-.136.186 2.84 2.84 0 0 1-2.35 2.356.27.27 0 0 0-.204.161c-.008.026-.008.043-.008.068q0 .051.025.102a.21.21 0 0 0 .186.135M27.379 19.636l.127-.077a.38.38 0 0 0 .017-.745c-.043-.009-.085-.009-.119-.009-.076.009-.27.076-.313.085h-.008c-12.871 5.33-12.6 11.95-12.6 11.95s-.145 3.058 3.128 5.423c3.281 2.28 3.214 4.407 3.214 4.407.059 3.983-9.379 8.059-10.47 8.517a.6.6 0 0 0-.279.22.47.47 0 0 0-.076.246c0 .084.025.152.068.195a.37.37 0 0 0 .287.135h10.647c7.341-4.915 6.512-9.568 6.512-9.568s-.448-3.508-4.99-5.678c-5.26-2.517-4.752-5.686-4.752-5.686.558-4.67 8.414-8.788 9.607-9.415"/><path d="M27.6 19.415s-.11.144-.297.17z"/><path d="M27.658 19.492s-.22.135-.465.237.034-.237.034-.237zM49.556 19.328v14.635q0 3.046-1.756 4.748-1.755 1.673-4.7 1.673-2.977 0-4.821-1.732-1.815-1.733-1.815-4.839h4.166q.03 1.344.624 2.091.625.747 1.786.747 1.131 0 1.725-.717t.595-1.971V19.328zm12.179 21.116q-2.381 0-4.285-1.046a7.83 7.83 0 0 1-3.005-3.016q-1.071-1.941-1.071-4.48t1.1-4.48a7.76 7.76 0 0 1 3.065-2.987q1.935-1.075 4.315-1.075t4.314 1.075a7.56 7.56 0 0 1 3.035 2.987q1.13 1.94 1.13 4.48 0 2.539-1.16 4.48a7.9 7.9 0 0 1-3.094 3.016q-1.935 1.046-4.344 1.046m0-3.644a4.33 4.33 0 0 0 2.112-.538q1.012-.567 1.607-1.672.595-1.106.595-2.688 0-2.36-1.25-3.614-1.219-1.284-3.005-1.284t-3.005 1.284q-1.19 1.254-1.19 3.614t1.16 3.644Q59.95 36.8 61.735 36.8m27.424-13.171L78.955 48H74.52l3.57-8.243-6.605-16.128h4.672l4.255 11.558 4.314-11.558zm9.12 12.693 4.165-12.693h4.433l-6.1 16.546h-5.058l-6.07-16.546h4.464zm13.051-14.665q-1.102 0-1.845-.686-.715-.717-.715-1.763 0-1.044.715-1.732.743-.717 1.845-.717 1.1 0 1.815.717.743.687.743 1.732 0 1.046-.743 1.763-.715.686-1.815.686m2.052 1.972v16.546h-4.165V23.629zm3.045 8.213q0-2.508.982-4.45 1.012-1.941 2.707-2.987 1.727-1.045 3.839-1.045 1.845 0 3.213.747 1.4.746 2.232 1.881v-2.36h4.195v16.547H129.4v-2.42q-.803 1.165-2.232 1.942-1.398.747-3.243.747a7.06 7.06 0 0 1-3.809-1.075q-1.695-1.076-2.707-3.017-.982-1.97-.982-4.51m12.973.06q0-1.524-.595-2.599-.596-1.104-1.607-1.672a4.2 4.2 0 0 0-2.172-.598q-1.161 0-2.142.568-.982.567-1.607 1.673-.595 1.074-.595 2.568t.595 2.628q.625 1.106 1.607 1.703a4.14 4.14 0 0 0 2.142.597q1.161 0 2.172-.567a4.34 4.34 0 0 0 1.607-1.673q.594-1.105.595-2.628"/></g></svg>
			</a>
		 </div>

<div class="pc-none">
<a href="#"class="header_right-block-icon alert-icon">Уведомления</a>
</div>


<input type="checkbox" id="menu-checkbox-mobile" class="menu-checkbox-mobile">
<label for="menu-checkbox-mobile">Меню</label>

<nav class="main-navigation" itemscope itemtype="https://schema.org/SiteNavigationElement">
            <ul class="menu-main">
                <li class="menu-item menu-item--catalog">
                    <a href="/performers/" itemprop="url"><span itemprop="name">Каталог исполнителей</span></a>
                    <div class="menu">
                        <?php
                        $header_professions = get_transient('header_professions_menu');

                        if (false === $header_professions) {
                            $catalog_pages = get_posts([
                                'post_type'      => 'catalog_page',
                                'post_status'    => 'publish',
                                'posts_per_page' => -1,
                                'orderby'        => 'date',
                                'order'          => 'ASC',
                            ]);

                            $header_professions = [];

                            foreach ($catalog_pages as $cp) {
                                $type = get_field('page_type', $cp->ID);
                                if ($type === 'prof_only') {
                                    $tid = get_field('catalog_profession', $cp->ID);
                                    if ($tid) {
                                        $term = get_term($tid, 'profession');
                                        if ($term && !is_wp_error($term)) {
                                            $header_professions[] = [
                                                'id'   => $term->term_id,
                                                'name' => $term->name,
                                                'url'  => get_post_meta($cp->ID, 'generated_url', true),
                                            ];
                                        }
                                    }
                                }
                            }
                            set_transient('header_professions_menu', $header_professions, 12 * HOUR_IN_SECONDS);
                        }

                        if (!empty($header_professions)) {
                            $plural_names = [
                                'Фотограф'    => 'Фотографы',
                                'Видеограф'   => 'Видеографы',
                                'Ведущий'     => 'Ведущие',
                                'Аниматор'    => 'Аниматоры',
                                'Фокусник'    => 'Фокусники',
                                'Диджей'      => 'Диджеи',
                                'Дед Мороз'   => 'Дед Морозы',
                                'Организатор' => 'Организаторы'
                            ];

                            foreach ($header_professions as $item) {
                                $display_name = isset($plural_names[$item['name']]) ? $plural_names[$item['name']] : $item['name'];
                                $link = home_url('/' . ltrim($item['url'], '/') . '/');

                                echo '<div class="menu-item">';
                                echo '<a href="' . esc_url($link) . '" itemprop="url"><span itemprop="name">' . esc_html($display_name) . '</span></a>';

                                $child_cats = get_terms([
                                    'taxonomy'   => 'profession',
                                    'hide_empty' => false,
                                    'parent'     => $item['id']
                                ]);

                                if (!empty($child_cats) && !is_wp_error($child_cats)) {
                                    echo '<ul class="submenu">';
                                    echo '<li class="submenu-item nopc-notablet">';
                                    echo '<a href="' . esc_url($link) . '" itemprop="url"><span itemprop="name">Все ' . esc_html(mb_substr(mb_strtolower($display_name, 'UTF-8'), 0, 1, 'UTF-8') . mb_substr($display_name, 1, null, 'UTF-8')) . '</span></a></li>';

                                    foreach ($child_cats as $index => $child) {
                                        $class = ($index > 2) ? 'submenu-item hideli' : 'submenu-item';
                                        echo '<li class="' . $class . '">';
                                        echo '<a href="' . esc_url(get_term_link($child)) . '" itemprop="url"><span itemprop="name">' . esc_html($child->name) . '</span></a></li>';
                                    }

                                    if (count($child_cats) > 3) {
                                        $n = count($child_cats) - 3;
                                        echo '<li class="submenu-item submenu-item--more"><button class="show-more">Ещё ' . $n . '</button></li>';
                                    }

                                    echo '</ul>';
                                }
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </li>
            </ul>

            <?php
            wp_nav_menu([
                'theme_location' => 'main-menu',
                'menu'           => 'Mainmenu',
                'menu_class'     => 'main-menu',
                'container'      => false,
                'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                'link_before'    => '<span itemprop="name">',
                'link_after'     => '</span>',
            ]);
            ?>

            <div class="header_right-block">
            <?php if ( is_user_logged_in() ) : ?>
                <div class="header_right-block-colaps">
                   <a href="/favorites/" class="header_right-block-icon heart-icon" style="display:none">Избранное</a>
                   <a href="#" class="header_right-block-icon zakaz-icon" style="display:none"><span>0</span>Заказы</a>
                   <a href="#" class="header_right-block-icon chat-icon" style="display:none"><span>0</span>Чат</a>
                   <a href="#" class="header_right-block-icon alert-icon" style="display:none">Уведомл.</a>
                   <a href="/profile-settings/" class="header_right-block-icon profile-icon">Профиль</a>
                </div>
            <?php else : ?>
                <button class="start-performer" onclick="openPopup('start-performer')">
                    Стать исполнителем
                </button>
            <?php endif; ?>
            </div>
        </nav>


   </div>
 </header>

<main class="main">
<?php if ( !is_front_page() ) : ?>
  <section class="section section--breadcrumbs">
    <?php 
    if ( is_author() || is_singular( 'performer' ) ) : ?>  
      <nav aria-label="breadcrumbs" class="rank-math-breadcrumb">
          <p>
              <a href="<?php echo home_url(); ?>">Главная</a><span class="separator"> / </span>
              <a href="/performers/">Исполнители</a><span class="separator"> / </span>
              <span class="last">
                  <?php
                  $obj = get_queried_object();
                  $user_id = is_author() ? $obj->ID : $obj->post_author;
                  $first = get_user_meta( $user_id, 'first_name', true );  
                  $last  = get_user_meta( $user_id, 'last_name', true ); 
                  $name  = trim( $first . ' ' . $last );
                  if ( ! $name && ! is_author() ) {  $name = get_the_title( $obj->ID );   }
                  echo esc_html( $name );
                  ?>
              </span> 
          </p> 
      </nav>
    
    <?php 
    elseif ( get_post_type() !== 'catalog_page' ) : ?>
      <?php if ( function_exists( 'rank_math_the_breadcrumbs' ) ) rank_math_the_breadcrumbs(); ?>
    <?php endif; ?>
  </section>
<?php endif; ?>