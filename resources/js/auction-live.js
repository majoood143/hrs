// Live auction pages (resources/views/site/auctions): countdowns, the lot's bid board and the
// catalogue's prices. The server's clock is the only one that counts: every countdown runs on
// `server_now` plus the time since the page got it. Polling, not sockets: the open lot every 3 s,
// the catalogue every 15 s, nothing while the tab is hidden.
// shortcut: polling serves tens of bidders per lot; move to Laravel Reverb if a sale draws hundreds at once.

const LOT_POLL_MS = 3000;
const LIST_POLL_MS = 15000;

let clockOffset = 0;

function syncClock(serverNow) {
    if (serverNow) clockOffset = new Date(serverNow).getTime() - Date.now();
}

function formatRemaining(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const days = Math.floor(total / 86400);
    const pad = (n) => String(n).padStart(2, '0');
    const clock = `${pad(Math.floor((total % 86400) / 3600))}:${pad(Math.floor((total % 3600) / 60))}:${pad(total % 60)}`;

    return days > 0 ? `${days}d ${clock}` : clock;
}

function tickCountdowns(endedLabel) {
    const now = Date.now() + clockOffset;
    document.querySelectorAll('[data-countdown]').forEach((el) => {
        const left = new Date(el.dataset.countdown).getTime() - now;
        el.textContent = left > 0 ? formatRemaining(left) : endedLabel;
        el.classList.toggle('text-rose-600', left > 0 && left < 3 * 60 * 1000);
    });
}

// SiteSetting::formatAmount(): more decimals only when the amount needs them (baisa are exact).
export function formatMoney(baisa, fmt) {
    let decimals = fmt.decimals;
    while (decimals < 3 && Math.round(baisa / 10 ** (3 - decimals)) * 10 ** (3 - decimals) !== baisa) decimals++;
    const [whole, part] = (baisa / 1000).toFixed(decimals).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, fmt.thou);

    return `${fmt.symbol} ${part ? grouped + fmt.dec + part : grouped}`;
}

// AuctionLot::buyerCosts(): hammer + buyer's premium + the lot's VAT on the hammer + VAT on the premium.
export function buyerTotal(hammer, costs) {
    const premium = Math.round((hammer * costs.premium) / 100);

    return hammer + premium + Math.round((hammer * costs.vat) / 100) + Math.round((premium * costs.fee_vat) / 100);
}

const toBaisa = (value) => Math.round(parseFloat(value || '0') * 1000);

function every(ms, task) {
    const run = async () => {
        if (document.visibilityState === 'visible') {
            try {
                await task();
            } catch {
                // a missed poll is retried on the next tick
            }
        }
        setTimeout(run, ms);
    };
    setTimeout(run, ms);
}

function lotBoard(board) {
    const labels = JSON.parse(board.dataset.labels);
    const money = JSON.parse(board.dataset.money);
    const costs = JSON.parse(board.dataset.costs);
    const form = board.querySelector('[data-bid-form]');
    const amount = form?.querySelector('[name="amount"]');
    const max = form?.querySelector('[name="max_amount"]');
    const you = board.querySelector('[data-you]');
    let state = null;

    const showYou = (text, tone) => {
        you.textContent = text;
        you.className = `mt-3 rounded-xl px-3 py-2 text-sm font-semibold ${tone}`;
        you.hidden = !text;
        you.classList.toggle('hidden', !text);
    };

    const updateTotal = () => {
        const target = board.querySelector('[data-total]');
        if (target && amount) target.textContent = amount.value ? formatMoney(buyerTotal(toBaisa(amount.value), costs), money) : '';
    };

    const render = (next) => {
        const first = state === null;
        // closed or just opened: the page shows the final (or the open) board
        if (!first && (next.status !== state.status || next.open !== state.open)) {
            window.location.reload();
            return;
        }
        state = next;
        syncClock(next.server_now);

        const set = (selector, value) => {
            const el = board.querySelector(selector);
            if (el && value !== undefined) el.textContent = value;
        };
        set('[data-price]', next.price ?? next.starting);
        set('[data-minimum]', next.minimum_label);
        set('[data-reserve]', next.reserve ? labels.reserve[next.reserve] : '');
        const counts = board.querySelector('[data-counts]');
        if (counts) counts.innerHTML = `<span>${next.bidders} ${labels.bidders}</span><span>${next.bids} ${labels.bids}</span>`;
        board.querySelector('[data-countdown]')?.setAttribute('data-countdown', next.ends_at);

        if (amount) {
            amount.min = next.minimum;
            if (first || toBaisa(amount.value) < toBaisa(next.minimum)) amount.value = next.minimum;
            updateTotal();
        }

        const tones = {
            leading: 'bg-emerald-50 text-emerald-800',
            outbid: 'bg-amber-50 text-amber-800',
            won: 'bg-warm-900 text-white',
            lost: 'bg-warm-50 text-warm-800',
        };
        if (next.you) {
            const extra = next.you === 'leading' && next.your_max ? ` (${labels.you.your_max} ${next.your_max})` : '';
            showYou(labels.you[next.you] + extra, tones[next.you]);
        } else {
            showYou('', '');
        }

        const history = board.querySelector('[data-history]');
        if (history && next.history.length) {
            history.innerHTML = '';
            next.history.forEach((row) => {
                const li = document.createElement('li');
                li.className = `flex justify-between gap-3 ${row.mine ? 'font-semibold text-warm-900' : 'text-warm-900/70'}`;
                const who = document.createElement('span');
                who.textContent = row.mine ? labels.yours : row.bidder;
                const what = document.createElement('span');
                what.dir = 'ltr';
                what.textContent = `${row.amount} · ${row.at}`;
                li.append(who, what);
                history.append(li);
            });
        }
    };

    const poll = async () => {
        const response = await fetch(board.dataset.stateUrl, { headers: { Accept: 'application/json' } });
        if (response.ok) render(await response.json());
    };

    syncClock(board.dataset.serverNow);
    poll();
    every(LOT_POLL_MS, poll);

    board.querySelectorAll('[data-quick-bid]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!state || !amount) return;
            const steps = parseInt(button.dataset.quickBid, 10);
            amount.value = ((toBaisa(state.minimum) + (steps - 1) * toBaisa(state.step)) / 1000).toFixed(3);
            updateTotal();
            amount.focus();
        });
    });
    amount?.addEventListener('input', updateTotal);

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const bid = toBaisa(amount.value);
        const ceiling = max?.value ? toBaisa(max.value) : null;
        const question = (ceiling ? labels.confirm_max : labels.confirm)
            .replace(':amount', formatMoney(bid, money))
            .replace(':max', ceiling ? formatMoney(ceiling, money) : '');
        if (!window.confirm(question)) return;

        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': board.dataset.csrf },
                body: new FormData(form),
            });
            const body = await response.json().catch(() => ({}));
            if (body.state) render(body.state);
            if (response.ok) {
                if (max) max.value = '';
                showYou(body.message, body.state?.you === 'leading' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800');
            } else {
                showYou(body.message || labels.error, 'bg-rose-50 text-rose-800');
            }
        } catch {
            showYou(labels.error, 'bg-rose-50 text-rose-800');
        } finally {
            button.disabled = false;
        }
    });

    const watch = board.querySelector('[data-watch-toggle]');
    watch?.addEventListener('click', async () => {
        const response = await fetch(board.dataset.watchUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': board.dataset.csrf },
        });
        if (!response.ok) return;
        const { watching } = await response.json();
        watch.setAttribute('aria-pressed', watching ? 'true' : 'false');
        watch.querySelector('span').textContent = watching ? labels.watching : labels.watch;
    });

    return labels.ended;
}

function lotList(grid) {
    const labels = JSON.parse(grid.dataset.labels);
    syncClock(grid.dataset.serverNow);

    every(LIST_POLL_MS, async () => {
        const response = await fetch(grid.dataset.stateUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        syncClock(data.server_now);
        data.lots.forEach((lot) => {
            const card = grid.querySelector(`[data-lot-card="${lot.number}"]`);
            if (!card) return;
            if (lot.price) card.querySelector('[data-lot-price]').textContent = lot.price;
            card.querySelector('[data-countdown]')?.setAttribute('data-countdown', lot.ends_at);
            const counts = card.querySelector('[data-lot-counts]');
            if (counts) counts.textContent = `${lot.bids} ${labels.bids}`;
        });
    });

    return labels.ended;
}

export function auctionLive() {
    const board = document.querySelector('[data-auction-lot]');
    const grid = document.querySelector('[data-auction-lots]');
    let ended = null;

    if (board) ended = lotBoard(board);
    if (grid) ended = lotList(grid) ?? ended;
    if (!document.querySelector('[data-countdown]')) return;

    const endedLabel = ended ?? JSON.parse(document.querySelector('[data-labels]')?.dataset.labels ?? '{}').ended ?? '—';
    tickCountdowns(endedLabel);
    setInterval(() => tickCountdowns(endedLabel), 1000);
}
