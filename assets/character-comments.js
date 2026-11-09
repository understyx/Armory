let activeSection = null;
let cleanupCurrent = null;

const replaceFragment = (content, html, append = false) => {
    const template = document.createElement('template');
    template.innerHTML = html;
    const incomingList = template.content.querySelector('[data-comment-list]');
    const existingList = content.querySelector('[data-comment-list]');

    if (!append) {
        content.innerHTML = html;
        return;
    }

    if (existingList && incomingList) {
        existingList.append(...incomingList.childNodes);
    } else if (!existingList && incomingList) {
        content.prepend(incomingList);
    }

    content.querySelector('[data-comments-more]')?.remove();
    const nextButton = template.content.querySelector('[data-comments-more]');
    if (nextButton) content.append(nextButton);
};

const handleClick = async (event, section, signal) => {
    const button = event.target.closest('[data-comments-load], [data-comments-more]');
    if (!button || button.closest('#profile-comments') !== section || button.disabled) return;

    const content = section.querySelector('#character-comments-content');
    if (!content) return;

    event.preventDefault();
    const append = button.hasAttribute('data-comments-more');
    button.disabled = true;
    try {
        const response = await fetch(button.dataset.url, {
            headers: { Accept: 'text/html' },
            cache: 'no-store',
            signal,
        });
        if (!response.ok) throw new Error('Comments could not be loaded.');
        replaceFragment(content, await response.text(), append);
        section.querySelector('[data-comments-load]')?.remove();
    } catch {
        if (signal.aborted) return;
        button.disabled = false;
        button.textContent = 'Comments could not be loaded. Try again.';
    }
};

const handleSubmit = async (event, section, signal) => {
    const form = event.target.closest('[data-comment-form], [data-comment-delete]');
    if (!form || form.closest('#profile-comments') !== section) return;

    const content = section.querySelector('#character-comments-content');
    const button = form.querySelector('button[type="submit"]');
    if (!content) return;

    event.preventDefault();
    if (button) button.disabled = true;
    try {
        const response = await fetch(form.action, {
            method: form.method || 'POST',
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
            cache: 'no-store',
            signal,
        });
        replaceFragment(content, await response.text());
    } catch {
        if (signal.aborted) return;
        if (button) button.disabled = false;
        let error = content.querySelector('.comment-error');
        if (!error) {
            error = document.createElement('p');
            error.className = 'comment-error';
            error.setAttribute('role', 'alert');
            content.prepend(error);
        }
        error.textContent = 'Your comment could not be submitted. Please try again.';
    }
};

const initialize = () => {
    const section = document.querySelector('#profile-comments');
    if (section && section === activeSection) return;

    cleanupCurrent?.();
    cleanupCurrent = null;
    activeSection = null;
    if (!section) return;

    const lifecycle = new AbortController();
    activeSection = section;
    section.addEventListener('click', (event) => handleClick(event, section, lifecycle.signal), { signal: lifecycle.signal });
    section.addEventListener('submit', (event) => handleSubmit(event, section, lifecycle.signal), { signal: lifecycle.signal });
    cleanupCurrent = () => {
        lifecycle.abort();
        if (activeSection === section) activeSection = null;
    };
};

const stopForCache = () => {
    cleanupCurrent?.();
    cleanupCurrent = null;
    activeSection = null;
};

initialize();
document.addEventListener('turbo:load', initialize);
document.addEventListener('turbo:before-cache', stopForCache);
