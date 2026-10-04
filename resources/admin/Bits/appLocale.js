import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';

/*
 * The same three plugins FluentMail.js extends its copy with.
 *
 * This bundle's dayjs is the one the app actually formats with - the chart and
 * the date helpers both end up on it - so it needs the formats those use:
 * localizedFormat for the 'LT' in 'DD MMM YYYY LT', and utc/timezone for
 * $siteNow(). Extending is idempotent, so a plugin Element Plus has already
 * added is not added twice.
 */
dayjs.extend(localizedFormat);
dayjs.extend(utc);
dayjs.extend(timezone);

/*
 * Element Plus ships one complete locale object per language, and its translator
 * looks a key up in that object with no fallback to English - a partial object
 * would render the missing keys as their dotted paths. So the whole module is
 * imported, not a hand-written subset.
 *
 * Only the date-bearing parts of these objects are used by this app today (the
 * pagination totals and the date picker panels), but the rest comes along with
 * the module and costs nothing extra to keep.
 */
import ar from 'element-plus/es/locale/lang/ar';
import cs from 'element-plus/es/locale/lang/cs';
import da from 'element-plus/es/locale/lang/da';
import de from 'element-plus/es/locale/lang/de';
import es from 'element-plus/es/locale/lang/es';
import fi from 'element-plus/es/locale/lang/fi';
import fr from 'element-plus/es/locale/lang/fr';
import he from 'element-plus/es/locale/lang/he';
import hi from 'element-plus/es/locale/lang/hi';
import id from 'element-plus/es/locale/lang/id';
import it from 'element-plus/es/locale/lang/it';
import ja from 'element-plus/es/locale/lang/ja';
import ko from 'element-plus/es/locale/lang/ko';
import nbNo from 'element-plus/es/locale/lang/nb-no';
import nl from 'element-plus/es/locale/lang/nl';
import pl from 'element-plus/es/locale/lang/pl';
import pt from 'element-plus/es/locale/lang/pt';
import ptBr from 'element-plus/es/locale/lang/pt-br';
import ro from 'element-plus/es/locale/lang/ro';
import ru from 'element-plus/es/locale/lang/ru';
import sv from 'element-plus/es/locale/lang/sv';
import th from 'element-plus/es/locale/lang/th';
import tr from 'element-plus/es/locale/lang/tr';
import uk from 'element-plus/es/locale/lang/uk';
import vi from 'element-plus/es/locale/lang/vi';
import zhCn from 'element-plus/es/locale/lang/zh-cn';
import zhTw from 'element-plus/es/locale/lang/zh-tw';

/*
 * The month names, weekday names and first-day-of-week that formatting consults.
 *
 * Each module is a plain data object; the map holds the objects themselves rather
 * than names, because there are two dayjs instances to set (see applyLocale) and
 * a name only reaches instances that have already registered that data.
 */
import dayjsAr from 'dayjs/locale/ar';
import dayjsCs from 'dayjs/locale/cs';
import dayjsDa from 'dayjs/locale/da';
import dayjsDe from 'dayjs/locale/de';
import dayjsEs from 'dayjs/locale/es';
import dayjsFi from 'dayjs/locale/fi';
import dayjsFr from 'dayjs/locale/fr';
import dayjsHe from 'dayjs/locale/he';
import dayjsHi from 'dayjs/locale/hi';
import dayjsId from 'dayjs/locale/id';
import dayjsIt from 'dayjs/locale/it';
import dayjsJa from 'dayjs/locale/ja';
import dayjsKo from 'dayjs/locale/ko';
import dayjsNb from 'dayjs/locale/nb';
import dayjsNl from 'dayjs/locale/nl';
import dayjsPl from 'dayjs/locale/pl';
import dayjsPt from 'dayjs/locale/pt';
import dayjsPtBr from 'dayjs/locale/pt-br';
import dayjsRo from 'dayjs/locale/ro';
import dayjsRu from 'dayjs/locale/ru';
import dayjsSv from 'dayjs/locale/sv';
import dayjsTh from 'dayjs/locale/th';
import dayjsTr from 'dayjs/locale/tr';
import dayjsUk from 'dayjs/locale/uk';
import dayjsVi from 'dayjs/locale/vi';
import dayjsZhCn from 'dayjs/locale/zh-cn';
import dayjsZhTw from 'dayjs/locale/zh-tw';

/**
 * Which languages the admin app can render dates and its date pickers in.
 *
 * The app is one un-split bundle, so a locale cannot be fetched when the site
 * turns out to need it - whatever ships is what is available, for every visitor,
 * whichever language they read the admin in. Rather than the 143 locales dayjs
 * carries, this ships the languages WordPress sites actually run in: the outcome
 * of that trade is that a site in a language outside this list gets the same
 * English dates it gets today, not a broken screen.
 *
 * Adding a language is three lines: the Element Plus import, the dayjs import,
 * and the key here. Both packages name locales the same way except Norwegian,
 * where dayjs says 'nb' and Element Plus says 'nb-no'.
 */
const LOCALES = {
    ar: { el: ar, dayjs: dayjsAr },
    cs: { el: cs, dayjs: dayjsCs },
    da: { el: da, dayjs: dayjsDa },
    de: { el: de, dayjs: dayjsDe },
    es: { el: es, dayjs: dayjsEs },
    fi: { el: fi, dayjs: dayjsFi },
    fr: { el: fr, dayjs: dayjsFr },
    he: { el: he, dayjs: dayjsHe },
    hi: { el: hi, dayjs: dayjsHi },
    id: { el: id, dayjs: dayjsId },
    it: { el: it, dayjs: dayjsIt },
    ja: { el: ja, dayjs: dayjsJa },
    ko: { el: ko, dayjs: dayjsKo },
    nb: { el: nbNo, dayjs: dayjsNb },
    nl: { el: nl, dayjs: dayjsNl },
    pl: { el: pl, dayjs: dayjsPl },
    pt: { el: pt, dayjs: dayjsPt },
    'pt-br': { el: ptBr, dayjs: dayjsPtBr },
    ro: { el: ro, dayjs: dayjsRo },
    ru: { el: ru, dayjs: dayjsRu },
    sv: { el: sv, dayjs: dayjsSv },
    th: { el: th, dayjs: dayjsTh },
    tr: { el: tr, dayjs: dayjsTr },
    uk: { el: uk, dayjs: dayjsUk },
    vi: { el: vi, dayjs: dayjsVi },
    'zh-cn': { el: zhCn, dayjs: dayjsZhCn },
    'zh-tw': { el: zhTw, dayjs: dayjsZhTw }
};

/*
 * Languages that are not shipped but whose neighbour is close enough to read.
 *
 * WordPress separates a few of these where the two libraries do not: Nynorsk has
 * its own wp_locale but no Element Plus translation, and Hong Kong and Macau read
 * Traditional Chinese. Falling back to English for those would be a bigger jump
 * than reusing the sibling that is almost the same language. Simplified and
 * Traditional Chinese are also written with a script suffix in some installs, and
 * a script is unambiguous where a region is not.
 */
const ALIASES = {
    nn: 'nb',
    no: 'nb',
    'pt-pt': 'pt',
    'zh-hans': 'zh-cn',
    'zh-hant': 'zh-tw',
    'zh-hk': 'zh-tw',
    'zh-mo': 'zh-tw'
};

/**
 * The shipped locale that matches a WordPress locale, or null for English.
 *
 * wp_locale is a language, a region and sometimes a formality - 'de_DE',
 * 'de_DE_formal', 'pt_BR', 'zh_Hans' - while these libraries key on the language
 * alone. So the match is tried from the most specific name down, dropping one
 * part at a time: de-de-formal, then de-de, then de. Nothing matches only when
 * the language is not shipped at all, which includes every English variant and is
 * why null means "leave the app as it is" rather than an error.
 */
export function resolveLocale(wpLocale) {
    if (!wpLocale || typeof wpLocale !== 'string') {
        return null;
    }

    let candidate = wpLocale.trim().toLowerCase().replace(/_/g, '-');

    while (candidate) {
        const key = ALIASES[candidate] || candidate;

        if (LOCALES[key]) {
            return key;
        }

        const cut = candidate.lastIndexOf('-');

        if (cut === -1) {
            break;
        }

        candidate = candidate.slice(0, cut);
    }

    return null;
}

/*
 * Every dayjs instance the admin app formats with.
 *
 * The build produces two sealed bundles, each with its own copy of dayjs: the
 * boot bundle, which owns the class whose date helpers the components call
 * through `$dateFormat`, and this app bundle. Setting the locale on this one
 * alone would leave every `DD MMM YYYY` in the log list and the activity feed
 * English. window.FluentMail exposes the boot copy - see Bits/FluentMail.js -
 * and both are set from here so the two cannot drift apart. The guard covers the
 * boot bundle not having run yet, which is the case in a unit test.
 */
function eachDayjs(callback) {
    callback(dayjs);

    // `window` is absent when this module is imported outside a browser, which is
    // how the unit tests load it.
    const boot = typeof window !== 'undefined' && window.FluentMail;
    const bootDayjs = boot && boot.dayjs;

    if (bootDayjs && bootDayjs !== dayjs) {
        callback(bootDayjs);
    }
}

/**
 * Puts the app into the admin's language and hands Element Plus its locale.
 *
 * Every instance is set on every call - to the matched language, or back to
 * English when nothing matched. Leaving one alone for the no-match case would
 * make this depend on call order: switching away from a language and back would
 * keep the first one's month names.
 *
 * The return value is what provideGlobalConfig() takes. Element Plus already
 * defaults to English, and its own translator falls back to the English locale
 * when the one it holds is null, so null is the honest answer for English and
 * for a language that is not shipped - handing it a locale it was not given
 * would be worse than letting it default.
 */
export function applyLocale(wpLocale) {
    const key = resolveLocale(wpLocale);

    if (!key) {
        eachDayjs((instance) => instance.locale('en'));

        return null;
    }

    eachDayjs((instance) => instance.locale(LOCALES[key].dayjs));

    return LOCALES[key].el;
}
