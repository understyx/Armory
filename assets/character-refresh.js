const form = document.querySelector('[data-character-refresh]');
const banner = document.querySelector('#character-refresh-status');

if (form && banner) {
    const button = form.querySelector('button[type="submit"]');
    const statusUrl = form.dataset.statusUrl;
    const updatedLabel = document.querySelector('.scraped-relative');
    let pollTimer = null;
    let pollStartedAt = 0;
    let stopped = false;

    const etaText = (seconds) => {
        const duration = Math.max(0, Number(seconds) || 20);
        if (duration < 60) return 'less than a minute';
        const minutes = Math.ceil(duration / 60);
        return `about ${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`;
    };

    const retryText = (seconds) => {
        const minutes = Math.max(1, Math.ceil((Number(seconds) || 0) / 60));
        return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`;
    };

    const show = (message) => {
        banner.textContent = message;
        banner.hidden = false;
    };

    const setBusy = (busy) => {
        if (button) button.disabled = busy;
    };

    const stopPolling = () => {
        stopped = true;
        if (pollTimer !== null) window.clearTimeout(pollTimer);
        pollTimer = null;
    };

    const finish = (state, retryAfterSeconds = 0) => {
        stopPolling();
        switch (state) {
            case 'updated':
                show('Character updated. Reloading…');
                window.setTimeout(() => window.location.reload(), 700);
                break;
            case 'partial':
                show("Character updated, but Warmane skipped some pages, so those sections keep their saved data. Reloading…");
                window.setTimeout(() => window.location.reload(), 4000);
                break;
            case 'unchanged':
                show('Warmane has no new data for this character yet.');
                if (updatedLabel) updatedLabel.textContent = 'Updated just now';
                document.querySelector('.stale-notice')?.remove();
                setBusy(false);
                break;
            case 'source_unavailable':
                show("Warmane didn't respond this time, so nothing changed. Try again in a few minutes.");
                setBusy(false);
                break;
            case 'not_found':
                show('Warmane could not find this character.');
                setBusy(false);
                break;
            case 'failed':
                show('The refresh failed. Saved data is unchanged. Try again in a few minutes.');
                setBusy(false);
                break;
            case 'throttled':
                show(`This character was refreshed recently. Try again in about ${retryText(retryAfterSeconds)}.`);
                setBusy(false);
                break;
            default:
                show('Refresh status is unavailable. You can try again in a moment.');
                setBusy(false);
        }
    };

    const renderActive = (status) => {
        if (status.state === 'running') {
            show('Refreshing from Warmane now. This can take up to a minute.');
            return;
        }

        if (status.state === 'queued') {
            const position = Math.max(1, Number(status.position) || 1);
            const place = position === 1 ? "You're next" : `${position - 1} ahead of you`;
            show(`Refresh queued. ${place}, ${etaText(status.etaSeconds)}.`);
            return;
        }

        finish(status.state, status.retryAfterSeconds);
    };

    const poll = async () => {
        if (stopped) return;
        if (Date.now() - pollStartedAt >= 10 * 60 * 1000) {
            stopPolling();
            show('Refresh is taking longer than expected. You can reload later to check for updated data.');
            setBusy(false);
            return;
        }

        try {
            const response = await fetch(statusUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Status request failed');
            const status = await response.json();
            if (status.state === 'queued' || status.state === 'running') {
                renderActive(status);
                const delay = Date.now() - pollStartedAt >= 2 * 60 * 1000 ? 10000 : 3000;
                pollTimer = window.setTimeout(poll, delay);
                return;
            }
            finish(status.state);
        } catch {
            if (!stopped) {
                const delay = Date.now() - pollStartedAt >= 2 * 60 * 1000 ? 10000 : 3000;
                pollTimer = window.setTimeout(poll, delay);
            }
        }
    };

    const startPolling = () => {
        stopPolling();
        stopped = false;
        pollStartedAt = Date.now();
        pollTimer = window.setTimeout(poll, 3000);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        stopPolling();
        setBusy(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });
            const result = await response.json();
            if (result.state === 'loading' && result.redirect) {
                window.location.assign(result.redirect);
                return;
            }
            if (result.state === 'throttled') {
                finish('throttled', result.retryAfterSeconds);
                return;
            }
            if (result.state === 'queued' || result.state === 'running') {
                renderActive(result);
                startPolling();
                return;
            }
            finish(result.state, result.retryAfterSeconds);
        } catch {
            show('Could not start the refresh. Try again in a moment.');
            setBusy(false);
        }
    });

    fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' })
        .then((response) => response.ok ? response.json() : Promise.reject(new Error('Status request failed')))
        .then((status) => {
            if (status.state === 'queued' || status.state === 'running') {
                setBusy(true);
                renderActive(status);
                startPolling();
            }
        })
        .catch(() => {});
}
