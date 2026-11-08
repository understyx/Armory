const account = document.querySelector('[data-claim-status-url]');

if (account) {
    const statusUrl = account.dataset.claimStatusUrl;

    const poll = async () => {
        try {
            const response = await fetch(statusUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Status unavailable');

            const status = await response.json();
            const stateChanged = status.state !== account.dataset.claimState;
            const revisionChanged = String(status.revision ?? 0) !== account.dataset.claimRevision;
            if (stateChanged || revisionChanged) {
                window.location.reload();
                return;
            }

            if (['scanning_baseline', 'scanning_verify', 'awaiting_removal'].includes(status.state)) {
                window.setTimeout(poll, 4000);
            }
        } catch {
            window.setTimeout(poll, 6000);
        }
    };

    if (['scanning_baseline', 'scanning_verify', 'awaiting_removal'].includes(account.dataset.claimState)) {
        window.setTimeout(poll, 3000);
    }
}
