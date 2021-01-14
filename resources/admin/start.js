import routes from './routes';
const vueRouter = new window.FluentMail.Router({
    routes: window.FluentMail.applyFilters('fluent_mail_global_routes', routes)
});

window.FluentMail.Vue.prototype.$rest = window.FluentMail.$rest;
window.FluentMail.Vue.prototype.$get = window.FluentMail.$get;
window.FluentMail.Vue.prototype.$post = window.FluentMail.$post;
window.FluentMail.Vue.prototype.$bus = new window.FluentMail.Vue();

new window.FluentMail.Vue({
    el: '#fluent_mail_app',
    render: h => h(require('./Application').default),
    router: vueRouter
});
