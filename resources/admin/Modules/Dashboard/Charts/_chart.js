import { h } from 'vue';
import dayjs from 'dayjs';
import * as echarts from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

/*
 * Apache ECharts, imported a piece at a time.
 *
 * The dashboard is the only screen with a chart on it, and the app ships as one
 * un-split bundle, so everything imported here is downloaded by every screen.
 * `echarts/core` plus the four things this chart actually draws with costs about
 * a third of what `import * as echarts from 'echarts'` does, which pulls in every
 * chart type the library has - maps, treemaps, graphs, the lot.
 *
 * This replaces Chart.js, which drew the same two series here. FluentCart's
 * dashboard is ECharts, and the two products sit in the same admin: a chart that
 * behaves differently on hover in one of them reads as a different product.
 */
echarts.use([
    BarChart,
    LineChart,
    GridComponent,
    LegendComponent,
    TooltipComponent,
    CanvasRenderer
]);

/*
 * The reporting endpoint keys its buckets either by day - 'Y-m-d', which is also
 * how it keys a weekly bucket, using the Monday - or by month, already formatted
 * as 'Sep 2026'. So a key that parses as a date gets shortened for the axis and
 * spelled out in the tooltip, and anything else is already in the shape both want.
 */
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

function axisLabel(key) {
    return ISO_DATE.test(key) ? dayjs(key).format('MMM D') : key;
}

function tooltipLabel(key) {
    return ISO_DATE.test(key) ? dayjs(key).format('MMMM D, YYYY') : key;
}

/*
 * How many labels to skip between the ones drawn.
 *
 * Left to itself ECharts drops whichever labels collide, which reads as an axis
 * that starts somewhere other than the first bucket and relabels itself as the
 * window moves. Deciding the gap up front keeps the first bucket labelled and the
 * labels evenly spaced - but it has to know how much room there is, or the seven
 * days that fit across a dashboard column run into each other on a phone.
 */
function labelInterval(count, width) {
    // "Aug 30" and its neighbour need about this much room between their starts.
    const fits = Math.max(2, Math.floor(width / 64));

    if (count <= fits) {
        return 0;
    }

    return Math.ceil(count / fits) - 1;
}

/*
 * Keep the tooltip off the point it describes.
 *
 * ECharts anchors it to the cursor, so near the right-hand edge of a wide card the
 * box is pushed back over the plot and covers the column being read. This is
 * FluentCart's rule: sit on whichever side of the cursor has room, and never let the
 * box leave the chart. Ported rather than shared because the two plugins do not
 * share a bundle.
 */
function tooltipPosition(point, params, dom, rect, size) {
    const gap = 16;
    const [chartWidth, chartHeight] = size.viewSize;
    const [boxWidth, boxHeight] = size.contentSize;

    const toLeft = point[0] - gap - boxWidth;
    const toRight = point[0] + gap;

    let left = point[0] > (chartWidth / 2) ? toLeft : toRight;

    if (left < gap) {
        left = toRight;
    } else if (left + boxWidth > chartWidth - gap) {
        left = toLeft;
    }

    left = Math.min(Math.max(gap, left), Math.max(gap, chartWidth - boxWidth - gap));

    const top = Math.min(
        Math.max(gap, point[1] - (boxHeight / 2)),
        Math.max(gap, chartHeight - boxHeight - gap)
    );

    return [left, top];
}

/*
 * ECharts paints to a canvas, so it cannot read a CSS variable the way the rest of
 * the app does - it needs values. Reading them off the live app root is what keeps the
 * chart's grid, labels and plotted series on the same palette as everything around it
 * without either side holding a second copy of the colours.
 */
function themeColours() {
    const root = document.getElementById('fluent_mail_app');
    const styles = root ? getComputedStyle(root) : null;
    const read = (name, fallback) =>
        (styles ? styles.getPropertyValue(name).trim() : '') || fallback;

    return {
        grid: read('--fsm-border', '#EAECF0'),
        tick: read('--fsm-text-light', '#6D6F7B'),
        legend: read('--fsm-text-mid', '#565865'),
        surface: read('--fsm-surface', '#FFFFFF'),
        line: read('--fsm-border-strong', '#D6DAE1'),
        sent: read('--fsm-chart-line', '#147DB3'),
        sentFill: read('--fsm-chart-line-fill', 'rgba(20, 125, 179, .12)'),
        failed: read('--fsm-chart-failed', '#B12A07'),
        failedFill: read('--fsm-chart-failed-fill', 'rgba(177, 42, 7, .3)')
    };
}

export default {
    name: 'SendingChart',
    props: {
        // The bucket keys the endpoint returned, in order.
        labels: {
            type: Array,
            required: true
        },
        // Emails that went out in each bucket.
        sent: {
            type: Array,
            required: true
        },
        // Emails that came back as failures in each bucket.
        failed: {
            type: Array,
            required: true
        },
        // How both series are drawn: 'bar' or 'line'.
        chartType: {
            type: String,
            default: 'line'
        }
    },
    data() {
        return {
            // How many axis labels are skipped between the ones drawn. It depends on how
            // wide the chart has ended up, so it is worked out at draw time rather than
            // read off the data alone.
            interval: 0
        };
    },
    watch: {
        labels: 'draw',
        sent: 'draw',
        failed: 'draw',
        chartType: 'draw'
    },
    methods: {
        option() {
            const colours = themeColours();
            const isBar = this.chartType === 'bar';

            const shared = {
                symbol: 'circle',
                symbolSize: 8,
                showSymbol: this.labels.length <= 45,
                smooth: false,
                animationEasing: 'cubicOut',
                animationDuration: 800
            };

            return {
                legend: {
                    icon: 'circle',
                    itemWidth: 8,
                    itemHeight: 8,
                    itemGap: 24,
                    top: 0,
                    textStyle: { color: colours.legend }
                },
                grid: {
                    top: 40,
                    left: 8,
                    right: 8,
                    bottom: 0,
                    containLabel: true
                },
                tooltip: {
                    trigger: 'axis',
                    confine: true,
                    position: tooltipPosition,
                    backgroundColor: colours.surface,
                    borderColor: colours.line,
                    borderWidth: 1,
                    textStyle: { color: colours.legend },
                    /*
                     * A bar gets the shadow band, which is sized to the category and so
                     * lands exactly on the column under the cursor. A line keeps the
                     * thin pointer: the same band under a line chart of seven days
                     * covers a seventh of the plot.
                     *
                     * The band has to stay nearly transparent. ECharts paints the axis
                     * pointer over the series rather than behind it, so a solid band
                     * washes out the very bar it is pointing at - which reads as the
                     * hovered bar going pale.
                     */
                    axisPointer: isBar
                        ? { type: 'shadow', shadowStyle: { color: colours.grid, opacity: 0.15 } }
                        : { type: 'line', lineStyle: { color: colours.line, width: 2 } },
                    formatter: (params) => {
                        const rows = params.map(param => `<div>
                            ${param.marker}
                            <span>${param.seriesName}</span>
                            <span style="float: right; margin-left: 24px; font-weight: 500;">${param.value}</span>
                        </div>`);

                        /*
                         * The bucket as it came from the server rather than
                         * `params[0].name`, which is the short form already drawn on the
                         * axis - the tooltip is where the whole date is spelled out.
                         */
                        const bucket = this.labels[params[0].dataIndex];

                        return `<div style="font-weight: 500; margin-bottom: 4px;">
                            ${tooltipLabel(bucket)}
                        </div>${rows.join('')}`;
                    }
                },
                xAxis: {
                    type: 'category',
                    data: this.labels.map(axisLabel),
                    /*
                     * Bars need the gap - they are drawn across a category rather than at
                     * a point on it - and the line keeps it so that switching between the
                     * two only changes how the same data is drawn, not where. Without it
                     * the first and last points sit on the axis lines themselves.
                     */
                    boundaryGap: true,
                    axisTick: { show: false },
                    axisLine: { lineStyle: { color: colours.grid } },
                    axisLabel: {
                        color: colours.tick,
                        fontSize: 12,
                        interval: this.interval
                    }
                },
                /*
                 * One axis, because both series are a count of emails. They were on two
                 * when the second series was a running total, which is a different
                 * quantity on a different scale; plotting sent against failed on
                 * separate axes would draw one failure level with three hundred sends.
                 */
                yAxis: {
                    type: 'value',
                    position: 'left',
                    min: 0,
                    // A count of emails is a whole number, so the axis may not invent a
                    // tick at 2.5 - which is what a range of 0-5 otherwise gets.
                    minInterval: 1,
                    axisLabel: { color: colours.tick, fontSize: 12 },
                    splitLine: {
                        show: true,
                        lineStyle: { color: colours.grid, type: 'dashed' }
                    }
                },
                series: [
                    this.series(this.$t('Sent'), this.sent, colours.sent, colours.sentFill, shared),
                    this.series(this.$t('Failed'), this.failed, colours.failed, colours.failedFill, shared)
                ]
            };
        },
        /*
         * One series, drawn the way the toggle in the card header asks for. A line gets
         * the wash under it; a bar has no area of its own to fill, and the two sit side
         * by side in the category on ECharts' own spacing.
         */
        series(name, data, colour, fill, shared) {
            const isBar = this.chartType === 'bar';

            return {
                ...shared,
                name,
                data,
                type: this.chartType,
                color: colour,
                barMaxWidth: 30,
                lineStyle: { width: 3, color: colour },
                itemStyle: { color: colour, borderRadius: [4, 4, 0, 0] },
                ...(isBar ? {} : { areaStyle: { color: fill } })
            };
        },
        draw() {
            if (!this.chart) {
                return;
            }

            this.interval = labelInterval(this.labels.length, this.$refs.container.clientWidth);

            /*
             * notMerge, because the daily series changes type between draws and ECharts
             * merges an option into the one already set: the bar's own settings would
             * otherwise survive the switch to a line and paint a bar behind it.
             */
            this.chart.setOption(this.option(), { notMerge: true });
        },
        // ThemeSwitch fires this whenever it applies a theme, including when the change
        // arrived from another tab. The colours are read off the root at draw time, so
        // redrawing is all there is to it.
        onThemeChange() {
            this.draw();
        },
        onResize() {
            if (!this.chart) {
                return;
            }

            this.chart.resize();

            /*
             * Resizing rescales what is already drawn; it does not reconsider how many
             * labels the axis has room for. A full redraw is only needed when that
             * answer has actually changed, which is a handful of widths rather than
             * every pixel of a drag.
             */
            if (labelInterval(this.labels.length, this.$refs.container.clientWidth) !== this.interval) {
                this.draw();
            }
        }
    },
    /*
     * The chart instance and its observer are kept off `data` deliberately. Neither is
     * rendered, and ECharts holds a canvas, a scene graph and its own event handlers -
     * making that reactive walks all of it on every access for no purpose.
     */
    created() {
        this.chart = null;
        this.observer = null;
    },
    mounted() {
        this.chart = echarts.init(this.$refs.container);
        this.draw();

        window.addEventListener('fluent_theme_applied', this.onThemeChange);

        /*
         * The canvas is sized in pixels at init, so it has to be told when the box it
         * sits in changes. A window listener would miss the two ways this box actually
         * changes width without the window doing anything: the admin menu collapsing,
         * and the card's own column reflowing.
         */
        if (window.ResizeObserver) {
            this.observer = new ResizeObserver(this.onResize);
            this.observer.observe(this.$refs.container);
        }
    },
    beforeUnmount() {
        window.removeEventListener('fluent_theme_applied', this.onThemeChange);

        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }

        // Without this the canvas, its handlers and the whole scene graph outlive the
        // component - the dashboard's date filter remounts this on every Apply.
        if (this.chart) {
            this.chart.dispose();
            this.chart = null;
        }
    },
    render() {
        return h('div', { ref: 'container', class: 'fss_chart_plot' });
    }
};
