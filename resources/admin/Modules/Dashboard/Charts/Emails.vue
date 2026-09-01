<template>
    <div v-loading="fetching" class="fss_body fss_chart_box">
        <div class="fss_chart_canvas">
            <el-alert v-if="load_error" type="error" :closable="false" show-icon>
                <p>{{ load_error }}</p>
                <el-button size="small" @click="fetchReport()">{{ $t('Retry') }}</el-button>
            </el-alert>
            <growth-chart v-else-if="chartData" :chart-data="chartData"/>
        </div>
    </div>
</template>

<script type="text/babel">
    import GrowthChart from './_chart'
    import each from 'lodash/each';

    export default {
        name: 'email-sendings',
        props: ['date_range'],
        components: {
            GrowthChart
        },
        data() {
            return {
                fetching: false,
                load_error: '',
                stats: {},
                // null until the first report lands: Chart.js needs a data
                // object with datasets in it, not an empty one.
                chartData: null
            }
        },
        computed: {},
        methods: {
            fetchReport() {
                this.load_error = '';
                this.fetching = true;
                this.$get('sending_stats', {
                    date_range: this.date_range
                })
                    .then(res => {
                        this.stats = res.stats;
                        this.setupChartItems();
                    })
                    .fail(error => {
                        this.load_error = this.$errorMessage(error);
                    })
                    .always(() => {
                        this.fetching = false;
                    });
            },
            setupChartItems() {
                const labels = [];
                /*
                 * No colours here. They used to be hard-coded on each dataset, which
                 * meant a chart drawn in the light theme kept its light-theme blue after
                 * the theme went dark - and the blue itself measured 2.79:1 on the card
                 * it was drawn on. _chart.js puts them on by axis id instead, off the
                 * same tokens the grid and the labels come from, and re-reads them when
                 * the theme changes.
                 */
                const ItemValues = {
                    label: this.$t('By Date'),
                    yAxisID: 'byDate',
                    data: [],
                    fill: false
                };

                const cumulativeItems = {
                    label: this.$t('Cumulative'),
                    data: [],
                    yAxisID: 'byCumulative',
                    type: 'line',
                    // Chart.js 2 filled a line dataset by default and 4 does
                    // not, so the wash under this line has to be asked for.
                    fill: true
                };

                let currentTotal = 0;
                each(this.stats, (count, label) => {
                    ItemValues.data.push(count);
                    labels.push(label);
                    currentTotal += parseInt(count);
                    cumulativeItems.data.push(currentTotal);
                });
                this.chartData = {
                    labels: labels,
                    datasets: [ItemValues, cumulativeItems]
                }
            }
        },
        mounted() {
            this.fetchReport();
        }
    };
</script>

<style lang="scss">
    /*
     * Chart.js sizes its canvas to the parent when maintainAspectRatio is off,
     * so the parent has to have a height. vue-chartjs 2 carried a default
     * height prop of 400 on the canvas itself; vue-chartjs 5 does not, and
     * without this the chart collapses to nothing.
     */
    .fss_chart_canvas {
        position: relative;
        height: 400px;
    }
</style>
