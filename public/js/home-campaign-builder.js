for (const builder of document.querySelectorAll('[data-campaign-builder]')) {
    const rows = builder.querySelector('[data-rows]');
    let index = Math.max(-1, ...Array.from(rows.querySelectorAll('input[name]'), input => Number(input.name.split('[')[1].split(']')[0]))) + 1;
    builder.addEventListener('click', event => {
        if (event.target.closest('[data-remove]')) event.target.closest('[data-row]').remove();
        if (!event.target.closest('[data-add]')) return;
        const row = builder.querySelector('template').content.cloneNode(true);
        for (const field of row.querySelectorAll('[name]')) field.name = field.name.replace('INDEX', String(index));
        index++;
        rows.append(row);
        rows.lastElementChild.querySelector('input').focus();
    });
}
