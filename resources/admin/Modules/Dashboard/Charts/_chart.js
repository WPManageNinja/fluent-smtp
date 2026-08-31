import { h } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Filler,
    Legend,
    Tooltip
} from 'chart.js';

/*
 * Chart.js is a bundle import now, not a global.
 *
 * It used to be vendored at resources/libs/chartjs/ and enqueued by
 * AdminMenuHandler as two separate <script> tags publishing window.VueChartJs -
 * and the vendored copy was Chart.js 2.7.1, not the ^3.4.1 package.json
 * declared. The v2 options API this file was written against (scales.yAxes[]
 * arrays, gridLines, ticks.userCallback, the reactiveProp mixin,
 * this.renderChart) does not exist in v4, so this is a rewrite rather than a
 * port. See §6.6 of the redesign plan.
 *
 * v4 ships nothing by default; every controller, element and scale the chart
 * uses has to be registered. Filler is the non-obvious one - it is what draws
 * the wash under the cumulative line.
 */
Chart.register(
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Filler,
    Legend,
    Tooltip
);

// A count of emails is a whole number; hide the fractional ticks Chart.js
// interpolates between them. This was `ticks.userCallback` in v2.
const wholeNumbersOnly = (value) => (Math.floor(value) === value ? value : undefined);

/*
 * Chart.js paints to a canvas, so it cannot read a CSS variable the way the rest of
 * the app does - it needs values. Reading them off the live app root is what keeps the
 * chart's grid and labels on the same palette as everything around it without either
 * side holding a second copy of the colours.
 */
function themeColours() {
    const root = document.getElementById('fluent_mail_app');
    const styles = root ? getComputedStyle(root) : null;
    const read = (name, fallback) =>
        (styles ? styles.getPropertyValue(name).trim() : '') || fallback;

    return {
        grid: read('--fsm-border', '#EAECF0'),
        tick: read('--fsm-text-light', '#9D9FAC')
    };
}

export default {
    name: 'GrowthChart',
    props: {
        chartData: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            colours: themeColours()
        };
    },
    computed: {
        options() {
            const {grid, tick} = this.colours;

            return {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { left: 0, right: 0, top: 0, bottom: 20 }
                },
                plugins: {
                    legend: { labels: { color: tick } }
                },
                scales: {
                    // Keyed by axis id in v4, where v2 took an array of axes.
                    byDate: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        border: { color: grid },
                        grid: { drawOnChartArea: false, color: grid },
                        ticks: { callback: wholeNumbersOnly, color: tick }
                    },
                    byCumulative: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        border: { color: grid },
                        grid: { drawOnChartArea: true, color: grid },
                        ticks: { callback: wholeNumbersOnly, color: tick }
                    },
                    x: {
                        border: { color: grid },
                        grid: { drawOnChartArea: false, color: grid },
                        ticks: { autoSkip: true, maxTicksLimit: 10, color: tick }
                    }
                }
            };
        }
    },
    methods: {
        // ThemeSwitch fires this whenever it applies a theme, including when the change
        // arrived from another tab. Re-reading the root is enough: `options` is computed
        // from `colours`, and vue-chartjs redraws when its options prop changes.
        onThemeChange() {
            this.colours = themeColours();
        }
    },
    mounted() {
        window.addEventListener('fluent_theme_applied', this.onThemeChange);
    },
    beforeUnmount() {
        window.removeEventListener('fluent_theme_applied', this.onThemeChange);
    },
    render() {
        /*
         * vue-chartjs 5 has no reactiveProp mixin and no renderChart(): the
         * chart is a component and its data is a bound prop, so reactivity is
         * just Vue's.
         */
        return h(Bar, {
            data: this.chartData,
            options: this.options
        });
    }
};
