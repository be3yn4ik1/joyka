<?php
get_header();

$current_user = wp_get_current_user();
if (!$current_user->exists()) {
    wp_redirect(home_url('/?login=1'));
    exit;
}

$uid        = $current_user->ID;
$title      = get_the_title();
$user_email = $current_user->user_email;

// 1. Получаем ID поста для работы (Новая архитектура)
$post_id = Joyvia_Profile_Manager::get_editable_post_id($uid);

$cities    = get_terms(['taxonomy' => 'city', 'hide_empty' => false, 'parent' => 0]);
$all_terms = get_terms(['taxonomy' => 'city', 'hide_empty' => false]);
$regions   = [];
if (!is_wp_error($all_terms)) {
    foreach ($all_terms as $term) {
        if ($term->parent != 0) $regions[$term->parent][] = $term;
    }
}

$exp_field   = acf_get_field('field_69df86f51a19f');
$exp_choices = $exp_field['choices'] ?? [];

// Базовые данные из wp_users
$s_first_name   = get_user_meta($uid, 'first_name', true);
$s_last_name    = get_user_meta($uid, 'last_name', true);

// Город и районы из ACF поста
$s_city_term = get_field('gorod', $post_id);
$s_city_id   = 0;
$s_city_name = '';
$s_regions   = [];
if (!empty($s_city_term) && is_array($s_city_term)) {
    foreach ($s_city_term as $t) {
        $term_id = is_object($t) ? $t->term_id : (int)$t;
        $term_obj = get_term($term_id, 'city');
        if (!$term_obj || is_wp_error($term_obj)) continue;
        if ($term_obj->parent == 0) {
            $s_city_id   = $term_obj->term_id;
            $s_city_name = $term_obj->name;
        } else {
            $s_regions[] = $term_obj->term_id;
        }
    }
}

$s_opyt         = get_field('opyt_raboty', $post_id);
$s_opyt_label   = $exp_choices[$s_opyt] ?? $s_opyt;
$s_o_sebe       = wp_strip_all_tags((string)get_field('o_sebe', $post_id));
$s_avatar       = get_field('avatarka', $post_id);
$s_avatar_url   = is_array($s_avatar) ? ($s_avatar['sizes']['medium'] ?? $s_avatar['url'] ?? '') : '';

// Контакты из ACF поста
$kontakty_raw   = get_field('kontaktnye_dannye', $post_id);
$kontakty_list  = is_array($kontakty_raw) ? ($kontakty_raw['kontakt'] ?? []) : [];
$contacts_map   = [];
foreach ($kontakty_list as $k) {
    $contacts_map[mb_strtolower($k['chto_eto'])] = $k['ssylka_na_kontakt'];
}
$s_phone    = $contacts_map['телефон']  ?? '';
$s_telegram = $contacts_map['telegram'] ?? '';

// Шаг 2: Профессия и события (нативные таксономии поста)
$prof_terms = wp_get_object_terms($post_id, 'profession', ['fields' => 'ids']);
$s_profession_id = !empty($prof_terms) && !is_wp_error($prof_terms) ? (int)$prof_terms[0] : 0;

$event_terms = wp_get_object_terms($post_id, 'event', ['fields' => 'ids']);
$s_events = !is_wp_error($event_terms) ? array_map('intval', $event_terms) : [];

$s_event_extra = (array)get_post_meta($post_id, 'selected_event_extra', true);

// Шаг 3: Специализации и навыки
$spec_terms = wp_get_object_terms($post_id, 'specialization', ['fields' => 'ids']);
$s_specs = !is_wp_error($spec_terms) ? array_map('intval', $spec_terms) : [];

$skill_terms = wp_get_object_terms($post_id, 'skill', ['fields' => 'ids']);
$s_skills = !is_wp_error($skill_terms) ? array_map('intval', $skill_terms) : [];

// Шаг 4: Пакеты услуг
$s_packages = (array)get_post_meta($post_id, 'service_packages_data', true);

// Шаг 5: Портфолио
$s_portfolio_raw = get_field('portfolio', $post_id);
$s_portfolio     = is_array($s_portfolio_raw) ? $s_portfolio_raw : [];
$portfolio_js    = [];
foreach ($s_portfolio as $img) {
    if (is_array($img) && isset($img['ID'])) {
        $portfolio_js[] = ['id' => $img['ID'], 'url' => $img['sizes']['medium'] ?? $img['url']];
    } elseif (is_numeric($img)) {
        $portfolio_js[] = ['id' => (int)$img, 'url' => wp_get_attachment_image_url((int)$img, 'medium')];
    }
}
$s_portfolio_videos_raw = get_field('portfolio_videos', $post_id);
$s_portfolio_videos     = is_array($s_portfolio_videos_raw) ? $s_portfolio_videos_raw : [];
$portfolio_videos_js    = [];
foreach ($s_portfolio_videos as $vid) {
    if (is_array($vid) && isset($vid['ID'])) {
        $portfolio_videos_js[] = ['id' => $vid['ID'], 'url' => $vid['url']];
    } elseif (is_numeric($vid)) {
        $portfolio_videos_js[] = ['id' => (int)$vid, 'url' => wp_get_attachment_url((int)$vid)];
    }
}

$s_social = get_field('portfolio_social_acf', $post_id) ?: [];
$s_video_links_raw = get_field('portfolio_video_links_acf', $post_id) ?: [];
$s_video_links = [];
if(is_array($s_video_links_raw)) {
    foreach($s_video_links_raw as $vl) {
        if(!empty($vl['video_url'])) $s_video_links[] = $vl['video_url'];
    }
}

$s_status = get_field('profile_status', $post_id) ?: 'draft';
$status_labels = [
    'draft'       => 'Не опубликован',
    'pending'     => 'На модерации',
    'published'   => 'Опубликован',
    'rejected'    => 'Отклонен',
    'deactivated' => 'Скрыт',
];
$current_status_label = $status_labels[$s_status] ?? 'Не опубликован';

// UI навигация
$s_current_step = (int)(get_user_meta($uid, 'profile_setup_current_step', true) ?: 0);
$steps_meta = [
    ['О себе', 'Шаг 1 из 5'],
    ['Профессия и события', 'Шаг 2 из 5'],
    ['Специализация', 'Шаг 3 из 5'],
    ['Пакеты услуг', 'Шаг 4 из 5'],
    ['Портфолио', 'Шаг 5 из 5'],
];
?>

<section class="section section--content-title">
    <h1 class="section__title"><?php echo esc_html($title); ?></h1>
</section>

<section class="section section--content all">
    <p class="f15">Заполните профиль, чтобы получать заявки от клиентов</p>
    <div class="no-publicate f13 status-<?= esc_attr($s_status) ?>">
        <?= esc_html($current_status_label) ?>
    </div>
</section>

<section class="steps" id="steps-nav">
    <?php foreach ($steps_meta as $i => [$label, $step_txt]): ?>
    <div class="step-item <?= $i < $s_current_step ? 'done' : ($i === $s_current_step ? 'active' : '') ?>">
        <div class="step-num" id="sn<?= $i ?>"><?= $i + 1 ?></div>
        <div class="step-label">
            <strong><?= esc_html($step_txt) ?></strong><span><?= esc_html($label) ?></span>
        </div>
        <div class="step-underline"></div>
    </div>
    <?php endforeach; ?>
</section>

<section class="step-reg">

<!-- ===== ШАГ 1: О СЕБЕ ===== -->
<div class="step-content <?= $s_current_step === 0 ? 'active' : '' ?>" goTo="0">
    <div class="wrap">
        <h2>Основная информация</h2>
        <div class="form-avatar" id="avatar-zone">
            <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
            <div class="form-avatar_image">
                <img id="avatar-preview" src="<?= $s_avatar_url ? esc_url($s_avatar_url) : '/wp-content/themes/ava.svg' ?>" alt="Avatar">
            </div>
            <div class="form-avatar_txt">
                <p id="avatar-upload-status">Загрузить</p>
                <span>JPG, PNG, WEBP<br>Не более 10 MB</span>
            </div>
        </div>
        <div id="avatar-error" class="field-error" style="display:none;color:red;font-size:13px;margin-top:6px;"></div>

        <div class="form-row mw400px">
            <div class="form-group">
                <div class="fieldinput">
                    <input type="text" name="first-name" id="inp-first-name" placeholder=" " value="<?= esc_attr($s_first_name) ?>" required />
                    <label>Имя<span class="required">*</span></label>
                </div>
            </div>
            <div class="form-group">
                <div class="fieldinput">
                    <input type="text" name="last-name" id="inp-last-name" placeholder=" " value="<?= esc_attr($s_last_name) ?>" required />
                    <label>Фамилия<span class="required">*</span></label>
                </div>
            </div>
        </div>

        <div class="form-group mw400px">
            <input type="hidden" id="city-id-hidden" value="<?= esc_attr($s_city_id) ?>">
            <div class="fieldinput city-wrap">
                <input type="text" name="gorod" id="city-input" placeholder=" " value="<?= esc_attr($s_city_name) ?>" required autocomplete="new-password" />
                <label>Город<span class="required">*</span></label>
                <div class="result">
                    <?php if (!is_wp_error($cities) && !empty($cities)): ?>
                        <?php foreach ($cities as $city): ?>
                            <span data-id="<?= esc_attr($city->term_id) ?>"><?= esc_html($city->name) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="empty" style="display:none;pointer-events:none;padding:14px 20px;">Ничего не найдено</div>
                </div>
            </div>
            <div class="fieldinput region-wrap" style="<?= ($s_city_id && !empty($regions[$s_city_id])) ? '' : 'display:none;' ?>">
                <input type="text" name="region_display" id="region-display" placeholder=" " readonly style="cursor:pointer;" autocomplete="new-password"/>
                <label>Район работы<span class="required req-asterisk" style="<?= ($s_city_id && !empty($regions[$s_city_id])) ? '' : 'display:none;' ?>">*</span></label>
                <div class="result">
                    <?php foreach ($regions as $parent_id => $child_regions): ?>
                        <div class="region-group" data-parent="<?= esc_attr($parent_id) ?>" style="<?= ($parent_id == $s_city_id) ? 'display:flex;' : 'display:none;' ?>">
<?php foreach ($child_regions as $region): ?>
    <label>
        <input type="checkbox" name="region[]" value="<?= esc_attr($region->term_id) ?>" <?= in_array($region->term_id, $s_regions) ? 'checked' : '' ?>>
        <span><?= esc_html($region->name) ?></span>
    </label>
<?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="form-group mt30">
            <h2>Опыт работы</h2>
            <input type="hidden" id="opyt-val-hidden" value="<?= esc_attr($s_opyt) ?>">
            <div class="fieldinput opyt_raboty mw400px">
                <input type="text" name="opyt_raboty" id="opyt-display" placeholder=" " readonly style="cursor:pointer;" value="<?= esc_attr($s_opyt_label) ?>" />
                <label>Опыт работы</label>
                <div class="result">
                    <?php if (!empty($exp_choices)): ?>
                        <?php foreach ($exp_choices as $val => $label): ?>
                            <span data-val="<?= esc_attr($val) ?>"><?= esc_html($label) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="wrap">
        <h2>О себе</h2>
        <p class="f15 mw400px c646 mt15 lh13">Опишите, чем вы занимаетесь, с какими задачами работаете и в каких случаях клиенту стоит выбрать именно вас.</p>
        <div class="form-group mt30">
            <div class="fieldinput">
                <textarea rows="6" name="osebe" id="osebe-textarea" placeholder=" " required><?= esc_textarea($s_o_sebe) ?></textarea>
                <label class="lt005">Расскажите о себе<span class="required">*</span></label>
            </div>
            <p class="f13 txt-num"><span class="count-check" id="osebe-count"><?= mb_strlen($s_o_sebe) ?></span> из 1200 символов (минимум 200)</p>
        </div>
    </div>

    <div class="wrap contact-inform">
        <h2>Контактные данные</h2>
        <p class="f15 mw400px c646 mt15 lh13">Эти контакты используются для уведомлений о новых заявках и сообщениях</p>
        <div class="form-row mw400px mt30">
            <div class="form-group">
                <div class="fieldinput">
                    <input type="tel" name="phone-contact" id="inp-phone" placeholder=" " value="<?= esc_attr($s_phone) ?>" required />
                    <label>Телефон<span class="required">*</span></label>
                </div>
            </div>
            <div class="form-group">
                <div class="fieldinput">
                    <input type="email" name="email-contact" id="inp-email" placeholder=" " value="<?= esc_attr($user_email) ?>" required />
                    <label>Email<span class="required">*</span></label>
                </div>
            </div>
            <p class="f15 mw400px mt30 lh13">Подключите Telegram, чтобы мгновенно получать уведомления о заявках</p>
            <div class="form-group mt22">
                <div class="fieldinput">
                    <input type="text" name="telegram-contact" id="inp-telegram" placeholder=" " value="<?= esc_attr($s_telegram) ?>" />
                    <label>Telegram</label>
                </div>
            </div>
        </div>
    </div>
    <div class="step-button mt30 dflex jstc-sb alit-ct">
        <div class="dflex g10">
            <a href="/" class="step-button-back">Выйти</a>
            <button class="step-button-next" onclick="saveAndGoTo(0,1)">Далее</button>
        </div>
        <div class="step-footername">Шаг 1 из 5</div>
    </div>
</div>

<!-- ===== ШАГ 2: ПРОФЕССИЯ И СОБЫТИЯ ===== -->
<div class="step-content <?= $s_current_step === 1 ? 'active' : '' ?>" goTo="1">
    <div class="dflex g10 dflex-wrap profession-wrap">
        <div class="wrap">
            <h2>Ваша профессия</h2>
            <p class="f15 mw310px c646 mt15 lh13">Выберите основную. Определяет ваш главный каталог.</p>
            <?php $professions = get_terms(['taxonomy' => 'profession', 'hide_empty' => false]);
            if (!empty($professions) && !is_wp_error($professions)): ?>
            <div class="dgrid grid-3 m-grid-2 list-left-professia mt30 m-mt20 g10">
                <?php foreach ($professions as $profession):
                    $icon_url = get_field('profession_icon_svg', 'profession_' . $profession->term_id); ?>
                <label class="profession-radio-label">
                    <input type="radio" name="profession" value="<?= esc_attr($profession->term_id) ?>" class="profession-radio-input" <?= ($s_profession_id == $profession->term_id) ? 'checked' : '' ?> required>
                    <div class="profession-card">
                        <?php if ($icon_url): ?>
                        <img src="<?= esc_url($icon_url) ?>" alt="<?= esc_attr($profession->name) ?>" class="profession-icon">
                        <?php endif; ?>
                        <span class="profession-name"><?= esc_html($profession->name) ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="wrap list-right-professia dfld-col" style="<?= $s_profession_id ? '' : 'display:none;' ?>">
            <div class="dflex jstc-sb"><h2>Где вы появитесь</h2> <span class="list-right-professia_count">0 каталогов</span></div>
            <div class="list-right-professia_dynamiclist"></div>
        </div>
        <div class="wrap list-bottom-event" style="<?= $s_profession_id ? '' : 'display:none;' ?>">
            <div class="dflex dfld-col">
                <h2>На каких мероприятиях вы работаете?</h2>
                <p class="f15 m-f13 mw310px c646 mt15 m-mt15 lh13">Выберите все подходящие. Вы появитесь в каталоге каждого события</p>
                <div class="list-bottom-event_dynamiclist mt30 g15 m-mt20">
                    <?php
                    $parent_events = get_terms(['taxonomy' => 'event', 'parent' => 0, 'hide_empty' => false]);
                    if (!empty($parent_events) && !is_wp_error($parent_events)):
                        foreach ($parent_events as $parent):
                            $has_extra   = get_field('has_extra_checkboxes', 'event_' . $parent->term_id);
                            $extra_title = get_field('extra_checkboxes_title', 'event_' . $parent->term_id);
                            $extra_opts  = get_field('extra_checkboxes_options', 'event_' . $parent->term_id);
                            $children    = get_terms(['taxonomy' => 'event', 'child_of' => $parent->term_id, 'hide_empty' => false]);
                            $parent_checked = in_array($parent->term_id, $s_events);
                    ?>
                    <div class="event-group-wrapper">
                        <label class="event-checkbox-label">
                            <input type="checkbox" name="events[]" value="<?= esc_attr($parent->term_id) ?>" class="parent-event-cb" data-name="<?= esc_attr($parent->name) ?>" <?= $parent_checked ? 'checked' : '' ?>>
                            <div class="event-checkbox-card">
                                <p><?= esc_html($parent->name) ?></p>
                                <?php if ($parent->description): ?><span><?= esc_html($parent->description) ?></span><?php endif; ?>
                            </div>
                        </label>
                        <?php if (!empty($children) || $has_extra): ?>
                        <div class="event-checkbox-dynamiclist" style="<?= $parent_checked ? 'display:block;' : 'display:none;' ?>">
                            <?php foreach ($children as $child):
                                $child_checked = in_array($child->term_id, $s_events); ?>
                            <label class="event-checkbox-label">
                                <input type="checkbox" name="events[]" value="<?= esc_attr($child->term_id) ?>" class="child-event-cb" data-name="<?= esc_attr($child->name) ?>" <?= $child_checked ? 'checked' : '' ?>>
                                <div class="event-checkbox-card"><p><?= esc_html($child->name) ?></p></div>
                            </label>
                            <?php endforeach; ?>
                            <?php if ($has_extra && $extra_opts): ?>
                            <div class="event-checkbox-dop">
                                <p><?= esc_html($extra_title) ?></p>
                                <?php foreach (array_filter(array_map('trim', explode("\n", str_replace("\r", "", $extra_opts)))) as $opt):
                                    $saved_extras_for_parent = $s_event_extra[$parent->term_id] ?? [];
                                    $extra_checked = in_array($opt, $saved_extras_for_parent); ?>
                                <label class="event-checkbox-dop-label">
                                    <input type="checkbox" name="event_extra[<?= esc_attr($parent->term_id) ?>][]" value="<?= esc_attr($opt) ?>" <?= $extra_checked ? 'checked' : '' ?>>
                                    <div class="event-checkbox-dop-p"><?= esc_html($opt) ?></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="step-button mt30 dflex jstc-sb alit-ct">
        <div class="dflex g10">
            <button class="step-button-back l-arrow" onclick="goTo(0)">Назад</button>
            <button class="step-button-next" onclick="saveAndGoTo(1,2)">Далее</button>
        </div>
        <div class="step-footername">Шаг 2 из 5</div>
    </div>
</div>

<!-- ===== ШАГ 3: СПЕЦИАЛИЗАЦИЯ ===== -->
<div class="step-content <?= $s_current_step === 2 ? 'active' : '' ?>" goTo="2">
    <div class="dflex g10 dflex-wrap specialization-wrap">
        <div class="wrap dfld-col">
            <h2>Специализации</h2>
            <p class="f15 mw310px c646 mt12 lh13">Уточните что именно вы снимаете. Это добавит вас в узкие каталоги с меньшей конкуренцией.</p>
            <div class="specializationc-list">
                <h2>Виды специализации</h2>
                <p class="f15 mw310px c646 mt19 lh13">Выберите все, которые вы реально делаете на профессиональном уровне</p>
                <div class="specializationc-checkbox-dynamiclist">
                    <?php if ($s_profession_id): ?>
                    <div class="loader"></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="skill-list">
                <h2>Теги навыков</h2>
                <p class="f15 mw310px c646 mt19 lh13">Что вы умеете — клиенты видят это на вашей странице</p>
                <div class="skill-checkbox-dynamiclist">
                    <?php if ($s_profession_id): ?>
                    <div class="loader"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="wrap list-right-professia dfld-col" style="<?= $s_profession_id ? '' : 'display:none;' ?>">
            <div class="dflex jstc-sb"><h2>Где вы появитесь</h2> <span class="list-right-professia_count">0 каталогов</span></div>
            <div class="list-right-professia_dynamiclist"></div>
        </div>
    </div>
    <div class="step-button mt30 dflex jstc-sb alit-ct">
        <div class="dflex g10">
            <button class="step-button-back l-arrow" onclick="goTo(1)">Назад</button>
            <button class="step-button-next" onclick="saveAndGoTo(2,3)">Далее</button>
        </div>
        <div class="step-footername">Шаг 3 из 5</div>
    </div>
</div>

<!-- ===== ШАГ 4: ПАКЕТЫ УСЛУГ ===== -->
<div class="step-content <?= $s_current_step === 3 ? 'active' : '' ?>" goTo="3">
    <div class="dflex w100pr">
        <div class="wrap dfld-col w100pr">
            <h2>Пакеты услуг и цены</h2>
            <p class="f15 mw425px c646 mt12 lh13">Создайте пакеты для выбранных событий и специализаций. Цена в каталоге — минимальная из ваших пакетов.</p>
            <div class="price-list mt30" id="step4-price-list"></div>
        </div>
    </div>
    <div class="step-button mt30 dflex jstc-sb alit-ct">
        <div class="dflex g10">
            <button class="step-button-back l-arrow" onclick="goTo(2)">Назад</button>
            <button class="step-button-next" onclick="saveAndGoTo(3,4)">Далее</button>
        </div>
        <div class="step-footername">Шаг 4 из 5</div>
    </div>
</div>

<!-- ===== ШАГ 5: ПОРТФОЛИО ===== -->
<div class="step-content <?= $s_current_step === 4 ? 'active' : '' ?>" goTo="4">
    <div class="dflex w100pr">
        <div class="wrap dfld-col w100pr portfolio-wrap">
            <h2>Портфолио</h2>
            <p class="f13 mw425px c646 mt12 lh13">Загрузите лучшие работы — клиенты смотрят их в первую очередь.</p>
            <div class="portfolio-tabs">
                <button class="portfolio-tab active" onclick="showTab('portfoliophoto', this)">Фотографии</button>
                <button class="portfolio-tab" onclick="showTab('portfoliovideo', this)">Видео</button>
                <button class="portfolio-tab" onclick="showTab('portfoliolinks', this)">Ссылки</button>
            </div>
            <div class="portfolio-content">
                <!-- Фотографии -->
                <div id="portfoliophoto" class="tab-block">
                    <div class="preview-img-photo" id="portfolio-photo-preview"></div>
                    <div class="leaders" id="portfolio-photo-dropzone">
                        <input type="file" id="portfolio-photo-input" name="portfoliophoto[]" accept="image/jpeg,image/png" multiple style="display:none">
                        <p>Выберите файл или перетащите его сюда</p>
                        <span>JPEG, PNG до 20 файлов, максимум 10 МБ каждый</span>
                        <div class="dwnloadportfoliophoto" onclick="document.getElementById('portfolio-photo-input').click()">Загрузить файл</div>
                    </div>
                </div>
                <!-- Видео -->
                <div id="portfoliovideo" class="tab-block" style="display:none;">
                    <div class="tab-block-description mw425px">Видео — самый удивительный способ показать как вы работаете. Вставьте ссылку.</div>
					<div class="preview-img-video" id="portfolio-video-preview"></div>
					<div class="leaders" id="portfolio-video-dropzone">
                        <input type="file" id="portfolio-video-input" name="portfoliovideo[]" accept="video/mp4,video/quicktime" multiple style="display:none">
                        <p>Выберите файл или перетащите его сюда</p>
                        <span>MP4, MOV до 5 файлов, максимум 500 МБ</span>
                        <div class="dwnloadportfoliovideo" onclick="document.getElementById('portfolio-video-input').click()">Загрузить файл</div>
                    </div>
                    <p class="leaders-txt1">или вставьте ссылку</p>
                    <p class="leaders-txt2">YOUTUBE / RUTUBE / VIMEO / VK ВИДЕО</p>
                    <div class="portfoliovideo-links" id="video-links-container">
                        <div class="portfoliovideo-link">
                            <input type="url" placeholder="https://youtube.com/watch?v=" name="link">
                            <div class="portfoliovideo-link-remove" onclick="removeVideoLink(this)"></div>
                        </div>
                    </div>
                    <div class="add-portfoliovideo-link" onclick="addVideoLink()">Добавить ещё ссылку</div>
                </div>
                <!-- Ссылки -->
                <div id="portfoliolinks" class="tab-block" style="display:none;">
                    <div class="tab-block-description">Укажите ваши профили и сайты — клиенты смогут посмотреть больше работ.</div>
                    <p class="leaders-txt2">INSTAGRAM / ВКОНТАКТЕ</p>
                    <input type="url" id="social-instagram" placeholder="https://instagram.com/ваш_ник" name="link-inst" value="<?= esc_attr($s_social['instagram'] ?? '') ?>">
                    <input type="url" id="social-vk" placeholder="https://vk.com/ваш_ник" name="link-vk" value="<?= esc_attr($s_social['vk'] ?? '') ?>">
                    <p class="leaders-txt2">ЛИЧНЫЙ САЙТ ИЛИ ПОРТФОЛИО</p>
                    <input type="url" id="social-website" placeholder="https://ваша ссылка" name="link-mysite" value="<?= esc_attr($s_social['website'] ?? '') ?>">
                    <p class="leaders-txt2">ДРУГИЕ ССЫЛКИ <span>— опционально</span></p>
                    <input type="url" id="social-other" placeholder="https://ваша ссылка" name="link-other" value="<?= esc_attr($s_social['other'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    <div class="step-button mt30 dflex jstc-sb alit-ct">
        <div class="dflex g10">
            <button class="step-button-back l-arrow" onclick="goTo(3)">Назад</button>
            <button class="step-button-next finish-btn" onclick="saveAndPublish()">Опубликовать</button>
        </div>
        <div class="step-footername">Шаг 5 из 5</div>
    </div>
</div>

</section>

<div class="modal-overlay" id="modal-packet-remove-btn">
    <div class="modal-box">
        <div class="modal-header dflex jstc-sb"><span data-action="closemodal">Назад</span> <button class="modal-close" data-action="closemodal">×</button></div>
		<p>Удалить услугу?</p>
        <div class="modal-body">
            <button class="btn-primarydel w100pr" id="packet-remove-modal">Удалить</button>
        </div>
    </div>
</div>



<!-- Уведомление -->
<div id="joyvia-notify" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;padding:14px 20px;border-radius:8px;font-size:14px;max-width:340px;box-shadow:0 4px 16px rgba(0,0,0,.15);"></div>

<script>
const JOYVIA = {
    nonce: '<?= wp_create_nonce("joyvia_profile_nonce") ?>',
    ajax: '<?= admin_url("admin-ajax.php") ?>',
    uid: <?= $uid ?>,
    redirect: '<?= esc_url(get_author_posts_url($uid)) ?>',
    currentStep: <?= $s_current_step ?>,
    savedProfessionId: <?= $s_profession_id ?>,
    savedSpecs: <?= json_encode($s_specs) ?>,
    savedSkills: <?= json_encode($s_skills) ?>,
    savedPackages: <?= json_encode($s_packages) ?>,
    portfolioPhotos: <?= json_encode($portfolio_js) ?>,
    portfolioVideos: <?= json_encode($portfolio_videos_js) ?>, 
    videoLinks: <?= json_encode($s_video_links) ?>,
    social: <?= json_encode($s_social) ?>,
};

// ===========================
// УВЕДОМЛЕНИЯ
// ===========================
function notify(msg, type = 'success') {
    const el = document.getElementById('joyvia-notify');
    el.textContent = msg;
    el.style.display = 'block';
    el.style.background = type === 'error' ? '#ffe0e0' : '#e0ffe8';
    el.style.color = type === 'error' ? '#c00' : '#080';
    el.style.border = type === 'error' ? '1px solid #fcc' : '1px solid #9d9';
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.display = 'none'; }, 4000);
}

function showFieldError(el, msg) {
    el.style.borderColor = '#e53';
    let err = el.parentElement.querySelector('.field-err-msg');
    if (!err) { err = document.createElement('p'); err.className = 'field-err-msg'; err.style.cssText = 'color:#e53;font-size:12px;margin:4px 0 0;'; el.parentElement.appendChild(err); }
    err.textContent = msg;
}
function clearFieldErrors(container) {
    container.querySelectorAll('.field-err-msg').forEach(e => e.remove());
    container.querySelectorAll('[style*="border-color"]').forEach(e => e.style.borderColor = '');
}

// ===========================
// НАВИГАЦИЯ ШАГОВ
// ===========================

// goTo определена здесь (вне DOMContentLoaded), чтобы onclick-атрибуты могли вызывать её сразу
function goTo(step) {
    document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
    const target = document.querySelector('.step-content[goTo="' + step + '"]');
    if (target) target.classList.add('active');
    document.querySelectorAll('#steps-nav .step-item').forEach((el, i) => {
        el.classList.toggle('done', i < step);
        el.classList.toggle('active', i === step);
        const numEl = el.querySelector('.step-num');
        if (numEl) { numEl.textContent = i + 1; }
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (typeof JOYVIA !== 'undefined') JOYVIA.currentStep = step;
}

document.addEventListener('DOMContentLoaded', function() {
    // goTo уже определена глобально выше — ничего переопределять не нужно

    // ===========================
    // ШАГ 1: ИНИЦИАЛИЗАЦИЯ
    // ===========================
    const cityInput  = document.querySelector('input[name="gorod"]');
    const cityResult = document.querySelector('.city-wrap .result');
    const cityItems  = cityResult ? Array.from(cityResult.querySelectorAll('span[data-id]')) : [];
    const cityEmpty  = cityResult ? cityResult.querySelector('.empty') : null;
    const regionWrap = document.querySelector('.region-wrap');
    const regionResult = document.querySelector('.region-wrap .result');
    const regionDisplay = document.querySelector('input[name="region_display"]');
    const regionGroups  = Array.from(document.querySelectorAll('.region-group'));
    const reqAsterisk   = document.querySelector('.req-asterisk');
    const expInput   = document.getElementById('opyt-display');
    const expWrap    = document.querySelector('.opyt_raboty');
    const expResult  = expWrap ? expWrap.querySelector('.result') : null;
    const expHidden  = document.getElementById('opyt-val-hidden');
    const osebeInput = document.getElementById('osebe-textarea');
    const osebeCount = document.getElementById('osebe-count');
    let isRegionReq  = !!(<?= $s_city_id ? 'true' : 'false' ?> && <?= !empty($regions[$s_city_id]) ? 'true' : 'false' ?>);

    // Инициализируем счётчик района если есть сохранённые
if (regionResult && regionDisplay) {
        const checkedRegions = regionResult.querySelectorAll('input:checked');
        if (checkedRegions.length === 1) {
            regionDisplay.value = checkedRegions[0].nextElementSibling.textContent;
        } else if (checkedRegions.length > 1) {
            regionDisplay.value = 'Выбрано: ' + checkedRegions.length;
        } else {
            regionDisplay.value = '';
        }
    }

    const checkCity = () => cityItems.some(i => i.textContent === cityInput.value);

    if (cityInput && cityResult) {
        cityInput.addEventListener('focus', () => {
            cityResult.classList.add('active');
            if (regionResult) regionResult.classList.remove('active');
        });
        cityInput.addEventListener('input', () => {
            const val = cityInput.value.toLowerCase();
            let matches = 0;
            cityItems.forEach(i => { const m = i.textContent.toLowerCase().includes(val); i.style.display = m ? 'block' : 'none'; if (m) matches++; });
            if (cityEmpty) cityEmpty.style.display = matches ? 'none' : 'block';
        });
        cityResult.addEventListener('mousedown', (e) => {
            const target = e.target.closest('span[data-id]');
            if (!target) return;
            e.preventDefault();
            cityInput.value = target.textContent;
            cityResult.classList.remove('active');
            cityInput.style.borderColor = '';
            const cityId = target.getAttribute('data-id');
            document.getElementById('city-id-hidden').value = cityId;
            regionGroups.forEach(g => g.style.display = 'none');
            document.querySelectorAll('input[name="region[]"]').forEach(cb => cb.checked = false);
            if (regionDisplay) regionDisplay.value = '';
            const activeGroup = document.querySelector('.region-group[data-parent="' + cityId + '"]');
            if (activeGroup && regionWrap) {
                regionWrap.style.display = 'block';
                activeGroup.style.display = 'flex';
                isRegionReq = true;
                if (reqAsterisk) reqAsterisk.style.display = 'inline';
            } else if (regionWrap) {
                regionWrap.style.display = 'none';
                isRegionReq = false;
                if (reqAsterisk) reqAsterisk.style.display = 'none';
            }
        });
        cityInput.addEventListener('blur', () => {
            setTimeout(() => {
                if (!checkCity()) { cityInput.value = ''; document.getElementById('city-id-hidden').value = ''; if (regionWrap) regionWrap.style.display = 'none'; isRegionReq = false; }
            }, 150);
        });
    }

if (regionDisplay && regionResult) {
        regionDisplay.addEventListener('mousedown', (e) => {
            e.preventDefault();
            regionResult.classList.toggle('active');
        });
        regionResult.addEventListener('change', (e) => {
            if (e.target.matches('input[type="checkbox"]')) {
                const checkedRegions = regionResult.querySelectorAll('input:checked');
                if (checkedRegions.length === 1) {
                    regionDisplay.value = checkedRegions[0].nextElementSibling.textContent;
                } else if (checkedRegions.length > 1) {
                    regionDisplay.value = 'Выбрано: ' + checkedRegions.length;
                } else {
                    regionDisplay.value = '';
                }
            }
        });
    }

    if (expInput && expResult) {
        expInput.addEventListener('mousedown', (e) => { e.preventDefault(); expResult.classList.toggle('active'); });
        expResult.addEventListener('mousedown', (e) => {
            if (e.target.tagName === 'SPAN') {
                e.preventDefault();
                expInput.value = e.target.textContent;
                expHidden.value = e.target.getAttribute('data-val');
                expResult.classList.remove('active');
                expInput.style.borderColor = '';
            }
        });
    }

    if (osebeInput && osebeCount) {
        osebeInput.addEventListener('input', (e) => {
            const t = e.target; t.value = t.value.slice(0, 1200);
            osebeCount.textContent = t.value.length;
            osebeCount.style.color = t.value.length < 200 ? 'red' : '';
        });
        osebeCount.style.color = parseInt(osebeCount.textContent) < 200 ? 'red' : '';
    }

    document.addEventListener('click', (e) => {
        if (cityResult && !e.target.closest('.city-wrap')) cityResult.classList.remove('active');
        if (regionResult && !e.target.closest('.region-wrap')) regionResult.classList.remove('active');
        if (expResult && !e.target.closest('.opyt_raboty')) expResult.classList.remove('active');
    });

    // ===========================
    // АВАТАР
    // ===========================
    const avatarZone  = document.getElementById('avatar-zone');
    const avatarInput = document.getElementById('avatar-file-input');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarStatus  = document.getElementById('avatar-upload-status');
    const avatarError   = document.getElementById('avatar-error');

    avatarZone.addEventListener('click', () => avatarInput.click());
    avatarInput.addEventListener('change', async () => {
        const file = avatarInput.files[0];
        if (!file) return;
        if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
            avatarError.textContent = 'Допустимые форматы: JPG, PNG, WEBP';
            avatarError.style.display = 'block'; return;
        }
        if (file.size > 10 * 1024 * 1024) {
            avatarError.textContent = 'Размер файла не должен превышать 10 МБ';
            avatarError.style.display = 'block'; return;
        }
        avatarError.style.display = 'none';
        const reader = new FileReader();
        reader.onload = e => { avatarPreview.src = e.target.result; };
        reader.readAsDataURL(file);
        avatarStatus.textContent = 'Загрузка...';
        const fd = new FormData();
        fd.append('action', 'joyvia_upload_avatar');
        fd.append('nonce', JOYVIA.nonce);
        fd.append('avatar', file);
        try {
            const r = await fetch(JOYVIA.ajax, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) { avatarPreview.src = d.data.url; avatarStatus.textContent = 'Загружено ✓'; avatarError.style.display = 'none'; }
            else { avatarError.textContent = 'Ошибка загрузки: ' + (d.data || ''); avatarError.style.display = 'block'; avatarStatus.textContent = 'Загрузить'; }
        } catch(e) { avatarError.textContent = 'Ошибка сети'; avatarError.style.display = 'block'; avatarStatus.textContent = 'Загрузить'; }
    });

    // ===========================
    // ШАГ 2: ПРОФЕССИЯ И СОБЫТИЯ
    // ===========================
    const profRadios      = document.querySelectorAll('.profession-radio-input');
    const eventBlock      = document.querySelector('.list-bottom-event');
    const rightProfBlocks = document.querySelectorAll('.list-right-professia');
    const liveLists       = document.querySelectorAll('.list-right-professia_dynamiclist');
    const liveCounts      = document.querySelectorAll('.list-right-professia_count');

    function declOfNum(n, f) {
        n = Math.abs(n) % 100; const n1 = n % 10;
        if (n > 10 && n < 20) return f[2];
        if (n1 > 1 && n1 < 5) return f[1];
        if (n1 === 1) return f[0];
        return f[2];
    }

function updateLivePreview() {
    let items = [];
    const selProf = document.querySelector('.profession-radio-input:checked');
    if (selProf) items.push('<p>' + selProf.closest('.profession-radio-label').querySelector('.profession-name').textContent + '</p>');
    document.querySelectorAll('.parent-event-cb:checked').forEach(cb => items.push('<p>' + cb.dataset.name + '</p>'));
    document.querySelectorAll('.child-event-cb:checked').forEach(cb => items.push('<p>' + cb.dataset.name + '</p>'));
    document.querySelectorAll('.specializationc-checkbox-dynamiclist input:checked').forEach(cb => { if (cb.nextElementSibling) items.push('<p>' + cb.nextElementSibling.textContent + '</p>'); });
    const count = items.length;
    const word = declOfNum(count, ['каталог', 'каталога', 'каталогов']);
    const html = items.join('');
    liveLists.forEach(l => l.innerHTML = html);
    liveCounts.forEach(c => { c.textContent = count + ' ' + word; c.style.display = count > 0 ? 'inline-block' : 'none'; });
}

    async function loadProfData(profId, savedSpecs, savedSkills) {
        const specCont  = document.querySelector('.specializationc-checkbox-dynamiclist');
        const skillCont = document.querySelector('.skill-checkbox-dynamiclist');
        if (specCont)  specCont.innerHTML  = '<div class="loader"></div>';
        if (skillCont) skillCont.innerHTML = '<div class="loader"></div>';
        const fd = new FormData();
        fd.append('action', 'get_prof_data_setup');
        fd.append('prof_id', profId);
        savedSpecs.forEach(s => fd.append('saved_specs[]', s));
        savedSkills.forEach(s => fd.append('saved_skills[]', s));
        try {
            const r = await fetch(JOYVIA.ajax, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                if (specCont)  specCont.innerHTML  = d.data.specs  || '<p class="f14 c646">Нет специализаций для этой профессии</p>';
                if (skillCont) skillCont.innerHTML = d.data.skills || '<p class="f14 c646">Нет навыков для этой профессии</p>';
                updateLivePreview();
            }
        } catch(e) {}
    }

    profRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (eventBlock) eventBlock.style.display = 'block';
            rightProfBlocks.forEach(b => b.style.display = 'flex');
            loadProfData(radio.value, [], []);
            updateLivePreview();
        });
    });

document.addEventListener('change', (e) => {
        if (e.target.classList.contains('parent-event-cb')) {
            const wrapper = e.target.closest('.event-group-wrapper');
            const childBlock = wrapper ? wrapper.querySelector('.event-checkbox-dynamiclist') : null;
            if (childBlock) {
                childBlock.style.display = e.target.checked ? 'block' : 'none';
                childBlock.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = e.target.checked);
            }
            updateLivePreview();
        } else if (e.target.classList.contains('child-event-cb') || e.target.closest('.specializationc-checkbox-dynamiclist') || e.target.closest('.skill-checkbox-dynamiclist')) {
            updateLivePreview();
        }
    });

    // Восстанавливаем состояние шага 2 если профессия была выбрана
    if (JOYVIA.savedProfessionId) {
        if (eventBlock) eventBlock.style.display = 'block';
        rightProfBlocks.forEach(b => b.style.display = 'flex');
        loadProfData(JOYVIA.savedProfessionId, JOYVIA.savedSpecs, JOYVIA.savedSkills);
        updateLivePreview();
    }

    // ===========================
    // ШАГ 4: ПАКЕТЫ — ИНИЦИАЛИЗАЦИЯ
    // ===========================
    if (JOYVIA.currentStep >= 3) {
        generatePackages();
    }

    // ===========================
    // ШАГ 5: ПОРТФОЛИО — ИНИЦИАЛИЗАЦИЯ
    // ===========================
    initPortfolioPhotos();
	initPortfolioVideos();
    initVideoLinks();

    // ===========================
    // DRAG-AND-DROP ДЛЯ ФОТО
    // ===========================
    const dropzonePhoto = document.getElementById('portfolio-photo-dropzone');
    if (dropzonePhoto) {
        ['dragenter','dragover'].forEach(ev => dropzonePhoto.addEventListener(ev, e => { e.preventDefault(); dropzonePhoto.classList.add('drag-over'); }));
        ['dragleave','drop'].forEach(ev => dropzonePhoto.addEventListener(ev, e => { e.preventDefault(); dropzonePhoto.classList.remove('drag-over'); }));
        dropzonePhoto.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files.length) uploadPortfolioPhotos(Array.from(files), dropzonePhoto);
        });
    }

    const photoInput = document.getElementById('portfolio-photo-input');
    if (photoInput) {
        photoInput.addEventListener('change', () => uploadPortfolioPhotos(Array.from(photoInput.files), dropzonePhoto));
    }

    // ВИДЕО DROPZONE
    const dropzoneVideo = document.getElementById('portfolio-video-dropzone');
    if (dropzoneVideo) {
        ['dragenter','dragover'].forEach(ev => dropzoneVideo.addEventListener(ev, e => { e.preventDefault(); dropzoneVideo.classList.add('drag-over'); }));
        ['dragleave','drop'].forEach(ev => dropzoneVideo.addEventListener(ev, e => { e.preventDefault(); dropzoneVideo.classList.remove('drag-over'); }));
        dropzoneVideo.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files.length) uploadPortfolioVideos(Array.from(files), dropzoneVideo);
        });
    }

    const videoInput = document.getElementById('portfolio-video-input');
    if (videoInput) {
        videoInput.addEventListener('change', () => uploadPortfolioVideos(Array.from(videoInput.files), dropzoneVideo));
    }
});

// ===========================
// ПОРТФОЛИО: ФОТОГРАФИИ
// ===========================
function initPortfolioPhotos() {
    const preview = document.getElementById('portfolio-photo-preview');
    if (!preview) return;
    JOYVIA.portfolioPhotos.forEach(photo => appendPhotoThumb(photo.id, photo.url));
    if (JOYVIA.portfolioPhotos.length > 0) preview.style.display = 'flex';
}

function appendPhotoThumb(id, url) {
    const preview = document.getElementById('portfolio-photo-preview');
    if (!preview) return;
    const div = document.createElement('div');
    div.className = 'portfolio-thumb';
    div.dataset.id = id;
    div.innerHTML = '<img src="' + url + '" alt=""><button type="button" class="portfolio-thumb-remove" onclick="deletePortfolioPhoto(' + id + ', this.closest(\'.portfolio-thumb\'))">✕</button>';
    preview.appendChild(div);
    preview.style.display = 'flex';
}

async function uploadPortfolioPhotos(files, dropzoneEl) {
    const existing = document.querySelectorAll('#portfolio-photo-preview .portfolio-thumb').length;
    const allowed  = files.slice(0, 20 - existing);
    
    if (allowed.length > 0 && dropzoneEl) dropzoneEl.classList.add('loadingobject');

    for (const file of allowed) {
        if (!['image/jpeg','image/png'].includes(file.type)) { notify('Допустимые форматы: JPEG, PNG', 'error'); continue; }
        if (file.size > 10 * 1024 * 1024) { notify('Файл ' + file.name + ' превышает 10 МБ', 'error'); continue; }
        const fd = new FormData();
        fd.append('action', 'joyvia_upload_portfolio_photo');
        fd.append('nonce', JOYVIA.nonce);
        fd.append('photo', file);
        try {
            const r = await fetch(JOYVIA.ajax, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) { appendPhotoThumb(d.data.id, d.data.url); JOYVIA.portfolioPhotos.push({ id: d.data.id, url: d.data.url }); }
            else notify('Ошибка загрузки: ' + (d.data || ''), 'error');
        } catch(e) { notify('Ошибка сети', 'error'); }
    }
    
    if (dropzoneEl) dropzoneEl.classList.remove('loadingobject');
}

async function deletePortfolioPhoto(id, el) {
    el.style.opacity = '0.5';
    const fd = new FormData();
    fd.append('action', 'joyvia_delete_portfolio_photo');
    fd.append('nonce', JOYVIA.nonce);
    fd.append('attachment_id', id);
    try {
        const r = await fetch(JOYVIA.ajax, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            el.remove();
            JOYVIA.portfolioPhotos = JOYVIA.portfolioPhotos.filter(p => p.id !== id);
            const preview = document.getElementById('portfolio-photo-preview');
            if (preview && !preview.children.length) preview.style.display = 'none';
        } else {
            el.style.opacity = '1';
        }
    } catch(e) { el.style.opacity = '1'; }
}

// ===========================
// ПОРТФОЛИО: ЗАГРУЗКА ВИДЕО
// ===========================
function initPortfolioVideos() {
    const preview = document.getElementById('portfolio-video-preview');
    if (!preview) return;
    JOYVIA.portfolioVideos.forEach(vid => appendVideoThumb(vid.id, vid.url));
    if (JOYVIA.portfolioVideos.length > 0) preview.style.display = 'flex';
}

function appendVideoThumb(id, url) {
    const preview = document.getElementById('portfolio-video-preview');
    if (!preview) return;
    const div = document.createElement('div');
    // Можно использовать тот же класс portfolio-thumb для общих стилей рамки/кнопки
    div.className = 'portfolio-thumb portfolio-video-thumb';
    div.dataset.id = id;
    div.innerHTML = `
        <video src="${url}"></video>
        <button type="button" class="portfolio-thumb-remove" onclick="deletePortfolioVideo(${id}, this.closest('.portfolio-thumb'))">✕</button>
    `;
    preview.appendChild(div);
    preview.style.display = 'flex';
}

async function uploadPortfolioVideos(files, dropzoneEl) {
    const existing = document.querySelectorAll('#portfolio-video-preview .portfolio-thumb').length;
    const allowed  = files.slice(0, 5 - existing); // Максимум 5 файлов, как у вас указано в HTML
    
    if (allowed.length > 0 && dropzoneEl) dropzoneEl.classList.add('loadingobject');

    for (const file of allowed) {
        if (!['video/mp4','video/quicktime'].includes(file.type)) { notify('Допустимые форматы: MP4, MOV', 'error'); continue; }
        if (file.size > 500 * 1024 * 1024) { notify('Файл ' + file.name + ' превышает 500 МБ', 'error'); continue; }
        
        const fd = new FormData();
        fd.append('action', 'joyvia_upload_portfolio_video');
        fd.append('nonce', JOYVIA.nonce);
        fd.append('video', file);
        try {
            const r = await fetch(JOYVIA.ajax, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) { 
                appendVideoThumb(d.data.id, d.data.url); 
                JOYVIA.portfolioVideos.push({ id: d.data.id, url: d.data.url }); 
            }
            else notify('Ошибка загрузки: ' + (d.data || ''), 'error');
        } catch(e) { notify('Ошибка сети', 'error'); }
    }
    
    if (dropzoneEl) dropzoneEl.classList.remove('loadingobject');
}

async function deletePortfolioVideo(id, el) {
    el.style.opacity = '0.5';
    const fd = new FormData();
    fd.append('action', 'joyvia_delete_portfolio_video');
    fd.append('nonce', JOYVIA.nonce);
    fd.append('attachment_id', id);
    try {
        const r = await fetch(JOYVIA.ajax, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            el.remove();
            JOYVIA.portfolioVideos = JOYVIA.portfolioVideos.filter(p => p.id !== id);
            const preview = document.getElementById('portfolio-video-preview');
            if (preview && !preview.children.length) preview.style.display = 'none';
        } else {
            el.style.opacity = '1';
        }
    } catch(e) { el.style.opacity = '1'; }
}

// ===========================
// ПОРТФОЛИО: ВИДЕО ССЫЛКИ
// ===========================
function initVideoLinks() {
    if (!JOYVIA.videoLinks || !JOYVIA.videoLinks.length) return;
    const container = document.getElementById('video-links-container');
    if (!container) return;
    container.innerHTML = '';
    JOYVIA.videoLinks.forEach(url => {
        const div = document.createElement('div');
        div.className = 'portfoliovideo-link';
        div.innerHTML = '<input type="url" placeholder="https://youtube.com/watch?v=" name="link" value="' + url + '"><div class="portfoliovideo-link-remove" onclick="removeVideoLink(this)"></div>';
        container.appendChild(div);
    });
    if (!JOYVIA.videoLinks.length) addVideoLink();
}

function addVideoLink() {
    const container = document.getElementById('video-links-container');
    if (!container) return;
    const div = document.createElement('div');
    div.className = 'portfoliovideo-link';
    div.innerHTML = '<input type="url" placeholder="https://youtube.com/watch?v=" name="link"><div class="portfoliovideo-link-remove" onclick="removeVideoLink(this)"></div>';
    container.appendChild(div);
}

function removeVideoLink(btn) {
    const container = document.getElementById('video-links-container');
    if (!container) return;
    btn.closest('.portfoliovideo-link').remove();
    if (!container.children.length) addVideoLink();
}

// ===========================
// ПАКЕТЫ УСЛУГ
// ===========================
window.generatePackages = function() {
    const container = document.getElementById('step4-price-list');
    if (!container) return;
    container.innerHTML = '';
    let hasItems = false;

    function getPackagesFor(type, parentId) {
        return JOYVIA.savedPackages.filter(p => p.type === type && String(p.parent_id) === String(parentId))
            .sort((a, b) => (a.index || 0) - (b.index || 0));
    }

    function createSection(type, id, title, children = []) {
        const childrenHtml = children.length
            ? '<div class="price-list-wrap_event-line">' + children.map(c => '<p>' + c + '</p>').join('') + '</div>'
            : '';
        const savedPkgs = getPackagesFor(type, id);
        let pkgsHtml = '';
        if (savedPkgs.length) {
            savedPkgs.forEach((p, idx) => { pkgsHtml += getPackageItemHtml(type, id, idx, p); });
        } else {
            pkgsHtml = getPackageItemHtml(type, id, 0, null);
        }
        return '<div class="price-list-wrap mb30" data-type="' + type + '" data-id="' + id + '"><h3>' + title + '</h3>' + childrenHtml +
            '<div class="packages-container">' + pkgsHtml + '</div>' +
            '<div class="add-price-list-wrap mt20 g10"><div class="add-price-list-wrap-btn" onclick="addPackage(this,\'' + type + '\',\'' + id + '\')">Добавить ещё пакет</div></div></div>';
    }

    document.querySelectorAll('.parent-event-cb:checked').forEach(cb => {
        hasItems = true;
        const wrapper = cb.closest('.event-group-wrapper');
        const children = wrapper ? Array.from(wrapper.querySelectorAll('.child-event-cb:checked')).map(c => c.dataset.name) : [];
        container.insertAdjacentHTML('beforeend', createSection('event', cb.value, cb.dataset.name, children));
    });

    document.querySelectorAll('.specializationc-checkbox-dynamiclist input:checked').forEach(cb => {
        hasItems = true;
        const label = cb.nextElementSibling ? cb.nextElementSibling.textContent : '';
        container.insertAdjacentHTML('beforeend', createSection('spec', cb.value, label));
    });

    if (!hasItems) {
        container.innerHTML = '<p class="f15 c646 mt12">Вы не выбрали ни одного события или специализации. Вернитесь назад и сделайте выбор.</p>';
    }
    attachTextareaCounters(container);
};

function getPackageItemHtml(type, parentId, index, saved) {
    const name  = saved ? saved.name  : '';
    const price = saved ? saved.price : '';
    const desc  = saved ? saved.desc  : '';
    const removeBtnHtml = index > 0
        ? '<div class="packet-remove-btn mt10" style="color:red;cursor:pointer;font-size:13px;" onclick="openRemovePackageModal(this)">Удалить пакет</div>'
        : '';
    return '<div class="price-list-wrap_event-name-price packet-item mt20">' +
        '<div class="form-group w100pr dflex jstc-sb">' +
            '<div class="fieldinput w73pr"><input type="text" name="packet[' + type + '][' + parentId + '][' + index + '][name]" placeholder=" " value="' + escAttr(name) + '" required><label>Название пакета<span class="required">*</span></label></div>' +
            '<div class="fieldinput w25pr"><input type="number" name="packet[' + type + '][' + parentId + '][' + index + '][price]" placeholder="Цена" value="' + escAttr(price) + '" required><p class="price-packet-after">₽</p></div>' +
        '</div>' +
        '<div class="fieldinput w100pr mt15"><textarea rows="6" name="packet[' + type + '][' + parentId + '][' + index + '][desc]" placeholder=" " required class="packet-desc-textarea">' + escHtml(desc) + '</textarea><label class="lt005">Что входит<span class="required">*</span></label></div>' +
        '<p class="f13 txt-num mt5"><span class="count-check">' + String(desc).length + '</span> из 1000 символов (минимум 200)</p>' +
        removeBtnHtml +
    '</div>';
}

let packageToRemove = null;

window.openRemovePackageModal = function(btn) {
    packageToRemove = btn.closest('.packet-item');
    document.getElementById('modal-packet-remove-btn').classList.add('open');
};

document.addEventListener('DOMContentLoaded', function() {
    const removeModal = document.getElementById('modal-packet-remove-btn');
    const confirmDeleteBtn = document.getElementById('packet-remove-modal');

    if (removeModal && confirmDeleteBtn) {
        removeModal.querySelectorAll('[data-action="closemodal"]').forEach(btn => {
            btn.addEventListener('click', () => {
                removeModal.classList.remove('open');
                packageToRemove = null;
            });
        });

        confirmDeleteBtn.addEventListener('click', () => {
            if (packageToRemove) {
                packageToRemove.remove();
                packageToRemove = null;
            }
            removeModal.classList.remove('open');
        });

        removeModal.addEventListener('click', (e) => {
            if (e.target === removeModal) {
                removeModal.classList.remove('open');
                packageToRemove = null;
            }
        });
    }
});



function escAttr(v) { return String(v).replace(/"/g, '&quot;'); }
function escHtml(v) { return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

window.addPackage = function(btn, type, parentId) {
    const cont  = btn.closest('.price-list-wrap').querySelector('.packages-container');
    const count = cont.querySelectorAll('.packet-item').length;
    cont.insertAdjacentHTML('beforeend', getPackageItemHtml(type, parentId, count, null));
    attachTextareaCounters(cont);
};

function attachTextareaCounters(container) {
    container.querySelectorAll('.packet-desc-textarea').forEach(ta => {
        const clone = ta.cloneNode(true);
        ta.parentNode.replaceChild(clone, ta);
        clone.addEventListener('input', (e) => {
            const t = e.target; t.value = t.value.slice(0, 1000);
            const counter = t.closest('.packet-item').querySelector('.count-check');
            if (counter) { counter.textContent = t.value.length; counter.style.color = t.value.length < 200 ? 'red' : ''; }
        });
    });
}

// ===========================
// ПОРТФОЛИО: ТАБЫ
// ===========================
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-block').forEach(el => el.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
    document.querySelectorAll('.portfolio-tab').forEach(el => el.classList.remove('active'));
    if (btn) btn.classList.add('active');
    else {
        const tabs = { portfoliophoto: 0, portfoliovideo: 1, portfoliolinks: 2 };
        const idx = tabs[tabId];
        const allTabs = document.querySelectorAll('.portfolio-tab');
        if (allTabs[idx]) allTabs[idx].classList.add('active');
    }
}

// ===========================
// ВАЛИДАЦИЯ
// ===========================
function validateStep(step) {
    const errors = [];

    if (step === 0) {
        const container = document.querySelector('.step-content[goTo="0"]');
        clearFieldErrors(container);
        const fn = document.getElementById('inp-first-name');
        const ln = document.getElementById('inp-last-name');
        const ci = document.getElementById('city-input');
        const ph = document.getElementById('inp-phone');
        const em = document.getElementById('inp-email');
        const ob = document.getElementById('osebe-textarea');
        // Аватар обязателен: считается загруженным если статус «Загружено ✓» или уже есть сохранённый
        const avatarStatus = document.getElementById('avatar-upload-status');
        const avatarUploaded = avatarStatus && avatarStatus.textContent.includes('✓');
        const avatarSaved = !!(<?= $s_avatar_url ? 'true' : 'false' ?>);
        if (!avatarUploaded && !avatarSaved) {
            const avatarErr = document.getElementById('avatar-error');
            if (avatarErr) { avatarErr.textContent = 'Загрузите фото профиля'; avatarErr.style.display = 'block'; }
            errors.push(document.getElementById('avatar-zone'));
        }
        if (!fn.value.trim()) { showFieldError(fn, 'Введите имя'); errors.push(fn); }
        if (!ln.value.trim()) { showFieldError(ln, 'Введите фамилию'); errors.push(ln); }
        if (!document.getElementById('city-id-hidden').value) { showFieldError(ci, 'Выберите город из списка'); errors.push(ci); }
        if (!ph.value.trim()) { showFieldError(ph, 'Введите телефон'); errors.push(ph); }
        if (!em.value.trim() || !em.value.includes('@')) { showFieldError(em, 'Введите корректный email'); errors.push(em); }
        if (ob.value.trim().length < 200) { showFieldError(ob, 'Минимум 200 символов (сейчас: ' + ob.value.trim().length + ')'); errors.push(ob); }
    }

    if (step === 1) {
        const container = document.querySelector('.step-content[goTo="1"]');
        clearFieldErrors(container);
        if (!document.querySelector('.profession-radio-input:checked')) {
            const profBlock = document.querySelector('.list-left-professia');
            if (profBlock) { const err = document.createElement('p'); err.className = 'field-err-msg'; err.style.cssText = 'color:#e53;font-size:12px;margin:8px 0;'; err.textContent = 'Выберите профессию'; profBlock.insertAdjacentElement('beforebegin', err); errors.push(err); }
        }
    }

    if (step === 3) {
        const container = document.getElementById('step4-price-list');
        if (!container || container.children.length === 0) {
            notify('Добавьте хотя бы один пакет услуг', 'error');
            errors.push(container);
            return errors;
        }
        let pkgErrors = false;
        container.querySelectorAll('.price-list-wrap').forEach(section => {
            const items = section.querySelectorAll('.packet-item');
            if (!items.length) { pkgErrors = true; return; }
            items.forEach(item => {
                const nameI  = item.querySelector('input[name*="[name]"]');
                const priceI = item.querySelector('input[name*="[price]"]');
                const descI  = item.querySelector('textarea');
                if (!nameI.value.trim())  { showFieldError(nameI, 'Заполните название'); errors.push(nameI); pkgErrors = true; }
                if (!priceI.value)        { showFieldError(priceI, 'Укажите цену'); errors.push(priceI); pkgErrors = true; }
                if (descI && descI.value.trim().length < 200) { showFieldError(descI, 'Минимум 200 символов'); errors.push(descI); pkgErrors = true; }
            });
        });
        if (pkgErrors) notify('Заполните все поля пакетов', 'error');
    }

    return errors;
}

// ===========================
// СБОР ДАННЫХ ДЛЯ СОХРАНЕНИЯ
// ===========================
function collectPackages() {
    const packages = [];
    document.querySelectorAll('#step4-price-list .price-list-wrap').forEach(section => {
        const type     = section.dataset.type;
        const parentId = section.dataset.id;
        section.querySelectorAll('.packet-item').forEach((item, idx) => {
            const nameI  = item.querySelector('input[name*="[name]"]');
            const priceI = item.querySelector('input[name*="[price]"]');
            const descI  = item.querySelector('textarea');
            packages.push({
                type, parent_id: parentId, index: idx,
                name:  nameI  ? nameI.value.trim()  : '',
                price: priceI ? parseInt(priceI.value) || 0 : 0,
                desc:  descI  ? descI.value.trim()   : '',
            });
        });
    });
    return packages;
}

// ===========================
// СОХРАНЕНИЕ ШАГОВ
// ===========================
let isSaving = false;

async function saveAndGoTo(currentStep, nextStep) {
    if (isSaving) return;
    const errors = validateStep(currentStep);
    if (errors.length) {
        const firstEl = errors.find(e => e && e.focus);
        if (firstEl) firstEl.focus();
        return;
    }
    isSaving = true;
    const btn = document.querySelectorAll('.step-content[goTo="' + currentStep + '"] .step-button-next')[0];
    const origText = btn ? btn.textContent : '';
    if (btn) { btn.textContent = 'Ждите'; btn.disabled = true; }

    const fd = new FormData();
    fd.append('nonce', JOYVIA.nonce);

    if (currentStep === 0) {
        fd.append('action', 'joyvia_save_step1');
        fd.append('first_name', document.getElementById('inp-first-name').value.trim());
        fd.append('last_name',  document.getElementById('inp-last-name').value.trim());
        fd.append('city_id',    document.getElementById('city-id-hidden').value);
        fd.append('opyt',       document.getElementById('opyt-val-hidden').value);
        fd.append('o_sebe',     document.getElementById('osebe-textarea').value.trim());
        fd.append('phone',      document.getElementById('inp-phone').value.trim());
        fd.append('email',      document.getElementById('inp-email').value.trim());
        fd.append('telegram',   document.getElementById('inp-telegram').value.trim());
        document.querySelectorAll('input[name="region[]"]:checked').forEach(cb => fd.append('regions[]', cb.value));
    }

    if (currentStep === 1) {
        fd.append('action', 'joyvia_save_step2');
        const selProf = document.querySelector('.profession-radio-input:checked');
        fd.append('profession_id', selProf ? selProf.value : '');
        document.querySelectorAll('input[name="events[]"]:checked').forEach(cb => fd.append('events[]', cb.value));
        const eventExtra = {};
        document.querySelectorAll('input[name^="event_extra["]:checked').forEach(cb => {
            const m = cb.name.match(/event_extra\[(\d+)\]/);
            if (m) { if (!eventExtra[m[1]]) eventExtra[m[1]] = []; eventExtra[m[1]].push(cb.value); }
        });
        fd.append('event_extra', JSON.stringify(eventExtra));
    }

    if (currentStep === 2) {
        fd.append('action', 'joyvia_save_step3');
        document.querySelectorAll('input[name="specializations[]"]:checked').forEach(cb => fd.append('specs[]', cb.value));
        document.querySelectorAll('input[name="skills[]"]:checked').forEach(cb => fd.append('skills[]', cb.value));
    }

    if (currentStep === 3) {
        fd.append('action', 'joyvia_save_step4');
        const packages = collectPackages();
        JOYVIA.savedPackages = packages;
        fd.append('packages', JSON.stringify(packages));
    }

    try {
        const r = await fetch(JOYVIA.ajax, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            if (nextStep === 3) generatePackages();
            goTo(nextStep);
        } else {
            notify('Ошибка сохранения: ' + (d.data || 'попробуйте ещё раз'), 'error');
        }
    } catch(e) {
        notify('Ошибка сети. Проверьте подключение.', 'error');
    }

    if (btn) { btn.textContent = origText; btn.disabled = false; }
    isSaving = false;
}

async function saveAndPublish() {
    if (isSaving) return;
    isSaving = true;
    const btn = document.querySelector('.finish-btn');
    if (btn) { btn.textContent = 'Публикация...'; btn.disabled = true; }

    const fd = new FormData();
    fd.append('action', 'joyvia_save_step5');
    fd.append('nonce', JOYVIA.nonce);

    const videoLinks = Array.from(document.querySelectorAll('#video-links-container input[type="url"]'))
        .map(i => i.value.trim()).filter(Boolean);
    fd.append('video_links', JSON.stringify(videoLinks));
    fd.append('social', JSON.stringify({
        instagram: document.getElementById('social-instagram')?.value.trim() || '',
        vk:        document.getElementById('social-vk')?.value.trim() || '',
        website:   document.getElementById('social-website')?.value.trim() || '',
        other:     document.getElementById('social-other')?.value.trim() || '',
    }));

    try {
        const r = await fetch(JOYVIA.ajax, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            notify('Профиль отправлен на модерацию!');
            setTimeout(() => { window.location.href = d.data.redirect || JOYVIA.redirect; }, 1200);
        } else {
            notify('Ошибка: ' + (d.data || ''), 'error');
            if (btn) { btn.textContent = 'Опубликовать'; btn.disabled = false; }
        }
    } catch(e) {
        notify('Ошибка сети', 'error');
        if (btn) { btn.textContent = 'Опубликовать'; btn.disabled = false; }
    }
    isSaving = false;
}
</script>

<?php get_footer(); ?>