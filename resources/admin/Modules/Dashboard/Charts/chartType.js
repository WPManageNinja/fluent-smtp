import { reactive } from 'vue';

/*
 * Whether the sending chart draws its daily counts as bars or as a line.
 *
 * The control that sets it sits in the card's header and the chart that reads it is
 * in the card's body, and the two are siblings under the dashboard rather than
 * parent and child - so the preference lives here rather than being threaded
 * through the dashboard, which has nothing else to say about it.
 *
 * It is a reading preference, not a setting: it says nothing about the site and
 * nothing about the account, so it stays in this browser rather than going to the
 * server as an option.
 */
const STORAGE_KEY = 'fluent_smtp_sending_chart_type';
const TYPES = ['bar', 'line'];

function stored() {
    try {
        const saved = window.localStorage.getItem(STORAGE_KEY);

        return TYPES.includes(saved) ? saved : 'line';
    } catch (e) {
        // Storage throws rather than returning null when the browser has it turned
        // off, and a chart that cannot be drawn is a worse answer than a default one.
        return 'line';
    }
}

const chartType = reactive({
    current: stored(),
    set(type) {
        if (!TYPES.includes(type) || type === chartType.current) {
            return;
        }

        chartType.current = type;

        try {
            window.localStorage.setItem(STORAGE_KEY, type);
        } catch (e) {
            // Remembering it is the part that is allowed to fail.
        }
    }
});

export default chartType;
