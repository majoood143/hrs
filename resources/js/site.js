import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { raceWidget } from './race-widget';

gsap.registerPlugin(ScrollTrigger);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Swiper (+ its CSS and modules) is only needed by the three sliders below; loading it eagerly
// on every page — checkout, order status, plain content pages — cost their parse/execute time
// for nothing. Fetched once, on demand, the first time a page actually has a slider to build.
let swiperPromise = null;
function loadSwiper() {
    swiperPromise ??= Promise.all([
        import('swiper'),
        import('swiper/modules'),
        import('swiper/css'),
        import('swiper/css/effect-creative'),
        import('swiper/css/pagination'),
        import('swiper/css/navigation'),
    ]).then(([{ default: Swiper }, modules]) => ({ Swiper, ...modules }));

    return swiperPromise;
}

function heroSlideshow() {
    const root = document.querySelector('[data-hero-slider]');
    if (!root) return;

    const container = root.querySelector('.hero-swiper');
    if (!container) return;

    const slideCount = container.querySelectorAll('.swiper-slide').length;
    const autoplayEnabled = root.dataset.autoplay !== 'false' && !reduceMotion && slideCount > 1;
    const delay = parseInt(root.dataset.autoplayDelay ?? '6000', 10);

    function animateSlide(slideEl) {
        if (!slideEl) return;

        const bg = slideEl.querySelector('[data-hero-slide-image]');
        const eyebrow = slideEl.querySelector('[data-hero-eyebrow]');
        const heading = slideEl.querySelector('[data-hero-heading]');
        const sub = slideEl.querySelector('[data-hero-sub]');
        const ctas = slideEl.querySelectorAll('[data-hero-cta]');
        const targets = [eyebrow, heading, sub, ...ctas].filter(Boolean);

        if (reduceMotion) {
            gsap.set(targets, { opacity: 1, y: 0 });
            if (bg) gsap.set(bg, { scale: 1 });
            return;
        }

        gsap.set(targets, { opacity: 0, y: 24 });

        const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
        if (eyebrow) tl.to(eyebrow, { opacity: 1, y: 0, duration: 0.5 });
        if (heading) tl.to(heading, { opacity: 1, y: 0, duration: 0.7 }, '-=0.3');
        if (sub) tl.to(sub, { opacity: 1, y: 0, duration: 0.6 }, '-=0.4');
        if (ctas.length) tl.to(ctas, { opacity: 1, y: 0, duration: 0.5, stagger: 0.1 }, '-=0.3');

        if (bg) {
            gsap.killTweensOf(bg);
            gsap.fromTo(bg, { scale: 1 }, { scale: 1.12, duration: delay / 1000 + 1.5, ease: 'none' });
        }
    }

    loadSwiper().then(({ Swiper, Autoplay, EffectCreative, Pagination, Keyboard, A11y }) => {
        const swiper = new Swiper(container, {
            modules: [Autoplay, EffectCreative, Pagination, Keyboard, A11y],
            effect: 'creative',
            creativeEffect: {
                prev: { shadow: true, translate: [0, 0, -300], opacity: 0.6 },
                next: { translate: ['100%', 0, 0] },
            },
            speed: reduceMotion ? 0 : 900,
            loop: slideCount > 1,
            keyboard: { enabled: true },
            a11y: { enabled: true },
            pagination: slideCount > 1 ? {
                el: root.querySelector('[data-hero-pagination]'),
                clickable: true,
            } : false,
            autoplay: autoplayEnabled ? {
                delay,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            } : false,
            on: {
                init(sw) {
                    animateSlide(sw.slides[sw.activeIndex]);
                },
                slideChangeTransitionStart(sw) {
                    animateSlide(sw.slides[sw.activeIndex]);
                },
            },
        });

        root.addEventListener('focusin', () => swiper.autoplay?.stop());
        root.addEventListener('focusout', () => swiper.autoplay?.start());
    });
}

function horseGallerySlider() {
    const root = document.querySelector('[data-gallery-slider]');
    if (!root) return;

    const container = root.querySelector('.gallery-swiper');
    if (!container) return;

    const slideCount = container.querySelectorAll('.swiper-slide').length;

    loadSwiper().then(({ Swiper, Pagination, Navigation, Keyboard, A11y }) => {
        new Swiper(container, {
            modules: [Pagination, Navigation, Keyboard, A11y],
            loop: slideCount > 1,
            keyboard: { enabled: true },
            a11y: { enabled: true },
            pagination: { el: container.querySelector('.swiper-pagination'), clickable: true },
            navigation: {
                nextEl: container.querySelector('.swiper-button-next'),
                prevEl: container.querySelector('.swiper-button-prev'),
            },
        });
    });
}

function promoSliders() {
    document.querySelectorAll('[data-promo-slider]').forEach((root) => {
        const container = root.querySelector('.promo-swiper');
        if (!container) return;

        const slideCount = container.querySelectorAll('.swiper-slide').length;
        const columns = Math.min(parseInt(root.dataset.columns ?? '1', 10), slideCount) || 1;
        const autoplayEnabled = root.dataset.autoplay !== 'false' && !reduceMotion && slideCount > 1;
        const delay = parseInt(root.dataset.autoplayDelay ?? '5000', 10);

        function playActiveVideo(sw) {
            container.querySelectorAll('[data-promo-video]').forEach((video) => video.pause());
            const activeVideo = sw.slides[sw.activeIndex]?.querySelector('[data-promo-video]');
            if (activeVideo) activeVideo.play().catch(() => {});
        }

        loadSwiper().then(({ Swiper, Autoplay, Pagination, Navigation, Keyboard, A11y }) => {
            const swiper = new Swiper(container, {
                modules: [Autoplay, Pagination, Navigation, Keyboard, A11y],
                slidesPerView: 1,
                spaceBetween: 24,
                loop: slideCount > columns,
                keyboard: { enabled: true },
                a11y: { enabled: true },
                breakpoints: {
                    768: { slidesPerView: columns },
                },
                pagination: slideCount > 1 ? {
                    el: container.querySelector('.swiper-pagination'),
                    clickable: true,
                } : false,
                navigation: slideCount > 1 ? {
                    nextEl: container.querySelector('.swiper-button-next'),
                    prevEl: container.querySelector('.swiper-button-prev'),
                } : false,
                autoplay: autoplayEnabled ? {
                    delay,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true,
                } : false,
                on: {
                    init: playActiveVideo,
                    slideChangeTransitionStart: playActiveVideo,
                },
            });

            root.addEventListener('focusin', () => swiper.autoplay?.stop());
            root.addEventListener('focusout', () => swiper.autoplay?.start());
        });
    });
}

function revealOnScroll() {
    const groups = document.querySelectorAll('[data-reveal-group]');

    groups.forEach((group) => {
        const items = group.querySelectorAll('[data-reveal-item]');
        if (!items.length) return;

        if (reduceMotion) {
            gsap.set(items, { opacity: 1, y: 0 });
            return;
        }

        gsap.set(items, { opacity: 0, y: 32 });
        gsap.to(items, {
            opacity: 1,
            y: 0,
            duration: 0.7,
            ease: 'power3.out',
            stagger: 0.12,
            scrollTrigger: {
                trigger: group,
                start: 'top 82%',
                once: true,
            },
        });
    });

    const solo = document.querySelectorAll('[data-reveal]');
    solo.forEach((el) => {
        if (reduceMotion) {
            gsap.set(el, { opacity: 1, y: 0 });
            return;
        }

        gsap.set(el, { opacity: 0, y: 24 });
        gsap.to(el, {
            opacity: 1,
            y: 0,
            duration: 0.6,
            ease: 'power3.out',
            scrollTrigger: {
                trigger: el,
                start: 'top 85%',
                once: true,
            },
        });
    });
}

function horseCardTilt() {
    if (reduceMotion) return;

    document.querySelectorAll('[data-tilt-card]').forEach((card) => {
        const image = card.querySelector('[data-tilt-image]');
        const quickScale = gsap.quickTo(image ?? card, 'scale', { duration: 0.4, ease: 'power2.out' });
        const quickRotate = gsap.quickTo(card, 'rotateX', { duration: 0.4, ease: 'power2.out' });
        const quickRotateY = gsap.quickTo(card, 'rotateY', { duration: 0.4, ease: 'power2.out' });

        card.style.transformStyle = 'preserve-3d';
        card.style.perspective = '800px';

        card.addEventListener('mouseenter', () => quickScale(1.06));
        card.addEventListener('mouseleave', () => {
            quickScale(1);
            quickRotate(0);
            quickRotateY(0);
        });
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const px = (e.clientX - rect.left) / rect.width - 0.5;
            const py = (e.clientY - rect.top) / rect.height - 0.5;
            quickRotate(py * -8);
            quickRotateY(px * 8);
        });
    });
}

function animatedCounters() {
    document.querySelectorAll('[data-counter]').forEach((el) => {
        const target = parseFloat(el.dataset.counter);
        if (Number.isNaN(target)) return;

        const counter = { value: 0 };
        const suffix = el.dataset.counterSuffix ?? '';
        const decimals = el.dataset.counterDecimals ? parseInt(el.dataset.counterDecimals, 10) : 0;

        const render = () => {
            el.textContent = counter.value.toFixed(decimals) + suffix;
        };

        if (reduceMotion) {
            counter.value = target;
            render();
            return;
        }

        render();

        gsap.to(counter, {
            value: target,
            duration: 1.6,
            ease: 'power2.out',
            onUpdate: render,
            scrollTrigger: {
                trigger: el,
                start: 'top 90%',
                once: true,
            },
        });
    });
}

function searchPreviewCascade() {
    const form = document.querySelector('[data-search-preview]');
    if (!form) return;

    const countrySelect = form.querySelector('[data-field="from_country_id"]');
    const toCountrySelect = form.querySelector('[data-field="to_country_id"]');

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const params = new URLSearchParams();
        if (countrySelect?.value) params.set('from_country_id', countrySelect.value);
        if (toCountrySelect?.value) params.set('to_country_id', toCountrySelect.value);

        const dateInput = form.querySelector('[data-field="transfer_date"]');
        if (dateInput?.value) params.set('date_from', dateInput.value);

        const typeInput = form.querySelector('[data-field="type"]');
        if (typeInput?.value) params.set('type', typeInput.value);

        const query = params.toString();
        window.location.href = form.action + (query ? `?${query}` : '');
    });
}

function transferBoardFilters() {
    const panel = document.querySelector('[data-board-panel]');
    const toggle = document.querySelector('[data-board-drawer-toggle]');
    const close = document.querySelector('[data-board-drawer-close]');
    const backdrop = document.querySelector('[data-board-drawer-backdrop]');
    const form = document.querySelector('[data-board-filters]');
    if (!panel || !form) return;

    const isDesktop = () => window.matchMedia('(min-width: 768px)').matches;

    const openDrawer = () => {
        panel.classList.add('is-open');
        backdrop?.classList.remove('hidden');
        backdrop?.classList.add('is-open');
    };

    const closeDrawer = () => {
        panel.classList.remove('is-open');
        backdrop?.classList.remove('is-open');
        backdrop?.classList.add('hidden');
    };

    toggle?.addEventListener('click', openDrawer);
    close?.addEventListener('click', closeDrawer);
    backdrop?.addEventListener('click', closeDrawer);

    form.querySelectorAll('[data-auto-submit]').forEach((field) => {
        field.addEventListener('change', () => {
            const resetNames = (field.dataset.resetOnChange || '').split(',').map((n) => n.trim()).filter(Boolean);
            resetNames.forEach((name) => {
                const target = form.querySelector(`[name="${name}"]`);
                if (target) target.value = '';
            });

            if (isDesktop()) form.submit();
        });
    });

    form.querySelectorAll('[data-type-toggle]').forEach((field) => {
        field.addEventListener('change', () => form.submit());
    });
}

function mobileMenu() {
    const toggle = document.querySelector('[data-mobile-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');
    const backdrop = document.querySelector('[data-mobile-menu-backdrop]');
    if (!toggle || !menu) return;

    const iconOpen = toggle.querySelector('[data-icon-open]');
    const iconClose = toggle.querySelector('[data-icon-close]');

    menu.setAttribute('inert', '');
    menu.setAttribute('aria-hidden', 'true');

    const isOpen = () => menu.classList.contains('is-open');

    const openMenu = () => {
        menu.classList.add('is-open');
        menu.removeAttribute('inert');
        menu.setAttribute('aria-hidden', 'false');
        backdrop?.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        iconOpen?.classList.add('hidden');
        iconClose?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeMenu = () => {
        menu.classList.remove('is-open');
        menu.setAttribute('inert', '');
        menu.setAttribute('aria-hidden', 'true');
        backdrop?.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
        iconOpen?.classList.remove('hidden');
        iconClose?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    toggle.addEventListener('click', () => (isOpen() ? closeMenu() : openMenu()));
    backdrop?.addEventListener('click', closeMenu);
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) closeMenu();
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches) closeMenu();
    });
}

function contactReveal() {
    document.querySelectorAll('[data-reveal-contact]').forEach((button) => {
        button.addEventListener('click', () => {
            const number = button.dataset.revealContact ?? '';
            const link = document.createElement('a');
            link.href = 'tel:' + number.replace(/[^0-9+]/g, '');
            link.className = button.className.replace('js-reveal', '');
            link.textContent = number;
            button.replaceWith(link);
        }, { once: true });
    });
}

function passportReveal() {
    document.querySelectorAll('[data-reveal-passport]').forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.dataset.revealPassport ?? '';
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = button.className.replace('js-reveal', '');
            link.innerHTML = button.innerHTML;
            button.replaceWith(link);
        }, { once: true });
    });
}

function copyLink() {
    document.querySelectorAll('[data-copy-link]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.copyLink ?? window.location.href;

            try {
                await navigator.clipboard.writeText(url);
            } catch {
                return;
            }

            const icon = button.querySelector('[data-copy-icon]');
            const originalTitle = button.getAttribute('aria-label');
            button.setAttribute('aria-label', button.dataset.copiedLabel ?? originalTitle);
            button.classList.add('!bg-emerald-600', '!text-white');

            if (icon) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />';
            }

            setTimeout(() => {
                button.setAttribute('aria-label', originalTitle ?? button.dataset.copyLabel);
                button.classList.remove('!bg-emerald-600', '!text-white');
                if (icon) {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757M10.81 15.313a4.5 4.5 0 0 1-1.242-7.244l4.5-4.5a4.5 4.5 0 0 1 6.364 6.364l-1.757 1.757" />';
                }
            }, 2000);
        });
    });
}

// The Print buttons are rendered hidden: without this script they could do nothing.
function printButtons() {
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => window.print());
    });
}

function transferBoardWizard() {
    const wizard = document.querySelector('[data-wizard="transfer"]');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('[data-step]'));
    const stepLabel = wizard.querySelector('[data-step-label]');
    const progressFill = wizard.querySelector('[data-progress-fill]');
    const backBtn = wizard.querySelector('[data-back]');
    const nextBtn = wizard.querySelector('[data-next]');
    const submitBtn = wizard.querySelector('[data-submit]');
    const labelTemplate = wizard.dataset.stepLabelTemplate ?? ':current/:total';
    let current = 0;

    function show(index) {
        current = Math.max(0, Math.min(index, steps.length - 1));

        steps.forEach((step, i) => step.classList.toggle('hidden', i !== current));
        backBtn?.classList.toggle('hidden', current === 0);
        nextBtn?.classList.toggle('hidden', current === steps.length - 1);
        submitBtn?.classList.toggle('hidden', current !== steps.length - 1);

        if (stepLabel) {
            stepLabel.textContent = labelTemplate
                .replace(':current', current + 1)
                .replace(':total', steps.length);
        }
        if (progressFill) {
            progressFill.style.width = `${((current + 1) / steps.length) * 100}%`;
        }
        if (current === steps.length - 1) fillReview();

        wizard.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    }

    nextBtn?.addEventListener('click', () => {
        const fields = steps[current].querySelectorAll('input, select, textarea');
        for (const field of fields) {
            if (field.offsetParent !== null && !field.checkValidity()) {
                field.reportValidity();
                return;
            }
        }
        show(current + 1);
    });

    backBtn?.addEventListener('click', () => show(current - 1));

    const priceField = wizard.querySelector('[data-price-field]');
    function syncPriceVisibility() {
        const type = wizard.querySelector('[name="type"]:checked')?.value;
        const isOffer = type === 'offer';
        priceField?.classList.toggle('hidden', !isOffer);
    }
    wizard.querySelectorAll('[name="type"]').forEach((input) => input.addEventListener('change', syncPriceVisibility));
    syncPriceVisibility();

    const photoInput = wizard.querySelector('[data-photo-input]');
    const photoPreview = wizard.querySelector('[data-photo-preview]');
    photoInput?.addEventListener('change', () => {
        const file = photoInput.files?.[0];
        if (!file || !photoPreview) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.src = e.target.result;
            photoPreview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    function fillReview() {
        const setReview = (key, value) => {
            const el = wizard.querySelector(`[data-review="${key}"]`);
            if (el) el.textContent = value || '—';
        };

        const typeInput = wizard.querySelector('[name="type"]:checked');
        const fromSelect = wizard.querySelector('[name="from_city_id"]');
        const toSelect = wizard.querySelector('[name="to_city_id"]');
        const priceInput = wizard.querySelector('[name="price"]');

        setReview('type', typeInput?.parentElement.querySelector('[data-card-title]')?.textContent.trim());
        setReview('route', `${fromSelect?.selectedOptions[0]?.textContent ?? ''} → ${toSelect?.selectedOptions[0]?.textContent ?? ''}`);
        setReview('capacity', wizard.querySelector('[name="capacity"]')?.value);
        setReview('date', wizard.querySelector('[name="transfer_date"]')?.value);
        setReview('price', typeInput?.value === 'offer' && priceInput?.value ? (wizard.dataset.currencySymbol ?? '') + priceInput.value : wizard.dataset.budgetOpenLabel);
        setReview('contact', wizard.querySelector('[name="contact_number"]')?.value);
    }

    wizard.querySelector('[data-captcha-refresh]')?.addEventListener('click', () => {
        const img = wizard.querySelector('[data-captcha-image]');
        if (img) img.src = img.dataset.src + '?t=' + Date.now();
    });

    initLocationCascades(wizard);

    const errorStep = parseInt(wizard.dataset.errorStep ?? '0', 10);
    show(Number.isNaN(errorStep) ? 0 : errorStep);
}

function initLocationCascades(wizard) {
    const treeEl = wizard.querySelector('[data-location-tree]');
    if (!treeEl) return;

    let tree = [];
    try {
        tree = JSON.parse(treeEl.textContent || '[]');
    } catch {
        tree = [];
    }

    function populateSelect(select, items, placeholder) {
        select.innerHTML = '';
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder;
        select.appendChild(placeholderOption);
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            select.appendChild(option);
        });
    }

    function findCityLocation(cityId) {
        for (const country of tree) {
            for (const region of country.regions) {
                const city = region.cities.find((c) => String(c.id) === String(cityId));
                if (city) return { country, region };
            }
        }
        return null;
    }

    wizard.querySelectorAll('[data-location-group]').forEach((group) => {
        const countrySelect = group.querySelector('[data-location-country]');
        const regionSelect = group.querySelector('[data-location-region]');
        const citySelect = group.querySelector('[data-location-city]');
        if (!countrySelect || !regionSelect || !citySelect) return;

        const countryPlaceholder = countrySelect.querySelector('option')?.textContent ?? '';
        const regionPlaceholder = regionSelect.querySelector('option')?.textContent ?? '';
        const cityPlaceholder = citySelect.querySelector('option')?.textContent ?? '';

        populateSelect(countrySelect, tree, countryPlaceholder);

        countrySelect.addEventListener('change', () => {
            const country = tree.find((c) => String(c.id) === countrySelect.value);
            populateSelect(regionSelect, country ? country.regions : [], regionPlaceholder);
            populateSelect(citySelect, [], cityPlaceholder);
            regionSelect.disabled = !country;
            citySelect.disabled = true;
        });

        regionSelect.addEventListener('change', () => {
            const country = tree.find((c) => String(c.id) === countrySelect.value);
            const region = country?.regions.find((r) => String(r.id) === regionSelect.value);
            populateSelect(citySelect, region ? region.cities : [], cityPlaceholder);
            citySelect.disabled = !region;
        });

        const oldValue = citySelect.dataset.oldValue;
        const location = oldValue ? findCityLocation(oldValue) : null;
        if (location) {
            populateSelect(regionSelect, location.country.regions, regionPlaceholder);
            populateSelect(citySelect, location.region.cities, cityPlaceholder);
            countrySelect.value = String(location.country.id);
            regionSelect.value = String(location.region.id);
            citySelect.value = String(oldValue);
            regionSelect.disabled = false;
            citySelect.disabled = false;
        }
    });
}

function horseSaleWizard() {
    const wizard = document.querySelector('[data-wizard="horse-sale"]');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('[data-step]'));
    const stepLabel = wizard.querySelector('[data-step-label]');
    const progressFill = wizard.querySelector('[data-progress-fill]');
    const backBtn = wizard.querySelector('[data-back]');
    const nextBtn = wizard.querySelector('[data-next]');
    const submitBtn = wizard.querySelector('[data-submit]');
    const labelTemplate = wizard.dataset.stepLabelTemplate ?? ':current/:total';
    let current = 0;

    function show(index) {
        current = Math.max(0, Math.min(index, steps.length - 1));

        steps.forEach((step, i) => step.classList.toggle('hidden', i !== current));
        backBtn?.classList.toggle('hidden', current === 0);
        nextBtn?.classList.toggle('hidden', current === steps.length - 1);
        submitBtn?.classList.toggle('hidden', current !== steps.length - 1);

        if (stepLabel) {
            stepLabel.textContent = labelTemplate
                .replace(':current', current + 1)
                .replace(':total', steps.length);
        }
        if (progressFill) {
            progressFill.style.width = `${((current + 1) / steps.length) * 100}%`;
        }
        if (current === steps.length - 1) fillReview();

        wizard.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    }

    nextBtn?.addEventListener('click', () => {
        const fields = steps[current].querySelectorAll('input, select, textarea');
        for (const field of fields) {
            if (field.offsetParent !== null && !field.checkValidity()) {
                field.reportValidity();
                return;
            }
        }
        show(current + 1);
    });

    backBtn?.addEventListener('click', () => show(current - 1));

    const photoInput = wizard.querySelector('[data-photo-input]');
    const photoPreview = wizard.querySelector('[data-photo-preview]');
    photoInput?.addEventListener('change', () => {
        const file = photoInput.files?.[0];
        if (!file || !photoPreview) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.src = e.target.result;
            photoPreview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    const galleryInput = wizard.querySelector('[data-gallery-input]');
    const galleryPreviews = wizard.querySelector('[data-gallery-previews]');
    galleryInput?.addEventListener('change', () => {
        if (!galleryPreviews) return;
        galleryPreviews.innerHTML = '';

        const files = Array.from(galleryInput.files ?? []);
        galleryPreviews.classList.toggle('hidden', files.length === 0);

        files.forEach((file) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = '';
                img.className = 'h-16 w-16 rounded-lg object-cover';
                galleryPreviews.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });

    function fillReview() {
        const setReview = (key, value) => {
            const el = wizard.querySelector(`[data-review="${key}"]`);
            if (el) el.textContent = value || '—';
        };

        const setOptionalRow = (key, value) => {
            const row = wizard.querySelector(`[data-review-row="${key}"]`);
            if (!row) return value;
            row.classList.toggle('hidden', !value);
            row.classList.toggle('flex', !!value);
            setReview(key, value);
            return value;
        };

        const citySelect = wizard.querySelector('[name="city_id"]');
        const priceInput = wizard.querySelector('[name="price"]');
        const birthCountrySelect = wizard.querySelector('[name="birth_country_id"]');
        const passportFile = wizard.querySelector('[name="passport_document"]')?.files?.[0];

        setReview('name', wizard.querySelector('[name="en_name"]')?.value);
        setReview('city', citySelect?.selectedOptions[0]?.textContent ?? '');
        setReview('price', priceInput?.value ?? '');
        setReview('contact', wizard.querySelector('[name="contact_number"]')?.value);

        const hasPedigree = [
            setOptionalRow('dam', wizard.querySelector('[name="dam"]')?.value),
            setOptionalRow('sire', wizard.querySelector('[name="sire"]')?.value),
            setOptionalRow('birth_country', birthCountrySelect?.value ? birthCountrySelect.selectedOptions[0]?.textContent : ''),
            setOptionalRow('passport_number', wizard.querySelector('[name="passport_number"]')?.value),
            setOptionalRow('passport_document', passportFile?.name),
        ].some(Boolean);

        wizard.querySelector('[data-review-pedigree]')?.classList.toggle('hidden', !hasPedigree);
    }

    wizard.querySelector('[data-captcha-refresh]')?.addEventListener('click', () => {
        const img = wizard.querySelector('[data-captcha-image]');
        if (img) img.src = img.dataset.src + '?t=' + Date.now();
    });

    const errorStep = parseInt(wizard.dataset.errorStep ?? '0', 10);
    show(Number.isNaN(errorStep) ? 0 : errorStep);
}

function farrierWizard() {
    const wizard = document.querySelector('[data-wizard="farrier"]');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('[data-step]'));
    const stepLabel = wizard.querySelector('[data-step-label]');
    const progressFill = wizard.querySelector('[data-progress-fill]');
    const backBtn = wizard.querySelector('[data-back]');
    const nextBtn = wizard.querySelector('[data-next]');
    const submitBtn = wizard.querySelector('[data-submit]');
    const labelTemplate = wizard.dataset.stepLabelTemplate ?? ':current/:total';
    let current = 0;

    function show(index) {
        current = Math.max(0, Math.min(index, steps.length - 1));

        steps.forEach((step, i) => step.classList.toggle('hidden', i !== current));
        backBtn?.classList.toggle('hidden', current === 0);
        nextBtn?.classList.toggle('hidden', current === steps.length - 1);
        submitBtn?.classList.toggle('hidden', current !== steps.length - 1);

        if (stepLabel) {
            stepLabel.textContent = labelTemplate
                .replace(':current', current + 1)
                .replace(':total', steps.length);
        }
        if (progressFill) {
            progressFill.style.width = `${((current + 1) / steps.length) * 100}%`;
        }
        if (current === steps.length - 1) fillReview();

        wizard.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    }

    nextBtn?.addEventListener('click', () => {
        const fields = steps[current].querySelectorAll('input, select, textarea');
        for (const field of fields) {
            if (field.offsetParent !== null && !field.checkValidity()) {
                field.reportValidity();
                return;
            }
        }
        show(current + 1);
    });

    backBtn?.addEventListener('click', () => show(current - 1));

    const photoInput = wizard.querySelector('[data-photo-input]');
    const photoPreview = wizard.querySelector('[data-photo-preview]');
    photoInput?.addEventListener('change', () => {
        const file = photoInput.files?.[0];
        if (!file || !photoPreview) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.src = e.target.result;
            photoPreview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    function fillReview() {
        const setReview = (key, value) => {
            const el = wizard.querySelector(`[data-review="${key}"]`);
            if (el) el.textContent = value || '—';
        };

        const citySelect = wizard.querySelector('[name="city_id"]');
        const priceInput = wizard.querySelector('[name="price"]');

        setReview('name', wizard.querySelector('[name="en_name"]')?.value);
        setReview('city', citySelect?.selectedOptions[0]?.textContent ?? '');
        setReview('price', priceInput?.value ? (wizard.dataset.currencySymbol ?? '') + priceInput.value : '');
        setReview('contact', wizard.querySelector('[name="contact_number"]')?.value);
    }

    wizard.querySelector('[data-captcha-refresh]')?.addEventListener('click', () => {
        const img = wizard.querySelector('[data-captcha-image]');
        if (img) img.src = img.dataset.src + '?t=' + Date.now();
    });

    const errorStep = parseInt(wizard.dataset.errorStep ?? '0', 10);
    show(Number.isNaN(errorStep) ? 0 : errorStep);
}

function toolSaleWizard() {
    const wizard = document.querySelector('[data-wizard="tool-sale"]');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('[data-step]'));
    const stepLabel = wizard.querySelector('[data-step-label]');
    const progressFill = wizard.querySelector('[data-progress-fill]');
    const backBtn = wizard.querySelector('[data-back]');
    const nextBtn = wizard.querySelector('[data-next]');
    const submitBtn = wizard.querySelector('[data-submit]');
    const labelTemplate = wizard.dataset.stepLabelTemplate ?? ':current/:total';
    let current = 0;

    function show(index) {
        current = Math.max(0, Math.min(index, steps.length - 1));

        steps.forEach((step, i) => step.classList.toggle('hidden', i !== current));
        backBtn?.classList.toggle('hidden', current === 0);
        nextBtn?.classList.toggle('hidden', current === steps.length - 1);
        submitBtn?.classList.toggle('hidden', current !== steps.length - 1);

        if (stepLabel) {
            stepLabel.textContent = labelTemplate
                .replace(':current', current + 1)
                .replace(':total', steps.length);
        }
        if (progressFill) {
            progressFill.style.width = `${((current + 1) / steps.length) * 100}%`;
        }
        if (current === steps.length - 1) fillReview();

        wizard.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    }

    nextBtn?.addEventListener('click', () => {
        const fields = steps[current].querySelectorAll('input, select, textarea');
        for (const field of fields) {
            if (field.offsetParent !== null && !field.checkValidity()) {
                field.reportValidity();
                return;
            }
        }
        show(current + 1);
    });

    backBtn?.addEventListener('click', () => show(current - 1));

    const photoInput = wizard.querySelector('[data-photo-input]');
    const photoPreview = wizard.querySelector('[data-photo-preview]');
    photoInput?.addEventListener('change', () => {
        const file = photoInput.files?.[0];
        if (!file || !photoPreview) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.src = e.target.result;
            photoPreview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    function fillReview() {
        const setReview = (key, value) => {
            const el = wizard.querySelector(`[data-review="${key}"]`);
            if (el) el.textContent = value || '—';
        };

        const citySelect = wizard.querySelector('[name="city_id"]');
        const priceInput = wizard.querySelector('[name="price"]');

        setReview('name', wizard.querySelector('[name="en_name"]')?.value);
        setReview('city', citySelect?.selectedOptions[0]?.textContent ?? '');
        setReview('price', priceInput?.value ?? '');
        setReview('contact', wizard.querySelector('[name="contact_number"]')?.value);
    }

    wizard.querySelector('[data-captcha-refresh]')?.addEventListener('click', () => {
        const img = wizard.querySelector('[data-captcha-image]');
        if (img) img.src = img.dataset.src + '?t=' + Date.now();
    });

    initLocationCascades(wizard);

    const errorStep = parseInt(wizard.dataset.errorStep ?? '0', 10);
    show(Number.isNaN(errorStep) ? 0 : errorStep);
}

function eventsCalendar() {
    const root = document.querySelector('[data-events-calendar]');
    if (!root) return;

    const ICON_EXTERNAL = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 14L21 3"/></svg>';
    const ICON_INTERNAL = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

    const payloadEl = root.querySelector('[data-events-payload]');
    const i18nEl = root.querySelector('[data-events-i18n]');

    let events = [];
    try { events = JSON.parse(payloadEl?.textContent || '[]'); } catch { events = []; }

    let i18n = { noEventsDay: 'No events on this day.', more: ':count more', noLink: 'No link attached.' };
    try { i18n = Object.assign(i18n, JSON.parse(i18nEl?.textContent || '{}')); } catch { /* keep defaults */ }

    const locale = root.dataset.locale === 'ar' ? 'ar' : 'en';
    const grid = root.querySelector('[data-events-grid]');
    const weekdaysEl = root.querySelector('[data-events-weekdays]');
    const monthLabel = root.querySelector('[data-events-month-label]');
    const searchInput = root.querySelector('[data-events-search]');
    const chipsWrap = root.querySelector('[data-events-chips]');

    const drawer = document.querySelector('[data-events-drawer]');
    const drawerBackdrop = document.querySelector('[data-events-drawer-backdrop]');
    const drawerTitle = document.querySelector('[data-events-drawer-title]');
    const drawerContent = document.querySelector('[data-events-drawer-content]');
    const drawerClose = document.querySelector('[data-events-drawer-close]');

    if (!grid) return;

    let cursor = new Date();
    cursor.setDate(1);
    let filterCategory = 'all';
    let filterSearch = '';

    const pad = (n) => String(n).padStart(2, '0');
    const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    function weekdayLabels() {
        const base = new Date(2023, 0, 1); // a known Sunday
        const fmt = new Intl.DateTimeFormat(locale, { weekday: 'short' });
        return Array.from({ length: 7 }, (_, i) => {
            const d = new Date(base);
            d.setDate(base.getDate() + i);
            return fmt.format(d);
        });
    }

    function matchesFilter(ev) {
        if (filterCategory !== 'all' && String(ev.category) !== String(filterCategory)) return false;
        if (filterSearch) {
            const q = filterSearch.toLowerCase();
            const hay = `${ev.title} ${ev.description || ''}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    }

    function eventsForDate(dateStr) {
        return events
            .filter((ev) => ev.date === dateStr && matchesFilter(ev))
            .sort((a, b) => a.startTime.localeCompare(b.startTime));
    }

    function handleOpen(ev) {
        if (!ev.link) return;
        if (ev.isExternal) {
            window.open(ev.link, '_blank', 'noopener,noreferrer');
        } else {
            window.location.href = ev.link;
        }
    }

    function pillHtml(ev) {
        const icon = ev.link ? (ev.isExternal ? ICON_EXTERNAL : ICON_INTERNAL) : '';
        return `<div class="event-pill" style="background:${ev.categoryColor}" data-id="${ev.id}" title="${escapeHtml(ev.title)}">${icon}<span>${escapeHtml(ev.title)}</span></div>`;
    }

    function buildMonthCells(cur) {
        const year = cur.getFullYear();
        const month = cur.getMonth();
        const firstDay = new Date(year, month, 1);
        const startOffset = firstDay.getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        const cells = [];

        for (let i = 0; i < startOffset; i++) {
            const day = daysInPrevMonth - startOffset + i + 1;
            cells.push({ date: new Date(year, month - 1, day), outside: true });
        }
        for (let d = 1; d <= daysInMonth; d++) {
            cells.push({ date: new Date(year, month, d), outside: false });
        }
        while (cells.length % 7 !== 0 || cells.length < 42) {
            const last = cells[cells.length - 1].date;
            cells.push({ date: new Date(last.getFullYear(), last.getMonth(), last.getDate() + 1), outside: true });
            if (cells.length >= 42) break;
        }
        return cells;
    }

    function bindCellHandlers() {
        grid.querySelectorAll('.event-pill').forEach((p) => {
            p.addEventListener('click', (e) => {
                e.stopPropagation();
                const ev = events.find((x) => String(x.id) === p.dataset.id);
                if (ev) handleOpen(ev);
            });
        });
        grid.querySelectorAll('.events-cell').forEach((cell) => {
            cell.addEventListener('click', () => openDrawer(cell.dataset.date));
        });
    }

    function renderCalendar(animate) {
        const monthFmt = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' });
        monthLabel.textContent = monthFmt.format(cursor);

        const cells = buildMonthCells(cursor);
        const todayStr = ymd(new Date());

        const html = cells.map((c) => {
            const dateStr = ymd(c.date);
            const evs = eventsForDate(dateStr);
            const isToday = dateStr === todayStr;
            const pills = evs.slice(0, 3).map(pillHtml).join('');
            const more = evs.length > 3
                ? `<div class="mt-1 text-xs text-warm-700">${i18n.more.replace(':count', evs.length - 3)}</div>`
                : '';
            const dots = evs.slice(0, 5)
                .map((ev) => `<span class="event-dot" style="background:${ev.categoryColor}"></span>`)
                .join('');

            return `<div class="events-cell${c.outside ? ' is-outside' : ''}${isToday ? ' is-today' : ''}" data-date="${dateStr}">
                <span class="events-daynum">${c.date.getDate()}</span>
                <div class="hidden sm:block">${pills}${more}</div>
                <div class="mt-1 flex flex-wrap gap-1 sm:hidden">${dots}</div>
            </div>`;
        }).join('');

        const apply = () => {
            grid.innerHTML = html;
            bindCellHandlers();
            if (!reduceMotion) {
                gsap.fromTo(grid.querySelectorAll('.event-pill'), { opacity: 0, y: 4 }, { opacity: 1, y: 0, duration: 0.25, stagger: 0.01 });
            }
        };

        if (animate && !reduceMotion) {
            gsap.to(grid, {
                opacity: 0,
                duration: 0.15,
                onComplete: () => {
                    apply();
                    gsap.fromTo(grid, { opacity: 0 }, { opacity: 1, duration: 0.25 });
                },
            });
        } else {
            apply();
        }
    }

    function openDrawer(dateStr) {
        if (!drawer || !drawerContent || !drawerTitle) return;

        const evs = eventsForDate(dateStr);
        const d = new Date(`${dateStr}T00:00:00`);
        drawerTitle.textContent = new Intl.DateTimeFormat(locale, {
            weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
        }).format(d);

        if (!evs.length) {
            drawerContent.innerHTML = `<p class="text-sm text-warm-700">${i18n.noEventsDay}</p>`;
        } else {
            drawerContent.innerHTML = evs.map((ev) => {
                const icon = ev.link ? (ev.isExternal ? ICON_EXTERNAL : ICON_INTERNAL) : '';
                const desc = ev.description
                    ? `<div class="event-description mt-1 text-xs text-warm-700">${ev.description}</div>`
                    : '';
                return `<div class="drawer-event cursor-pointer rounded-xl border border-warm-200 p-3" data-id="${ev.id}">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 text-sm font-semibold text-warm-900">${icon}<span>${escapeHtml(ev.title)}</span></div>
                        <span class="text-xs text-warm-600">${ev.startLabel} - ${ev.endLabel}</span>
                    </div>
                    ${desc}
                    <span class="mt-2 inline-block rounded-full px-2 py-0.5 text-xs text-white" style="background:${ev.categoryColor}">${escapeHtml(ev.categoryLabel)}</span>
                </div>`;
            }).join('');

            drawerContent.querySelectorAll('.drawer-event').forEach((el) => {
                el.addEventListener('click', () => {
                    const ev = events.find((x) => String(x.id) === el.dataset.id);
                    if (ev) handleOpen(ev);
                });
            });
        }

        drawerBackdrop?.classList.remove('hidden');
        requestAnimationFrame(() => {
            drawerBackdrop?.classList.add('is-open');
            drawer.classList.add('is-open');
        });
    }

    function closeDrawer() {
        drawer?.classList.remove('is-open');
        drawerBackdrop?.classList.remove('is-open');
        setTimeout(() => drawerBackdrop?.classList.add('hidden'), 300);
    }

    drawerClose?.addEventListener('click', closeDrawer);
    drawerBackdrop?.addEventListener('click', closeDrawer);

    root.querySelector('[data-events-prev]')?.addEventListener('click', () => {
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1);
        renderCalendar(true);
    });
    root.querySelector('[data-events-next]')?.addEventListener('click', () => {
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
        renderCalendar(true);
    });
    root.querySelector('[data-events-today]')?.addEventListener('click', () => {
        cursor = new Date();
        cursor.setDate(1);
        renderCalendar(true);
    });
    searchInput?.addEventListener('input', (e) => {
        filterSearch = e.target.value.trim();
        renderCalendar(false);
    });
    chipsWrap?.querySelectorAll('[data-events-chip]').forEach((chip) => {
        chip.addEventListener('click', () => {
            filterCategory = chip.getAttribute('data-events-chip');
            chipsWrap.querySelectorAll('[data-events-chip]').forEach((c) => c.classList.remove('is-active'));
            chip.classList.add('is-active');
            renderCalendar(false);
        });
    });

    if (weekdaysEl) {
        weekdaysEl.innerHTML = weekdayLabels().map((w) => `<div class="events-weekday">${w}</div>`).join('');
    }
    renderCalendar(false);
}

document.addEventListener('DOMContentLoaded', () => {
    heroSlideshow();
    horseGallerySlider();
    promoSliders();
    revealOnScroll();
    horseCardTilt();
    animatedCounters();
    searchPreviewCascade();
    transferBoardFilters();
    mobileMenu();
    contactReveal();
    passportReveal();
    copyLink();
    printButtons();
    transferBoardWizard();
    horseSaleWizard();
    farrierWizard();
    toolSaleWizard();
    eventsCalendar();
    raceWidget();
});
