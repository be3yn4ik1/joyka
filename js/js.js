//подставляем URL в скрытое поле
document.addEventListener('DOMContentLoaded', function() {
  var inputs = document.querySelectorAll('input[name="page-url"]');
  inputs.forEach(function(input) {
    input.value = window.location.href;
  });
});

//показываем услуги
const initUslugi = () => {
    document.querySelectorAll('.show-uslug:not(.init)').forEach(btn => {
        const items = btn.closest('.executor-card')?.querySelectorAll('.hidden-usluga');
        if (!items?.length) return btn.hidden = true;
        btn.classList.add('init');
        let exp = false;
        const render = () => {
            btn.dataset.expanded = exp;
            btn.textContent = exp ? 'Скрыть' : `Ещё ${items.length} услуг`;
            items.forEach(i => i.classList.toggle('hidden-usluga', !exp));
        };
        render();
        btn.addEventListener('click', () => { exp = !exp; render(); });
    });
};
document.addEventListener('DOMContentLoaded', () => {
    initUslugi();
    new MutationObserver(m => m.some(r => r.addedNodes.length) && initUslugi())
        .observe(document.body, { childList: true, subtree: true });
});


//загрузить еше похожих исполнителей
document.addEventListener("DOMContentLoaded", () => {
    // Обработка для обычной кнопки "Показать ещё" (если она есть)
    const button = document.querySelector("#load-more-authors");
    const grid = document.querySelector("#authors-grid");
    
    if (button && grid) {
        button.addEventListener("click", () => {
            const offset = parseInt(button.dataset.offset, 10);
            button.textContent = "Загрузка...";
            
            fetch(load_more_authors_obj.ajaxurl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                },
                body: new URLSearchParams({
                    action: "load_more_authors",
                    offset: offset
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.html.trim() !== "") {
                    grid.insertAdjacentHTML("beforeend", result.html);
                    button.dataset.offset = offset + 4;
                    button.textContent = "Показать ещё";
                    if (!result.has_more) {
                        button.remove();
                    }
                } else {
                    button.remove();
                }
            })
            .catch(err => {
                console.error("Ошибка загрузки:", err);
                button.textContent = "Ошибка, попробуйте ещё";
            });
        });
    }
    
    // Обработка для кнопки "Показать ещё" похожих исполнителей
    const similarButton = document.querySelector("#load-more-similar-authors");
    const similarGrid = document.querySelector("#similar-authors-grid");
    
    if (similarButton && similarGrid) {
        similarButton.addEventListener("click", () => {
            const offset = parseInt(similarButton.dataset.offset, 10);
            const currentUserId = similarGrid.getAttribute('data-current-user-id');
            const currentTerms = similarGrid.getAttribute('data-current-terms');
            
            similarButton.textContent = "Загрузка...";
            similarButton.disabled = true;
            
            fetch(load_more_authors_obj.ajaxurl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                },
                body: new URLSearchParams({
                    action: "load_more_similar_authors",
                    offset: offset,
                    current_user_id: currentUserId,
                    current_terms: currentTerms
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data.html.trim() !== "") {
                    similarGrid.insertAdjacentHTML("beforeend", result.data.html);
                    similarButton.dataset.offset = result.data.next_offset;
                    similarButton.textContent = "Показать ещё";
                    similarButton.disabled = false;
                    
                    if (!result.data.has_more) {
                        similarButton.remove();
                    }
                } else {
                    similarButton.remove();
                }
            })
            .catch(err => {
                console.error("Ошибка загрузки похожих исполнителей:", err);
                similarButton.textContent = "Ошибка, попробуйте ещё";
                similarButton.disabled = false;
            });
        });
    }
});



//Логика работы POPUP
document.addEventListener("DOMContentLoaded", () => {
    window.openPopup = function(popupId) {
        const overlay = document.getElementById('overlay');
        const popup = document.getElementById(popupId);
        overlay.style.display = 'block';
        popup.style.display = 'block';
        overlay.style.opacity = '0';
        popup.style.opacity = '0';
        let start = null;
        function fadeIn(timestamp) {
            if (!start) start = timestamp;
            const progress = (timestamp - start) / 300;
            const opacity = Math.min(progress, 1);
            overlay.style.opacity = opacity;
            popup.style.opacity = opacity;
            if (progress < 1) {
                requestAnimationFrame(fadeIn);
            }
        }
        requestAnimationFrame(fadeIn);
    }

    window.closePopup = function() {
        const overlay = document.getElementById('overlay');
        const popups = document.querySelectorAll('.popup-all--popup');
        let start = null;
        function fadeOut(timestamp) {
            if (!start) start = timestamp;
            const progress = (timestamp - start) / 300;
            const opacity = Math.max(1 - progress, 0);
            overlay.style.opacity = opacity;
            popups.forEach(popup => {
                popup.style.opacity = opacity;
            });
            if (progress < 1) {
                requestAnimationFrame(fadeOut);
            } else {
                overlay.style.display = 'none';
                popups.forEach(popup => {
                    popup.style.display = 'none';
                });
            }
        }
        requestAnimationFrame(fadeOut);
    }

    const overlay = document.getElementById('overlay');
    overlay.addEventListener('click', () => {
        window.closePopup();
    });

    // Закрытие по клавише ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === "Escape") {
            window.closePopup();
        }
    });
});







//Логика работы показать еще в меню дочерние категории
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".submenu .hideli").forEach(el => {
        el.style.display = "none";
    });
    document.querySelectorAll(".show-more").forEach(button => {
        button.addEventListener("click", function() {
            const submenu = this.closest(".submenu");
            submenu.querySelectorAll(".hideli").forEach(el => {
                el.style.display = "list-item";
            });
            this.closest(".submenu-item--more").style.display = "none";
        });
    });
});

//Hover для меню
const menuItem = document.querySelector('li.menu-item.menu-item--catalog');
const mq = window.matchMedia('(min-width: 768px)');
function toggleHoverClass(e) {
    if (e.matches) {
        menuItem.addEventListener('mouseenter', enterHandler);
        menuItem.addEventListener('mouseleave', leaveHandler);
    } else {
        menuItem.classList.remove('hoveractive');
        menuItem.removeEventListener('mouseenter', enterHandler);
        menuItem.removeEventListener('mouseleave', leaveHandler);
    }
}
function enterHandler() { menuItem.classList.add('hoveractive'); }
function leaveHandler() { menuItem.classList.remove('hoveractive'); }
mq.addListener(toggleHoverClass);
toggleHoverClass(mq);



//Логика работы меню в мобильной версии
document.addEventListener("DOMContentLoaded", function () {
    const catalogLink = document.querySelector(".menu-item--catalog > a");
    const mainMenu   = document.getElementById("menu-mainmenu");
    const menuMain   = document.querySelector(".menu-main"); 
    const catalogMenu = document.querySelector(".menu-item--catalog .menu");
    const catalogItems = catalogMenu.querySelectorAll(".menu-item");

    let inCatalog = false;
    let currentSubmenu = null;
    let currentItem = null;
    let bound = false; 

    catalogItems.forEach(item => {
        const link = item.querySelector(":scope > a");
        if (link) link.dataset.origText = link.textContent;
    });
    catalogLink.dataset.origText = catalogLink.textContent;

    function resetMenu() {
        inCatalog = false;
        currentSubmenu = null;
        currentItem = null;
        menuMain.classList.remove("openmenu");
        mainMenu && mainMenu.classList.remove("is-hidden");
        catalogMenu.classList.remove("is-active");
        catalogItems.forEach(el => el.classList.remove("is-active", "is-active-submenu"));
        catalogMenu.querySelectorAll(".submenu").forEach(s => s.classList.remove("is-active"));
        catalogLink.textContent = catalogLink.dataset.origText;
        catalogItems.forEach(item => {
            const link = item.querySelector(":scope > a");
            if (link) link.textContent = link.dataset.origText;
        });
    }

    function openCatalog() {
        catalogItems.forEach(el => el.classList.add("is-active"));
        catalogMenu.classList.add("is-active");
        menuMain.classList.add("openmenu");
        if (mainMenu) mainMenu.classList.add("is-hidden");
        catalogLink.textContent = "Назад";
        inCatalog = true;
    }

    function handleCatalogClick(e) {
        e.preventDefault();
        if (!inCatalog) {
            openCatalog();
        } else if (currentSubmenu) {
            // если внутри подкатегории → выйти только в каталог
            currentSubmenu.classList.remove("is-active");
            currentItem.classList.remove("is-active-submenu");
            catalogItems.forEach(el => el.classList.add("is-active"));
            const link = currentItem.querySelector(":scope > a");
            if (link) link.textContent = link.dataset.origText;
            currentSubmenu = null;
            currentItem = null;
        } else {
            // если на уровне каталога → выйти в главное меню
            resetMenu();
        }
    }

    function handleSubmenuClick(e) {
        const link = e.currentTarget;
        const item = link.parentElement;
        const submenu = item.querySelector(".submenu");
        if (!submenu) return;
        e.preventDefault();

        // открыть подкатегорию
        catalogItems.forEach(el => el.classList.remove("is-active", "is-active-submenu"));
        item.classList.add("is-active", "is-active-submenu");
        submenu.classList.add("is-active");
        link.textContent = "Назад";
        currentSubmenu = submenu;
        currentItem = item;
    }

    function enableMobileMenu() {
        if (bound) return;
        catalogLink.addEventListener("click", handleCatalogClick);
        catalogItems.forEach(item => {
            const link = item.querySelector(":scope > a");
            const submenu = item.querySelector(".submenu");
            if (link && submenu) link.addEventListener("click", handleSubmenuClick);
        });
        bound = true;
    }

    function disableMobileMenu() {
        if (!bound) return;
        catalogLink.removeEventListener("click", handleCatalogClick);
        catalogItems.forEach(item => {
            const link = item.querySelector(":scope > a");
            const submenu = item.querySelector(".submenu");
            if (link && submenu) link.removeEventListener("click", handleSubmenuClick);
        });
        bound = false;
        resetMenu();
    }

    function checkWidth() {
        if (window.innerWidth <= 1020) {
            enableMobileMenu();
        } else {
            disableMobileMenu();
        }
    }

    window.addEventListener("resize", checkWidth);
    checkWidth();
});



//Маска ввода номера телефона
document.querySelectorAll('input[type="tel"]').forEach(input => {
  input.addEventListener('focus', function() { if (!this.value) this.value = '+7('; });
  input.addEventListener('input', function() {
    let x = this.value.replace(/\D/g, "").match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
    this.value = !x[2] ? "+7("
               : "+7(" + x[2] + (x[3] ? ")" + x[3] : "")
               + (x[4] ? "-" + x[4] : "")
               + (x[5] ? "-" + x[5] : "");
  });

});

// Определяем браузер Определяем устройство
document.addEventListener('DOMContentLoaded', function() {
    const html = document.documentElement;
    const ua = navigator.userAgent.toLowerCase();
    if (/iphone|ipod|ipad/.test(ua)) {
        html.classList.add('ios', 'mobile');
    } else if (/android/.test(ua)) {
        html.classList.add('android', 'mobile');
    }
    if (navigator.userAgent.indexOf("MSIE") >= 0 || navigator.userAgent.indexOf("Trident/") >= 0) {
        html.classList.add('ie');
    } else if (navigator.userAgent.indexOf("Chrome") >= 0) {
        html.classList.add('chrome');
    } else if (navigator.userAgent.indexOf("Firefox") >= 0) {
        html.classList.add('firefox');
    } else if (navigator.userAgent.indexOf("Safari") >= 0 && navigator.userAgent.indexOf("Chrome") < 0) {
        html.classList.add('safari');
    } else if (navigator.userAgent.indexOf("Opera") >= 0 || navigator.userAgent.indexOf("OPR") >= 0) {
        html.classList.add('opera');
    }
});



// Логика анимации и функционала фильтра
class CalendarSlider {
  constructor(container) {
    this.today = new Date();
    this.today.setHours(0, 0, 0, 0);
    this.container = typeof container === 'string' ? document.querySelector(container) : container;
    if (!this.container) return;
    this.currentDate = new Date();
    this.selectedDate = null;
    this.monthNames = [
      'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
      'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
    ];
    this.weekDays = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
    this.init();
  }

  init() {
    if (!this.container) return;
    this.render();
    this.bindEvents();
  }

  render() {
    if (!this.container) return;
    this.container.innerHTML = `
      <div class="calendar-container">
        <div class="calendar-header">
          <button class="nav-button prev-month">&#10094;</button>
          <div class="month-year">
            ${this.monthNames[this.currentDate.getMonth()]} ${this.currentDate.getFullYear()}
          </div>
          <button class="nav-button next-month">&#10095;</button>
        </div>
        <div class="calendar-grid">
          <div class="weekdays">
            ${this.weekDays.map(day => `<div class="weekday">${day}</div>`).join('')}
          </div>
          <div class="days">
            ${this.generateDays()}
          </div>
        </div>
      </div>
    `;
  }

  generateDays() {
    const year = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    let startDay = firstDay.getDay() - 1;
    if (startDay === -1) startDay = 6;
    const days = [];
    const prevMonth = new Date(year, month, 0);
    
    for (let i = startDay - 1; i >= 0; i--) {
      const day = prevMonth.getDate() - i;
      days.push({
        day: day,
        isOtherMonth: true,
        date: new Date(year, month - 1, day)
      });
    }
    for (let day = 1; day <= lastDay.getDate(); day++) {
      const date = new Date(year, month, day);
      days.push({
        day: day,
        isOtherMonth: false,
        date: date,
        isToday: this.isSameDay(date, this.today),
        isSelected: this.selectedDate && this.isSameDay(date, this.selectedDate),
        isDisabled: date < this.today
      });
    }
    const remainingCells = 42 - days.length;
    for (let day = 1; day <= remainingCells && days.length < 42; day++) {
      days.push({
        day: day,
        isOtherMonth: true,
        date: new Date(year, month + 1, day)
      });
    }
    return days.map(dayData => {
      let classes = ['day'];
      if (dayData.isOtherMonth) classes.push('other-month');
      if (dayData.isToday) classes.push('today');
      if (dayData.isSelected) classes.push('selected');
      if (dayData.isDisabled) classes.push('disabled');
      return `<div class="${classes.join(' ')}" data-date="${dayData.date.getTime()}">${dayData.day}</div>`;
    }).join('');
  }

  bindEvents() {
    if (!this.container) return;
    this.container.querySelector('.prev-month').addEventListener('click', () => {
      this.currentDate.setMonth(this.currentDate.getMonth() - 1);
      this.render();
      this.bindEvents();
    });
    this.container.querySelector('.next-month').addEventListener('click', () => {
      this.currentDate.setMonth(this.currentDate.getMonth() + 1);
      this.render();
      this.bindEvents();
    });
    this.container.querySelectorAll('.day').forEach(dayElement => {
      dayElement.addEventListener('click', (e) => {
        if (dayElement.classList.contains('disabled') || dayElement.classList.contains('other-month')) {
          return;
        }
        const timestamp = parseInt(e.target.getAttribute('data-date'));
        this.selectedDate = new Date(timestamp);
        this.render();
        this.bindEvents();
        if (this.onDateSelect) {
          this.onDateSelect(this.selectedDate);
        }
      });
    });
  }

  isSameDay(date1, date2) {
    return date1.getDate() === date2.getDate() &&
      date1.getMonth() === date2.getMonth() &&
      date1.getFullYear() === date2.getFullYear();
  }

  formatDate(date) {
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear().toString().slice(-2);
    return `${day}.${month}.${year}`;
  }

  getSelectedDate() {
    return this.selectedDate;
  }

  onSelect(callback) {
    this.onDateSelect = callback;
  }

  setDate(date) {
    if (!this.container) return;
    this.selectedDate = date;
    this.currentDate = new Date(date);
    this.render();
    this.bindEvents();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const filters = {
    category: {
      trigger: '.category-filter p',
      select: '.category-filter--select',
      items: '.category-filter--select div',
      reset: '.category-filter .reset',
      defaultText: 'Выберите специализацию',
      param: 'category'
    },
    gorod: {
      trigger: '.gorod-filter p',
      select: '.gorod-filter--select',
      items: '.gorod-filter--select div',
      reset: '.gorod-filter .reset',
      defaultText: 'Выберите город',
      param: 'city'
    }
  };
  
  const goFilter = document.querySelector('.go-filter');
  const sbrosButtons = document.querySelectorAll('.sbros');
  const priceFromInput = document.querySelector('.price-range__input--from');
  const priceToInput = document.querySelector('.price-range__input--to');
  let selectedDate = null;

  const mobileFilterBtn = document.querySelector('.section-filter__filter-mobile-btn');
  let mobileFilterCount = null;
  
  if (mobileFilterBtn) {
    mobileFilterCount = mobileFilterBtn.querySelector('span');
  }

  const updateMobileFilterCount = () => {
    if (!mobileFilterBtn) return;

    let count = 0;
    if (document.querySelector('.category-filter--select .active')) count++;
    if (document.querySelector('.gorod-filter--select .active')) count++;
    if (selectedDate) count++;
    if (priceFromInput && priceFromInput.value.trim()) count++;
    if (priceToInput && priceToInput.value.trim()) count++;

    if (count > 0) {
      mobileFilterBtn.classList.add('readyfilter');
      if (mobileFilterCount) mobileFilterCount.textContent = count;
    } else {
      mobileFilterBtn.classList.remove('readyfilter');
      if (mobileFilterCount) mobileFilterCount.textContent = '';
    }
  };

  const updateFilterLink = () => {
    if (!goFilter) return;

    const params = new URLSearchParams();
    let categoryPath = '';

    document.querySelectorAll('.category-filter--select .active, .gorod-filter--select .active')
      .forEach(el => {
        const filterType = el.closest('.category-filter--select') ? 'category' : 'city';
        const value = el.getAttribute('data-id');
        if (value && filterType === 'category') {
          categoryPath = value;
        } else if (value && filterType === 'city') {
          params.set('city', value);
        }
      });

    if (selectedDate) params.set('date', selectedDate);
    if (priceFromInput && priceFromInput.value.trim()) params.set('price_from', priceFromInput.value.trim());
    if (priceToInput && priceToInput.value.trim()) params.set('price_to', priceToInput.value.trim());

    const queryString = params.toString() ? `?${params.toString()}` : '';
    goFilter.setAttribute('href', `/${categoryPath || 'performers'}/${queryString}`);

    updateMobileFilterCount();
  };

  const initFilter = (config) => {
    const trigger = document.querySelector(config.trigger);
    const select = document.querySelector(config.select);
    const items = document.querySelectorAll(config.items);
    const reset = document.querySelector(config.reset);

    if (!trigger || !select || !reset) {
      return { trigger: null, select: null };
    }

    trigger.addEventListener('click', () => select.classList.toggle('openselect'));
    items.forEach(item => {
      item.addEventListener('click', () => {
        items.forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        select.classList.remove('openselect');
        trigger.textContent = item.textContent;
        trigger.parentElement.classList.add('ready');
        updateFilterLink();
      });
    });
    reset.addEventListener('click', () => {
      trigger.parentElement.classList.remove('ready');
      items.forEach(item => item.classList.remove('active'));
      trigger.textContent = config.defaultText;
      updateFilterLink();
    });

    return { trigger, select };
  };

  const parseURLParams = () => {
    const pathParts = window.location.pathname.split('/');
    const category = pathParts[1] || 'performers';
    const params = new URLSearchParams(window.location.search);
    const city = params.get('city');
    const date = params.get('date');
    const priceFrom = params.get('price_from');
    const priceTo = params.get('price_to');
    return { category, city, date, priceFrom, priceTo };
  };

  const applyURLParams = () => {
    const { category, city, date, priceFrom, priceTo } = parseURLParams();

    if (category !== 'performers') {
      const categoryItem = document.querySelector(`.category-filter--select div[data-id="${category}"]`);
      if (categoryItem) {
        const trigger = document.querySelector(filters.category.trigger);
        if (trigger) {
          categoryItem.classList.add('active');
          trigger.textContent = categoryItem.textContent;
          trigger.parentElement.classList.add('ready');
        }
      }
    }

    if (city) {
      const cityItem = document.querySelector(`.gorod-filter--select div[data-id="${city}"]`);
      if (cityItem) {
        const trigger = document.querySelector(filters.gorod.trigger);
        if (trigger) {
          cityItem.classList.add('active');
          trigger.textContent = cityItem.textContent;
          trigger.parentElement.classList.add('ready');
        }
      }
    }

    if (date) {
      const [day, month, year] = date.split('.');
      const parsedDate = new Date(`20${year}`, month - 1, day);
      if (!isNaN(parsedDate.getTime()) && parsedDate >= calendar.today) {
        selectedDate = date;
        calendar.setDate(parsedDate);
        const dateTrigger = document.querySelector('.data-filter p');
        if (dateTrigger) {
          dateTrigger.textContent = selectedDate;
          dateTrigger.parentElement.classList.add('ready');
        }
      }
    }

    if (priceFrom && priceFromInput) priceFromInput.value = priceFrom;
    if (priceTo && priceToInput) priceToInput.value = priceTo;

    updateFilterLink();
  };

  const filterElements = Object.values(filters).map(initFilter);

  const calendar = new CalendarSlider('.data-filter--select');
  const dateTrigger = document.querySelector('.data-filter p');
  const dateSelect = document.querySelector('.data-filter--select');
  const dateReset = document.querySelector('.data-filter .reset');

  if (dateTrigger && dateSelect && dateReset) {
    dateTrigger.addEventListener('click', () => {
      dateSelect.classList.toggle('openselect');
    });

    calendar.onSelect(function (date) {
      selectedDate = calendar.formatDate(date);
      dateTrigger.textContent = selectedDate;
      dateTrigger.parentElement.classList.add('ready');
      dateSelect.classList.remove('openselect');
      updateFilterLink();
    });

    dateReset.addEventListener('click', () => {
      dateTrigger.parentElement.classList.remove('ready');
      dateTrigger.textContent = 'Дата';
      selectedDate = null;
      calendar.selectedDate = null;
      calendar.render();
      calendar.bindEvents();
      updateFilterLink();
    });
  }

  if (priceFromInput && priceToInput) {
    [priceFromInput, priceToInput].forEach(input => {
      input.addEventListener('input', () => {
        updateFilterLink();
      });
    });
  }

  sbrosButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const categoryTrigger = document.querySelector(filters.category.trigger);
      if (categoryTrigger) {
        categoryTrigger.parentElement.classList.remove('ready');
        document.querySelectorAll(filters.category.items).forEach(item => item.classList.remove('active'));
        categoryTrigger.textContent = filters.category.defaultText;
      }

      const gorodTrigger = document.querySelector(filters.gorod.trigger);
      if (gorodTrigger) {
        gorodTrigger.parentElement.classList.remove('ready');
        document.querySelectorAll(filters.gorod.items).forEach(item => item.classList.remove('active'));
        gorodTrigger.textContent = filters.gorod.defaultText;
      }

      if (dateTrigger) {
        dateTrigger.parentElement.classList.remove('ready');
        dateTrigger.textContent = 'Дата';
      }
      
      selectedDate = null;
      calendar.selectedDate = null;
      calendar.render();
      calendar.bindEvents();

      if (priceFromInput) priceFromInput.value = '';
      if (priceToInput) priceToInput.value = '';

      updateFilterLink();
    });
  });

  document.addEventListener('click', (e) => {
    filterElements.forEach(({ trigger, select }) => {
      if (!trigger || !select) return;
      if (!trigger.contains(e.target) && !select.contains(e.target)) {
        select.classList.remove('openselect');
      }
    });
    
    if (dateTrigger && dateSelect) {
      if (!dateTrigger.contains(e.target) && 
          !dateSelect.contains(e.target) && 
          !e.target.closest('.calendar-header')) {
        dateSelect.classList.remove('openselect');
      }
    }
  });

  applyURLParams();
});



const mobileFilterWrap = document.querySelector('.section-filter__filter-mobile');
const mobileFilterBtnToggle = document.querySelector('.section-filter__filter-mobile-btn');
const filterBlock = document.querySelector('.section-filter__filter-block');

if (mobileFilterWrap && mobileFilterBtnToggle) {
  // Открытие/закрытие по клику на кнопку
  mobileFilterBtnToggle.addEventListener('click', (e) => {
    e.stopPropagation(); // чтобы клик по кнопке не улетал в общий обработчик
    mobileFilterWrap.classList.toggle('open-filter-mobile');
  });

  // Закрытие при клике вне кнопки и блока фильтров
  document.addEventListener('click', (e) => {
    if (
      !mobileFilterBtnToggle.contains(e.target) &&
      !filterBlock.contains(e.target)
    ) {
      mobileFilterWrap.classList.remove('open-filter-mobile');
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('login') === '1') {
        if (typeof window.openPopup === 'function') {
            window.openPopup('start-performer');
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});