const rosterTable = document.querySelector('.guild-roster-table');

if (rosterTable) {
    const tableBody = rosterTable.tBodies[0];
    const sortButtons = Array.from(rosterTable.querySelectorAll('.guild-sort-button'));
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    let activeColumn = null;
    let direction = 'ascending';

    const sortValue = (row, column) => row.cells[column]?.dataset.sortValue.trim() ?? '';

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
                const firstIsEmpty = firstValue === '';
                const secondIsEmpty = secondValue === '';

                // Keep unavailable values, such as pending parses, at the end.
                if (firstIsEmpty !== secondIsEmpty) {
                    return firstIsEmpty ? 1 : -1;
                }

                let comparison = 0;
                if (!firstIsEmpty) {
                    comparison = type === 'number'
                        ? Number(firstValue) - Number(secondValue)
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
