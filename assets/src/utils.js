/**
 * Decode HTML entities (e.g. "&amp;" -> "&") in strings coming from the
 * REST API, since WordPress stores titles/term names with entities encoded
 * but React text nodes render them literally.
 * @param {string} text
 * @returns {string}
 */
export function decodeHtmlEntities(text) {
    if (!text) return text;
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
}
