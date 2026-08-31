import '../scss/fluent-mail-admin.scss';

import * as Vue from 'vue';
import * as VueRouter from 'vue-router';
import { ElLoading, ElNotification, ElMessageBox } from 'element-plus';

import * as icons from './Bits/icons';
import routes from './routes';
import Application from './Application.vue';

/*
 * The public `window.FluentMail.Vue` / `.Router`, published from here rather
 * than from boot.js so that they are the very modules the app itself runs on.
 * See the class comment in Bits/FluentMail.js for why they are namespaces now.
 */
window.FluentMail.Vue = Vue;
window.FluentMail.Router = VueRouter;

const router = VueRouter.createRouter({
    history: VueRouter.createWebHashHistory(),
    routes: window.FluentMail.applyFilters('fluent_mail_global_routes', routes)
});

const app = Vue.createApp(Application);

app.use(router);
// v-loading is a directive, so unlike the tags it cannot be auto-imported.
app.use(ElLoading);

/*
 * Element Plus does not put these on the instance the way Element UI did, but
 * roughly sixty call sites reach for `this.$notify` and `this.$confirm`. Putting
 * them back as global properties is what lets the components stay untouched.
 */
app.config.globalProperties.$notify = ElNotification;
app.config.globalProperties.$confirm = ElMessageBox.confirm;

app.config.globalProperties.$rest = window.FluentMail.$rest;
app.config.globalProperties.$get = window.FluentMail.$get;
app.config.globalProperties.$post = window.FluentMail.$post;

/*
 * Register the icon set globally rather than per component.
 *
 * Element Plus dropped Element UI's icon font, so 47 `el-icon-*` class names had
 * to become components. Registering them here keeps the templates declarative -
 * `<el-icon><el-icon-info /></el-icon>` - and, because el-button resolves its
 * `icon` prop through `<component :is>`, it also lets `icon="ElIconSearch"` keep
 * working as a plain string attribute, including where the icon is chosen by an
 * expression at runtime.
 */
Object.entries(icons).forEach(([name, icon]) => app.component(name, icon));

app.mixin(window.FluentMail.appMixin());

/*
 * app.mount() renders *inside* #fluent_mail_app, where Vue 2's `new Vue({el})`
 * replaced the node outright. That is why the id survives into the live DOM,
 * which is exactly what tailwind.config.js's `important: '#fluent_mail_app'`
 * selector needs. Do not change the mount markup in app/views/admin/menu.php.
 */
window.FluentMail.app = app;
app.mount('#fluent_mail_app');
