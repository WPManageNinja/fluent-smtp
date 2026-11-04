/**
 * Attachment states that the server reports for each email log row
 * (`attachment_list`, built by Logger::describeAttachments()).
 *
 * - ok:        the file is still on disk, so a resend includes it
 * - in_memory: built in memory by the sending plugin (e.g. an invoice PDF);
 *              the log does not keep the data, so it can't be resent
 * - missing:   the file was deleted or moved, so it can't be resent
 */

/** The logged attachments, or a best-effort list for rows from older responses. */
export function attachmentList(log) {
    if (!log) {
        return [];
    }

    if (Array.isArray(log.attachment_list)) {
        return log.attachment_list;
    }

    const raw = Array.isArray(log.attachments) ? log.attachments : (log.attachments ? [log.attachments] : []);

    return raw.map(attachment => {
        const path = Array.isArray(attachment) ? String(attachment[0] || '') : String(attachment || '');
        return {name: path.replace(/\\/g, '/').split('/').pop(), status: 'unknown', inline: false, size: null};
    });
}

/** The attachments a resend of this log row cannot include. */
export function unresendableAttachments(log) {
    return attachmentList(log).filter(item => item.status === 'in_memory' || item.status === 'missing');
}

/** A short label for an attachment's state, or '' when it can be resent. */
export function attachmentStatusLabel(item, vm) {
    if (item.status === 'in_memory') {
        return vm.$t('Not stored');
    }
    if (item.status === 'missing') {
        return vm.$t('File missing');
    }
    return '';
}

/** The full reason an attachment can't be resent, or '' when it can. */
export function attachmentStatusReason(item, vm) {
    if (item.status === 'in_memory') {
        return vm.$t('Created in memory by the sending plugin. It was not stored, so it can\'t be resent.');
    }
    if (item.status === 'missing') {
        return vm.$t('The file no longer exists on the server, so it can\'t be resent.');
    }
    return '';
}

/**
 * Ask before a resend that would go out without some of its attachments.
 * Resolves when there is nothing to warn about or the admin confirms; rejects
 * when they cancel.
 */
export function confirmResendWithoutAttachments(vm, log) {
    const missing = unresendableAttachments(log);

    if (!missing.length) {
        return Promise.resolve();
    }

    const names = missing.map(item => item.name).join(', ');

    return vm.$confirm(
        vm.$t('These attachments can\'t be included and the email will go out without them: %s', names),
        vm.$t('Resend without some attachments?'),
        {
            type: 'warning',
            confirmButtonText: vm.$t('Resend without them'),
            cancelButtonText: vm.$t('Cancel')
        }
    );
}

/** Notify the result of a resend: a warning when attachments were left out. */
export function notifyResendResult(vm, res) {
    const skipped = res && res.data && Array.isArray(res.data.skipped_attachments) ? res.data.skipped_attachments : [];

    if (skipped.length) {
        vm.$notify.warning({
            offset: 19,
            title: vm.$t('Sent without some attachments'),
            message: res.data.message,
            duration: 8000
        });
        return;
    }

    vm.$notify.success({
        offset: 19,
        title: vm.$t('Done'),
        message: res.data.message
    });
}
