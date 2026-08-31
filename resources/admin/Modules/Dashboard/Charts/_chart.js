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

export default {
    name: 'GrowthChart',
    props: {
        chartData: {
            type: Object,
            required: true
        }
    },
    computed: {
        options() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { left: 0, right: 0, top: 0, bottom: 20 }
                },
                scales: {
                    // Keyed by axis id in v4, where v2 took an array of axes.
                    byDate: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        ticks: { callback: wholeNumbersOnly }
                    },
                    byCumulative: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: true },
                        ticks: { callback: wholeNumbersOnly }
                    },
                    x: {
                        grid: { drawOnChartArea: false },
                        ticks: { autoSkip: true, maxTicksLimit: 10 }
                    }
                }
            };
        }
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
