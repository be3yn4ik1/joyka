document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('auth-toggle');
    const actionInput = document.getElementById('auth-action');
    const submitBtn = document.getElementById('auth-submit');
    const authTitle = document.getElementById('auth-title');
    const authForm = document.getElementById('auth-form');
    const errorDiv = document.getElementById('auth-error');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            errorDiv.style.display = 'none';
            if (actionInput.value === 'login') {
                actionInput.value = 'register';
                authTitle.textContent = 'Регистрация';
                submitBtn.textContent = 'Зарегистрироваться';
                toggleBtn.textContent = 'Уже есть аккаунт? Войти';
            } else {
                actionInput.value = 'login';
                authTitle.textContent = 'Вход';
                submitBtn.textContent = 'Войти';
                toggleBtn.textContent = 'Зарегистрироваться';
            }
        });
    }

    if (authForm) {
        authForm.addEventListener('submit', (e) => {
            e.preventDefault();
            errorDiv.style.display = 'none';

            const captchaResponse = grecaptcha.getResponse();
            if (!captchaResponse) {
                errorDiv.textContent = 'Подтвердите, что вы не робот';
                errorDiv.style.display = 'block';
                return;
            }

            submitBtn.disabled = true;
            submitBtn.classList.add('think');
            
            const formData = new FormData(authForm);
            formData.append('action', 'joyvia_auth_handler');
            formData.append('g-recaptcha-response', captchaResponse);
            formData.set('nonce', joyvia_ajax.nonce);

            fetch(joyvia_ajax.url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.data.redirect;
                } else {
                    errorDiv.textContent = data.data;
                    errorDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('think');
                    grecaptcha.reset();
                }
            })
            .catch(() => {
                errorDiv.textContent = 'Ошибка сервера';
                errorDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.classList.remove('think');
            });
        });
    }
});