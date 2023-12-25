import Vue from 'vue';
import lang from 'element-ui/lib/locale/lang/en';
import locale from 'element-ui/lib/locale';

import {
    Tag,
    Row,
    Col,
    Menu,
    Form,
    Alert,
    Table,
    Input,
    Option,
    Radio,
    Button,
    Select,
    Dialog,
    Popover,
    Loading,
    Tooltip,
    MenuItem,
    Checkbox,
    FormItem,
    Pagination,
    DatePicker,
    TimePicker,
    RadioGroup,
    MessageBox,
    OptionGroup,
    ButtonGroup,
    TableColumn,
    Notification,
    CheckboxGroup,
    RadioButton,
    Switch,
    Collapse,
    CollapseItem,
    Skeleton,
    SkeletonItem
} from 'element-ui';

Vue.use(Row);
Vue.use(Tag);
Vue.use(Menu);
Vue.use(Skeleton);
Vue.use(SkeletonItem);
Vue.use(Menu);
Vue.use(MenuItem);
Vue.use(Col);
Vue.use(Collapse);
Vue.use(CollapseItem);
Vue.use(Form);
Vue.use(Alert);
Vue.use(Table);
Vue.use(Input);
Vue.use(Radio);
Vue.use(RadioButton);
Vue.use(Button);
Vue.use(Select);
Vue.use(Switch);
Vue.use(Option);
Vue.use(Dialog);
Vue.use(Popover);
Vue.use(Tooltip);
Vue.use(Checkbox);
Vue.use(FormItem);
Vue.use(Pagination);
Vue.use(DatePicker);
Vue.use(TimePicker);
Vue.use(RadioGroup);
Vue.use(OptionGroup);
Vue.use(ButtonGroup);
Vue.use(TableColumn);
Vue.use(CheckboxGroup);
Vue.use(Loading.directive);

Vue.prototype.$message = MessageBox.alert;
Vue.prototype.$notify = Notification;
Vue.prototype.$confirm = MessageBox.confirm;

locale.use(lang);

import Router from 'vue-router';
import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';

import {
    applyFilters,
    addFilter,
    addAction,
    doAction,
    removeAllActions
} from '@wordpress/hooks';

export default class FluentMail {
    constructor() {
        this.Router = Router;
        this.doAction = doAction;
        this.addFilter = addFilter;
        this.addAction = addAction;
        this.applyFilters = applyFilters;
        this.removeAllActions = removeAllActions;
        this.appVars = window.FluentMailAdmin;
        this.Vue = this.extendVueConstructor();
    }

    extendVueConstructor() {
        const self = this;

        Vue.mixin({
            data() {
                return {
                    appVars: self.appVars,
                    settings: self.appVars.settings
                }
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
                dayjs: dayjs.extend(localizedFormat),
                escapeHtml: self.escapeHtml,
                hasPro: () => Boolean(window.FluentMail.appVars.has_pro),
                $t(string) {
                    return window.FluentMailAdmin.trans[string] || string;
                }
            }
        });

        Vue.filter('dateFormat', self.dateFormat);
        Vue.filter('ucFirst', self.ucFirst);
        Vue.filter('ucWords', self.ucWords);

        Vue.use(this.Router);

        return Vue;
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
