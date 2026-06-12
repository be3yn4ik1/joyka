<?php
if (!defined('ABSPATH')) exit;

function joyvia_schema_print($data) {
    if (empty($data)) return;
    echo '<script type="application/ld+json">' . wp_json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    ) . '</script>';
}

function joyvia_schema_org_node() {
    return [
        '@type'       => 'Organization',
        '@id'         => home_url('/#organization'),
        'name'        => 'Joyvia',
        'url'         => home_url('/'),
        'logo'        => [
            '@type' => 'ImageObject',
            'url'   => 'https://joyvia.ru/wp-content/uploads/2025/08/Logo-1-1.png',
        ],
        'description' => 'Маркетплейс исполнителей для мероприятий: фотографы, видеографы, ведущие, аниматоры',
        'contactPoint' => [
            '@type'             => 'ContactPoint',
            'telephone'         => '+7-928-447-34-07',
            'email'             => 'support@joyvia.ru',
            'contactType'       => 'customer service',
            'availableLanguage' => 'Russian',
        ],
        'sameAs' => [
            'https://t.me/joyvia',
            'https://wa.me/joyvia',
        ],
        'areaServed' => [
            '@type' => 'Country',
            'name'  => 'Россия',
        ],
    ];
}

function joyvia_schema_website_node() {
    return [
        '@type'     => 'WebSite',
        '@id'       => home_url('/#website'),
        'url'       => home_url('/'),
        'name'      => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'inLanguage' => 'ru-RU',
        'publisher' => ['@id' => home_url('/#organization')],
    ];
}

function joyvia_schema_aggregate_stats() {
    $q = new WP_Query([
        'post_type'      => 'performer',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $count_performers = count($q->posts);
    $sum_reviews = 0;
    $sum_rating  = 0.0;
    foreach ($q->posts as $pid) {
        $sum_reviews += (int) get_post_meta($pid, 'kol-vo_oczenok', true);
        $sum_rating  += (float) get_post_meta($pid, 'rejting', true);
    }
    $avg_rating = $count_performers > 0 ? round($sum_rating / $count_performers, 1) : 0;
    return [
        'performers' => $count_performers,
        'reviews'    => $sum_reviews,
        'rating'     => $avg_rating,
    ];
}

function joyvia_schema_performer_card($performer_post, $position, $price_override = null) {
    if (!is_object($performer_post)) return null;

    $post_id = $performer_post->ID;
    $user_id = (int) $performer_post->post_author;

    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name  = get_user_meta($user_id, 'last_name', true);
    $full_name  = trim($first_name . ' ' . $last_name) ?: get_the_title($post_id);

    $avatar = get_field('avatarka', $post_id);
    $avatar_url = is_array($avatar) && !empty($avatar['url']) ? $avatar['url'] : '';

    $rating = (float) (get_field('rejting', $post_id) ?: 0);
    $reviews_count = (int) (get_field('kol-vo_oczenok', $post_id) ?: 0);

    $city_name = '';
    $gorod_terms = get_field('gorod', $post_id);
    if (!empty($gorod_terms) && is_array($gorod_terms)) {
        foreach ($gorod_terms as $t) {
            $term_obj = get_term(is_object($t) ? $t->term_id : (int) $t, 'city');
            if ($term_obj && !is_wp_error($term_obj) && $term_obj->parent == 0) {
                $city_name = $term_obj->name;
                break;
            }
        }
    }

    $packages = get_post_meta($post_id, 'service_packages_data', true);
    if (!is_array($packages)) $packages = [];
    $min_price = null;
    foreach ($packages as $pkg) {
        $p = (int) ($pkg['price'] ?? 0);
        if ($p > 0 && ($min_price === null || $p < $min_price)) $min_price = $p;
    }

    $bio = get_field('o_sebe', $post_id);
    $bio_short = '';
    if (!empty($bio)) {
        $bio_text = wp_strip_all_tags((string) $bio);
        $bio_short = mb_strlen($bio_text) > 150 ? mb_strimwidth($bio_text, 0, 150, '...') : $bio_text;
    }

    $price = $price_override !== null ? $price_override : $min_price;
    $profile_url = get_permalink($post_id);

    $card = [
        '@type'    => 'ListItem',
        'position' => (int) $position,
        'item'     => [
            '@type'       => 'LocalBusiness',
            '@id'         => $profile_url,
            'name'        => $full_name,
            'url'         => $profile_url,
        ],
    ];

    if ($avatar_url) $card['item']['image'] = $avatar_url;
    if ($bio_short)  $card['item']['description'] = $bio_short;
    if ($price !== null) $card['item']['priceRange'] = 'от ' . number_format($price, 0, '.', ' ') . ' ₽';

    if ($city_name) {
        $card['item']['address'] = [
            '@type'           => 'PostalAddress',
            'addressLocality' => $city_name,
            'addressCountry'  => 'RU',
        ];
    }

    if ($reviews_count > 0 && $rating > 0) {
        $card['item']['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => (string) $rating,
            'reviewCount' => (string) $reviews_count,
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
    }

    return $card;
}

function joyvia_schema_get_performer_price_for_context($post_id, $context_event_id, $context_spec_id) {
    $packages = get_post_meta($post_id, 'service_packages_data', true);
    if (!is_array($packages)) return null;

    $relevant_min = null;
    $overall_min  = null;

    foreach ($packages as $pkg) {
        $price = (int) ($pkg['price'] ?? 0);
        if ($price <= 0) continue;
        $type   = $pkg['type'] ?? '';
        $parent = (int) ($pkg['parent_id'] ?? 0);

        if ($overall_min === null || $price < $overall_min) $overall_min = $price;

        $is_relevant = false;
        if ($context_event_id > 0 && $type === 'event' && $parent === $context_event_id) $is_relevant = true;
        if ($context_spec_id  > 0 && $type === 'spec'  && $parent === $context_spec_id)  $is_relevant = true;

        if ($is_relevant && ($relevant_min === null || $price < $relevant_min)) {
            $relevant_min = $price;
        }
    }

    if (($context_event_id > 0 || $context_spec_id > 0) && $relevant_min !== null) {
        return $relevant_min;
    }
    return $overall_min;
}

function joyvia_schema_build_breadcrumb($items, $page_url) {
    $list = [];
    $i = 1;
    foreach ($items as $it) {
        $entry = [
            '@type'    => 'ListItem',
            'position' => $i++,
            'name'     => $it['name'],
        ];
        if (!empty($it['url'])) $entry['item'] = $it['url'];
        $list[] = $entry;
    }
    return [
        '@type'           => 'BreadcrumbList',
        '@id'             => $page_url . '#breadcrumb',
        'itemListElement' => $list,
    ];
}

function joyvia_schema_get_catalog_page_url($page_id) {
    $url = get_post_meta($page_id, 'generated_url', true);
    if (!empty($url)) {
        return home_url('/' . trim($url, '/') . '/');
    }
    return home_url(wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}

function joyvia_schema_render_homepage() {
    $stats = joyvia_schema_aggregate_stats();
    $home_url = home_url('/');
    $org = joyvia_schema_org_node();
    
    if ($stats['reviews'] > 0 && $stats['rating'] > 0) {
        $org['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => (string) $stats['rating'],
            'reviewCount' => (string) $stats['reviews'],
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
    }

    $graph = [
        joyvia_schema_website_node(),
        $org,
        [
            '@type'       => 'WebPage',
            '@id'         => $home_url . '#webpage',
            'url'         => $home_url,
            'name'        => wp_get_document_title(),
            'description' => get_bloginfo('description'),
            'isPartOf'    => ['@id' => $home_url . '#website'],
            'about'       => ['@id' => $home_url . '#organization'],
            'inLanguage'  => 'ru-RU',
        ],
    ];

    $popular = get_posts([
        'post_type'      => 'performer',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        'meta_query'     => [[
            'key'     => 'prioritet',
            'value'   => 5,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ]],
        'orderby'        => 'rand',
        'no_found_rows'  => true,
    ]);

    if (!empty($popular)) {
        $items = [];
        $pos = 1;
        foreach ($popular as $perf) {
            $card = joyvia_schema_performer_card($perf, $pos++);
            if ($card) $items[] = $card;
        }
        if (!empty($items)) {
            $graph[] = [
                '@type'           => 'ItemList',
                '@id'             => $home_url . '#popular-performers',
                'name'            => 'Популярные исполнители',
                'numberOfItems'   => (string) count($items),
                'itemListElement' => $items,
            ];
        }
    }

    $graph[] = [
        '@type' => 'HowTo',
        '@id'   => $home_url . '#how-it-works',
        'name'  => 'Как мы работаем',
        'step'  => [
            ['@type' => 'HowToStep', 'position' => 1, 'name' => 'Выберите исполнителя', 'text' => 'Просмотрите каталог исполнителей, изучите портфолио и отзывы'],
            ['@type' => 'HowToStep', 'position' => 2, 'name' => 'Оформите заявку',     'text' => 'Оставьте заявку напрямую исполнителю с указанием даты и пожеланий'],
            ['@type' => 'HowToStep', 'position' => 3, 'name' => 'Получите предложения','text' => 'Исполнитель свяжется с вами и согласует детали'],
        ],
    ];

    joyvia_schema_print(['@context' => 'https://schema.org', '@graph' => $graph]);
}

function joyvia_schema_render_catalog($page_id, $page_type, $prof_id, $event_id, $subevent_id, $spec_id, $performers_query) {
    $page_url = joyvia_schema_get_catalog_page_url($page_id);
    $page_title       = wp_get_document_title();
    $page_description = get_post_meta($page_id, 'rank_math_description', true) ?: '';
    $h1 = get_field('seo_h1', $page_id) ?: get_the_title($page_id);

    $crumbs = [['name' => 'Главная', 'url' => home_url('/')]];
    if ($page_type === 'prof_only' && $prof_id) {
        $crumbs[] = ['name' => get_morpher_declension($prof_id, 'И_мн')];
    } elseif ($page_type === 'event_prof' && $event_id && $prof_id) {
        $event = get_term($event_id);
        $crumbs[] = ['name' => get_morpher_declension($event_id, 'И_мн'), 'url' => home_url('/' . $event->slug . '/')];
        $crumbs[] = ['name' => get_morpher_declension($prof_id, 'И_мн') . ' на ' . mb_strtolower(get_morpher_declension($event_id, 'В'), 'UTF-8')];
    } elseif ($page_type === 'subevent_prof' && $event_id && $subevent_id && $prof_id) {
        $event = get_term($event_id);
        $sub   = get_term($subevent_id);
        $crumbs[] = ['name' => get_morpher_declension($event_id, 'И_мн'), 'url' => home_url('/' . $event->slug . '/')];
        $crumbs[] = ['name' => get_morpher_declension($subevent_id, 'И_мн'), 'url' => home_url('/' . $event->slug . '/' . $sub->slug . '/')];
        $crumbs[] = ['name' => get_morpher_declension($prof_id, 'И_мн') . ' на ' . mb_strtolower(get_morpher_declension($subevent_id, 'В'), 'UTF-8')];
    } elseif ($page_type === 'spec_prof' && $prof_id && $spec_id) {
        $prof = get_term($prof_id);
        $crumbs[] = ['name' => get_morpher_declension($prof_id, 'И_мн'), 'url' => home_url('/' . $prof->slug . '/')];
        $crumbs[] = ['name' => get_morpher_declension($spec_id, 'И_мн')];
    }

    $graph = [];

    $collection = [
        '@type'       => 'CollectionPage',
        '@id'         => $page_url . '#webpage',
        'url'         => $page_url,
        'name'        => $page_title,
        'description' => $page_description,
        'isPartOf'    => ['@id' => home_url('/#website')],
        'breadcrumb'  => ['@id' => $page_url . '#breadcrumb'],
        'inLanguage'  => 'ru-RU',
    ];
    $graph[] = $collection;
    $graph[] = joyvia_schema_build_breadcrumb($crumbs, $page_url);

    $context_event_id = $subevent_id ?: $event_id;
    $context_spec_id  = $spec_id;

    $items = [];
    $position = 1;
    $page_min_price = null;

    if ($performers_query && $performers_query->have_posts()) {
        $cur_count = 0;
        $max_in_schema = 20;
        foreach ($performers_query->posts as $perf) {
            if ($cur_count >= $max_in_schema) break;
            $price = joyvia_schema_get_performer_price_for_context($perf->ID, $context_event_id, $context_spec_id);
            if ($price !== null && ($page_min_price === null || $price < $page_min_price)) {
                $page_min_price = $price;
            }
            $card = joyvia_schema_performer_card($perf, $position++, $price);
            if ($card) $items[] = $card;
            $cur_count++;
        }
    }

    if (!empty($items)) {
        $graph[] = [
            '@type'           => 'ItemList',
            '@id'             => $page_url . '#itemlist',
            'name'            => $h1,
            'url'             => $page_url,
            'numberOfItems'   => (string) ($performers_query ? (int) $performers_query->found_posts : count($items)),
            'itemListElement' => $items,
        ];
    }

    if ($page_min_price !== null && $performers_query && (int) $performers_query->found_posts > 0) {
        $graph[0]['offers'] = [
            '@type'         => 'AggregateOffer',
            'lowPrice'      => (string) $page_min_price,
            'priceCurrency' => 'RUB',
            'offerCount'    => (string) (int) $performers_query->found_posts,
        ];
    }

    joyvia_schema_print(['@context' => 'https://schema.org', '@graph' => $graph]);
}

function joyvia_schema_render_hub($page_id, $page_type, $event_id, $subevent_id) {
    $page_url = joyvia_schema_get_catalog_page_url($page_id);
    $page_title       = wp_get_document_title();
    $page_description = get_post_meta($page_id, 'rank_math_description', true) ?: '';
    $h1 = get_field('seo_h1', $page_id) ?: get_the_title($page_id);

    $crumbs = [['name' => 'Главная', 'url' => home_url('/')]];
    $event_term = get_term($event_id);
    if ($subevent_id) {
        $sub_term = get_term($subevent_id);
        $crumbs[] = ['name' => $event_term->name, 'url' => home_url('/' . $event_term->slug . '/')];
        $crumbs[] = ['name' => $sub_term->name];
    } else {
        $crumbs[] = ['name' => $event_term->name];
    }

    $mq = ['relation' => 'AND', ['key' => 'page_type', 'value' => $subevent_id ? 'subevent_prof' : 'event_prof'], ['key' => 'catalog_event', 'value' => $event_id]];
    if ($subevent_id) $mq[] = ['key' => 'catalog_subevent', 'value' => $subevent_id];
    $hub_pages = get_posts(['post_type' => 'catalog_page', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_query' => $mq]);

    $items = [];
    $position = 1;
    $total_performers = 0;
    $hub_min_price = null;

    foreach ($hub_pages as $h_page) {
        $h_prof_id = get_field('catalog_profession', $h_page->ID);
        if (!$h_prof_id) continue;
        $h_prof_term = get_term($h_prof_id);
        if (is_wp_error($h_prof_term) || !$h_prof_term) continue;

        $tax_q = ['relation' => 'AND', ['taxonomy' => 'profession', 'field' => 'term_id', 'terms' => $h_prof_id], ['taxonomy' => 'event', 'field' => 'term_id', 'terms' => $subevent_id ?: $event_id]];
        $p_query = new WP_Query(['post_type' => 'performer', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => $tax_q]);
        
        $count = $p_query->found_posts;
        if ($count > 0) {
            $total_performers += $count;
            $h_url = joyvia_schema_get_catalog_page_url($h_page->ID);
            $prof_min = null;

            foreach ($p_query->posts as $pid) {
                $price = joyvia_schema_get_performer_price_for_context($pid, $subevent_id ?: $event_id, 0);
                if ($price !== null && ($prof_min === null || $price < $prof_min)) $prof_min = $price;
            }

            if ($prof_min !== null && ($hub_min_price === null || $prof_min < $hub_min_price)) $hub_min_price = $prof_min;

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'item'     => [
                    '@type'       => 'Service',
                    'name'        => $h_prof_term->name . ' на ' . mb_strtolower(get_morpher_declension($subevent_id ?: $event_id, 'В'), 'UTF-8'),
                    'url'         => $h_url,
                    'description' => $count . ' исполнителей',
                    'offers'      => [
                        '@type'         => 'AggregateOffer',
                        'lowPrice'      => (string) ($prof_min ?: 0),
                        'priceCurrency' => 'RUB'
                    ],
                    'provider'    => [
                        '@type' => 'Organization',
                        '@id'   => home_url('/#organization')
                    ]
                ]
            ];
        }
    }

    $collection = [
        '@type'       => 'CollectionPage',
        '@id'         => $page_url . '#webpage',
        'url'         => $page_url,
        'name'        => $page_title,
        'description' => $page_description,
        'isPartOf'    => ['@id' => home_url('/#website')],
        'breadcrumb'  => ['@id' => $page_url . '#breadcrumb'],
        'inLanguage'  => 'ru-RU',
    ];

    if ($hub_min_price !== null && $total_performers > 0) {
        $collection['offers'] = [
            '@type'         => 'AggregateOffer',
            'lowPrice'      => (string) $hub_min_price,
            'priceCurrency' => 'RUB',
            'offerCount'    => (string) $total_performers
        ];
    }

    $graph = [
        $collection,
        joyvia_schema_build_breadcrumb($crumbs, $page_url)
    ];

    if (!empty($items)) {
        $graph[] = [
            '@type'           => 'ItemList',
            '@id'             => $page_url . '#itemlist',
            'name'            => $h1,
            'description'     => $page_description,
            'url'             => $page_url,
            'numberOfItems'   => (string) count($items),
            'itemListElement' => $items
        ];
    }

    joyvia_schema_print(['@context' => 'https://schema.org', '@graph' => $graph]);
}

function joyvia_schema_render_performer($post_id) {
    $performer_post = get_post($post_id);
    if (!$performer_post || $performer_post->post_type !== 'performer') return;

    $user_id = (int) $performer_post->post_author;
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name  = get_user_meta($user_id, 'last_name', true);
    $full_name  = trim($first_name . ' ' . $last_name) ?: get_the_title($post_id);
    $profile_url = get_permalink($post_id);

    $avatar = get_field('avatarka', $post_id);
    $avatar_url = is_array($avatar) && !empty($avatar['url']) ? $avatar['url'] : '';

    $portfolio_images = [];
    $portfolio = get_field('portfolio', $post_id);
    if (is_array($portfolio)) {
        foreach ($portfolio as $img) {
            if (is_array($img) && !empty($img['url'])) {
                $portfolio_images[] = $img['url'];
                if (count($portfolio_images) >= 3) break;
            }
        }
    }

    $rating = (float) (get_field('rejting', $post_id) ?: 0);
    $reviews_count = (int) (get_field('kol-vo_oczenok', $post_id) ?: 0);

    $city_name = '';
    $gorod_terms = get_field('gorod', $post_id);
    if (!empty($gorod_terms) && is_array($gorod_terms)) {
        foreach ($gorod_terms as $t) {
            $term_obj = get_term(is_object($t) ? $t->term_id : (int) $t, 'city');
            if ($term_obj && !is_wp_error($term_obj) && $term_obj->parent == 0) {
                $city_name = $term_obj->name;
                break;
            }
        }
    }

    $packages = get_post_meta($post_id, 'service_packages_data', true);
    if (!is_array($packages)) $packages = [];
    $min_price = null;
    $offers = [];
    foreach ($packages as $pkg) {
        $price = (int) ($pkg['price'] ?? 0);
        if ($price <= 0) continue;
        if ($min_price === null || $price < $min_price) $min_price = $price;

        $pkg_name = (string) ($pkg['name'] ?? '');
        $offer_name = $pkg_name . ' | ' . $full_name;
        $offers[] = [
            '@type' => 'Offer',
            'name'  => $offer_name,
            'priceSpecification' => [
                '@type'         => 'PriceSpecification',
                'minPrice'      => (string) $price,
                'priceCurrency' => 'RUB',
            ],
            'itemOffered' => [
                '@type' => 'Service',
                'name'  => $offer_name,
            ],
        ];
    }

    $bio = get_field('o_sebe', $post_id);
    $bio_short = '';
    if (!empty($bio)) {
        $bio_text = wp_strip_all_tags((string) $bio);
        $bio_short = mb_strlen($bio_text) > 150 ? mb_strimwidth($bio_text, 0, 150, '...') : $bio_text;
    }

    $profession_name = '';
    $prof_terms = wp_get_object_terms($post_id, 'profession');
    if (!empty($prof_terms) && !is_wp_error($prof_terms)) {
        $profession_name = $prof_terms[0]->name;
    }

    $spec_names = [];
    $spec_terms = wp_get_object_terms($post_id, 'specialization');
    if (!empty($spec_terms) && !is_wp_error($spec_terms)) {
        foreach ($spec_terms as $st) $spec_names[] = $st->name;
    }

    $local_business = [
        '@type' => 'LocalBusiness',
        '@id'   => $profile_url,
        'url'   => $profile_url,
        'name'  => $full_name,
    ];
    if (!empty($portfolio_images)) $local_business['image'] = $portfolio_images;
    if ($avatar_url) $local_business['logo'] = $avatar_url;
    if ($min_price !== null) $local_business['priceRange'] = 'от ' . number_format($min_price, 0, '.', ' ') . ' ₽';
    if ($city_name) {
        $local_business['address'] = [
            '@type'           => 'PostalAddress',
            'addressLocality' => $city_name,
            'addressCountry'  => 'RU',
        ];
    }
    if ($reviews_count > 0 && $rating > 0) {
        $local_business['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => (string) $rating,
            'reviewCount' => (string) $reviews_count,
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
    }
    if (!empty($offers)) $local_business['makesOffer'] = $offers;

    $person = [
        '@type' => 'Person',
        '@id'   => $profile_url . '#person',
        'name'  => $full_name,
        'url'   => $profile_url,
    ];
    if ($avatar_url) {
        $person['image'] = [
            '@type'   => 'ImageObject',
            'url'     => $avatar_url,
            'caption' => $full_name,
        ];
    }
    if ($bio_short) $person['description'] = $bio_short;
    if ($profession_name) $person['jobTitle'] = $profession_name;
    if ($city_name) {
        $person['address'] = [
            '@type'           => 'PostalAddress',
            'addressLocality' => $city_name,
        ];
    }
    if (!empty($spec_names)) $person['knowsAbout'] = $spec_names;
    $person['worksFor'] = ['@id' => home_url('/#organization')];

    $breadcrumbs = [
        '@type' => 'BreadcrumbList',
        '@id'   => $profile_url . '#breadcrumb',
        'itemListElement' => [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => 'Главная',
                'item'     => home_url('/'),
            ],
            [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => 'Исполнители',
                'item'     => home_url('/performers/'),
            ],
            [
                '@type'    => 'ListItem',
                'position' => 3,
                'name'     => $full_name,
            ]
        ]
    ];

    $graph = [$local_business, $person, $breadcrumbs];

    $prof_id = (!empty($prof_terms) && !is_wp_error($prof_terms)) ? $prof_terms[0]->term_id : 0;
    
    $similar = get_posts([
        'post_type'      => 'performer',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'post__not_in'   => [$post_id],
        'tax_query'      => $prof_id ? [['taxonomy' => 'profession', 'field' => 'term_id', 'terms' => $prof_id]] : []
    ]);

    if (!empty($similar)) {
        $item_list_elements = [];
        $pos = 1;
        foreach ($similar as $s_post) {
            $card = joyvia_schema_performer_card($s_post, $pos++);
            if ($card) $item_list_elements[] = $card;
        }
        if (!empty($item_list_elements)) {
            $graph[] = [
                '@type' => 'ItemList',
                'name'  => 'Похожие исполнители',
                'itemListElement' => $item_list_elements
            ];
        }
    }

    joyvia_schema_print(['@context' => 'https://schema.org', '@graph' => $graph]);
}

add_filter('rank_math/frontend/opengraph_type', 'joyvia_schema_force_og_website_homepage_profile');
function joyvia_schema_force_og_website_homepage_profile($type) {
    if (is_front_page() || is_home() || is_singular('performer')) return 'website';
    return $type;
}

add_filter('rank_math/json_ld', 'joyvia_disable_rankmath_schema', 99, 2);
function joyvia_disable_rankmath_schema($data, $jsonld) {
    if (is_front_page() || is_home() || is_singular('performer') || get_post_type() === 'catalog_page') {
        return [];
    }
    return $data;
}