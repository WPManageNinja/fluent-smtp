import Dashboard from './Modules/Dashboard/Dashboard';
import Connections from './Modules/Settings/Connections';
import Connection from './Modules/Settings/Connection';
import Logs from './Modules/Logger/Logs';
import Test from './Modules/Test/Test';
import Misc from './Modules/Misc/Misc';

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
    // {
    //     name: 'global-settings',
    //     path: '/global-settings',
    //     meta: {},
    //     component: Misc
    // },
    {
        name: 'logs',
        path: '/logs',
        meta: {},
        component: Logs
    }
];
