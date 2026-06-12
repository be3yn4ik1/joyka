<?php /* Template Name: Шаблон Page.php */ ?>
<?php get_header(); ?>

<?php
// Получаем значения полей ACF
$tekst = get_field('tekst');
$kartinka_sprava = get_field('kartinka_sprava');

$title = get_the_title();
?>

<section class="section section--content-title">
    <h1 class="section__title"><?php echo esc_html($title); ?></h1>
</section>


<?php if ($tekst || $kartinka_sprava) : ?>
<section class="section section--content about">
        <div class="section__content-wrapper">
            <?php if ($tekst) : ?>
                <div class="section__text-block section__text-block--wysiwyg">
                    <?php echo wp_kses_post($tekst); ?>
                </div>
            <?php endif; ?>

            <?php if ($kartinka_sprava) : ?>
                <div class="section__image-block section__image-block--right">
                    <img src="<?php echo esc_url($kartinka_sprava['url']); ?>" 
                         alt="<?php echo esc_attr($kartinka_sprava['alt'] ?: 'Image'); ?>" 
                         class="section__image" />
                </div>
            <?php endif; ?>
        </div>
</section>
<?php endif; ?>

<section class="section section--content all"><?php the_content() ?></section>


<?php if (is_page(49)) { ?>
<section class="section section--contact-info">
    <div class="section--contact-info__wrapper">
		<div><span>Ежедневно 10:00 - 20:00</span>
<a href="tel:<?php echo esc_attr(preg_replace('/[^\d+]/', '', get_field('nomer_telefona-global', 'option'))); ?>" 
   class="section--contact-info__link section--contact-info__link--phone">
    <?php echo esc_html(get_field('nomer_telefona-global', 'option')); ?>
</a>
		</div>
        <div><span>Email</span>
        <a href="mailto:<?php echo esc_attr(get_field('email-global', 'option')); ?>" 
           class="section--contact-info__link section--contact-info__link--email">
            <?php echo esc_html(get_field('email-global', 'option')); ?>
        </a>
        </div>
    </div>
</section>	
<?php } ?>




<?php get_footer() ?>