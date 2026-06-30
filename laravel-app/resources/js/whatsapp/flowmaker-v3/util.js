export function uid(prefix = 'id') {
    return `${prefix}_${Math.random().toString(36).slice(2, 9)}`;
}

export function edgePath(x1, y1, x2, y2, style = 'bezier') {
    if (style === 'step') {
        const mid = x1 + (x2 - x1) / 2;
        return `M ${x1} ${y1} L ${mid} ${y1} L ${mid} ${y2} L ${x2} ${y2}`;
    }

    const dx = Math.max(80, Math.abs(x2 - x1) * 0.45);
    return `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`;
}

export function waFormat(text = '') {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
        .replace(/_(.*?)_/g, '<em>$1</em>')
        .replace(/\n/g, '<br>');
}

export function fillVars(text = '', values = {}) {
    return String(text).replace(/\{\{([^}]+)}}/g, (_, key) => values[key.trim()] ?? `{{${key}}}`);
}
