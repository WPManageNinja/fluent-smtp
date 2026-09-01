import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';

import {
    applyFilters,
    addFilter,
    addAction,
    doAction,
    removeAllActions
} from '@wordpress/hooks';

dayjs.extend(localizedFormat);
dayjs.extend(utc);
dayjs.extend(timezone);

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
                /*
                 * Two call sites pass a value to substitute, one positionally against
                 * `%s` and one by name against `{title}`, and both used to render the
                 * placeholder to the user because the argument was silently dropped.
                 * Translators are already shipping strings that carry the placeholder,
                 * so the substitution belongs here rather than at the call sites.
                 */
                /*
                 * "Now" on the site's clock, not the browser's.
                 *
                 * Email logs are written and filtered in the site's timezone, so every
                 * date the app builds has to be in that timezone too. An administrator
                 * in Dhaka looking at a site set to New York was asking for a "Today"
                 * that had not started there yet, and getting an empty list for it.
                 *
                 * Derived from the site's timezone on every call rather than from a
                 * skew measured once at page load: a skew is a fixed number of
                 * milliseconds, so a tab open across a daylight-saving change on either
                 * side would have been an hour out until it was reloaded.
                 */
                $siteNow: self.siteNow,
                /*
                 * The site's calendar day, as a Date the picker will read back correctly.
                 *
                 * `$siteNow().toDate()` looks like it should do this and does not. A
                 * Date holds an instant, not a wall clock; Element Plus formats it with
                 * the *browser's* calendar fields. So at 02:30 on 2 September in Dhaka,
                 * a site set to New York is still on 1 September, `$siteNow()` correctly
                 * says the 1st - and `.toDate()` then hands the picker an instant that
                 * the browser renders as the 2nd. "Today" asked for a day the site had
                 * not reached, and came back empty.
                 *
                 * Rebuilding from the year/month/date components moves the site's wall
                 * clock onto the browser's, which is the frame the picker reads in.
                 * Midnight, because these feed a date-only range.
                 */
                $siteCalendarDate(daysAgo = 0) {
                    const day = this.$siteNow().subtract(daysAgo, 'day');

                    return new Date(day.year(), day.month(), day.date());
                },
                /*
                 * The one place that decides what a failed request says to the user.
                 *
                 * Nearly every failure handler in the app used to read
                 * `error.responseJSON.data.message` directly, which is only there when
                 * the server got far enough to send WordPress JSON. It very often does
                 * not: a plugin emitting a notice under WP_DEBUG prepends text to the
                 * body and breaks the parse, a shared host returns an HTML 502, a WAF
                 * returns its own page, a PHP fatal returns a stack trace. In all of
                 * those `responseJSON` is undefined, so the handler threw on its own
                 * first line - and because jQuery fires a Deferred's callbacks without
                 * a try/catch, and `.always()` sits after `.fail()` on the same list,
                 * the throw skipped the cleanup too. The spinner then stayed up until
                 * the page was reloaded.
                 *
                 * Returning a string rather than showing one: the call sites disagree
                 * about what to do with it - notify, record against a field, render an
                 * inline error - and only agree on what it says.
                 */
                $errorMessage(error, fallback = '') {
                    const payload = error && error.responseJSON && error.responseJSON.data;

                    if (payload && payload.message) {
                        return payload.message;
                    }

                    if (this.$isAuthError(error)) {
                        return this.$t('Security Failed. Please reload the page');
                    }

                    /*
                     * No usable body. Saying so beats a blank screen: the request may
                     * well have reached PHP and done its work, so "it failed" would be
                     * a guess. The one thing that is certainly true is that we cannot
                     * tell, and that reloading is how you find out.
                     */
                    return fallback || this.$t('__REQUEST_FAILED');
                },
                /*
                 * An expired nonce and a revoked capability both arrive as 403 from
                 * Controller::verify(). Neither is anything the user typed, so a screen
                 * that treats a failure as invalid input has to be able to tell them
                 * apart - see ConnectionWizard, which was reporting an expired session
                 * as "check your inputs" and sending people off to reset a working
                 * SMTP password.
                 */
                $isAuthError(error) {
                    return Boolean(error && error.status === 403);
                },
                $t(string, ...args) {
                    const translated = window.FluentMailAdmin.trans[string] || string;

                    if (!args.length) {
                        return translated;
                    }

                    const [first] = args;

                    if (first && typeof first === 'object') {
                        return translated.replace(
                            /{(\w+)}/g,
                            (match, key) => (key in first ? first[key] : match)
                        );
                    }

                    let index = 0;

                    return translated.replace(
                        /%s/g,
                        () => (index < args.length ? args[index++] : '%s')
                    );
                }
            }
        };
    }

    siteNow() {
        const zone = window.FluentMail.appVars && window.FluentMail.appVars.site_timezone;

        if (!zone) {
            return dayjs();
        }

        try {
            /*
             * wp_timezone_string() gives either a zone name or a fixed offset, and only
             * the first is a timezone as far as dayjs is concerned.
             */
            return /^[+-]\d{2}:\d{2}$/.test(zone)
                ? dayjs().utcOffset(zone)
                : dayjs().tz(zone);
        } catch (e) {
            // An unknown zone name would otherwise take every date on the screen with it.
            return dayjs();
        }
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
