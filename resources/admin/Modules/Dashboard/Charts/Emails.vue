<template>
    <div v-loading="fetching" class="fss_body fss_chart_box">
        <el-alert v-if="load_error" type="error" :closable="false" show-icon>
            <p>{{ load_error }}</p>
            <el-button size="small" @click="fetchReport()">{{ $t('Retry') }}</el-button>
        </el-alert>
        <p v-else-if="is_empty" class="fss_chart_empty">
            {{ $t('No emails were sent in this date range.') }}
        </p>
        <div v-else-if="labels.length" class="fss_chart_canvas">
            <sending-chart :labels="labels" :sent="sent" :failed="failed"
                           :chart-type="chart_type.current"/>
        </div>
    </div>
</template>

<script type="text/babel">
    import SendingChart from './_chart'
    import chartType from './chartType'

    export default {
        name: 'email-sendings',
        props: ['date_range'],
        components: {
            SendingChart
        },
        data() {
            return {
                fetching: false,
                load_error: '',
                chart_type: chartType,
                // The bucket keys the endpoint returned, and the two series read off
                // them: what went out in each bucket, and what came back as a failure.
                labels: [],
                sent: [],
                failed: []
            }
        },
        computed: {
            /*
             * A range with buckets in it but nothing in any of them. Worth saying out
             * loud: an axis drawn from 0 to 1 with a flat line along the bottom looks
             * like a chart that failed to load rather than like a quiet week.
             */
            is_empty() {
                return this.labels.length > 0
                    && this.sent.every(count => !count)
                    && this.failed.every(count => !count);
            }
        },
        watch: {
            date_range: 'fetchReport'
        },
        methods: {
            fetchReport() {
                this.load_error = '';
                this.fetching = true;
                this.$get('sending_stats', {
                    date_range: this.date_range
                })
                    .then(res => {
                        this.setupChartItems(res.stats || {});
                    })
                    .fail(error => {
                        this.load_error = this.$errorMessage(error);
                    })
                    .always(() => {
                        this.fetching = false;
                    });
            },
            /*
             * The endpoint answers with one entry per bucket - a day, a Monday or a
             * month, depending on how long the range is - keyed by the bucket and
             * holding {sent, failed}, with the empty buckets already filled in. The keys
             * are kept as they came: _chart.js is what decides how a bucket is written
             * on the axis and in the tooltip.
             */
            setupChartItems(stats) {
                const count = (bucket, key) => parseInt(bucket && bucket[key]) || 0;

                this.labels = Object.keys(stats);
                this.sent = this.labels.map(label => count(stats[label], 'sent'));
                this.failed = this.labels.map(label => count(stats[label], 'failed'));
            }
        },
        mounted() {
            this.fetchReport();
        }
    };
</script>

<style lang="scss">
    /*
     * ECharts sizes its canvas in pixels at init, so the box it is given has to have a
     * height of its own - a chart in an auto-height parent measures zero and draws
     * nothing. The height is on the box rather than only on the canvas so that the card
     * is the same size while the report is still loading and while it is empty, which
     * is what stops the dashboard jumping under the cursor when the range changes.
     */
    .fss_chart_box {
        min-height: 400px;
    }

    .fss_chart_canvas,
    .fss_chart_plot {
        height: 400px;
    }

    .fss_chart_empty {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 400px;
        margin: 0;
        color: var(--fsm-text-light);
    }

    /* A phone is not tall enough to give four hundred pixels to one card. */
    @media (max-width: 640px) {
        .fss_chart_box {
            min-height: 280px;
        }

        .fss_chart_canvas,
        .fss_chart_plot,
        .fss_chart_empty {
            height: 280px;
        }
    }
</style>
