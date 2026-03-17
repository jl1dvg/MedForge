(function () {
    const filter = document.getElementById('pricelevel-category-filter');
    if (!filter) {
        return;
    }

    const rows = Array.from(document.querySelectorAll('[data-pricelevel-row="1"]'));
    const counter = document.getElementById('pricelevel-visible-counter');
    const total = rows.length;

    const applyFilter = function () {
        const selected = filter.value;
        let visible = 0;

        rows.forEach(function (row) {
            const rowCategory = row.getAttribute('data-pricelevel-category') || '';
            const show = selected === '' || rowCategory === selected;
            row.classList.toggle('d-none', !show);
            if (show) {
                visible += 1;
            }
        });

        if (counter) {
            counter.textContent = 'Mostrando ' + visible + ' de ' + total + ' afiliaciones';
        }
    };

    filter.addEventListener('change', applyFilter);
    applyFilter();
})();
