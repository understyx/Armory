const rosterTable = document.querySelector('.guild-roster-table');

if (rosterTable) {
    const tableBody = rosterTable.tBodies[0];
    const filterInput = document.getElementById('guild-member-filter');
    const matchCount = document.getElementById('guild-match-count');
    const rows = Array.from(tableBody.querySelectorAll('tr[data-sortable-row]'));
    const sortButtons = Array.from(rosterTable.querySelectorAll('.guild-sort-button'));
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    let activeColumn = null;
    let direction = 'ascending';

    const sortValue = (row, column) => row.cells[column]?.textContent.trim().replaceAll(',', '') ?? '';

    const updateFilter = () => {
        const term = filterInput?.value.trim().toLocaleLowerCase() ?? '';
        let visibleCount = 0;

        rows.forEach((row) => {
            const matches = term === '' || (row.dataset.search || '').toLocaleLowerCase().includes(term);
            row.hidden = !matches;
            if (matches) visibleCount += 1;
        });

        if (matchCount) matchCount.textContent = String(visibleCount);
    };

    filterInput?.addEventListener('input', updateFilter);
    updateFilter();

    const updateHeaderState = (activeButton) => {
        sortButtons.forEach((button) => {
            const header = button.closest('th');
            const isActive = button === activeButton;

            header.setAttribute('aria-sort', isActive ? direction : 'none');
            button.setAttribute(
                'aria-label',
                `${button.textContent.trim()}, ${isActive ? `sorted ${direction}` : 'not sorted'}`
            );
        });
    };

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const column = Number(button.dataset.sortColumn);
            const type = button.dataset.sortType;

            direction = activeColumn === column && direction === 'ascending' ? 'descending' : 'ascending';
            activeColumn = column;

            const rows = Array.from(tableBody.querySelectorAll('tr[data-sortable-row]'))
                .map((row, index) => ({ row, index }));

            rows.sort((first, second) => {
                const firstValue = sortValue(first.row, column);
                const secondValue = sortValue(second.row, column);
                const firstNumber = type === 'number' ? Number.parseFloat(firstValue) : null;
                const secondNumber = type === 'number' ? Number.parseFloat(secondValue) : null;
                const firstIsEmpty = firstValue === '' || (type === 'number' && Number.isNaN(firstNumber));
                const secondIsEmpty = secondValue === '' || (type === 'number' && Number.isNaN(secondNumber));

                // Keep unavailable values, such as pending parses, at the end.
                if (firstIsEmpty !== secondIsEmpty) {
                    return firstIsEmpty ? 1 : -1;
                }

                let comparison = 0;
                if (!firstIsEmpty) {
                    comparison = type === 'number'
                        ? firstNumber - secondNumber
                        : collator.compare(firstValue, secondValue);
                }

                return comparison === 0
                    ? first.index - second.index
                    : comparison * (direction === 'ascending' ? 1 : -1);
            });

            rows.forEach(({ row }) => tableBody.append(row));
            updateHeaderState(button);
        });
    });

    updateHeaderState(null);
}
