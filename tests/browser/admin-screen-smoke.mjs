/**
 * Browser smoke for FluentSMTP's Vue admin routes.
 *
 * The caller supplies an authenticated browser-client Tab and must install a
 * fail-closed outbound HTTP seam. This module deliberately has no Playwright
 * dependency: it runs through the browser-control surface used by the suite.
 */

/*
 * Markers are substrings of the shell's rendered innerText, so they have to be
 * strings the screen actually prints - not route titles, not class names.
 *
 * The dashboard has two branches. With no connection configured it renders the
 * setup wizard, and none of these markers appear; the site under test must have
 * at least one connection saved. That is also true of the connections list, so
 * the precondition is the suite's, not this screen's alone.
 *
 * Keep every marker anchored to an unconditional element. `Sending Stats` and
 * `Welcome to` both sit directly in the configured branch rather than behind the
 * `!loading` guard the stat tiles use, so neither races the dashboard fetch.
 */
export const adminScreens = [
    { name: 'dashboard', hash: '/', markers: ['Sending Stats', 'Welcome to'] },
    { name: 'connections', hash: '/connections', markers: ['Active Email Connections', 'General Settings'] },
    { name: 'new connection', hash: '/connection', markers: ['Add Connection', 'Connection Provider'] },
    { name: 'email test', hash: '/test', markers: ['Send Test Email', 'Send To'] },
    { name: 'email logs', hash: '/logs', markers: ['Email Logs', 'Filter'] },
    { name: 'alerts', hash: '/notification-settings', markers: ['Summary Email', 'Email Sending Error Notifications'] },
    { name: 'about', hash: '/support', markers: ['About', 'Contributors'] },
    { name: 'documentation', hash: '/documentation', markers: ['How can we help you?', 'Please view the documentation first.'] },
];

export async function runAdminScreenSmoke(tab, baseUrl) {
    const results = [];
    const adminUrl = baseUrl.replace(/\/$/, '') + '/wp-admin/options-general.php?page=fluent-mail#';

    for (const screen of adminScreens) {
        await tab.goto(adminUrl + screen.hash);
        await tab.playwright.waitForLoadState({ state: 'domcontentloaded', timeoutMs: 10000 });

        const app = tab.playwright.locator('.fluent-mail-app');
        const appCount = await app.count();
        const visible = appCount === 1 && await app.isVisible();
        const content = visible ? await app.innerText({ timeoutMs: 5000 }) : '';
        const missing = screen.markers.filter((marker) => !content.includes(marker));

        results.push({
            name: screen.name,
            hash: screen.hash,
            passed: appCount === 1 && visible && missing.length === 0,
            appCount,
            visible,
            missing,
        });
    }

    return results;
}
