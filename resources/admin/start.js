import '../scss/fluent-mail-admin.scss';

import * as Vue from 'vue';
import * as VueRouter from 'vue-router';
import { ElLoading, ElNotification, ElMessageBox, notificationTypes, provideGlobalConfig } from 'element-plus';

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
 * Where Element Plus starts stacking.
 *
 * Its overlays count up from 2000, and wp-admin's chrome is far above that - the admin
 * bar is at 99999 and the menu at 9990 - so a dialog opened over either of them was
 * drawn underneath it. This is a range to move out of rather than a number to nudge, and
 * moving the base is what keeps the components in the order Element Plus puts them in:
 * they all count up from the same figure, so a dropdown inside a dialog still lands
 * above its dialog.
 *
 * It has to go through the global *config*, not through `provide(zIndexContextKey)`.
 * The two are not equivalent, and the difference is invisible until you open a confirm
 * dialog on top of another dialog:
 *
 *   - `el-dialog` and the other in-tree components call `useZIndex()` with no argument,
 *     which falls back to `inject(zIndexContextKey)`. Providing the key reaches them.
 *   - `ElMessageBox` and `ElNotification` go through `useGlobalComponentSettings()`,
 *     which calls `useZIndex(computed(() => config.value?.zIndex ?? 2000))`. Passing an
 *     override makes `useZIndex` skip the inject fallback entirely, so the provided key
 *     never reaches them and they keep counting from 2000.
 *
 * The result was a dialog at 100001 with its own confirmation drawn at 2001, underneath
 * it: "Manage Additional Senders" could open, but the Remove confirmation behind it was
 * unreachable, and any notification raised from inside a dialog was hidden by it.
 *
 * `provideGlobalConfig` sets the config both paths read, so the whole family counts from
 * one base again. The third argument makes it the global default, which is what the
 * imperative services pick up when they render outside the component tree.
 */
provideGlobalConfig({ zIndex: 100000 }, app, true);

/*
 * The message box and the notification are rendered outside the component tree, so
 * neither can inject anything unless it has been handed the app's context - which is
 * what their own install() does, and the only reason these two are `use`d rather than
 * imported and called. This has to stay *after* provideGlobalConfig: install() is what
 * binds them to the app whose config was just set.
 */
app.use(ElMessageBox);
app.use(ElNotification);

/**
 * How far down the screen a notification has to start to clear wp-admin's own bar.
 *
 * Measured rather than assumed: the bar is 32px on a desktop and 46px below 782px, and
 * below that width WordPress stops pinning it, at which point it scrolls away and there
 * is nothing to clear.
 */
function adminBarOffset() {
    const bar = document.getElementById('wpadminbar');

    if (!bar || window.getComputedStyle(bar).position !== 'fixed') {
        return 0;
    }

    return bar.offsetHeight;
}

/*
 * A notification is positioned from the top of the viewport, so the 19px offset that
 * around sixty call sites pass put the first one behind that bar. The offset is added
 * here rather than at the call sites: none of them should have to know what wp-admin is
 * doing above the app, and there are sixty of them to keep in step if they did.
 */
function withAdminBarOffset(options) {
    const settings = typeof options === 'string' ? {message: options} : {...options};

    settings.offset = (settings.offset || 0) + adminBarOffset();

    return settings;
}

const notify = (options = {}) => ElNotification(withAdminBarOffset(options));

// Every call site goes through $notify.success() or $notify.error(), so the type
// helpers are the ones that actually have to carry the offset.
notificationTypes.forEach((type) => {
    notify[type] = (options = {}) => ElNotification[type](withAdminBarOffset(options));
});

/*
 * Element Plus does not put these on the instance the way Element UI did, but
 * roughly sixty call sites reach for `this.$notify` and `this.$confirm`. Putting
 * them back as global properties is what lets the components stay untouched.
 */
app.config.globalProperties.$notify = notify;
app.config.globalProperties.$confirm = ElMessageBox.confirm;

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
