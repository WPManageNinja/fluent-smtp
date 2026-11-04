/**
 * Whether a log's body was sent as plain text, from its logged Content-Type header.
 * Anything else, including a missing header, is treated as HTML.
 *
 * @param {Object} headers The log's headers.
 * @return {boolean}
 */
export function isPlainTextLog(headers) {
    const type = String((headers && headers['content-type']) || '').split(';')[0].trim().toLowerCase();

    return type === 'text/plain';
}
