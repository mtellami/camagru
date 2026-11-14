/** Create a DOM element with optional class names and safe text content. */
export function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    if (typeof text !== 'undefined') {
        node.textContent = text;
    }
    return node;
}
/** Build an SVG element from one of the shared icon paths. */
export function svgIcon(path, className) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    if (className) {
        svg.setAttribute('class', className);
    }
    const shape = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    shape.setAttribute('d', path);
    svg.appendChild(shape);
    return svg;
}
