import { afterEach, describe, expect, it } from 'vitest';
import { createRequire } from 'node:module';
import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';

import { applyLocale, resolveLocale } from '../../resources/admin/Bits/appLocale';

/*
 * The app formats its dates with dayjs and its date pickers with Element Plus, and
 * both default to English. These cover the bridge from the WordPress locale PHP hands
 * the page to the one locale each library understands: the matching itself, the
 * languages whose names do not line up between the two, and that switching dayjs
 * actually changes the format string output.
 *
 * localisedFormat is what makes 'DD MMM YYYY' read the locale at all. FluentMail.js
 * extends dayjs with it when the admin app boots; this file does the same so the
 * assertions here run against the same dayjs the app uses.
 */
dayjs.extend(localizedFormat);

/*
 * A second, independent dayjs, the way the boot bundle has one. Clearing the require
 * cache before re-requiring is what makes it a fresh copy; vitest's own module
 * registry returns the same instance to both importers.
 *
 * The localizedFormat module is dropped from the cache too. dayjs.extend() marks the
 * plugin object it was handed and skips a second registration, so a cached plugin
 * module would leave the fresh copy without the 'LT' handling while looking
 * installed. This is what the boot copy is actually extended with in FluentMail.js.
 */
function freshDayjs() {
    const require = createRequire(import.meta.url);

    delete require.cache[require.resolve('dayjs')];
    delete require.cache[require.resolve('dayjs/plugin/localizedFormat')];

    const copy = require('dayjs');

    copy.extend(require('dayjs/plugin/localizedFormat'));

    return copy.default || copy;
}

afterEach(() => {
    delete global.window;
    applyLocale('en_US');
});

describe('admin app locale bridge', () => {
    describe('resolveLocale', () => {
        it('maps a WordPress locale to the shipped language', () => {
            expect(resolveLocale('de_DE')).toBe('de');
            expect(resolveLocale('fr_FR')).toBe('fr');
            expect(resolveLocale('ru_RU')).toBe('ru');
        });

        it('drops a region the bundle does not carry', () => {
            expect(resolveLocale('de_CH')).toBe('de');
            expect(resolveLocale('es_AR')).toBe('es');
        });

        it('drops a formality suffix before the language', () => {
            expect(resolveLocale('de_DE_formal')).toBe('de');
        });

        it('keeps a regional language over its parent', () => {
            expect(resolveLocale('pt_BR')).toBe('pt-br');
            expect(resolveLocale('pt_PT')).toBe('pt');
        });

        it('maps locales onto the sibling the libraries ship', () => {
            // dayjs calls Norwegian 'nb' and Element Plus 'nb-no'.
            expect(resolveLocale('nb_NO')).toBe('nb');
            expect(resolveLocale('nn_NO')).toBe('nb');
            // Hong Kong and Macau read Traditional Chinese.
            expect(resolveLocale('zh_HK')).toBe('zh-tw');
        });

        it('handles the Chinese script-suffixed locales', () => {
            expect(resolveLocale('zh_CN')).toBe('zh-cn');
            expect(resolveLocale('zh_TW')).toBe('zh-tw');
            // Some installs name the script instead of the region.
            expect(resolveLocale('zh_Hans')).toBe('zh-cn');
            expect(resolveLocale('zh_Hant')).toBe('zh-tw');
        });

        it('returns null for English and for a language the bundle does not carry', () => {
            expect(resolveLocale('en_US')).toBeNull();
            expect(resolveLocale('en_GB')).toBeNull();
            expect(resolveLocale('sw_KE')).toBeNull();
            expect(resolveLocale('')).toBeNull();
            expect(resolveLocale(null)).toBeNull();
            expect(resolveLocale(undefined)).toBeNull();
        });
    });

    describe('applyLocale', () => {
        it('switches dayjs into the matched language', () => {
            applyLocale('de_DE');

            expect(dayjs.locale()).toBe('de');
            // German puts the day before the month, so the middle of a date is not
            // the English short month name.
            expect(dayjs('2026-03-04').format('DD MMM YYYY')).toBe('04 März 2026');

            applyLocale('en_US');
        });

        it('returns the Element Plus locale object for the matched language', () => {
            const locale = applyLocale('de_DE');

            expect(locale).toBeTruthy();
            expect(locale.name).toBe('de');
            expect(locale.el.pagination.total).toBeTruthy();

            applyLocale('en_US');
        });

        it('returns null for English, leaving the app as it was', () => {
            expect(applyLocale('en_US')).toBeNull();
            expect(applyLocale('sw_KE')).toBeNull();
        });

        it('leaves dayjs in English when nothing matches', () => {
            applyLocale('de_DE');
            applyLocale('en_US');

            expect(dayjs.locale()).toBe('en');
        });

        /*
         * The build is two sealed bundles and each carries its own dayjs: the date
         * helpers the components call through `$dateFormat` live on the boot bundle's
         * copy, so setting the locale on this bundle's copy alone would leave every
         * log timestamp English. window.FluentMail exposes the boot copy, and these
         * cover that applyLocale reaches it.
         *
         * A second copy is loaded through a cleared require cache rather than
         * vi.resetModules(), which vitest resolves back to the same instance.
         */
        it('sets the language on the boot bundle copy of dayjs too', () => {
            const bootDayjs = freshDayjs();
            expect(bootDayjs).not.toBe(dayjs);
            global.window = { FluentMail: { dayjs: bootDayjs } };

            applyLocale('de_DE');

            expect(dayjs.locale()).toBe('de');
            expect(bootDayjs.locale()).toBe('de');
            expect(bootDayjs('2026-03-04').format('DD MMM YYYY')).toBe('04 März 2026');
            // 'LT' is the format the log timestamps use, and it only renders through
            // the localizedFormat plugin the boot copy is extended with.
            expect(bootDayjs('2026-03-04 14:30:00').format('DD MMM YYYY LT')).toBe('04 März 2026 14:30');

            applyLocale('en_US');

            expect(dayjs.locale()).toBe('en');
            expect(bootDayjs.locale()).toBe('en');
        });

        it('works when the boot bundle has not run yet', () => {
            // No window at all, which is how the suite loads this module.
            expect(() => applyLocale('de_DE')).not.toThrow();
            expect(dayjs.locale()).toBe('de');
        });
    });
});
