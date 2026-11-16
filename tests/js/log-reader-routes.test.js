import { afterEach, describe, expect, it, vi } from 'vitest';
import FluentMail from '../../resources/admin/Bits/FluentMail';

const originalWindow = globalThis.window;
afterEach(() => { globalThis.window = originalWindow; });

describe('app extension isolation', () => {
    const core = [{ name: 'logs' }, { name: 'connections' }];
    it('readers never execute manager-assuming route filters', () => {
        globalThis.window = { FluentMailAdmin: { permissions: { manage: false } } };
        const app = new FluentMail();
        app.applyFilters = vi.fn(() => { throw new Error('Manager extension must not run'); });
        expect(app.getApplicationRoutes(core)).toBe(core);
        expect(app.applyFilters).not.toHaveBeenCalled();
    });
    it('managers retain the established extension route contract', () => {
        globalThis.window = { FluentMailAdmin: { permissions: { manage: true } } };
        const app = new FluentMail();
        const extended = [...core, { name: 'manager-addon' }];
        app.applyFilters = vi.fn(() => extended);
        expect(app.getApplicationRoutes(core)).toBe(extended);
        expect(app.applyFilters).toHaveBeenCalledWith('fluent_mail_global_routes', core);
    });
    it('older manager bootstraps retain their route extensions', () => {
        globalThis.window = { FluentMailAdmin: {} };
        const app = new FluentMail();
        app.applyFilters = vi.fn(() => core);
        expect(app.getApplicationRoutes(core)).toBe(core);
        expect(app.applyFilters).toHaveBeenCalledTimes(1);
    });
});
