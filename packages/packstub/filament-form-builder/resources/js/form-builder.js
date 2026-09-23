(function () {
    if (window.__packstubFormBuilder) return;
    window.__packstubFormBuilder = true;

    function setError(form, key, message) {
        var el = form.querySelector('[data-fb-error-for="' + key + '"]');
        var field = form.querySelector('[data-fb-field="' + key + '"]');
        if (el) { el.textContent = message || ''; el.hidden = !message; }
        if (field) {
            field.classList.toggle('fb-field--error', !!message);
            field.querySelectorAll('input, select, textarea').forEach(function (input) {
                if (message) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
            });
        }
    }

    function clearErrors(form) {
        form.querySelectorAll('[data-fb-error-for]').forEach(function (el) { setError(form, el.getAttribute('data-fb-error-for'), ''); });
        var alert = form.querySelector('[data-fb-alert]');
        if (alert) alert.hidden = true;
    }

    function showAlert(form, message) {
        var alert = form.querySelector('[data-fb-alert]');
        if (!alert) return;
        alert.textContent = message;
        alert.hidden = false;
        alert.focus && alert.setAttribute('tabindex', '-1');
        alert.focus();
    }

    function handle(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors(form);

            var button = form.querySelector('[data-fb-submit]');
            if (button) button.disabled = true;

            var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            var token = form.querySelector('input[name="_token"]');
            if (token) headers['X-CSRF-TOKEN'] = token.value;

            fetch(form.action, { method: 'POST', headers: headers, body: new FormData(form), credentials: 'same-origin' })
                .then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        return { status: response.status, body: body };
                    });
                })
                .then(function (result) {
                    var body = result.body || {};
                    if (result.status >= 200 && result.status < 300 && body.ok) {
                        if (body.redirect) { window.location.assign(body.redirect); return; }
                        var wrapper = form.closest('[data-fb-form]');
                        var success = document.createElement('div');
                        success.className = 'fb-success';
                        success.setAttribute('role', 'status');
                        success.setAttribute('data-fb-success', '');
                        success.textContent = body.message || '';
                        form.replaceWith(success);
                        if (wrapper) wrapper.dispatchEvent(new CustomEvent('form-builder:submitted', { bubbles: true, detail: body }));
                        return;
                    }
                    var errors = body.errors || {};
                    var first = null;
                    Object.keys(errors).forEach(function (key) {
                        if (key === 'form') return;
                        var message = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                        setError(form, key.split('.')[0], message);
                        if (!first) first = form.querySelector('[data-fb-field="' + key.split('.')[0] + '"] input, [data-fb-field="' + key.split('.')[0] + '"] select, [data-fb-field="' + key.split('.')[0] + '"] textarea');
                    });
                    showAlert(form, (errors.form && errors.form[0]) || body.message || form.getAttribute('data-fb-message-invalid') || 'Please check the form.');
                    if (first) first.focus();
                })
                .catch(function () {
                    showAlert(form, form.getAttribute('data-fb-message-failed') || 'Something went wrong. Please try again.');
                })
                .finally(function () { if (button) button.disabled = false; });
        });
    }

    // Turns a plain <select> (the "nationality" field) into a searchable, touch-friendly
    // combobox: a text input filters the option list live, the panel always opens downward
    // and scrolls internally instead of the browser's own datalist/select popup, which can
    // open upward and run off the top of the screen on a long option list. The <select> stays
    // in the DOM as the real, constrained form control (visually hidden once enhanced) — the
    // visible text can only ever hold one of its option labels, never arbitrary typed text, so
    // there is nothing for the server's validation to reject.
    function initCombobox(root) {
        (root || document).querySelectorAll('[data-fb-combobox]').forEach(function (wrapper) {
            if (wrapper.__fbCombobox) return;
            wrapper.__fbCombobox = true;

            var select = wrapper.querySelector('select');
            if (!select) return;

            var options = Array.prototype.slice.call(select.options).filter(function (option) { return option.value !== ''; });
            var placeholder = select.options.length && select.options[0].value === '' ? select.options[0].textContent : '';
            var inputId = select.id;

            var describedBy = select.getAttribute('aria-describedby');
            var required = select.hasAttribute('required');

            select.id = inputId + '-native';
            select.tabIndex = -1;
            select.classList.add('fb-combobox__select');
            select.setAttribute('aria-hidden', 'true');
            wrapper.classList.add('fb-combobox--active');

            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'fb-input fb-combobox__input';
            input.id = inputId;
            input.autocomplete = 'off';
            input.spellcheck = false;
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-expanded', 'false');
            input.setAttribute('aria-autocomplete', 'list');
            if (describedBy) input.setAttribute('aria-describedby', describedBy);
            if (required) input.setAttribute('aria-required', 'true');
            if (placeholder) input.placeholder = placeholder;

            var arrow = document.createElement('span');
            arrow.className = 'fb-combobox__arrow';
            arrow.setAttribute('aria-hidden', 'true');

            var panel = document.createElement('div');
            panel.className = 'fb-combobox__panel';
            panel.id = inputId + '-listbox';
            panel.setAttribute('role', 'listbox');
            panel.hidden = true;
            input.setAttribute('aria-controls', panel.id);

            wrapper.append(input, arrow, panel);

            var visible = [];
            var active = -1;
            var emptyText = wrapper.getAttribute('data-fb-empty') || '';

            var currentLabel = function () {
                var chosen = options.filter(function (option) { return option.value === select.value; })[0];
                return chosen ? chosen.textContent : '';
            };

            var highlight = function (index) {
                var items = panel.querySelectorAll('.fb-combobox__option');
                items.forEach(function (item) { item.classList.remove('fb-combobox__option--active'); });
                active = index;
                if (index >= 0 && items[index]) {
                    items[index].classList.add('fb-combobox__option--active');
                    items[index].scrollIntoView({ block: 'nearest' });
                    input.setAttribute('aria-activedescendant', items[index].id);
                } else {
                    input.removeAttribute('aria-activedescendant');
                }
            };

            var choose = function (option) {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                input.value = option.textContent;
                close(false);
                input.focus();
            };

            var renderPanel = function (filter) {
                var needle = (filter || '').trim().toLowerCase();
                visible = options.filter(function (option) { return !needle || option.textContent.toLowerCase().indexOf(needle) !== -1; });
                panel.innerHTML = '';
                active = -1;

                if (!visible.length) {
                    var empty = document.createElement('p');
                    empty.className = 'fb-combobox__empty';
                    empty.textContent = emptyText;
                    panel.appendChild(empty);
                    return;
                }

                visible.forEach(function (option, index) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.id = panel.id + '-' + index;
                    item.className = 'fb-combobox__option' + (option.value === select.value ? ' fb-combobox__option--selected' : '');
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
                    item.textContent = option.textContent;
                    // mousedown (not click) fires before the input's blur, so the panel can't
                    // close-and-revert out from under a tap before the choice is registered.
                    item.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        choose(option);
                    });
                    panel.appendChild(item);
                });
            };

            var open = function () {
                if (!panel.hidden) return;
                renderPanel(input.value === currentLabel() ? '' : input.value);
                panel.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            };

            var close = function (revert) {
                panel.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
                if (revert) input.value = currentLabel();
            };

            input.addEventListener('focus', open);
            input.addEventListener('click', open);
            input.addEventListener('input', function () { open(); renderPanel(input.value); });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    open();
                    highlight(Math.min(active + 1, visible.length - 1));
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    open();
                    highlight(Math.max(active - 1, 0));
                } else if (event.key === 'Enter') {
                    if (!panel.hidden && active >= 0 && visible[active]) {
                        event.preventDefault();
                        choose(visible[active]);
                    }
                } else if (event.key === 'Escape') {
                    close(true);
                }
            });

            input.addEventListener('blur', function () {
                // A mousedown on an option already ran (and preventDefault kept focus off it),
                // so by the time blur fires here any real selection has already happened.
                close(true);
            });

            document.addEventListener('click', function (event) {
                if (!wrapper.contains(event.target)) close(false);
            });

            input.value = currentLabel();
        });
    }

    // "Radio buttons with details": the stylesheet shows the details box when a revealing
    // answer is checked (CSS :has()); this keeps its "required" in step and disables it while
    // hidden, so a hidden box never blocks the browser's own validation or posts a value.
    function initConditional(root) {
        (root || document).querySelectorAll('[data-fb-conditional]').forEach(function (wrapper) {
            if (wrapper.__fbConditional) return;
            wrapper.__fbConditional = true;

            var details = wrapper.querySelector('[data-fb-cond-details]');
            var input = details && details.querySelector('input, textarea');
            if (!input) return;

            function sync() {
                var shown = !!wrapper.querySelector('[data-fb-reveals]:checked');
                details.hidden = !shown;
                input.disabled = !shown;
                input.required = shown && input.hasAttribute('data-fb-required');
            }

            wrapper.addEventListener('change', function (event) {
                if (event.target && event.target.type === 'radio') sync();
            });
            sync();
        });
    }

    function init(root) {
        initCombobox(root);
        initConditional(root);
        (root || document).querySelectorAll('form[data-fb-enhance="true"]').forEach(function (form) {
            if (form.__fb) return;
            form.__fb = true;
            handle(form);
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); }); else init();
    document.addEventListener('form-builder:init', function (event) { init(event.target); });
})();
