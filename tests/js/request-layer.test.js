import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('vue', () => ({
    default: {
        use: vi.fn(),
        mixin: vi.fn(),
        filter: vi.fn(),
        prototype: {}
    }
}));

vi.mock('vue-router', () => ({ default: function Router() {} }));
vi.mock('element-ui/lib/locale/lang/en', () => ({ default: {} }));
vi.mock('element-ui/lib/locale', () => ({ default: { use: vi.fn() } }));
vi.mock('element-ui', () => {
    const plugin = {};
    return {
        Tag: plugin,
        Row: plugin,
        Col: plugin,
        Menu: plugin,
        Form: plugin,
        Alert: plugin,
        Table: plugin,
        Input: plugin,
        Option: plugin,
        Radio: plugin,
        Button: plugin,
        Select: plugin,
        Dialog: plugin,
        Popover: plugin,
        Loading: { directive: plugin },
        Tooltip: plugin,
        MenuItem: plugin,
        Checkbox: plugin,
        FormItem: plugin,
        Pagination: plugin,
        DatePicker: plugin,
        TimePicker: plugin,
        RadioGroup: plugin,
        MessageBox: { alert: vi.fn(), confirm: vi.fn() },
        OptionGroup: plugin,
        ButtonGroup: plugin,
        TableColumn: plugin,
        Notification: vi.fn(),
        CheckboxGroup: plugin,
        RadioButton: plugin,
        Switch: plugin,
        Collapse: plugin,
        CollapseItem: plugin,
        Skeleton: plugin,
        SkeletonItem: plugin
    };
});

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
});
