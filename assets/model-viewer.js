const preferenceKey = 'armorystuff.modelViewer.enabled';
const toggle = document.getElementById('model-viewer-enabled');
const viewer = document.getElementById('character-model-viewer');
const status = document.getElementById('character-model-status');

const readPreference = () => {
    try {
        return window.localStorage.getItem(preferenceKey) !== 'false';
    } catch (error) {
        console.warn('Unable to read the 3D model preference.', error);
        return true;
    }
};

const writePreference = (enabled) => {
    try {
        window.localStorage.setItem(preferenceKey, String(enabled));
        return true;
    } catch (error) {
        console.warn('Unable to save the 3D model preference.', error);
        return false;
    }
};

const enabled = readPreference();

if (toggle) {
    toggle.checked = enabled;
    toggle.addEventListener('change', () => {
        if (writePreference(toggle.checked)) {
            // Reloading guarantees an active WebGL render loop is fully stopped.
            window.location.reload();
        } else {
            toggle.checked = enabled;
        }
    });
}

if (!enabled) {
    viewer?.setAttribute('hidden', '');
    if (status) {
        status.classList.add('is-unavailable');
        status.textContent = '3D model disabled';
    }
} else if (viewer) {
    const loadScript = (source, attributes = {}) => new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = source;
        Object.entries(attributes).forEach(([name, value]) => script.setAttribute(name, value));
        script.addEventListener('load', resolve, { once: true });
        script.addEventListener('error', reject, { once: true });
        document.head.appendChild(script);
    });

    const hideLoadingStatus = () => status?.remove();
    const canvasObserver = new MutationObserver(() => {
        if (viewer.querySelector('canvas')) {
            hideLoadingStatus();
            canvasObserver.disconnect();
        }
    });
    canvasObserver.observe(viewer, { childList: true, subtree: true });

    try {
        await loadScript('https://code.jquery.com/jquery-3.7.1.min.js', {
            integrity: 'sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=',
            crossorigin: 'anonymous',
        });
        await loadScript('https://wow.zamimg.com/modelviewer/live/viewer/viewer.min.js');

        window.CONTENT_PATH = new URL(
            viewer.dataset.assetUrl.replace('__asset__', ''),
            window.location.origin,
        ).href;

        const dataElement = document.getElementById('character-model-data');
        const character = JSON.parse(dataElement.textContent);
        const { generateModels } = await import('https://cdn.jsdelivr.net/npm/wow-model-viewer@1.5.3/+esm');
        await generateModels(1.88, '#character-model-viewer', character);
        hideLoadingStatus();
        canvasObserver.disconnect();
    } catch (error) {
        console.error('Unable to load the character model.', error);
        canvasObserver.disconnect();
        if (status?.isConnected) {
            status.classList.add('is-error');
            status.textContent = '3D model unavailable';
        }
    }
}
