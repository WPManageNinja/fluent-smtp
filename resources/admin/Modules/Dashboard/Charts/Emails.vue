<template>
    <div v-loading="fetching" class="fss_body fss_chart_box">
        <growth-chart :maxCumulativeValue="maxCumulativeValue" :chart-data="chartData"/>
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
                chartData: {},
                maxCumulativeValue: 0
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
                    fill: false,
                    gridLines: {
                        display: false
                    }
                };

                const cumulativeItems = {
                    label: this.$t('Cumulative'),
                    backgroundColor: 'rgba(55, 162, 235, 0.1)',
                    borderColor: '#37a2eb',
                    data: [],
                    yAxisID: 'byCumulative',
                    type: 'line'
                };

                let currentTotal = 0;
                each(this.stats, (count, label) => {
                    ItemValues.data.push(count);
                    labels.push(label);
                    currentTotal += parseInt(count);
                    cumulativeItems.data.push(currentTotal);
                });
                this.maxCumulativeValue = currentTotal + 10;
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
