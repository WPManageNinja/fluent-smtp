/**
 * Three authenticated browser flows for the FluentSMTP admin application.
 *
 * The caller supplies a browser-client Tab. The temporary MU-plugin in
 * fixtures/fsmtp-e2e-safety.php must be installed before this runner is used.
 */

const safetyAttributes = [
    'simulator',
    'log-fuse',
    'http-fuse',
    'settings-fuse',
];

function invariant(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

async function requireUnique(locator, label) {
    const count = await locator.count();
    invariant(count === 1, `${label}: expected one control, found ${count}`);
    return locator;
}

async function openAdminRoute(tab, adminUrl, hash, marker) {
    await tab.goto(adminUrl + hash);
    await tab.playwright.waitForLoadState({ state: 'domcontentloaded', timeoutMs: 10000 });

    const app = await requireUnique(tab.playwright.locator('.fluent-mail-app'), 'FluentSMTP app');
    const content = await app.innerText({ timeoutMs: 10000 });
    invariant(content.includes(marker), `${hash}: missing route marker "${marker}"`);
}

async function assertSafety(tab, flow) {
    const state = await tab.playwright.evaluate(() => {
        const marker = document.querySelector('#fsmtp-e2e-safety');
        if (!marker) {
            return null;
        }

        return {
            simulator: marker.getAttribute('data-simulator'),
            'log-fuse': marker.getAttribute('data-log-fuse'),
            'http-fuse': marker.getAttribute('data-http-fuse'),
            'settings-fuse': marker.getAttribute('data-settings-fuse'),
        };
    });

    invariant(state, `${flow}: E2E safety marker is absent`);
    for (const attribute of safetyAttributes) {
        invariant(
            state[attribute] === 'active',
            `${flow}: ${attribute} is not active`
        );
    }
}

async function dashboardRangeFlow(tab, adminUrl) {
    const name = 'dashboard last-week report';
    await openAdminRoute(tab, adminUrl, '/', 'Sending Stats');
    await assertSafety(tab, name);

    const start = await requireUnique(
        tab.playwright.getByRole('textbox', { name: 'Start date' }),
        `${name} start date`
    );
    await start.click();

    const shortcut = await requireUnique(
        tab.playwright.getByRole('button', { name: 'Last week', exact: true }),
        `${name} shortcut`
    );
    await shortcut.click();

    const range = await tab.playwright.evaluate(() => {
        const startInput = document.querySelector('.dashboard input[placeholder="Start date"]');
        const endInput = document.querySelector('.dashboard input[placeholder="End date"]');
        return {
            start: startInput ? startInput.value : '',
            end: endInput ? endInput.value : '',
        };
    });
    invariant(range.start && range.end, `${name}: shortcut did not populate both dates`);
    invariant(range.start !== range.end, `${name}: shortcut did not create a date range`);

    const apply = await requireUnique(
        tab.playwright.getByRole('button', { name: 'Apply', exact: true }),
        `${name} apply`
    );
    await apply.click();

    const chart = await requireUnique(
        tab.playwright.locator('.dashboard .fss_chart_box'),
        `${name} chart`
    );
    await chart.waitFor({ state: 'visible', timeoutMs: 10000 });
    await assertSafety(tab, name);
}

async function logsSearchFlow(tab, adminUrl) {
    const name = 'email logs no-match search';
    await openAdminRoute(tab, adminUrl, '/logs', 'Email Logs');
    await assertSafety(tab, name);

    const searchTerm = `fsmtp-e2e-no-match-${Date.now()}`;
    const search = await requireUnique(
        tab.playwright.getByRole('textbox', { name: 'Type & press enter...' }),
        `${name} input`
    );
    await search.fill(searchTerm);
    await search.press('Enter');

    const emptyState = tab.playwright.getByText('No Data', { exact: true });
    await emptyState.waitFor({ state: 'visible', timeoutMs: 10000 });

    const result = await tab.playwright.evaluate(() => ({
        hash: window.location.hash,
        rows: document.querySelectorAll('.logs .el-table__body-wrapper tbody tr').length,
    }));
    invariant(result.hash.includes(`search=${searchTerm}`), `${name}: URL did not preserve the search`);
    invariant(result.rows === 0, `${name}: impossible search returned ${result.rows} rows`);
    await assertSafety(tab, name);
}

async function simulatorEmailFlow(tab, adminUrl) {
    const name = 'simulator-backed test email';
    await openAdminRoute(tab, adminUrl, '/test', 'Send Test Email');
    await assertSafety(tab, name);

    const recipient = await requireUnique(
        tab.playwright.locator('input[name="fluentsmtp_test_to"]'),
        `${name} recipient`
    );
    await recipient.fill('fsmtp-e2e-recipient@example.test');

    const send = await requireUnique(
        tab.playwright.locator('.test_form button.el-button--primary'),
        `${name} send`
    );
    await assertSafety(tab, `${name} pre-send`);
    await send.click();

    const success = tab.playwright.locator('.success_wrapper h3');
    try {
        await success.waitFor({ state: 'visible', timeoutMs: 10000 });
    } catch (error) {
        throw new Error(`${name}: success confirmation did not render`);
    }
    invariant(
        (await success.innerText()).trim() === 'Test Email Has been successfully sent',
        `${name}: success confirmation did not render`
    );
    await assertSafety(tab, name);
}

export const browserFlows = {
    dashboard: dashboardRangeFlow,
    logs: logsSearchFlow,
    email: simulatorEmailFlow,
};

export async function runAdminE2E(tab, baseUrl, requestedFlows = Object.keys(browserFlows)) {
    // The query nonce forces one server navigation even when the caller's tab
    // is already on this hash-based SPA, so a newly installed safety MU-plugin
    // cannot be hidden by an in-memory admin document.
    const adminUrl = baseUrl.replace(/\/$/, '')
        + `/wp-admin/options-general.php?page=fluent-mail&fsmtp_e2e=${Date.now()}#`;
    const results = [];

    for (const key of requestedFlows) {
        const flow = browserFlows[key];
        if (!flow) {
            results.push({ name: key, passed: false, error: 'unknown flow' });
            continue;
        }

        try {
            await flow(tab, adminUrl);
            results.push({ name: key, passed: true });
        } catch (error) {
            results.push({
                name: key,
                passed: false,
                error: error instanceof Error ? error.message : String(error),
            });
        }
    }

    return results;
}
