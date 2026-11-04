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
    notifyResendResult
} from '../../resources/admin/Modules/Logger/attachmentStatus';

const vm = () => ({
    $t: (s, ...args) => args.reduce((out, arg) => out.replace('%s', arg), s),
    $confirm: vi.fn(() => Promise.resolve()),
    $notify: { success: vi.fn(), warning: vi.fn() }
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
        expect(attachmentStatusLabel(log.attachment_list[2], v)).toBe('File missing');
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

    it('warns after a resend that left attachments out, and confirms one that did not', () => {
        const v = vm();
        notifyResendResult(v, { data: { message: 'Email sent successfully.', skipped_attachments: [] } });
        expect(v.$notify.success).toHaveBeenCalledTimes(1);
        expect(v.$notify.warning).not.toHaveBeenCalled();

        notifyResendResult(v, { data: { message: 'Email sent. This attachment could not be included: invoice.pdf.', skipped_attachments: [{ name: 'invoice.pdf', status: 'in_memory' }] } });
        expect(v.$notify.warning).toHaveBeenCalledTimes(1);
        expect(v.$notify.warning.mock.calls[0][0].message).toContain('invoice.pdf');
    });
});
