import { beforeEach, describe, expect, it, vi } from 'vitest';

/*
 * There is nothing left to mock here.
 *
 * This file used to stub `vue`, `vue-router`, `element-ui` and
 * `element-ui/lib/locale` module by module, listing all 38 registered
 * components, because Bits/FluentMail.js imported them all in order to mutate
 * the global Vue constructor. Under Vue 3 an app is built by createApp() rather
 * than by extending a constructor, so that file no longer imports a framework
 * at all - the wiring moved to resources/admin/start.js - and the request layer
 * it does own can be tested against the real module.
 */

import FluentMail from '../../resources/admin/Bits/FluentMail';

describe('FluentMail admin request layer', () => {
    let fluentMail;

    beforeEach(() => {
        global.window = {
            ajaxurl: '/wp-admin/admin-ajax.php',
            FluentMailAdmin: {
                slug: 'fluentmail',
                nonce: 'suite-nonce'
            },
            jQuery: {
                get: vi.fn(() => 'get-result'),
                post: vi.fn(() => 'post-result')
            }
        };

        fluentMail = Object.create(FluentMail.prototype);
        fluentMail.appVars = window.FluentMailAdmin;
        window.FluentMail = fluentMail;
    });

    it('forwards the selected jQuery method to the WordPress AJAX URL', () => {
        const options = { action: 'suite-action', page: 2 };

        const result = fluentMail.request('get', options);

        expect(result).toBe('get-result');
        expect(window.jQuery.get).toHaveBeenCalledOnce();
        expect(window.jQuery.get).toHaveBeenCalledWith(window.ajaxurl, options);
        expect(window.jQuery.post).not.toHaveBeenCalled();
    });

    it('adds the GET action and nonce while preserving caller parameters', () => {
        const options = { page: 3, search: 'delivery report' };

        const result = fluentMail.$get('logs', options);

        expect(result).toBe('get-result');
        expect(window.jQuery.get).toHaveBeenCalledWith('/wp-admin/admin-ajax.php', {
            page: 3,
            search: 'delivery report',
            action: 'fluentmail-get-logs',
            nonce: 'suite-nonce'
        });
    });

    it('adds the POST action and nonce while preserving caller payloads', () => {
        const options = {
            id: [17, 23],
            settings: { log_emails: 'yes' }
        };

        const result = fluentMail.$post('logs/delete', options);

        expect(result).toBe('post-result');
        expect(window.jQuery.post).toHaveBeenCalledWith('/wp-admin/admin-ajax.php', {
            id: [17, 23],
            settings: { log_emails: 'yes' },
            action: 'fluentmail-post-logs/delete',
            nonce: 'suite-nonce'
        });
    });

    /*
     * The mixin is the lever that kept the Vue 3 migration from touching 59
     * component script blocks: every one of them calls these through `this.`,
     * so the day one goes missing is the day a screen breaks at runtime with no
     * build error to warn anyone.
     */
    it('exposes the helper methods every component reaches through this.', () => {
        window.FluentMailAdmin.trans = { Save: 'Speichern' };
        fluentMail.appVars = window.FluentMailAdmin;

        const mixin = fluentMail.appMixin();
        const methods = mixin.methods;

        for (const name of [
            'addFilter', 'applyFilters', 'doAction', 'addAction', 'removeAllActions',
            '$dateFormat', 'ucFirst', 'ucWords', 'slugify', 'dayjs', 'escapeHtml',
            'hasPro', '$t'
        ]) {
            expect(methods[name], name).toBeTypeOf('function');
        }

        expect(mixin.data().appVars).toBe(window.FluentMailAdmin);
        expect(methods.$t('Save')).toBe('Speichern');
        expect(methods.$t('Untranslated')).toBe('Untranslated');
        expect(methods.ucFirst('sent')).toBe('Sent');
        expect(methods.escapeHtml('<b>&</b>')).toBe('&lt;b&gt;&amp;&lt;/b&gt;');
    });
});
