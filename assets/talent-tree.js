const dataElement = document.getElementById('talent-spec-data');
let deferredSpecs = [];
let renderGlyphs = false;
let glyphTooltipKeys = {};

if (dataElement) {
    try {
        const payload = JSON.parse(dataElement.textContent || '{}');
        deferredSpecs = Array.isArray(payload.specs) ? payload.specs : [];
        renderGlyphs = payload.isWotlkServer === true;
        glyphTooltipKeys = payload.glyphTooltipKeys || {};
    } catch (error) {
        console.error('Unable to parse secondary talent tree data.', error);
    }
}

const makeElement = (tagName, className = '', text = null) => {
    const element = document.createElement(tagName);
    if (className) element.className = className;
    if (text !== null && text !== undefined) element.textContent = String(text);
    return element;
};

const setFallback = (image) => {
    image.addEventListener('error', () => {
        if (image.dataset.fallbackSrc) {
            const fallback = image.dataset.fallbackSrc;
            delete image.dataset.fallbackSrc;
            image.src = fallback;
        } else {
            image.style.display = 'none';
        }
    }, { once: true });
};

const safeLink = (value) => {
    try {
        const url = new URL(value || '#', window.location.origin);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : '#';
    } catch (error) {
        return '#';
    }
};

const renderGlyphList = (parent, kind, names) => {
    const section = makeElement('div', `glyph-section ${kind.toLowerCase()}-section`);
    const heading = makeElement('h3', `glyph-section-header ${kind.toLowerCase()}-header`, `${kind} Glyphs`);
    const list = makeElement('div', 'glyph-items-list');
    section.append(heading, list);

    if (Array.isArray(names) && names.length > 0) {
        names.forEach((name) => {
            const row = makeElement('div', `glyph-item ${kind.toLowerCase()}-item`);
            if (glyphTooltipKeys[name]) {
                row.tabIndex = 0;
                row.dataset.itemTooltip = glyphTooltipKeys[name];
            }
            row.append(
                makeElement('span', `glyph-ring-dot ${kind === 'Major' ? 'gold' : 'blue'}-ring`),
                makeElement('span', 'glyph-text', name),
            );
            list.appendChild(row);
        });
    } else {
        const row = makeElement('div', 'glyph-item empty-glyph-item');
        row.append(
            makeElement('span', 'glyph-ring-dot empty-ring'),
            makeElement('span', 'glyph-text muted', `No ${kind} Glyphs`),
        );
        list.appendChild(row);
    }

    parent.appendChild(section);
};

const renderDeferredSpec = (spec) => {
    const pane = makeElement('div', 'spec-pane');
    pane.id = `spec-pane-${spec.specId}`;

    const layout = makeElement('div', 'talent-calculator-layout');
    const treesContainer = makeElement('div', 'talent-trees-container');
    const treeGrid = makeElement('div', 'talent-trees-grid');
    treeGrid.setAttribute('role', 'region');
    treeGrid.setAttribute('aria-label', 'Talent trees');
    treeGrid.tabIndex = 0;

    (spec.trees || []).forEach((tree) => {
        const column = makeElement('div', 'talent-tree-column');
        const heading = makeElement('div', 'talent-tree-heading');
        heading.append(
            makeElement('h3', '', tree.name || 'Talent tree'),
            makeElement('span', '', `${tree.points || 0} points`),
        );
        const gridBox = makeElement('div', 'talent-tree-grid-box');

        if (Array.isArray(tree.tiers) && tree.tiers.length > 0) {
            tree.tiers.forEach((tier) => {
                const row = makeElement('div', 'talent-tier-row');
                for (let columnIndex = 0; columnIndex < 4; columnIndex += 1) {
                    const talent = tier?.[columnIndex];
                    if (!talent) {
                        row.appendChild(makeElement('div', 'talent-slot-cell empty-slot'));
                        continue;
                    }

                    const link = makeElement('a', `talent-icon-box status-${talent.status || 'disabled'}`);
                    link.href = safeLink(talent.href);
                    link.target = '_blank';
                    if (talent.tooltip_key) link.dataset.itemTooltip = talent.tooltip_key;

                    if (talent.iconUrl) {
                        const image = makeElement('img', 'talent-icon-img');
                        image.src = talent.iconUrl;
                        if (talent.iconFallbackUrl) image.dataset.fallbackSrc = talent.iconFallbackUrl;
                        image.alt = talent.name || `Talent spell ${talent.spellId || ''}`;
                        image.width = 36;
                        image.height = 36;
                        image.loading = 'lazy';
                        image.decoding = 'async';
                        image.fetchPriority = 'low';
                        setFallback(image);
                        link.appendChild(image);
                    }

                    link.appendChild(makeElement(
                        'div',
                        `talent-rank-badge status-${talent.status || 'disabled'}`,
                        talent.pointsText || '',
                    ));
                    row.appendChild(link);
                }
                gridBox.appendChild(row);
            });
        } else {
            const fallback = makeElement('div', 'fallback-tree-info');
            fallback.append(
                makeElement('h4', '', tree.name || 'Talent tree'),
                makeElement('p', '', `${tree.points || 0} Points Spent`),
            );
            gridBox.appendChild(fallback);
        }

        column.append(heading, gridBox);
        treeGrid.appendChild(column);
    });

    const footer = makeElement('div', 'talent-trees-footer');
    (spec.trees || []).forEach((tree) => {
        const item = makeElement('div', 'tree-footer-item');
        if (tree.iconUrl) {
            const image = makeElement('img', 'tree-footer-icon');
            image.src = tree.iconUrl;
            if (tree.iconFallbackUrl) image.dataset.fallbackSrc = tree.iconFallbackUrl;
            image.alt = tree.name || '';
            image.width = 22;
            image.height = 22;
            image.loading = 'lazy';
            image.decoding = 'async';
            image.fetchPriority = 'low';
            setFallback(image);
            item.appendChild(image);
        }
        item.append(
            makeElement('span', 'tree-footer-name', tree.name || ''),
            makeElement('span', 'tree-footer-points', tree.points || 0),
        );
        footer.appendChild(item);
    });
    treesContainer.append(
        makeElement('p', 'talent-scroll-hint', 'Swipe to explore talent trees →'),
        treeGrid,
        footer,
    );
    layout.appendChild(treesContainer);

    if (renderGlyphs) {
        const sidebar = makeElement('div', 'glyphs-sidebar');
        renderGlyphList(sidebar, 'Major', spec.glyphs?.['Major Glyphs']);
        renderGlyphList(sidebar, 'Minor', spec.glyphs?.['Minor Glyphs']);
        layout.appendChild(sidebar);
    }
    pane.appendChild(layout);

    if (spec.digitString) {
        const codeBox = makeElement('div', 'talent-code-box');
        codeBox.append(
            makeElement('span', 'code-label', 'Talent Distribution String:'),
            makeElement('code', '', spec.digitString),
        );
        pane.appendChild(codeBox);
    }

    return pane;
};

window.setSpec = (specId, button) => {
    const paneContainer = document.getElementById('talent-spec-panes');
    if (!paneContainer) return;

    let targetPane = document.getElementById(`spec-pane-${specId}`);
    if (!targetPane) {
        const spec = deferredSpecs.find((entry) => String(entry.specId) === String(specId));
        if (spec) {
            targetPane = renderDeferredSpec(spec);
            paneContainer.appendChild(targetPane);
        }
    }

    if (!targetPane) {
        targetPane = makeElement('div', 'spec-pane stat-unavailable', 'This talent tree is temporarily unavailable.');
        targetPane.id = `spec-pane-${specId}`;
        paneContainer.appendChild(targetPane);
    }

    paneContainer.querySelectorAll('.spec-pane').forEach((pane) => {
        pane.classList.toggle('active', pane === targetPane);
    });
    document.querySelectorAll('.spec-toggle-btn').forEach((tab) => {
        const active = tab === button || tab.dataset.specId === String(specId);
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-pressed', String(active));
    });
};
