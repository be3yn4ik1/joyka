<?php
if (!defined('ABSPATH')) exit;


function joyvia_admin_performer_status_labels() {
    return [
        'draft'       => ['label' => 'Черновик',     'color' => '#8a8a8a', 'bg' => '#f0f0f0'],
        'pending'     => ['label' => 'На модерации', 'color' => '#8a6500', 'bg' => '#fff5cc'],
        'published'   => ['label' => 'Опубликован',  'color' => '#2c8a3e', 'bg' => '#e3f5e7'],
        'rejected'    => ['label' => 'Отклонён',     'color' => '#a02020', 'bg' => '#fde6e6'],
        'deactivated' => ['label' => 'Скрыт',        'color' => '#555',    'bg' => '#e8e8e8'],
    ];
}


add_filter('manage_edit-performer_columns', 'joyvia_admin_performer_columns');
function joyvia_admin_performer_columns($columns) {
    $new = [];
    foreach ($columns as $k => $v) {
        if ($k === 'date') continue;
        $new[$k] = $v;
        if ($k === 'title') {
            $new['joyvia_status']     = 'Статус';
            $new['joyvia_profession'] = 'Профессия';
            $new['joyvia_city']       = 'Город';
        }
    }
    $new['date'] = 'Дата';
    return $new;
}


add_action('manage_performer_posts_custom_column', 'joyvia_admin_performer_column_content', 10, 2);
function joyvia_admin_performer_column_content($column, $post_id) {
    if ($column === 'joyvia_status') {
        $status = get_post_meta($post_id, 'profile_status', true) ?: 'draft';
        $labels = joyvia_admin_performer_status_labels();
        $info   = $labels[$status] ?? ['label' => $status, 'color' => '#555', 'bg' => '#e8e8e8'];
        printf(
            '<span style="display:inline-block;padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600;color:%s;background:%s;">%s</span>',
            esc_attr($info['color']), esc_attr($info['bg']), esc_html($info['label'])
        );
        return;
    }

    if ($column === 'joyvia_profession') {
        $terms = get_the_terms($post_id, 'profession');
        if (empty($terms) || is_wp_error($terms)) {
            echo '—';
            return;
        }
        $names = array_map(function($t) { return esc_html($t->name); }, $terms);
        echo implode(', ', $names);
        return;
    }

    if ($column === 'joyvia_city') {
        $gorod_terms = get_field('gorod', $post_id);
        if (empty($gorod_terms) || !is_array($gorod_terms)) {
            echo '—';
            return;
        }
        $name = '';
        foreach ($gorod_terms as $t) {
            $term = is_object($t) ? $t : get_term((int) $t, 'city');
            if ($term && !is_wp_error($term) && $term->parent == 0) {
                $name = $term->name;
                break;
            }
        }
        echo $name !== '' ? esc_html($name) : '—';
        return;
    }
}


add_filter('manage_edit-performer_sortable_columns', 'joyvia_admin_performer_sortable_columns');
function joyvia_admin_performer_sortable_columns($columns) {
    $columns['joyvia_status'] = 'joyvia_status';
    return $columns;
}


add_action('pre_get_posts', 'joyvia_admin_performer_orderby');
function joyvia_admin_performer_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'performer') return;

    $orderby = $query->get('orderby');
    if ($orderby === 'joyvia_status') {
        $query->set('meta_key', 'profile_status');
        $query->set('orderby', 'meta_value');
    }
}


add_action('restrict_manage_posts', 'joyvia_admin_performer_status_filter');
function joyvia_admin_performer_status_filter($post_type) {
    if ($post_type !== 'performer') return;
    $current = isset($_GET['joyvia_status_filter']) ? sanitize_text_field($_GET['joyvia_status_filter']) : '';
    $labels  = joyvia_admin_performer_status_labels();
    echo '<select name="joyvia_status_filter">';
    echo '<option value="">Все статусы</option>';
    foreach ($labels as $key => $info) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($key),
            selected($current, $key, false),
            esc_html($info['label'])
        );
    }
    echo '</select>';
}


add_action('pre_get_posts', 'joyvia_admin_performer_status_filter_apply');
function joyvia_admin_performer_status_filter_apply($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'performer') return;
    if (empty($_GET['joyvia_status_filter'])) return;

    $value = sanitize_text_field($_GET['joyvia_status_filter']);
    $labels = joyvia_admin_performer_status_labels();
    if (!isset($labels[$value])) return;

    $clause = ['key' => 'profile_status', 'value' => $value];
    $existing = $query->get('meta_query');

    if (empty($existing)) {
        $query->set('meta_query', [$clause]);
    } elseif (is_array($existing) && isset($existing['key'])) {
        $query->set('meta_query', ['relation' => 'AND', $existing, $clause]);
    } else {
        $existing[] = $clause;
        $query->set('meta_query', $existing);
    }
}
