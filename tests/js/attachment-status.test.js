import { describe, expect, it, vi } from 'vitest';

/*
 * The log viewer, the log list and the resend dialog all read the server's
 * per-row attachment_list. These pin which attachments count as "can't be
 * resent", that a resend asks first only when there are some, and that the
 * result notice turns into a warning when the server reports skipped files.
 */

import {
    attachmentList,
    unresendableAttachments,
    attachmentStatusLabel,
    attachmentStatusReason,
    confirmResendWithoutAttachments,
    confirmBulkResendWithoutAttachments,
    notifyResendResult,
    loadAttachmentStatus
} from '../../resources/admin/Modules/Logger/attachmentStatus';

// A jqXHR-like result for vm.$get: then() and always() run synchronously.
const ajax = (response, fail = false) => ({
    then(cb) { if (!fail) { cb(response); } return this; },
    always(cb) { cb(); return this; }
});

const vm = (statuses = {}, fail = false) => ({
    $t: (s, ...args) => args.reduce((out, arg) => out.replace('%s', arg), s),
    $confirm: vi.fn(() => Promise.resolve()),
    $notify: { success: vi.fn(), warning: vi.fn() },
    $get: vi.fn(() => ajax({ data: { attachments: statuses } }, fail))
});

const log = {
    attachment_list: [
        { name: 'report.pdf', status: 'ok', inline: false, size: 10 },
        { name: 'invoice.pdf', status: 'in_memory', inline: false, size: 200 },
        { name: 'old.pdf', status: 'missing', inline: false, size: null }
    ]
};

describe('attachment status', () => {
    it('lists only in-memory and missing attachments as unresendable', () => {
        expect(unresendableAttachments(log).map(a => a.name)).toEqual(['invoice.pdf', 'old.pdf']);
    });

    it('falls back to the raw attachments of an older response, with an unknown status', () => {
        const list = attachmentList({ attachments: [['/uploads/2026/report.pdf', 'report.pdf']] });
        expect(list).toEqual([{ name: 'report.pdf', status: 'unknown', inline: false, size: null }]);
        expect(unresendableAttachments({ attachments: [['/uploads/a.pdf']] })).toEqual([]);
    });

    it('labels and explains only the attachments that cannot be resent', () => {
        const v = vm();
        expect(attachmentStatusLabel(log.attachment_list[0], v)).toBe('');
        expect(attachmentStatusLabel(log.attachment_list[1], v)).toBe('Not stored');
        expect(attachmentStatusLabel(log.attachment_list[2], v)).toBe('File unavailable');
        expect(attachmentStatusReason(log.attachment_list[1], v)).toContain('Created in memory');
        expect(attachmentStatusReason(log.attachment_list[2], v)).toContain('no longer exists');
    });

    it('asks before a resend only when some attachments cannot be included', async () => {
        const v = vm();
        await confirmResendWithoutAttachments(v, { attachment_list: [log.attachment_list[0]] });
        expect(v.$confirm).not.toHaveBeenCalled();

        await confirmResendWithoutAttachments(v, log);
        expect(v.$confirm).toHaveBeenCalledTimes(1);
        expect(v.$confirm.mock.calls[0][0]).toContain('invoice.pdf, old.pdf');
    });

    it('asks the server about unchecked files only, and only for logs that have them', async () => {
        const unchecked = { id: 7, attachment_list: [{ name: 'report.pdf', status: 'unchecked', inline: false, size: null }] };
        const v = vm({ 7: [{ name: 'report.pdf', status: 'missing', inline: false, size: null }] });

        await confirmResendWithoutAttachments(v, unchecked);
        expect(v.$get).toHaveBeenCalledWith('logs/attachment-status', { ids: [7] });
        expect(unchecked.attachment_list[0].status).toBe('missing');
        expect(v.$confirm).toHaveBeenCalledTimes(1);
        expect(v.$confirm.mock.calls[0][0]).toContain('report.pdf');

        // No attachments, or only checked / in-memory ones: no request.
        const quiet = vm();
        await loadAttachmentStatus(quiet, [{ id: 1, attachment_list: [] }, { id: 2, attachment_list: [log.attachment_list[1]] }]);
        expect(quiet.$get).not.toHaveBeenCalled();
    });

    it('sends one request for a bulk resend, and goes ahead when the check fails', async () => {
        const rows = [
            { id: 3, attachment_list: [{ name: 'a.pdf', status: 'unchecked' }] },
            { id: 4, attachment_list: [] },
            { id: 5, attachment_list: [{ name: 'b.pdf', status: 'unchecked' }] }
        ];
        const v = vm({ 3: [{ name: 'a.pdf', status: 'ok' }], 5: [{ name: 'b.pdf', status: 'missing' }] });
        await confirmBulkResendWithoutAttachments(v, rows);
        expect(v.$get).toHaveBeenCalledTimes(1);
        expect(v.$get.mock.calls[0][1]).toEqual({ ids: [3, 5] });
        expect(v.$confirm.mock.calls[0][0]).toContain('One of the selected emails has');

        const failing = vm({}, true);
        await confirmResendWithoutAttachments(failing, { id: 9, attachment_list: [{ name: 'c.pdf', status: 'unchecked' }] });
        expect(failing.$confirm).not.toHaveBeenCalled();
    });

    it('warns after a resend that left attachments out, and confirms one that did not', () => {
        const v = vm();
        notifyResendResult(v, { data: { message: 'Email sent successfully.', skipped_attachments: [] } });
        expect(v.$notify.success).toHaveBeenCalledTimes(1);
        expect(v.$notify.warning).not.toHaveBeenCalled();

        notifyResendResult(v, { data: { message: 'Email sent. This attachment could not be included: invoice.pdf.', skipped_attachments: [{ name: 'invoice.pdf', status: 'in_memory' }] } });
        expect(v.$notify.warning).toHaveBeenCalledTimes(1);
        expect(v.$notify.warning.mock.calls[0][0].message).toContain('invoice.pdf');
    });

    it('asks before a bulk resend only when a selected email has attachments that cannot be included', async () => {
        const v = vm();
        await confirmBulkResendWithoutAttachments(v, [{ attachment_list: [log.attachment_list[0]] }, { attachments: [] }]);
        expect(v.$confirm).not.toHaveBeenCalled();

        await confirmBulkResendWithoutAttachments(v, [log, { attachment_list: [] }, log]);
        expect(v.$confirm).toHaveBeenCalledTimes(1);
        expect(v.$confirm.mock.calls[0][0]).toContain('2 of the selected emails');

        await confirmBulkResendWithoutAttachments(v, [log]);
        expect(v.$confirm.mock.calls[1][0]).toContain('One of the selected emails has');
    });
});
