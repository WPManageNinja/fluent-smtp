import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';

import {
    applyFilters,
    addFilter,
    addAction,
    doAction,
    removeAllActions
} from '@wordpress/hooks';

dayjs.extend(localizedFormat);

/**
 * The `window.FluentMail` global.
 *
 * This is a public extension surface on a plugin with hundreds of thousands of
 * installs, so everything on it that can be carried across is carried across:
 * the hook functions, registerBlock(), registerTopMenu(), the request helpers,
 * appVars and the string utilities all behave exactly as they did.
 *
 * The one thing that cannot survive is `.Vue`. It used to be the Vue 2
 * constructor, mutated in place with Vue.use() x38, Vue.mixin(), Vue.filter()
 * and Vue.prototype assignments - none of which exist in Vue 3, where an app is
 * an object created by createApp() rather than a constructor to extend. There is
 * no shim for that: a Vue 2 constructor and a Vue 3 app factory are not
 * substitutable, and pretending otherwise would fail at the first `new`. `.Vue`
 * is now the Vue 3 module namespace ({ createApp, ref, computed, h, ... }), which
 * is the closest honest equivalent, and `.Router` follows it to vue-router's.
 *
 * Nothing in the WPManageNinja repository set reads either one. A third-party
 * add-on that does will break; that belongs in the changelog under a note for
 * developers, not behind a shim that hides it until runtime.
 *
 * Both are assigned by start.js rather than imported here, so that the Vue an
 * extension reaches through `.Vue` is the same Vue the app is running on.
 * Importing it in this file would put a second copy in boot.js - a duplicate
 * that is 150 kB of payload and, worse, a second reactivity system sitting on
 * the public API under the name of the first.
 */
export default class FluentMail {
    constructor() {
        this.doAction = doAction;
        this.addFilter = addFilter;
        this.addAction = addAction;
        this.applyFilters = applyFilters;
        this.removeAllActions = removeAllActions;
        this.appVars = window.FluentMailAdmin;
    }

    /**
     * The helpers every component reaches for through `this.`.
     *
     * Installed as a global mixin rather than moved into each component, which
     * is what keeps the Vue 3 migration from touching 59 script blocks: a
     * component calling `this.$t()` or `this.ucFirst()` reads the same before
     * and after.
     *
     * The three Vue 2 filters that used to sit beside these - dateFormat,
     * ucFirst, ucWords - are gone. Vue 3 removed filters, and a grep over every
     * template found no `|` filter expression using them, so there was nothing
     * to port. The functions themselves are still here as methods.
     */
    appMixin() {
        const self = this;

        return {
            data() {
                return {
                    appVars: self.appVars,
                    settings: self.appVars.settings
                };
            },
            methods: {
                addFilter,
                applyFilters,
                doAction,
                addAction,
                removeAllActions,
                $dateFormat: self.dateFormat,
                ucFirst: self.ucFirst,
                ucWords: self.ucWords,
                slugify: self.slugify,
                dayjs,
                escapeHtml: self.escapeHtml,
                hasPro: () => Boolean(window.FluentMail.appVars.has_pro),
                $t(string) {
                    return window.FluentMailAdmin.trans[string] || string;
                }
            }
        };
    }

    registerBlock(blockLocation, blockName, block) {
        this.addFilter(blockLocation, this.appVars.slug, function (components) {
            components[blockName] = block;
            return components;
        });
    }

    registerTopMenu(title, route) {
        if (!title || !route.name || !route.path || !route.component) {
            return;
        }

        this.addFilter('fluent_mail_top_menus', this.appVars.slug, function (menus) {
            menus = menus.filter(m => m.route !== route.name);
            menus.push({
                route: route.name,
                title: title
            });
            return menus;
        });

        this.addFilter('fluent_mail_global_routes', this.appVars.slug, function (routes) {
            routes = routes.filter(r => r.name !== route.name);
            routes.push(route);
            return routes;
        });
    }

    request(method, options) {
        return window.jQuery[method](window.ajaxurl, options);
    }

    $get(url, options = {}) {
        options.action = this.appVars.slug + '-get-' + url;
        options.nonce = this.appVars.nonce;
        return window.FluentMail.request('get', options);
    }

    $post(url, options = {}) {
        options.action = this.appVars.slug + '-post-' + url;

        options.nonce = this.appVars.nonce;

        return window.FluentMail.request('post', options);
    }

    dateFormat(date, format) {
        const dateString = (date === undefined) ? null : date;
        const dateObj = dayjs(dateString);
        return dateObj.isValid() ? dateObj.format(format) : null;
    }

    ucFirst(text) {
        return text[0].toUpperCase() + text.slice(1).toLowerCase();
    }

    ucWords(text) {
        return (text + '').replace(/^(.)|\s+(.)/g, function ($1) {
            return $1.toUpperCase();
        })
    }

    slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-') // Replace spaces with -
            .replace(/[^\w\\-]+/g, '') // Remove all non-word chars
            .replace(/\\-\\-+/g, '-') // Replace multiple - with single -
            .replace(/^-+/, '') // Trim - from start of text
            .replace(/-+$/, ''); // Trim - from end of text
    }

    escapeHtml(text) {
        if (!text) {
            return text;
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, (m) => map[m]);
    }
}
