import { describe, expect, it } from 'vitest';

/*
 * The log viewer shows a body as plain text only when the log says it was sent
 * as text/plain. Plain text rendered as HTML loses its line breaks and anything
 * in angle brackets (an address such as <anna@example.com> is parsed as a tag
 * and removed), while HTML rendered as text would show raw markup. So the rule
 * has to be exact: text/plain, with or without parameters, in any case; and
 * everything else, including a missing header, keeps the HTML path.
 */

import { isPlainTextLog } from '../../resources/admin/Modules/Logger/bodyFormat';

describe('log viewer body format', () => {
    it('treats text/plain as plain text, with parameters and in any case', () => {
        expect(isPlainTextLog({ 'content-type': 'text/plain' })).toBe(true);
        expect(isPlainTextLog({ 'content-type': 'text/plain; charset=UTF-8' })).toBe(true);
        expect(isPlainTextLog({ 'content-type': ' Text/Plain ' })).toBe(true);
    });

    it('keeps HTML, multipart and unknown bodies on the HTML path', () => {
        expect(isPlainTextLog({ 'content-type': 'text/html' })).toBe(false);
        expect(isPlainTextLog({ 'content-type': 'multipart/alternative' })).toBe(false);
        expect(isPlainTextLog({ 'content-type': '' })).toBe(false);
    });

    it('keeps logs with no recorded content type on the HTML path', () => {
        expect(isPlainTextLog({})).toBe(false);
        expect(isPlainTextLog(null)).toBe(false);
        expect(isPlainTextLog(undefined)).toBe(false);
    });
});
