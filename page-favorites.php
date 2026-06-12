<?php
/* Template Name: Избранное */
get_header();
?>

<section class="section section--favorites">
    <div class="container">
        <h1 class="section__title">Избранные исполнители</h1>
        <div id="favorites-container"><p class="favorites-loading">Загрузка...</p></div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('favorites-container');
    const favs = JSON.parse(localStorage.getItem('joyvia_favorites') || '[]');
    console.log('ID в памяти браузера:', favs);
    if (favs.length === 0) {
        container.innerHTML = '<p class="favorites-empty">Вы пока не добавили исполнителей</p>';
        return;
    }
    const formData = new FormData();
    formData.append('action', 'joyvia_get_favorites_ajax');
    formData.append('ids', JSON.stringify(favs));
    fetch('<?= admin_url('admin-ajax.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log('Ответ от сервера:', data); // Смотрим ответ сервера в консоли браузера
        if (data.success && data.data.html.trim() !== '') { container.innerHTML = '<div class="authors-grid">' + data.data.html + '</div>';
        } else { container.innerHTML = '<p class="favorites-empty">Исполнители найдены в памяти, но база данных их не вернула.</p>'; }
    })
    .catch(error => {
        console.error('Ошибка сети или сервера:', error);
        container.innerHTML = '<p class="favorites-error">Произошла ошибка при загрузке.</p>';
    });
});
</script>

<?php get_footer(); ?>