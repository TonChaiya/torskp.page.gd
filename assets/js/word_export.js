function downloadWordFromPage(filename, selector) {
    const nodes = Array.from(document.querySelectorAll(selector));
    if (!nodes.length) return;

    const html = nodes.map((node) => cloneForWord(node).outerHTML).join('\n');
    const css = Array.from(document.querySelectorAll('style'))
        .map((style) => style.textContent || '')
        .join('\n');

    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'word_export.php';
    form.target = '_blank';
    form.style.display = 'none';

    const fields = {
        filename,
        html,
        css,
        profile: detectWordProfile(filename, nodes),
    };

    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('textarea');
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function detectWordProfile(filename, nodes) {
    const name = String(filename || '').toLowerCase();
    if (name.includes('principle')) return 'principle';
    if (name.includes('meeting')) return 'meeting';
    if (nodes.some((node) => node.classList?.contains('report-page'))) return 'meeting';
    return nodes.some((node) => node.classList?.contains('memo-page')) ? 'memo' : 'default';
}

function cloneForWord(node) {
    const clone = node.cloneNode(true);
    if (clone.classList?.contains('memo-page') && clone.id === 'memo_page_1') {
        preparePrincipleMemoForWord(clone);
    } else {
        inlineComputedStyles(node, clone);
    }
    return clone;
}

function preparePrincipleMemoForWord(page) {
    page.classList.add('word-principle-page');
    clearInlineStyles(page);
    convertPrincipleHeader(page);
    convertPrincipleMetaRows(page);
    convertLineGroupsToTables(page, '.medicine-line', 'word-medicine-table', ['24pt', 'auto', '62pt', '62pt']);
    convertLineGroupsToTables(page, '.committee-line', 'word-committee-table', ['18pt', '133pt', '116pt', '91pt']);
    convertNumberedBlocks(page);
    convertApprovalBlock(page);
    wrapPrincipleMemoSections(page);
}

function clearInlineStyles(root) {
    root.removeAttribute('style');
    root.querySelectorAll('[style]').forEach((node) => node.removeAttribute('style'));
}

function convertPrincipleHeader(page) {
    const header = Array.from(page.children).find((child) => child.querySelector?.('.memo-title') && child.querySelector?.('.garuda'));
    if (!header) return;

    const image = header.querySelector('.garuda');
    const title = header.querySelector('.memo-title');
    const table = makeWordTable('word-memo-header', 'width:100%;table-layout:fixed;border-collapse:collapse;border:none;margin:0 0 3pt 0;');
    const row = table.insertRow();
    appendWordCell(row, [image], 'width:84pt;border:none;padding:0;vertical-align:top;text-align:left;');
    appendWordCell(row, [title], 'border:none;padding:0;vertical-align:top;text-align:center;');
    appendWordCell(row, [], 'width:84pt;border:none;padding:0;vertical-align:top;');
    header.replaceWith(table);
}

function convertPrincipleMetaRows(page) {
    Array.from(page.children).forEach((row) => {
        if (!row.querySelector?.('.memo-label')) return;
        if (row.querySelector('.memo-title')) return;

        const parts = Array.from(row.children);
        const labels = parts.filter((part) => part.classList?.contains('memo-label'));
        if (!labels.length || parts.length < 2) return;

        const table = makeWordTable('word-meta-table', 'width:100%;table-layout:fixed;border-collapse:collapse;border:none;margin:2pt 0 0 0;');
        const tr = table.insertRow();

        if (labels.length >= 2 && parts.length >= 4) {
            appendWordCell(tr, [parts[0]], 'width:28pt;border:none;padding:0 5pt 0 0;vertical-align:bottom;white-space:nowrap;');
            appendWordCell(tr, [parts[1]], 'width:218pt;border:none;padding:0;vertical-align:bottom;');
            appendWordCell(tr, [parts[2]], 'width:45pt;border:none;padding:0 5pt 0 8pt;vertical-align:bottom;white-space:nowrap;');
            appendWordCell(tr, [parts[3]], 'border:none;padding:0;vertical-align:bottom;text-align:center;');
        } else {
            const labelWidth = parts[0].textContent.trim() === 'เรียน' ? '34pt' : '70pt';
            appendWordCell(tr, [parts[0]], `width:${labelWidth};border:none;padding:0 5pt 0 0;vertical-align:top;white-space:nowrap;`);
            appendWordCell(tr, parts.slice(1), 'border:none;padding:0;vertical-align:top;');
        }

        row.replaceWith(table);
    });
}

function convertLineGroupsToTables(page, selector, tableClass, widths) {
    const rows = Array.from(page.querySelectorAll(selector));
    const groups = new Map();
    rows.forEach((row) => {
        const parent = row.parentElement;
        if (!groups.has(parent)) groups.set(parent, []);
        groups.get(parent).push(row);
    });

    groups.forEach((groupRows, parent) => {
        const table = makeWordTable(tableClass, 'width:382pt;table-layout:fixed;border-collapse:collapse;border:none;margin:2pt 0 0 70pt;');
        groupRows.forEach((sourceRow) => {
            const tr = table.insertRow();
            Array.from(sourceRow.children).forEach((cell, index) => {
                const width = widths[index] && widths[index] !== 'auto' ? `width:${widths[index]};` : '';
                const align = index === 2 ? 'text-align:right;' : '';
                appendWordCell(tr, [cell], `${width}${align}border:none;padding:0 3pt 0 0;vertical-align:top;`);
            });
        });
        parent.innerHTML = '';
        parent.appendChild(table);
    });
}

function convertNumberedBlocks(page) {
    Array.from(page.querySelectorAll('.numbered')).forEach((block) => {
        const parts = Array.from(block.children);
        if (parts.length < 2) return;

        const table = makeWordTable('word-numbered-table', 'width:100%;table-layout:fixed;border-collapse:collapse;border:none;margin:2pt 0 0 0;');
        const row = table.insertRow();
        appendWordCell(row, [parts[0]], 'width:88pt;border:none;padding:0 4pt 0 70pt;vertical-align:top;');
        appendWordCell(row, parts.slice(1), 'border:none;padding:0;vertical-align:top;text-align:justify;');
        block.replaceWith(table);
    });
}

function convertApprovalBlock(page) {
    const approval = page.querySelector('.approval');
    if (!approval) return;

    const parts = Array.from(approval.children);
    const signature = parts[1];
    const spacer = signature?.firstElementChild;
    if (spacer && spacer.textContent.trim() === '') {
        spacer.setAttribute('style', 'height:31pt;line-height:31pt;');
    }
    const table = makeWordTable('word-approval-table', 'width:100%;table-layout:fixed;border-collapse:collapse;border:none;margin:22pt 0 0 0;');
    const row = table.insertRow();
    appendWordCell(row, parts.slice(0, 1), 'width:90pt;border:none;padding:0;vertical-align:top;');
    appendWordCell(row, parts.slice(1, 2), 'width:220pt;border:none;padding:0;text-align:center;vertical-align:top;');
    appendWordCell(row, [], 'border:none;padding:0;vertical-align:top;');
    approval.replaceWith(table);
}

function wrapPrincipleMemoSections(page) {
    const children = Array.from(page.children);
    const header = children.find((child) => child.classList?.contains('word-memo-header'));
    const firstBodyNode = children.find((child) => child.classList?.contains('section-title'));
    if (!header || !firstBodyNode) return;

    const main = makeWordTable('word-document-table', 'width:100%;table-layout:fixed;border-collapse:collapse;margin:0;');
    const headerRow = main.insertRow();
    appendWordCell(headerRow, [header], 'border:1px solid #808080;padding:2pt 4pt;vertical-align:top;');

    const metaNodes = [];
    const bodyNodes = [];
    let inBody = false;
    Array.from(page.children).forEach((child) => {
        if (child === header) return;
        if (child === firstBodyNode) inBody = true;
        (inBody ? bodyNodes : metaNodes).push(child);
    });

    const metaRow = main.insertRow();
    appendWordCell(metaRow, [buildPrincipleDetailsTable(metaNodes)], 'border:1px solid #808080;padding:0;vertical-align:top;');

    const bodyRow = main.insertRow();
    appendWordCell(bodyRow, bodyNodes, 'border:1px solid #808080;padding:4pt;vertical-align:top;');

    page.innerHTML = '';
    page.appendChild(main);
}

function buildPrincipleDetailsTable(metaNodes) {
    const sourceRows = metaNodes
        .flatMap((node) => Array.from(node.querySelectorAll?.('tr') || []))
        .map((row) => Array.from(row.cells).map((cell) => Array.from(cell.childNodes)));

    const table = makeWordTable('word-details-table', 'width:100%;table-layout:fixed;border-collapse:collapse;margin:0;');
    sourceRows.forEach((cells) => {
        const row = table.insertRow();
        if (cells.length >= 4) {
            appendWordCell(row, cells[0], 'width:28pt;border:1px solid #808080;padding:1pt 3pt;vertical-align:top;white-space:nowrap;');
            appendWordCell(row, cells[1], 'width:218pt;border:1px solid #808080;padding:1pt 3pt;vertical-align:top;');
            appendWordCell(row, cells[2], 'width:45pt;border:1px solid #808080;padding:1pt 3pt;vertical-align:top;white-space:nowrap;');
            appendWordCell(row, cells[3], 'border:1px solid #808080;padding:1pt 3pt;vertical-align:top;text-align:center;');
        } else if (cells.length >= 2) {
            appendWordCell(row, cells[0], 'width:70pt;border:1px solid #808080;padding:1pt 3pt;vertical-align:top;white-space:nowrap;');
            appendWordCell(row, cells.slice(1).flat(), 'border:1px solid #808080;padding:1pt 3pt;vertical-align:top;');
        }
    });

    return table;
}

function makeWordTable(className, style) {
    const table = document.createElement('table');
    table.className = className;
    table.setAttribute('cellspacing', '0');
    table.setAttribute('cellpadding', '0');
    table.setAttribute('border', '0');
    table.setAttribute('style', `${style}mso-table-lspace:0pt;mso-table-rspace:0pt;`);
    return table;
}

function appendWordCell(row, nodes, style) {
    const cell = row.insertCell();
    cell.setAttribute('style', `${style}font-family:"TH Sarabun New","Sarabun",sans-serif;font-size:16pt;line-height:1;mso-line-height-rule:exactly;`);
    nodes.forEach((node) => {
        if (node) cell.appendChild(node);
    });
    return cell;
}

function inlineComputedStyles(source, clone) {
    if (!(source instanceof Element) || !(clone instanceof Element)) return;

    const computed = window.getComputedStyle(source);
    const props = [
        'box-sizing',
        'display',
        'position',
        'float',
        'clear',
        'width',
        'min-width',
        'max-width',
        'height',
        'min-height',
        'max-height',
        'margin-top',
        'margin-right',
        'margin-bottom',
        'margin-left',
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        'border-top-width',
        'border-right-width',
        'border-bottom-width',
        'border-left-width',
        'border-top-style',
        'border-right-style',
        'border-bottom-style',
        'border-left-style',
        'border-top-color',
        'border-right-color',
        'border-bottom-color',
        'border-left-color',
        'border-collapse',
        'table-layout',
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'line-height',
        'letter-spacing',
        'text-align',
        'text-align-last',
        'text-indent',
        'text-decoration',
        'white-space',
        'vertical-align',
        'color',
        'background-color',
    ];

    const inline = props
        .map((prop) => {
            const value = computed.getPropertyValue(prop);
            return value ? `${prop}:${normalizeWordCssValue(prop, value)}` : '';
        })
        .filter(Boolean)
        .join(';');

    const existing = clone.getAttribute('style') || '';
    const paragraphReset = clone.tagName === 'P'
        ? 'margin-top:0!important;margin-bottom:0!important;mso-margin-top-alt:0pt;mso-margin-bottom-alt:0pt;line-height:1!important;'
        : '';
    clone.setAttribute('style', `${existing};${inline};${paragraphReset}mso-fareast-font-family:"TH Sarabun New";mso-bidi-font-family:"TH Sarabun New";mso-line-height-rule:exactly;`);

    if (clone.tagName === 'IMG') {
        clone.setAttribute('width', Math.round(source.getBoundingClientRect().width));
        clone.setAttribute('height', Math.round(source.getBoundingClientRect().height));
    }

    const sourceChildren = Array.from(source.children);
    const cloneChildren = Array.from(clone.children);
    sourceChildren.forEach((child, index) => inlineComputedStyles(child, cloneChildren[index]));
}

function normalizeWordCssValue(prop, value) {
    if (!value || value === 'normal' || value === 'auto' || value === 'none') return value;
    if (prop === 'line-height' && value.endsWith('px')) {
        return pxToPtValue(value);
    }
    if (/^(font-size|letter-spacing|text-indent|width|min-width|max-width|height|min-height|max-height|margin-|padding-|border-)/.test(prop)) {
        return pxToPtValue(value);
    }
    return value;
}

function pxToPtValue(value) {
    return value.replace(/(-?\d*\.?\d+)px/g, (_, number) => {
        const pt = Number(number) * 0.75;
        return `${Number(pt.toFixed(2))}pt`;
    });
}
