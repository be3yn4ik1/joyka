</main>


<?php if ( is_user_logged_in() ) : ?>
<div class="pc-none header_right-block-colaps footer-right-block">
   <a href="/favorites/"class="header_right-block-icon heart-icon" style="display:none">Избранное</a>
   <a href="#"class="header_right-block-icon zakaz-icon" style="display:none"><span>0</span>Заявки</a>
   <a href="#"class="header_right-block-icon chat-icon" style="display:none"><span>0</span>Чат</a>
   <a href="/profile-settings/"class="header_right-block-icon profile-icon">Профиль</a>
</div>
<?php endif; ?>


<footer class="footer">
    <div class="footer_section">
    <div class="footer__column">
	  <div class="footer__logo"><a href="/"> <svg xmlns="http://www.w3.org/2000/svg" width="144" height="60" fill="none"><g fill="#4C32E1" clip-path="url(#a)"><path d="M19.767 13.381a3.2 3.2 0 0 1 2.639 2.644c.017.102.085.195.186.22a.2.2 0 0 0 .068.01c.025 0 .05 0 .067-.01.102-.025.17-.118.186-.22a3.2 3.2 0 0 1 2.639-2.644c.101-.017.194-.084.22-.186.008-.026.008-.042.008-.068q.002-.04-.008-.068c-.026-.101-.119-.17-.22-.186a3.2 3.2 0 0 1-2.639-2.644c-.017-.102-.084-.195-.186-.22C22.702 10 22.685 10 22.66 10q-.04-.002-.068.008c-.101.026-.17.12-.186.22a3.2 3.2 0 0 1-2.639 2.645c-.101.017-.194.085-.22.186-.008.026-.008.043-.008.068q-.002.04.008.068c.026.093.119.17.22.186M14.921 19.025a2.84 2.84 0 0 1 2.351 2.356.24.24 0 0 0 .161.195.2.2 0 0 0 .068.009c.025 0 .05 0 .067-.009.085-.025.144-.11.161-.195a2.85 2.85 0 0 1 2.351-2.356c.093-.016.178-.076.203-.17.008-.016.008-.041.008-.058s0-.043-.008-.06c-.025-.093-.11-.152-.203-.17a2.85 2.85 0 0 1-2.35-2.355.25.25 0 0 0-.128-.186.2.2 0 0 0-.101-.026.3.3 0 0 0-.093.017.24.24 0 0 0-.136.186 2.84 2.84 0 0 1-2.35 2.356.27.27 0 0 0-.204.161c-.008.026-.008.043-.008.068q0 .051.025.102a.21.21 0 0 0 .186.135M27.379 19.636l.127-.077a.38.38 0 0 0 .017-.745c-.043-.009-.085-.009-.119-.009-.076.009-.27.076-.313.085h-.008c-12.871 5.33-12.6 11.95-12.6 11.95s-.145 3.058 3.128 5.423c3.281 2.28 3.214 4.407 3.214 4.407.059 3.983-9.379 8.059-10.47 8.517a.6.6 0 0 0-.279.22.47.47 0 0 0-.076.246c0 .084.025.152.068.195a.37.37 0 0 0 .287.135h10.647c7.341-4.915 6.512-9.568 6.512-9.568s-.448-3.508-4.99-5.678c-5.26-2.517-4.752-5.686-4.752-5.686.558-4.67 8.414-8.788 9.607-9.415"/><path d="M27.6 19.415s-.11.144-.297.17z"/><path d="M27.658 19.492s-.22.135-.465.237.034-.237.034-.237zM49.556 19.328v14.635q0 3.046-1.756 4.748-1.755 1.673-4.7 1.673-2.977 0-4.821-1.732-1.815-1.733-1.815-4.839h4.166q.03 1.344.624 2.091.625.747 1.786.747 1.131 0 1.725-.717t.595-1.971V19.328zm12.179 21.116q-2.381 0-4.285-1.046a7.83 7.83 0 0 1-3.005-3.016q-1.071-1.941-1.071-4.48t1.1-4.48a7.76 7.76 0 0 1 3.065-2.987q1.935-1.075 4.315-1.075t4.314 1.075a7.56 7.56 0 0 1 3.035 2.987q1.13 1.94 1.13 4.48 0 2.539-1.16 4.48a7.9 7.9 0 0 1-3.094 3.016q-1.935 1.046-4.344 1.046m0-3.644a4.33 4.33 0 0 0 2.112-.538q1.012-.567 1.607-1.672.595-1.106.595-2.688 0-2.36-1.25-3.614-1.219-1.284-3.005-1.284t-3.005 1.284q-1.19 1.254-1.19 3.614t1.16 3.644Q59.95 36.8 61.735 36.8m27.424-13.171L78.955 48H74.52l3.57-8.243-6.605-16.128h4.672l4.255 11.558 4.314-11.558zm9.12 12.693 4.165-12.693h4.433l-6.1 16.546h-5.058l-6.07-16.546h4.464zm13.051-14.665q-1.102 0-1.845-.686-.715-.717-.715-1.763 0-1.044.715-1.732.743-.717 1.845-.717 1.1 0 1.815.717.743.687.743 1.732 0 1.046-.743 1.763-.715.686-1.815.686m2.052 1.972v16.546h-4.165V23.629zm3.045 8.213q0-2.508.982-4.45 1.012-1.941 2.707-2.987 1.727-1.045 3.839-1.045 1.845 0 3.213.747 1.4.746 2.232 1.881v-2.36h4.195v16.547H129.4v-2.42q-.803 1.165-2.232 1.942-1.398.747-3.243.747a7.06 7.06 0 0 1-3.809-1.075q-1.695-1.076-2.707-3.017-.982-1.97-.982-4.51m12.973.06q0-1.524-.595-2.599-.596-1.104-1.607-1.672a4.2 4.2 0 0 0-2.172-.598q-1.161 0-2.142.568-.982.567-1.607 1.673-.595 1.074-.595 2.568t.595 2.628q.625 1.106 1.607 1.703a4.14 4.14 0 0 0 2.142.597q1.161 0 2.172-.567a4.34 4.34 0 0 0 1.607-1.673q.594-1.105.595-2.628"/></g></svg></a></div>
      <div class="footer__description"><?php the_field('opisanie_pod_logo', 'option'); ?></div>
    </div>
    <div class="footer__column">
      <div class="footer__title">Навигация</div>
<?php if( have_rows('ssylki_pod_navigacziej', 'option') ): ?>
    <?php while( have_rows('ssylki_pod_navigacziej', 'option') ): the_row(); ?>
        <div class="footer__item">
            <?php the_sub_field('ssylka'); ?>
        </div>
    <?php endwhile; ?>
<?php endif; ?>

    </div>
    <div class="footer__column">
      <div class="footer__title">Контакты</div>
      <div class="footer__item">
    <?php 
    $phone = get_field('nomer_telefona-global', 'option');
    $clean_phone = preg_replace('/[^\d+]/', '', $phone);
    ?>
    <a href="tel:<?php echo $clean_phone; ?>">
        <?php echo $phone; ?>
    </a>
</div>

      <div class="footer__item"><a href="mailto:<?php echo get_field('email-global', 'option'); ?>"><?php echo get_field('email-global', 'option'); ?></a></div>
      <div class="footer__item">
          <a href="<?php echo get_field('telegram-global', 'option'); ?>" class="footer__item--meesenger">
            <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_370_1371)"><path d="M9.37506 15.8049L19.9524 25.078C20.0742 25.1855 20.2219 25.2594 20.381 25.2927C20.54 25.3259 20.705 25.3172 20.8597 25.2674C21.0143 25.2177 21.1534 25.1286 21.2633 25.0089C21.3732 24.8892 21.4501 24.743 21.4864 24.5846L26.2501 3.89281C26.2547 3.87207 26.2536 3.85044 26.2469 3.83026C26.2402 3.81008 26.2282 3.79211 26.212 3.77826C26.1959 3.76441 26.1763 3.75521 26.1553 3.75165C26.1344 3.74809 26.1128 3.7503 26.093 3.75805L2.34381 13.0522C2.19638 13.1089 2.07133 13.212 1.98743 13.3458C1.90354 13.4797 1.86533 13.6372 1.87853 13.7946C1.89174 13.952 1.95565 14.1009 2.06068 14.2189C2.1657 14.3369 2.30617 14.4177 2.461 14.4491L9.37506 15.8049Z" stroke="#646466" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.375 15.8053L26.1809 3.76074" stroke="#646466" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.5746 20.3645L10.9875 24.0863C10.8579 24.2208 10.6911 24.3135 10.5084 24.3525C10.3258 24.3916 10.1356 24.3751 9.96236 24.3054C9.7891 24.2356 9.64063 24.1157 9.53598 23.961C9.43133 23.8063 9.37528 23.6239 9.375 23.4371V15.8047" stroke="#646466" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></g></svg>
          </a>
          <a href="<?php echo get_field('whatsapp-global', 'option'); ?>" class="footer__item--meesenger">
              <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_370_1257)"><path d="M8.4375 12.1875C8.4375 11.1929 8.83259 10.2391 9.53585 9.53585C10.2391 8.83259 11.1929 8.4375 12.1875 8.4375L14.0625 12.1875L12.6188 14.352C13.1914 15.7204 14.2796 16.8086 15.648 17.3813L17.8125 15.9375L21.5625 17.8125C21.5625 18.8071 21.1674 19.7609 20.4641 20.4641C19.7609 21.1674 18.8071 21.5625 17.8125 21.5625C15.3261 21.5625 12.9415 20.5748 11.1834 18.8166C9.42522 17.0585 8.4375 14.6739 8.4375 12.1875Z" stroke="#646466" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.36671 24.7398C11.7298 26.1075 14.5097 26.5691 17.188 26.0385C19.8663 25.508 22.2603 24.0215 23.9236 21.8562C25.5869 19.691 26.4059 16.9947 26.2281 14.2701C26.0502 11.5456 24.8876 8.97865 22.9569 7.04799C21.0263 5.11733 18.4594 3.95469 15.7348 3.77684C13.0102 3.59898 10.314 4.41805 8.14871 6.08133C5.98344 7.74462 4.49697 10.1386 3.96642 12.8169C3.43587 15.4952 3.89747 18.2751 5.26514 20.6382L3.79913 25.0152C3.74404 25.1804 3.73605 25.3576 3.77604 25.5271C3.81603 25.6966 3.90244 25.8516 4.02556 25.9747C4.14869 26.0978 4.30368 26.1842 4.47315 26.2242C4.64262 26.2642 4.81988 26.2562 4.98507 26.2011L9.36671 24.7398Z" stroke="#646466" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</g></svg>
          </a>
      </div>
    </div>
    <div class="footer__column">
      <div class="footer__title">Документация</div>
<?php if( have_rows('ssylki_pod_dokumentacziej', 'option') ): ?>
    <?php while( have_rows('ssylki_pod_dokumentacziej', 'option') ): the_row(); ?>
        <div class="footer__item">
            <?php the_sub_field('ssylka'); ?>
        </div>
    <?php endwhile; ?>
<?php endif; ?>

    </div>
       <div class="footer__copyright">© <?php echo date("Y"); ?> Joyvia. Все права защищены.</div>
    </div>
</footer>

<div class="popup-all"> <div class="popup-all--overlay" id="overlay"></div>
   
    <!--стать исполнителем-->
<div class="popup-all--popup" id="start-performer">
    <button class="close-btn" onclick="closePopup()">×</button>
    <div class="popup-all--header"><p id="auth-title">Вход</p></div>
    <div class="popup-all--content">
        <form id="auth-form" class="auth-form">
            <input type="hidden" id="auth-action" name="action_type" value="login">
            <input type="hidden" id="auth-nonce" name="nonce" value="<?php echo wp_create_nonce('joyvia_auth_nonce'); ?>">
            <div class="form-group"><input type="email" id="auth-email" name="email" placeholder="E-mail" required></div>
            <div class="form-group"><input type="password" id="auth-password" name="password" placeholder="Пароль" required minlength="6"></div>
            <div class="form-group" style="margin-bottom: 15px;"><div class="g-recaptcha" data-sitekey="6LcpBLgrAAAAAKlx4zTjeFZa5cLmWcNprkbIQ7Sh"></div></div>
            <div id="auth-error" class="auth-error" style="display:none; color:red; margin-bottom:10px; font-size:14px;"></div>
            <button type="submit" class="btn btn-submit wpcf7-submit" id="auth-submit">Войти</button>
            <button type="button" class="btn btn-outline" id="auth-toggle">Зарегистрироваться</button>
<div class="checkbox-group">
  <label class="checkbox-item"><input type="checkbox" checked name="personal_data" required><span>Я даю согласие на обработку персональных данных</span></label>
  <label class="checkbox-item"> <input type="checkbox" checked name="privacy_policy" required> <span>Я согласен с <a href="/privacy-policy" target="_blank">Политикой конфиденциальности</a></span></label>
</div>
        </form>
    </div>
</div>
    <!--заказ-->
    <div class="popup-all--popup" id="zayavka">
        <button class="close-btn" onclick="closePopup()">×</button>
        <div class="popup-all--header"> <p>Оставить заявку на услугу</p></div>
        <div class="popup-all--content"><?php echo do_shortcode( '[contact-form-7 id="e7c69fb" title="Оставить заявку на услугу"]' ); ?></div>
    </div>
    <!--заказ-->
	<!--заказ-->
    <div class="popup-all--popup" id="check">
        <button class="close-btn" onclick="closePopup()">×</button>
        <div class="popup-all--header"> <p>Как выбрать хорошего исполнителя?</p></div>
        <div class="popup-all--content"><?php echo get_field('tekst_dlya_kak_vybrat_horoshego_ispolnitelya', 'option'); ?></div>
    </div>
    <!--заказ-->
</div>




<?php wp_footer(); ?>
</body>
</html>