<template>
    <div v-loading="fetching" class="fss_body fss_chart_box">
        <div class="fss_chart_canvas">
            <growth-chart v-if="chartData" :chart-data="chartData"/>
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
                stats: {},
                // null until the first report lands: Chart.js needs a data
                // object with datasets in it, not an empty one.
                chartData: null
            }
        },
        computed: {},
        methods: {
            fetchReport() {
                this.fetching = true;
                this.$get('sending_stats', {
                    date_range: this.date_range
                })
                    .then(res => {
                        this.stats = res.stats;
                        this.setupChartItems();
                    })
                    .fail(error => {
                        console.log(error);
                    })
                    .always(() => {
                        this.fetching = false;
                    });
            },
            setupChartItems() {
                const labels = [];
                const ItemValues = {
                    label: this.$t('By Date'),
                    yAxisID: 'byDate',
                    backgroundColor: 'rgba(81, 52, 178, 0.5)',
                    borderColor: '#b175eb',
                    data: [],
                    fill: false
                };

                const cumulativeItems = {
                    label: this.$t('Cumulative'),
                    backgroundColor: 'rgba(55, 162, 235, 0.1)',
                    borderColor: '#37a2eb',
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
