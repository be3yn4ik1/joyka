<?php
if (!defined('ABSPATH')) exit;

if (!defined('JOYVIA_ENTITY_TAXONOMIES')) {
    define('JOYVIA_ENTITY_TAXONOMIES', ['profession', 'event', 'specialization', 'skill']);
}


function joyvia_is_term_active($term) {
    $term_obj = is_object($term) ? $term : get_term((int) $term);
    if (!$term_obj || is_wp_error($term_obj)) return false;
    $val = get_term_meta($term_obj->term_id, 'is_active', true);
    if ($val === '' || $val === null) return true;
    return (bool) $val;
}


add_action('acf/init', 'joyvia_register_entity_acf_fields');
function joyvia_register_entity_acf_fields() {
    if (!function_exists('acf_add_local_field_group')) return;

    foreach (JOYVIA_ENTITY_TAXONOMIES as $tax) {
        acf_add_local_field_group([
            'key'      => 'group_joyvia_' . $tax . '_active',
            'title'    => 'Статус активности',
            'fields'   => [[
                'key'           => 'field_joyvia_' . $tax . '_is_active',
                'label'         => 'Активна',
                'name'          => 'is_active',
                'type'          => 'true_false',
                'instructions'  => 'Если выключено — сущность не отображается в формах регистрации, на главной и в блоках выбора. Уже привязанные исполнители продолжают отображаться.',
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'Активна',
                'ui_off_text'   => 'Скрыта',
            ]],
            'location' => [[[
                'param'    => 'taxonomy',
                'operator' => '==',
                'value'    => $tax,
            ]]],
            'menu_order' => 0,
            'position'   => 'normal',
            'style'      => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
        ]);
    }
}


add_action('admin_init', 'joyvia_backfill_is_active_meta');
function joyvia_backfill_is_active_meta() {
    if (get_option('joyvia_is_active_backfilled')) return;
    foreach (JOYVIA_ENTITY_TAXONOMIES as $tax) {
        if (!taxonomy_exists($tax)) continue;
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids']);
        if (is_wp_error($terms)) continue;
        foreach ($terms as $tid) {
            if (get_term_meta($tid, 'is_active', true) === '') {
                update_term_meta($tid, 'is_active', 1);
            }
        }
    }
    update_option('joyvia_is_active_backfilled', 1);
}


function joyvia_dependent_catalog_pages($term_id, $taxonomy) {
    $field_map = [
        'profession'     => ['catalog_profession'],
        'event_parent'   => ['catalog_event'],
        'event_child'    => ['catalog_subevent', 'catalog_event'],
        'specialization' => ['catalog_spec'],
    ];
    // Типы страниц, для которых поле реально используется (по условной логике ACF).
    // Без этого «висячие» мета-значения скрытых полей дают ложные зависимости.
    $active_types = [
        'catalog_profession' => ['event_prof', 'subevent_prof', 'spec_prof', 'prof_only'],
        'catalog_event'      => ['hub_event', 'hub_subevent', 'event_prof', 'subevent_prof'],
        'catalog_subevent'   => ['hub_subevent', 'subevent_prof'],
        'catalog_spec'       => ['spec_prof'],
    ];
    $key = $taxonomy;
    if ($taxonomy === 'event') {
        $term = get_term($term_id);
        $key = ($term && !is_wp_error($term) && $term->parent != 0) ? 'event_child' : 'event_parent';
    }
    if (!isset($field_map[$key])) return [];

    $found = [];
    foreach ($field_map[$key] as $meta_key) {
        $meta_query = [
            'relation' => 'AND',
            ['key' => $meta_key, 'value' => (int) $term_id],
        ];
        if (!empty($active_types[$meta_key])) {
            $meta_query[] = ['key' => 'page_type', 'value' => $active_types[$meta_key], 'compare' => 'IN'];
        }
        $q = new WP_Query([
            'post_type'      => 'catalog_page',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
            'no_found_rows'  => true,
        ]);
        foreach ($q->posts as $pid) $found[$pid] = $pid;
    }
    return array_values($found);
}

function joyvia_dependent_performers($term_id, $taxonomy) {
    $q = new WP_Query([
        'post_type'      => 'performer',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => (int) $term_id,
        ]],
        'no_found_rows'  => true,
    ]);
    return $q->posts;
}

function joyvia_collect_term_dependencies($term_id, $taxonomy) {
    $deps = [
        'catalog_pages' => joyvia_dependent_catalog_pages($term_id, $taxonomy),
        'performers'    => joyvia_dependent_performers($term_id, $taxonomy),
        'child_events'  => [],
        'skills'        => [],
        'specs'         => [],
    ];

    if ($taxonomy === 'event') {
        $term = get_term($term_id);
        if ($term && !is_wp_error($term) && $term->parent == 0) {
            $children = get_terms([
                'taxonomy' => 'event', 'parent' => (int) $term_id,
                'hide_empty' => false, 'fields' => 'ids',
            ]);
            $deps['child_events'] = is_wp_error($children) ? [] : $children;
        }
    }
    if ($taxonomy === 'profession') {
        foreach (['skill' => 'skills', 'specialization' => 'specs'] as $tax_dep => $key) {
            $linked = get_terms([
                'taxonomy'   => $tax_dep,
                'hide_empty' => false,
                'fields'     => 'ids',
                'meta_query' => [['key' => 'profession_link', 'value' => (int) $term_id]],
            ]);
            $deps[$key] = is_wp_error($linked) ? [] : $linked;
        }
    }
    return $deps;
}

function joyvia_format_dependency_message($deps) {
    $parts = [];
    if (!empty($deps['catalog_pages'])) {
        $links = [];
        foreach (array_slice($deps['catalog_pages'], 0, 5) as $pid) {
            $url   = get_post_meta($pid, 'generated_url', true);
            $title = get_the_title($pid) ?: ('#' . $pid);
            $edit  = get_edit_post_link($pid, '');
            $label = $url ? ($title . ' (/' . trim($url, '/') . '/)') : $title;
            $links[] = $edit
                ? '<a href="' . esc_url($edit) . '">' . esc_html($label) . '</a>'
                : esc_html($label);
        }
        $more = count($deps['catalog_pages']) > 5 ? ' и ещё ' . (count($deps['catalog_pages']) - 5) : '';
        $parts[] = 'страницы каталога: ' . implode(', ', $links) . $more;
    }
    if (!empty($deps['performers'])) {
        $links = [];
        foreach (array_slice($deps['performers'], 0, 10) as $pid) {
            $title = get_the_title($pid) ?: ('#' . $pid);
            $edit  = get_edit_post_link($pid, '');
            $links[] = $edit
                ? '<a href="' . esc_url($edit) . '">' . esc_html($title) . '</a>'
                : esc_html($title);
        }
        $more = count($deps['performers']) > 10 ? ' и ещё ' . (count($deps['performers']) - 10) : '';
        $parts[] = 'связанные исполнители: ' . implode(', ', $links) . $more;
    }
    if (!empty($deps['child_events'])) {
        $names = [];
        foreach (array_slice($deps['child_events'], 0, 5) as $tid) {
            $t = get_term($tid);
            if ($t && !is_wp_error($t)) $names[] = esc_html($t->name);
        }
        $more = count($deps['child_events']) > 5 ? ' и ещё ' . (count($deps['child_events']) - 5) : '';
        $parts[] = 'подсобытия: ' . implode(', ', $names) . $more;
    }
    if (!empty($deps['skills'])) {
        $parts[] = 'навыки (' . count($deps['skills']) . ' шт.)';
    }
    if (!empty($deps['specs'])) {
        $parts[] = 'специализации (' . count($deps['specs']) . ' шт.)';
    }
    return $parts;
}


add_action('pre_delete_term', 'joyvia_block_term_delete_with_deps', 10, 2);
function joyvia_block_term_delete_with_deps($term_id, $taxonomy) {
    if (!in_array($taxonomy, JOYVIA_ENTITY_TAXONOMIES, true)) return;

    $deps  = joyvia_collect_term_dependencies($term_id, $taxonomy);
    $parts = joyvia_format_dependency_message($deps);

    if (empty($parts)) return;

    // Подсказку формируем по тому, что реально блокирует удаление.
    $todo = [];
    if (!empty($deps['catalog_pages'])) $todo[] = 'удалите или перепривяжите указанные страницы каталога';
    if (!empty($deps['performers']))    $todo[] = 'снимите сущность с перечисленных исполнителей';
    if (!empty($deps['child_events']))  $todo[] = 'сначала удалите подсобытия';
    if (!empty($deps['skills']) || !empty($deps['specs'])) $todo[] = 'отвяжите связанные навыки/специализации';
    $tail = $todo ? ' Чтобы продолжить — ' . implode('; ', $todo) . '.' : '';

    $message = 'Удаление невозможно. Сущность используется: ' . implode('; ', $parts) . '.' . $tail;

    if (wp_doing_ajax()) {
        wp_send_json_error($message, 409);
    }
    wp_die($message, 'Удаление заблокировано', ['back_link' => true, 'response' => 409]);
}


/**
 * WP удаляет термины из таблицы через AJAX (action=delete-tag). Наш hook
 * pre_delete_term отдаёт 409 + JSON, но штатный обработчик ничего не выводит —
 * пользователь видит только «409 (Conflict)» в консоли, а строка остаётся.
 * Перехватываем ошибку и показываем понятное уведомление сверху списка.
 */
add_action('admin_footer-edit-tags.php', 'joyvia_inject_term_delete_notice_js');
function joyvia_inject_term_delete_notice_js() {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->taxonomy, JOYVIA_ENTITY_TAXONOMIES, true)) return;
    ?>
    <script>
    (function($){
        $(document).ajaxError(function(event, jqXHR, settings){
            if (!jqXHR || jqXHR.status !== 409) return;
            var data = (settings && settings.data) ? String(settings.data) : '';
            if (data.indexOf('action=delete-tag') === -1) return;

            var msg = '';
            try {
                var r = JSON.parse(jqXHR.responseText);
                msg = (r && r.data) ? r.data : '';
            } catch (e) {
                msg = jqXHR.responseText || '';
            }
            if (!msg) return;

            $('#joyvia-term-delete-notice').remove();
            var $notice = $(
                '<div id="joyvia-term-delete-notice" class="notice notice-error is-dismissible">' +
                '<p>' + msg + '</p>' +
                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Скрыть</span></button>' +
                '</div>'
            );
            $notice.find('.notice-dismiss').on('click', function(){ $notice.remove(); });

            var $anchor = $('.wrap h1').first();
            if ($anchor.length) {
                $anchor.after($notice);
            } else {
                $('.wrap').first().prepend($notice);
            }
            $('html, body').animate({ scrollTop: 0 }, 200);
        });
    })(jQuery);
    </script>
    <?php
}


add_filter('acf/fields/taxonomy/query/key=field_cat_event', 'joyvia_filter_catalog_event_query', 10, 3);
function joyvia_filter_catalog_event_query($args, $field, $post_id) {
    $args['parent'] = 0;
    $args['hide_empty'] = false;
    $args = joyvia_append_active_meta_query($args);
    return $args;
}

add_filter('acf/fields/taxonomy/query/key=field_cat_subevent', 'joyvia_filter_catalog_subevent_query', 10, 3);
function joyvia_filter_catalog_subevent_query($args, $field, $post_id) {
    $args['hide_empty'] = false;

    $parent_id = joyvia_resolve_selected_event_id($post_id);

    if ($parent_id > 0) {
        $args['parent'] = $parent_id;
    } else {
        $root_ids = get_terms([
            'taxonomy'   => 'event',
            'parent'     => 0,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);
        if (!is_wp_error($root_ids) && !empty($root_ids)) {
            $args['exclude'] = $root_ids;
        }
    }

    $args = joyvia_append_active_meta_query($args);
    return $args;
}

function joyvia_resolve_selected_event_id($post_id) {
    if (!empty($_POST['joyvia_selected_event']) && is_numeric($_POST['joyvia_selected_event'])) {
        return (int) $_POST['joyvia_selected_event'];
    }

    if (!empty($_POST['acf']['field_cat_event'])) {
        return (int) $_POST['acf']['field_cat_event'];
    }

    if (!empty($_POST['field_form_data'])) {
        $form = $_POST['field_form_data'];
        if (is_string($form)) {
            $decoded = [];
            parse_str($form, $decoded);
            if (!empty($decoded['acf']['field_cat_event'])) {
                return (int) $decoded['acf']['field_cat_event'];
            }
        } elseif (is_array($form) && !empty($form['acf']['field_cat_event'])) {
            return (int) $form['acf']['field_cat_event'];
        }
    }

    if ($post_id && is_numeric($post_id)) {
        $stored = (int) get_post_meta((int) $post_id, 'catalog_event', true);
        if ($stored > 0) return $stored;
    }

    return 0;
}

add_filter('acf/fields/taxonomy/query/key=field_cat_spec', 'joyvia_filter_catalog_spec_query', 10, 3);
function joyvia_filter_catalog_spec_query($args, $field, $post_id) {
    $args['hide_empty'] = false;
    $args = joyvia_append_active_meta_query($args);
    return $args;
}

add_filter('acf/fields/taxonomy/query/key=field_cat_profession', 'joyvia_filter_catalog_profession_query', 10, 3);
function joyvia_filter_catalog_profession_query($args, $field, $post_id) {
    $args['hide_empty'] = false;
    $args = joyvia_append_active_meta_query($args);
    return $args;
}


add_action('admin_footer-post.php',     'joyvia_inject_subevent_dependency_js');
add_action('admin_footer-post-new.php', 'joyvia_inject_subevent_dependency_js');
function joyvia_inject_subevent_dependency_js() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'catalog_page') return;
    ?>
    <script>
    (function($){
        function getSelectedEventId() {
            var $sel = $('[data-key="field_cat_event"] select').first();
            if (!$sel.length) return 0;
            var v = $sel.val();
            return v && /^\d+$/.test(v) ? parseInt(v, 10) : 0;
        }

        $.ajaxPrefilter(function(options, originalOptions, jqXHR){
            if (!options || !options.data) return;

            var isFormData = (typeof FormData !== 'undefined' && options.data instanceof FormData);
            var dataStr = '';

            if (typeof options.data === 'string') {
                dataStr = options.data;
            } else if (isFormData) {
                var act  = options.data.get ? (options.data.get('action') || '')   : '';
                var fkey = options.data.get ? (options.data.get('field_key') || '') : '';
                dataStr = 'action=' + act + '&field_key=' + fkey;
            } else if (typeof options.data === 'object') {
                try { dataStr = $.param(options.data); } catch (e) { return; }
            }

            if (dataStr.indexOf('action=acf%2Ffields%2Ftaxonomy%2Fquery') === -1 &&
                dataStr.indexOf('action=acf/fields/taxonomy/query') === -1) return;
            if (dataStr.indexOf('field_key=field_cat_subevent') === -1) return;

            var eventId = getSelectedEventId();

            if (typeof options.data === 'string') {
                options.data += '&joyvia_selected_event=' + encodeURIComponent(eventId);
            } else if (isFormData) {
                if (options.data.has && options.data.has('joyvia_selected_event')) {
                    options.data.set('joyvia_selected_event', eventId);
                } else {
                    options.data.append('joyvia_selected_event', eventId);
                }
            } else if (typeof options.data === 'object') {
                options.data.joyvia_selected_event = eventId;
            }
        });

        $(document).on('change', '[data-key="field_cat_event"] select', function(){
            var $subSel = $('[data-key="field_cat_subevent"] select').first();
            if ($subSel.length) {
                $subSel.val(null).trigger('change');
            }
        });
    })(jQuery);
    </script>
    <?php
}

function joyvia_append_active_meta_query($args) {
    $clause = [
        'relation' => 'OR',
        ['key' => 'is_active', 'value' => '1'],
        ['key' => 'is_active', 'compare' => 'NOT EXISTS'],
    ];
    if (empty($args['meta_query'])) {
        $args['meta_query'] = $clause;
    } else {
        if (!isset($args['meta_query']['relation'])) {
            $args['meta_query'] = ['relation' => 'AND'] + $args['meta_query'];
        }
        $args['meta_query'][] = $clause;
    }
    return $args;
}


add_filter('manage_edit-profession_columns',     'joyvia_term_admin_columns');
add_filter('manage_edit-event_columns',          'joyvia_term_admin_columns');
add_filter('manage_edit-specialization_columns', 'joyvia_term_admin_columns');
add_filter('manage_edit-skill_columns',          'joyvia_term_admin_columns');
function joyvia_term_admin_columns($columns) {
    $new = [];
    foreach ($columns as $k => $v) {
        $new[$k] = $v;
        if ($k === 'name') {
            $new['joyvia_active'] = 'Активна';
        }
    }
    return $new;
}

add_filter('manage_profession_custom_column',     'joyvia_term_admin_column_content', 10, 3);
add_filter('manage_event_custom_column',          'joyvia_term_admin_column_content', 10, 3);
add_filter('manage_specialization_custom_column', 'joyvia_term_admin_column_content', 10, 3);
add_filter('manage_skill_custom_column',          'joyvia_term_admin_column_content', 10, 3);
function joyvia_term_admin_column_content($content, $column_name, $term_id) {
    if ($column_name !== 'joyvia_active') return $content;
    $active = joyvia_is_term_active($term_id);
    return $active
        ? '<span style="color:#2c8a3e;font-weight:600;">● Активна</span>'
        : '<span style="color:#a02020;font-weight:600;">○ Скрыта</span>';
}


function joyvia_catalog_structure_field_keys() {
    return [
        'field_cat_page_type',
        'field_cat_profession',
        'field_cat_event',
        'field_cat_subevent',
        'field_cat_spec',
    ];
}

function joyvia_is_catalog_page_locked($post_id) {
    if (!$post_id || !is_numeric($post_id)) return false;
    $post = get_post((int) $post_id);
    if (!$post || $post->post_type !== 'catalog_page') return false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_post_status'])) {
        $status_to_check = $_POST['original_post_status'];
    } else {
        $status_to_check = $post->post_status;
    }
    return !in_array($status_to_check, ['auto-draft', 'new', 'draft'], true);
}

foreach (joyvia_catalog_structure_field_keys() as $_joyvia_fkey) {
    add_filter('acf/load_field/key=' . $_joyvia_fkey, function($field) {
        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!joyvia_is_catalog_page_locked($post_id)) return $field;
        $field['disabled'] = true;
        $field['instructions'] = '🔒Изменение заблокировано. Для смены типа или привязок — удалите страницу и создайте заново.';
        return $field;
    });

    add_filter('acf/update_value/key=' . $_joyvia_fkey, function($value, $post_id, $field) use ($_joyvia_fkey) {
        if (!joyvia_is_catalog_page_locked($post_id)) return $value;
        return get_field($field['name'], $post_id);
    }, 10, 3);
}
unset($_joyvia_fkey);

add_action('admin_head', 'joyvia_catalog_structure_lock_css');
function joyvia_catalog_structure_lock_css() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'catalog_page' || $screen->base !== 'post') return;
    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if (!joyvia_is_catalog_page_locked($post_id)) return;

    $keys = joyvia_catalog_structure_field_keys();
    $selectors = [];
    foreach ($keys as $k) {
        $selectors[] = '[data-key="' . esc_attr($k) . '"]';
    }
    $sel = implode(', ', $selectors);
    ?>
    <style>
    <?= $sel ?> {
        opacity: .55;
        pointer-events: none;
        user-select: none;
        position: relative;
    }
    <?= $sel ?>::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 10;
        cursor: not-allowed;
    }
    </style>
    <?php
}