import Dashboard from './Modules/Dashboard/Dashboard';
import Connections from './Modules/Settings/Connections';
import Connection from './Modules/Settings/Connection';
import Logs from './Modules/Logger/Logs';
import Test from './Modules/Test/Test';
import Support from './Modules/Misc/Support';
import Docs from './Modules/Misc/Docs';
import NotificationSettings from './Modules/NotificationSettings/NotificationSettings.vue';

/*
 * Every `path` here is byte-identical to the one that shipped, and has to stay that way.
 *
 * NotificationHelper.php embeds these hashes into the Slack, Telegram and Discord alerts
 * it sends - `#/logs?per_page=10&page=1&status=failed&search=…` is sitting in people's
 * Slack history right now - the admin bar node points at `#/connections`, and
 * `#/notification-settings` is where the Slack OAuth round trip returns to. Renaming one
 * breaks a link that has already been delivered and cannot be recalled.
 *
 * `meta.active` is chrome rather than routing: it tells the app bar which of its four
 * destinations to light up. Settings - the connections screen, whose route keeps the
 * `connections` name - stays marked while the add and edit screens behind it are open.
 * Email Test and About light nothing - the first is the bar's own button, the second a
 * footer link - so they carry a value no destination matches.
 * `tests/lint/browser-route-coverage.php` fails the build if a path is added or removed
 * on one side of the browser smoke manifest only.
 */
export default [
    {
        name: 'dashboard',
        path: '/',
        meta: {
            active: 'dashboard',
            title: 'Dashboard'
        },
        component: Dashboard
    },
    {
        name: 'connections',
        path: '/connections',
        meta: {
            active: 'connections',
            title: 'Settings'
        },
        component: Connections
    },
    {
        name: 'connection',
        path: '/connection',
        meta: {
            active: 'connections',
            title: 'Add Connection'
        },
        component: Connection
    },
    {
        name: 'test',
        path: '/test',
        meta: {
            active: 'test',
            title: 'Email Test'
        },
        component: Test
    },
    {
        name: 'support',
        path: '/support',
        meta: {
            active: 'about',
            title: 'About'
        },
        component: Support
    },
    {
        name: 'logs',
        path: '/logs',
        meta: {
            active: 'logs',
            title: 'Email Logs'
        },
        component: Logs
    },
    {
        name: 'docs',
        path: '/documentation',
        meta: {
            active: 'docs',
            title: 'Documentation'
        },
        component: Docs
    },
    {
        name: 'notification_settings',
        path: '/notification-settings',
        meta: {
            active: 'alerts',
            title: 'Alerts & Notifications'
        },
        component: NotificationSettings
    }
];
