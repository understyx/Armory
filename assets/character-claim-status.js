const POLL_INTERVAL_MS = 5000;
const PENDING_STATES = ['scanning_baseline', 'scanning_verify', 'awaiting_removal'];

let pollTimer = null;

// Turbo swaps the page body without re-running this module, so start polling on every visit.
const start = () => {
    if (pollTimer !== null) window.clearTimeout(pollTimer);
    pollTimer = null;

    const account = document.querySelector('[data-claim-status-url]');
    if (!account || !PENDING_STATES.includes(account.dataset.claimState)) return;

    const statusUrl = account.dataset.claimStatusUrl;
    const schedule = () => {
        pollTimer = window.setTimeout(poll, POLL_INTERVAL_MS);
    };

    const poll = async () => {
        pollTimer = null;
        if (!account.isConnected) return;

        try {
            const response = await fetch(statusUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Status unavailable');

            const status = await response.json();
            if (!account.isConnected) return;

            const stateChanged = status.state !== account.dataset.claimState;
            const revisionChanged = String(status.revision ?? 0) !== account.dataset.claimRevision;
            if (stateChanged || revisionChanged) {
                window.location.reload();
                return;
            }

            if (PENDING_STATES.includes(status.state)) schedule();
        } catch {
            schedule();
        }
    };

    schedule();
};

start();
document.addEventListener('turbo:load', start);
