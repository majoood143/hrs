import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function heroTimeline() {
    const hero = document.querySelector('[data-hero]');
    if (!hero || reduceMotion) return;

    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    const eyebrow = hero.querySelector('[data-hero-eyebrow]');
    const heading = hero.querySelector('[data-hero-heading]');
    const sub = hero.querySelector('[data-hero-sub]');
    const ctas = hero.querySelectorAll('[data-hero-cta]');
    const image = hero.querySelector('[data-hero-image]');

    gsap.set([eyebrow, heading, sub, ...ctas], { opacity: 0, y: 24 });
    if (image) gsap.set(image, { opacity: 0, scale: 1.05 });

    tl.to(eyebrow, { opacity: 1, y: 0, duration: 0.5 })
        .to(heading, { opacity: 1, y: 0, duration: 0.7 }, '-=0.3')
        .to(sub, { opacity: 1, y: 0, duration: 0.6 }, '-=0.4')
        .to(ctas, { opacity: 1, y: 0, duration: 0.5, stagger: 0.1 }, '-=0.3');

    if (image) tl.to(image, { opacity: 1, scale: 1, duration: 1 }, '-=0.9');
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

    const errorStep = parseInt(wizard.dataset.errorStep ?? '0', 10);
    show(Number.isNaN(errorStep) ? 0 : errorStep);
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

document.addEventListener('DOMContentLoaded', () => {
    heroTimeline();
    revealOnScroll();
    horseCardTilt();
    animatedCounters();
    searchPreviewCascade();
    transferBoardFilters();
    mobileMenu();
    contactReveal();
    copyLink();
    transferBoardWizard();
    horseSaleWizard();
    farrierWizard();
    toolSaleWizard();
});
