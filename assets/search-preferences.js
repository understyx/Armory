const preferenceKey = 'armorystuff.searchPreferences';
const validRealms = new Set(['Icecrown', 'Lordaeron', 'Onyxia', 'Blackrock']);
const validTypes = new Set(['character', 'guild']);

const remember = (form) => {
    const realm = form.querySelector('[name="realm"]');
    const type = form.querySelector('[name="type"]');
    if (!realm || !type) return;

    try {
        window.localStorage.setItem(preferenceKey, JSON.stringify({realm: realm.value, type: type.value}));
    } catch (error) {
        // Search remains usable when browser storage is unavailable.
    }
};

let preferences = null;
try {
    preferences = JSON.parse(window.localStorage.getItem(preferenceKey) || 'null');
} catch (error) {
    preferences = null;
}

document.querySelectorAll('form.character-search').forEach((form) => {
    const name = form.querySelector('[name="character"]');
    const realm = form.querySelector('[name="realm"]');
    const type = form.querySelector('[name="type"]');
    const button = form.querySelector('.character-search-button');

    if (preferences && realm && validRealms.has(preferences.realm)) {
        realm.value = preferences.realm;
    }
    if (preferences && type && validTypes.has(preferences.type)) {
        type.value = preferences.type;
    }

    const updateLabels = () => {
        const isGuild = type?.value === 'guild';
        name?.setAttribute('aria-label', isGuild ? 'Guild name' : 'Character name');
        if (name) name.placeholder = isGuild ? 'Guild name' : 'Character name';
        button?.setAttribute('aria-label', isGuild ? 'Search guilds' : 'Search characters');
    };

    updateLabels();
    realm?.addEventListener('change', () => remember(form));
    type?.addEventListener('change', () => {
        updateLabels();
        remember(form);
    });
    form.addEventListener('submit', () => {
        remember(form);
        button?.classList.add('is-loading');
    });
});
