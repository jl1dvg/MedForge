(function () {
    function nextIndexFor(list) {
        const current = Number.parseInt(list.dataset.nextIndex || '0', 10);
        list.dataset.nextIndex = String(current + 1);
        return current;
    }

    function buildRow(listName, index) {
        const template = document.getElementById(listName + '-row-template');
        if (!template) {
            return null;
        }

        const html = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
        if (!html) {
            return null;
        }

        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = html;
        return wrapper.firstElementChild;
    }

    document.addEventListener('click', function (event) {
        const addButton = event.target.closest('[data-repeat-add]');
        if (addButton) {
            const listName = addButton.getAttribute('data-repeat-add');
            const list = document.querySelector('[data-repeat-list="' + listName + '"]');
            if (!list) {
                return;
            }

            const row = buildRow(listName, nextIndexFor(list));
            if (row) {
                list.appendChild(row);
            }

            return;
        }

        const removeButton = event.target.closest('[data-repeat-remove]');
        if (!removeButton) {
            return;
        }

        const row = removeButton.closest('[data-repeat-row]');
        if (!row) {
            return;
        }

        const list = row.parentElement;
        row.remove();

        if (list && list.children.length === 0 && list.dataset.repeatList) {
            const replacement = buildRow(list.dataset.repeatList, nextIndexFor(list));
            if (replacement) {
                list.appendChild(replacement);
            }
        }
    });
})();
