import Dashboard from './Modules/Dashboard/Dashboard';
import Connections from './Modules/Settings/Connections';
import Connection from './Modules/Settings/Connection';
import Logs from './Modules/Logger/Logs';
import Test from './Modules/Test/Test';
import Support from './Modules/Misc/Support';
import Docs from './Modules/Misc/Docs';
import NotificationSettings from './Modules/NotificationSettings/NotificationSettings.vue';

export default [
    {
        name: 'dashboard',
        path: '/',
        meta: {},
        component: Dashboard
    },
    {
        name: 'connections',
        path: '/connections',
        meta: {},
        component: Connections
    },
    {
        name: 'connection',
        path: '/connection',
        meta: {},
        component: Connection
    },
    {
        name: 'test',
        path: '/test',
        meta: {},
        component: Test
    },
    {
        name: 'support',
        path: '/support',
        meta: {},
        component: Support
    },
    {
        name: 'logs',
        path: '/logs',
        meta: {},
        component: Logs
    },
    {
        name: 'docs',
        path: '/documentation',
        meta: {},
        component: Docs
    },
    {
        name: 'notification_settings',
        path: '/notification-settings',
        meta: {},
        component: NotificationSettings
    }
];
