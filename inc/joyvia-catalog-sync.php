<?php
if (!defined('ABSPATH')) exit;


add_action('edited_term', 'joyvia_resync_catalog_pages_on_term_edit', 20, 3);
function joyvia_resync_catalog_pages_on_term_edit($term_id, $tt_id, $taxonomy) {
    if (!in_array($taxonomy, ['profession', 'event', 'specialization'], true)) return;

    $field_keys = [
        'profession'     => ['catalog_profession'],
        'event'          => ['catalog_event', 'catalog_subevent'],
        'specialization' => ['catalog_spec'],
    ];

    if (empty($field_keys[$taxonomy])) return;

    $found = [];
    foreach ($field_keys[$taxonomy] as $meta_key) {
        $q = new WP_Query([
            'post_type'      => 'catalog_page',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [['key' => $meta_key, 'value' => (int) $term_id]],
            'no_found_rows'  => true,
        ]);
        foreach ($q->posts as $pid) $found[$pid] = $pid;
    }

    if (empty($found) || !function_exists('joyvia_generate_catalog_url')) return;

    foreach ($found as $page_id) {
        joyvia_generate_catalog_url($page_id);
    }
}


add_filter('post_type_link', 'joyvia_catalog_page_permalink', 10, 2);
function joyvia_catalog_page_permalink($post_link, $post) {
    if (!is_object($post) || $post->post_type !== 'catalog_page') return $post_link;
    if ($post->post_status !== 'publish') return $post_link;
    $url = get_post_meta($post->ID, 'generated_url', true);
    if (empty($url)) return $post_link;
    return home_url('/' . trim($url, '/') . '/');
}


add_filter('get_terms_args', 'joyvia_filter_inactive_terms_on_front', 10, 2);
function joyvia_filter_inactive_terms_on_front($args, $taxonomies) {
    if (is_admin() && !wp_doing_ajax()) return $args;
    if (!empty($args['joyvia_skip_active_filter'])) return $args;

    $managed = ['profession', 'event', 'specialization', 'skill'];
    $taxonomies = (array) $taxonomies;
    if (empty(array_intersect($taxonomies, $managed))) return $args;

    $clause = [
        'relation' => 'OR',
        ['key' => 'is_active', 'value' => '1'],
        ['key' => 'is_active', 'compare' => 'NOT EXISTS'],
    ];

    $existing = $args['meta_query'] ?? [];

    if (empty($existing)) {
        $args['meta_query'] = $clause;
    } elseif (is_array($existing) && isset($existing['key'])) {
        $args['meta_query'] = ['relation' => 'AND', $existing, $clause];
    } elseif (is_array($existing) && !isset($existing['relation'])) {
        $existing['relation'] = 'AND';
        $existing[] = $clause;
        $args['meta_query'] = $existing;
    } else {
        $existing[] = $clause;
        $args['meta_query'] = $existing;
    }

    return $args;
}


add_filter('rank_math/frontend/canonical', 'joyvia_rank_math_canonical_for_catalog');
add_filter('wpseo_canonical',              'joyvia_rank_math_canonical_for_catalog');
add_filter('rank_math/opengraph/url',      'joyvia_rank_math_canonical_for_catalog');
add_filter('rank_math/frontend/og_url',    'joyvia_rank_math_canonical_for_catalog');
function joyvia_rank_math_canonical_for_catalog($canonical) {
    $page_id = (int) get_query_var('joyvia_catalog_id');
    if (!$page_id) return $canonical;
    $url = get_post_meta($page_id, 'generated_url', true);
    if (empty($url)) return $canonical;
    return home_url('/' . trim($url, '/') . '/');
}


function joyvia_resolve_catalog_seo_title($page_id) {
    $rm_title = get_post_meta($page_id, 'rank_math_title', true);
    if (!empty(trim((string) $rm_title))) {
        return joyvia_render_rank_math_variables($rm_title, $page_id);
    }
    $post_title = get_the_title($page_id);
    if (!empty(trim((string) $post_title))) {
        return $post_title . ' | ' . get_bloginfo('name');
    }
    return null;
}

function joyvia_resolve_catalog_seo_description($page_id) {
    $rm_desc = get_post_meta($page_id, 'rank_math_description', true);
    if (!empty(trim((string) $rm_desc))) {
        return joyvia_render_rank_math_variables($rm_desc, $page_id);
    }
    return null;
}

function joyvia_render_rank_math_variables($template, $page_id) {
    if (class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'replace_vars')) {
        $post = get_post($page_id);
        if ($post) return \RankMath\Helper::replace_vars($template, $post);
    }
    if (function_exists('rank_math_replace_seo_fields')) {
        $post = get_post($page_id);
        if ($post) return rank_math_replace_seo_fields($template, $post);
    }
    $sitename = get_bloginfo('name');
    $page_title = get_the_title($page_id);
    $separator = '|';
    $template = str_replace(['%title%', '%sitename%', '%sep%', '%site_name%', '%post_title%'],
        [$page_title, $sitename, $separator, $sitename, $page_title], $template);
    return $template;
}

add_filter('rank_math/frontend/title',           'joyvia_filter_catalog_title', 99);
add_filter('rank_math/opengraph/facebook/title', 'joyvia_filter_catalog_title', 99);
add_filter('rank_math/opengraph/twitter/title',  'joyvia_filter_catalog_title', 99);
add_filter('pre_get_document_title',             'joyvia_filter_catalog_title', 99);
add_filter('document_title_parts',               'joyvia_filter_catalog_title_parts', 99);
function joyvia_filter_catalog_title($title) {
    $page_id = (int) get_query_var('joyvia_catalog_id');
    if (!$page_id) return $title;
    $resolved = joyvia_resolve_catalog_seo_title($page_id);
    return $resolved !== null ? $resolved : $title;
}
function joyvia_filter_catalog_title_parts($parts) {
    $page_id = (int) get_query_var('joyvia_catalog_id');
    if (!$page_id) return $parts;
    $resolved = joyvia_resolve_catalog_seo_title($page_id);
    if ($resolved !== null) {
        $parts = ['title' => $resolved];
    }
    return $parts;
}

add_filter('rank_math/frontend/description',           'joyvia_filter_catalog_description', 99);
add_filter('rank_math/opengraph/facebook/description', 'joyvia_filter_catalog_description', 99);
add_filter('rank_math/opengraph/twitter/description',  'joyvia_filter_catalog_description', 99);
function joyvia_filter_catalog_description($desc) {
    $page_id = (int) get_query_var('joyvia_catalog_id');
    if (!$page_id) return $desc;
    $resolved = joyvia_resolve_catalog_seo_description($page_id);
    return $resolved !== null ? $resolved : $desc;
}


add_filter('rank_math/frontend/opengraph_type', 'joyvia_catalog_force_og_website');
function joyvia_catalog_force_og_website($type) {
    $page_id = (int) get_query_var('joyvia_catalog_id');
    return $page_id ? 'website' : $type;
}


add_filter('rank_math/sitemap/entry', 'joyvia_rank_math_sitemap_catalog_entry', 10, 3);
function joyvia_rank_math_sitemap_catalog_entry($entry, $type, $object) {
    if ($type !== 'post') return $entry;
    if (!is_object($object) || $object->post_type !== 'catalog_page') return $entry;
    if (!isset($object->post_status) || $object->post_status !== 'publish') return false;

    $url = get_post_meta($object->ID, 'generated_url', true);
    if (!empty($url)) {
        $entry['loc'] = home_url('/' . trim($url, '/') . '/');
    }

    $allow = (int) get_post_meta($object->ID, 'allow_indexing', true);
    if ($allow !== 1) return false;

    return $entry;
}


add_filter('rank_math/frontend/robots', 'joyvia_rank_math_robots_for_catalog');
function joyvia_rank_math_robots_for_catalog($robots) {
    $page_id = (int) get_query_var('joyvia_catalog_id');
    if (!$page_id) return $robots;

    if (!is_array($robots)) $robots = [];
    $allow = (int) get_post_meta($page_id, 'allow_indexing', true);
    if ($allow === 1) {
        $robots['index']  = 'index';
        $robots['follow'] = 'follow';
    } else {
        $robots['index']  = 'noindex';
        $robots['follow'] = 'nofollow';
    }
    return $robots;
}


add_action('acf/init', 'joyvia_register_catalog_indexing_field');
function joyvia_register_catalog_indexing_field() {
    if (!function_exists('acf_add_local_field_group')) return;

    acf_add_local_field_group([
        'key'      => 'group_joyvia_catalog_indexing',
        'title'    => 'Индексация',
        'fields'   => [[
            'key'           => 'field_joyvia_allow_indexing',
            'label'         => 'Разрешить индексацию',
            'name'          => 'allow_indexing',
            'type'          => 'true_false',
            'instructions'  => 'По умолчанию страница закрыта от индексации. Включите тумблер, когда страница готова к публикации в поиске.',
            'default_value' => 0,
            'ui'            => 1,
            'ui_on_text'    => 'Открыта',
            'ui_off_text'   => 'Закрыта',
        ]],
        'location' => [[[
            'param'    => 'post_type',
            'operator' => '==',
            'value'    => 'catalog_page',
        ]]],
        'menu_order' => 10,
        'position'   => 'side',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ]);
}