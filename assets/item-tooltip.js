const dataElement = document.getElementById('item-tooltip-data');

if (dataElement) {
    let tooltips = {};
    try {
        tooltips = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        console.error('Unable to parse local item tooltip data.', error);
    }

    let activeTrigger = null;
    let activeTooltip = null;
    let touchPrimedTrigger = null;

    const appendLine = (parent, text, className = '') => {
        if (text === null || text === undefined || text === '') return null;
        const line = document.createElement('div');
        line.className = `item-tooltip-line ${className}`.trim();
        line.textContent = text;
        parent.appendChild(line);
        return line;
    };

    const setIconSource = (image, source, fallbackSource) => {
        image.addEventListener('error', () => {
            if (image.dataset.fallbackSrc) {
                const fallback = image.dataset.fallbackSrc;
                delete image.dataset.fallbackSrc;
                image.src = fallback;
            } else {
                image.style.display = 'none';
            }
        });

        if (fallbackSource) {
            image.dataset.fallbackSrc = fallbackSource;
        }

        image.src = source;
    };

    const buildTooltip = (item) => {
        const tooltip = document.createElement('div');
        tooltip.id = 'local-item-tooltip';
        tooltip.className = `item-tooltip item-tooltip-q-${item.quality}`;
        tooltip.setAttribute('role', 'tooltip');

        if (item.icon_url) {
            const icon = document.createElement('img');
            icon.className = `item-tooltip-icon q-${item.quality}`;
            setIconSource(icon, item.icon_url, item.icon_fallback_url);
            icon.alt = '';
            tooltip.appendChild(icon);
        }

        const content = document.createElement('div');
        content.className = 'item-tooltip-content';
        tooltip.appendChild(content);

        appendLine(content, item.name, `item-tooltip-name q-${item.quality}`);
        if (item.heroic) appendLine(content, 'Heroic', 'item-tooltip-positive');
        if (Number(item.transmog_item?.id) > 0) {
            appendLine(content, `Transmogrified to: ${item.transmog_item.name}`, 'item-tooltip-transmog');
        }
        appendLine(content, item.binding);
        if (item.unique) appendLine(content, 'Unique');

        if (item.slot || item.subclass) {
            const typeRow = document.createElement('div');
            typeRow.className = 'item-tooltip-split';
            const slot = document.createElement('span');
            slot.textContent = item.slot || '';
            const subclass = document.createElement('span');
            subclass.textContent = item.subclass || '';
            typeRow.append(slot, subclass);
            content.appendChild(typeRow);
        }

        if (item.damage) {
            const damageRow = document.createElement('div');
            damageRow.className = 'item-tooltip-split';
            const range = document.createElement('span');
            range.textContent = `${item.damage.min} - ${item.damage.max} Damage`;
            const speed = document.createElement('span');
            speed.textContent = item.damage.speed ? `Speed ${item.damage.speed}` : '';
            damageRow.append(range, speed);
            content.appendChild(damageRow);
            if (item.damage.dps) appendLine(content, `(${item.damage.dps} damage per second)`);
        }

        if (item.armor > 0) appendLine(content, `${item.armor} Armor`);
        if (item.block > 0) appendLine(content, `${item.block} Block`);
        (item.primary_stats || []).forEach((stat) => appendLine(content, stat));

        if (item.enchant) appendLine(content, item.enchant, 'item-tooltip-positive');

        (item.sockets || []).forEach((socket) => {
            const row = document.createElement('div');
            row.className = `item-tooltip-socket ${socket.matches ? 'is-matched' : 'is-unmatched'}`;

            if (socket.gem?.icon_url) {
                const gemIcon = document.createElement('img');
                setIconSource(gemIcon, socket.gem.icon_url, socket.gem.icon_fallback_url);
                gemIcon.alt = '';
                row.appendChild(gemIcon);
            } else {
                const emptySocket = document.createElement('span');
                emptySocket.className = `item-tooltip-socket-empty socket-${String(socket.color).toLowerCase()}`;
                row.appendChild(emptySocket);
            }

            const socketText = document.createElement('span');
            socketText.textContent = socket.gem?.effect || `${socket.color} Socket`;
            row.appendChild(socketText);
            content.appendChild(row);
        });

        if (item.socket_bonus) {
            appendLine(
                content,
                `Socket Bonus: ${item.socket_bonus.text}${item.socket_bonus.active ? '' : ' (Inactive)'}`,
                item.socket_bonus.active ? 'item-tooltip-positive' : 'item-tooltip-inactive'
            );
        }

        if (item.required_level > 0) appendLine(content, `Requires Level ${item.required_level}`);
        if (item.item_level > 0) appendLine(content, `Item Level ${item.item_level}`);

        if (Array.isArray(item.effects)) {
            const effectPrefixes = {equip: 'Equip', use: 'Use', chance_on_hit: 'Chance on hit'};
            item.effects.forEach((effect) => appendLine(
                content,
                `${effectPrefixes[effect.type] || 'Equip'}: ${effect.text}`,
                'item-tooltip-positive'
            ));
        } else {
            (item.equip_effects || []).forEach((effect) => appendLine(content, `Equip: ${effect}`, 'item-tooltip-positive'));
        }

        if (item.item_set?.name) {
            const memberCount = item.item_set.members?.length || 0;
            appendLine(
                content,
                `${item.item_set.name} (${item.item_set.equipped_count}/${memberCount})`,
                'item-tooltip-set-name'
            );
            (item.item_set.members || []).forEach((member) => {
                const isEquipped = member.equipped || Number(member.item_id) === Number(item.id);
                appendLine(
                    content,
                    member.name,
                    `item-tooltip-set-member ${isEquipped ? 'is-equipped' : ''}`
                );
            });
            (item.item_set.bonuses || []).forEach((bonus) => appendLine(
                content,
                `(${bonus.required_count}) Set: ${bonus.description}`,
                bonus.active ? 'item-tooltip-positive item-tooltip-set-bonus' : 'item-tooltip-inactive item-tooltip-set-bonus'
            ));
        }
        appendLine(content, item.description, 'item-tooltip-flavor');

        if (item.sell_price) {
            const price = document.createElement('div');
            price.className = 'item-tooltip-price';
            const label = document.createElement('span');
            label.textContent = 'Sell Price:';
            price.appendChild(label);
            [['gold', 'gold'], ['silver', 'silver'], ['copper', 'copper']].forEach(([key, className]) => {
                if (item.sell_price[key] <= 0 && key === 'gold') return;
                const amount = document.createElement('span');
                amount.className = 'item-tooltip-coin-amount';
                amount.textContent = item.sell_price[key];
                const coin = document.createElement('span');
                coin.className = `item-tooltip-coin coin-${className}`;
                price.append(amount, coin);
            });
            content.appendChild(price);
        }

        return tooltip;
    };

    const itemAnchorRect = (trigger) => {
        const container = trigger.closest('.paperdoll-row, .paperdoll-bottom-card') || trigger;
        if (!container.classList.contains('paperdoll-row')) return container.getBoundingClientRect();

        const parts = [...container.querySelectorAll(':scope > .paperdoll-slot, :scope > .slot-info-block')];
        if (parts.length === 0) return container.getBoundingClientRect();

        const rects = parts.map((part) => part.getBoundingClientRect());
        const left = Math.min(...rects.map((rect) => rect.left));
        const right = Math.max(...rects.map((rect) => rect.right));
        const top = Math.min(...rects.map((rect) => rect.top));
        const bottom = Math.max(...rects.map((rect) => rect.bottom));

        return {left, right, top, bottom, width: right - left, height: bottom - top};
    };

    const positionTooltip = (trigger, tooltip) => {
        const margin = 10;
        const itemContainer = trigger.closest('.paperdoll-row, .paperdoll-bottom-card') || trigger;
        const anchorRect = itemAnchorRect(trigger);
        const tooltipRect = tooltip.getBoundingClientRect();
        const icon = tooltip.querySelector('.item-tooltip-icon');
        const iconOffset = icon && getComputedStyle(icon).display !== 'none' ? 55 : 0;
        const minimumLeft = margin + iconOffset;
        const rightPlacement = anchorRect.right + margin + iconOffset;
        const leftPlacement = anchorRect.left - tooltipRect.width - margin;
        const fitsRight = rightPlacement + tooltipRect.width <= window.innerWidth - margin;
        const fitsLeft = leftPlacement - iconOffset >= margin;
        const prefersLeft = itemContainer.classList.contains('right-row');
        let left;
        let top = anchorRect.top;
        let placedVertically = false;

        if (prefersLeft && fitsLeft) {
            left = leftPlacement;
        } else if (!prefersLeft && fitsRight) {
            left = rightPlacement;
        } else if (fitsRight) {
            left = rightPlacement;
        } else if (fitsLeft) {
            left = leftPlacement;
        } else {
            placedVertically = true;
            left = Math.max(minimumLeft, Math.min(
                anchorRect.left + (anchorRect.width - tooltipRect.width) / 2,
                window.innerWidth - tooltipRect.width - margin
            ));
        }

        if (placedVertically) {
            const below = anchorRect.bottom + margin;
            top = below + tooltipRect.height <= window.innerHeight - margin
                ? below
                : anchorRect.top - tooltipRect.height - margin;
        }
        top = Math.max(margin, Math.min(top, window.innerHeight - tooltipRect.height - margin));

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    };

    const hideTooltip = () => {
        if (activeTrigger) activeTrigger.removeAttribute('aria-describedby');
        activeTooltip?.remove();
        activeTooltip = null;
        activeTrigger = null;
    };

    const showTooltip = (trigger) => {
        const item = tooltips[trigger.dataset.itemTooltip];
        if (!item) return;
        if (activeTrigger === trigger && activeTooltip?.isConnected) return;

        hideTooltip();
        activeTrigger = trigger;
        activeTooltip = buildTooltip(item);
        document.body.appendChild(activeTooltip);
        trigger.setAttribute('aria-describedby', activeTooltip.id);
        positionTooltip(trigger, activeTooltip);
    };

    const tooltipTriggerFrom = (target) => target instanceof Element ? target.closest('[data-item-tooltip]') : null;

    document.addEventListener('pointerover', (event) => {
        if (event.pointerType === 'touch') return;
        const trigger = tooltipTriggerFrom(event.target);
        if (trigger) showTooltip(trigger);
    });

    document.addEventListener('pointerout', (event) => {
        if (event.pointerType === 'touch') return;
        const trigger = tooltipTriggerFrom(event.target);
        const destination = tooltipTriggerFrom(event.relatedTarget);
        if (trigger && destination !== trigger) hideTooltip();
    });

    document.addEventListener('focusin', (event) => {
        const trigger = tooltipTriggerFrom(event.target);
        if (trigger) showTooltip(trigger);
    });

    document.addEventListener('focusout', (event) => {
        const destination = tooltipTriggerFrom(event.relatedTarget);
        if (!destination) hideTooltip();
    });

    document.addEventListener('click', (event) => {
        const trigger = tooltipTriggerFrom(event.target);
        const touchOnly = window.matchMedia('(hover: none)').matches;
        if (touchOnly && trigger && touchPrimedTrigger !== trigger) {
            event.preventDefault();
            touchPrimedTrigger = trigger;
            showTooltip(trigger);
            return;
        }
        if (!trigger) {
            touchPrimedTrigger = null;
            hideTooltip();
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') hideTooltip();
    });

    window.addEventListener('resize', hideTooltip);
    window.addEventListener('scroll', hideTooltip, true);
}
