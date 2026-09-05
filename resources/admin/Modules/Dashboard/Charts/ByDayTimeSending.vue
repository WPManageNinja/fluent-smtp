<template>
    <div class="fsm_card fss_wid_widget fss_wid_day_by_day">
        <div class="fsm_card_head">
            <h3>{{ $t('Sending by time of day') }}</h3>
            <div class="fsm_card_head_actions">
                <el-select @change="fetchStats" size="small" v-model="last_day">
                    <el-option :value="7" :label="$t('Last 7 Days')"></el-option>
                    <el-option :value="30" :label="$t('Last 30 Days')"></el-option>
                    <el-option :value="0" :label="$t('All Time')"></el-option>
                </el-select>
            </div>
        </div>
        <div class="fsm_card_body">
            <el-alert v-if="load_error" type="error" :closable="false" show-icon>
                <p>{{ load_error }}</p>
                <el-button size="small" @click="fetchStats()">{{ $t('Retry') }}</el-button>
            </el-alert>
            <div v-else-if="appReady" class="fcraft_time_widget">
                <div class="fcraft_time_widget_header">
                    <div class="fcraft_time_day"></div>
                    <div v-for="day in days" :key="day" class="fcraft_time_day">{{ dayLabel(day) }}</div>
                </div>
                <div class="fcraft_time_widget_body">
                    <div class="fss_wid_sub_headers">
                        <div v-for="tipIndex in tipIndexes" :key="tipIndex" class="fss_wid_sub_header">
                            {{ tipIndex }}
                        </div>
                    </div>
                    <div v-for="(day, dayIndex) in days" :key="day" class="fcraft_time_day">
                        <div v-for="(keyItem, slotIndex) in filledSlots" :key="keyItem"
                             :class="'fss_wid_' + getLevel(dataItems[day][keyItem])"
                             class="fcraft_time_hour">

                            <!--
                                The count is the only thing this grid holds, and there
                                used to be exactly one way to get it: hover a <div> that
                                could not be focused, holding a number drawn at zero
                                opacity. That is nothing at all for a keyboard and
                                nothing for a touch screen. The cell is a button now,
                                named with its day, its hour and its count, and the
                                tooltip opens on focus as well as on hover so a keyboard
                                is shown what a mouse is shown. Nothing is drawn
                                differently.

                                One tab stop, not 168. Seven days by twenty-four hours is
                                a grid, and a grid is entered once and then walked with
                                the arrow keys; making every cell a tab stop would put
                                the whole widget between the keyboard and the rest of the
                                dashboard.
                            -->
                            <el-tooltip :content="countLabel(dataItems[day][keyItem])"
                                        :disabled="!dataItems[day][keyItem]"
                                        :trigger="['hover', 'focus']"
                                        placement="top">
                                <button type="button" class="fcraft_time_hour_value"
                                        :ref="setCellRef(dayIndex, slotIndex)"
                                        :tabindex="isCursor(dayIndex, slotIndex) ? 0 : -1"
                                        :aria-label="cellLabel(day, keyItem, dataItems[day][keyItem])"
                                        @focus="moveCursor(dayIndex, slotIndex)"
                                        @keydown="onCellKeydown($event, dayIndex, slotIndex)">
                                    <span>{{ dataItems[day][keyItem] }}</span>
                                </button>
                            </el-tooltip>
                        </div>
                    </div>
                </div>
            </div>
            <el-skeleton v-else :rows="5"></el-skeleton>
            <div class="fss_wid_label_info">
                <span class="fss_wid_dir">{{ $t('Less') }}</span>
                <span class="fss_wid_level fss_wid_level_1"></span>
                <span class="fss_wid_level fss_wid_level_2"></span>
                <span class="fss_wid_level fss_wid_level_3"></span>
                <span class="fss_wid_level fss_wid_level_4"></span>
                <span class="fss_wid_level fss_wid_level_5"></span>
                <span class="fss_wid_dir">{{ $t('More') }}</span>
            </div>

        </div>
    </div>
</template>
<script type="text/babel">
export default {
    name: 'SubmissionByDayGraph',
    props: [],
    data() {
        return {
            last_day: 30,
            appReady: false,
            load_error: '',
            dataItems: {},
            /* Where the arrow keys are. */
            cursor: {day: 0, slot: 0},
            days: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
            filledSlots: ['0:00', '1:00', '2:00', '3:00', '4:00', '5:00', '6:00', '7:00', '8:00', '9:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'],
            /*
             * Every third hour, starting at midnight. The labels are drawn on the same
             * grid as the cells below them and each one spans the three columns it
             * starts, so a label sits over the hour it names rather than a column or
             * two off it - which is what eight labels at an eighth of the width each,
             * over twenty-four cells, gave.
             */
            tipIndexes: ['12am', '3am', '6am', '9am', '12pm', '3pm', '6pm', '9pm'],
        }
    },
    computed: {
        maxValue() {
            let max = 0;
            for (let day in this.dataItems) {
                for (let key in this.dataItems[day]) {
                    if (this.dataItems[day][key] > max) {
                        max = this.dataItems[day][key];
                    }
                }
            }

            if (max < 5) {
                return 5;
            }

            return max;
        }
    },
    /*
     * The cell elements the arrow keys focus. Kept off `data` deliberately: it holds
     * 168 DOM nodes, it is never rendered, and nothing should re-render when it changes.
     */
    created() {
        this.cells = {};
    },
    methods: {
        /*
         * The one cell the tab key lands on. It follows the arrow keys, so tabbing back
         * into the grid returns to where the last visit left off.
         */
        isCursor(dayIndex, slotIndex) {
            return dayIndex === this.cursor.day && slotIndex === this.cursor.slot;
        },
        moveCursor(dayIndex, slotIndex) {
            this.cursor = {day: dayIndex, slot: slotIndex};
        },
        setCellRef(dayIndex, slotIndex) {
            return (el) => {
                if (el) {
                    this.cells[`${dayIndex}:${slotIndex}`] = el;
                } else {
                    delete this.cells[`${dayIndex}:${slotIndex}`];
                }
            };
        },
        onCellKeydown(event, dayIndex, slotIndex) {
            /*
             * A row per day, an hour per column: the day labels stack down the left and
             * each day's twenty-four hours run across (see flex-direction below). So the
             * horizontal keys walk the hours and the vertical ones walk the days, and
             * Home and End go to the ends of the day the cursor is already in.
             *
             * Each entry is [day, hour].
             */
            const moves = {
                ArrowLeft: [0, -1],
                ArrowRight: [0, 1],
                ArrowUp: [-1, 0],
                ArrowDown: [1, 0],
                Home: [0, -slotIndex],
                End: [0, this.filledSlots.length - 1 - slotIndex]
            };

            const move = moves[event.key];

            if (!move) {
                return;
            }

            event.preventDefault();

            const day = Math.min(Math.max(dayIndex + move[0], 0), this.days.length - 1);
            const slot = Math.min(Math.max(slotIndex + move[1], 0), this.filledSlots.length - 1);

            this.moveCursor(day, slot);

            this.$nextTick(() => {
                const cell = this.cells[`${day}:${slot}`];

                if (cell) {
                    cell.focus();
                }
            });
        },
        /*
         * What the tooltip says. It used to be a string concatenated in the template,
         * which meant it was the one line on this screen that never translated.
         */
        countLabel(value) {
            return this.$t('{count} emails sent', {count: parseInt(value) || 0});
        },
        /*
         * What the cell is called. The day and the hour are in the name rather than
         * left to the row and column labels, because in this layout those are plain
         * <div>s: they are not table headers and nothing associates them with a cell.
         */
        cellLabel(day, slot, value) {
            return this.$t('{day} at {time}, {count} emails sent', {
                day: this.dayLabel(day),
                time: slot,
                count: parseInt(value) || 0
            });
        },
        /*
         * `days` holds the keys the stats endpoint returns, so the array itself cannot be
         * translated without the lookups missing. The label is translated here instead,
         * which is also what puts the day into the cell's accessible name in the reader's
         * own language.
         */
        dayLabel(day) {
            return this.$t(day);
        },
        getLevel(value) {
            value = parseInt(value);
            if (!value) {
                return 'level_0';
            }

            const itemValue = Math.round((value / this.maxValue) * 100);

            if (itemValue > 80) {
                return 'level_5';
            } else if (itemValue > 60) {
                return 'level_4';
            } else if (itemValue > 40) {
                return 'level_3';
            } else if (itemValue > 20) {
                return 'level_2';
            } else {
                return 'level_1';
            }
        },
        fetchStats() {
            this.appReady = false;
            this.load_error = '';
            this.$get('day-time-stats', {
                last_day: this.last_day
            })
                .then(res => {
                    this.dataItems = res.stats || {};
                    /*
                     * Readiness is set here, not in always().
                     *
                     * The grid indexes `dataItems[day][keyItem]` for all seven days, so
                     * marking it ready on a failure - where `dataItems` is still {} -
                     * threw during render and left the card permanently blank with no
                     * error. Only a response that actually arrived can make it ready.
                     */
                    this.appReady = true;
                })
                .fail(error => {
                    this.load_error = this.$errorMessage(error);
                });
        }
    },
    mounted() {
        this.fetchStats();
    }
}
</script>

<style lang="scss">
.fss_wid_day_by_day {
    max-width: 100%;
    overflow-x: auto;

    /*
     * Element Plus's select is `width: 100%`, where Element UI's was sized by
     * its content. This header's actions box is floated, so it is shrink-to-fit:
     * a 100%-wide child inside it resolves to nothing and the control collapses
     * to the width of its chevron.
     */
    .fsm_card_head .el-select {
        width: 210px;
    }

    .fss_wid_widget_body {
        min-width: 750px;
    }

    .fss_wid_label_info {
        margin-top: 20px;
        padding-bottom: 16px;
        display: flex;
        align-items: center;
        color: var(--fsm-text-light);
        gap: 10px;

        .fss_wid_level {
            width: 20px;
            height: 20px;
        }
    }

    /*
     * The hour labels, on the same twenty-four tracks as the cells under them.
     *
     * They used to be eight blocks floated at 12.5% each, which is an eighth of the
     * row rather than three of its twenty-four columns - so "1am" sat over midnight
     * and every label after it drifted further from the hour it named. This row and
     * the day rows below it are both children of the same flex column, so both are
     * exactly as wide as the widest of them: one `1fr` here is one cell there, at
     * whatever width the cells have ended up, and a label spanning three of them
     * starts on the hour it names.
     */
    .fss_wid_sub_headers {
        display: grid;
        grid-template-columns: repeat(24, minmax(0, 1fr));
        margin-bottom: 5px;

        .fss_wid_sub_header {
            grid-column: span 3;
            font-size: 11px;
            /* Three 22px columns hold "12am" with room to spare; nothing may wrap. */
            white-space: nowrap;
        }
    }


    /*
     * The five filled levels are a data ramp - the same colours in both themes,
     * because a count of emails does not mean something different in the dark.
     * Level 0 is not data, it is the empty cell behind it, so it is chrome and
     * flips with the theme. Both are declared in styles/_theme.scss.
     */
    .fss_wid_level_5 { background: var(--fsm-heat-5); }
    .fss_wid_level_4 { background: var(--fsm-heat-4); }
    .fss_wid_level_3 { background: var(--fsm-heat-3); }
    .fss_wid_level_2 { background: var(--fsm-heat-2); }
    .fss_wid_level_1 { background: var(--fsm-heat-1); }
    .fss_wid_level_0 { background: var(--fsm-heat-0); }
}

.fcraft_time_widget {
    display: flex;
    flex-direction: row;

    .fcraft_time_widget_header {
        display: flex;
        flex-direction: column;
        padding: 0px 10px;
        justify-content: space-between;

        > div {
            height: 20px;
            border: 2px solid var(--fsm-surface);
        }
    }

    .fcraft_time_widget_body {
        display: flex;
        flex-direction: column;

        .fcraft_time_day {
            display: flex;
            flex-direction: row;
            justify-content: space-between;

            .fcraft_time_hour {
                display: flex;
                flex-direction: column;
                height: 22px;
                width: 22px;
                text-align: center;
                border: 3px solid var(--fsm-surface);
                opacity: 0.9;

                /*
                 * A button rather than a div, so the count can be reached and read out.
                 * Everything a button brings with it is taken straight back off: the
                 * cell's colour is on the parent and this fills it, so the whole square
                 * is the target it has always looked like.
                 */
                .fcraft_time_hour_value {
                    display: block;
                    flex: 1;
                    width: 100%;
                    padding: 0;
                    border: 0;
                    background: none;
                    color: inherit;
                    font-size: 10px;
                    font-weight: 300;
                    line-height: 1;
                    cursor: pointer;

                    /*
                     * The number stays hidden - it is here for the tooltip and for the
                     * accessible name, not to be read off a 22px square. The opacity is
                     * on the text rather than on the button, because an outline drawn on
                     * a fully transparent element is transparent too, and the keyboard
                     * ring is the reason the button exists.
                     */
                    span {
                        opacity: 0;
                    }

                    /* Sits in the 3px gutter between cells, so nothing shifts to fit it. */
                    &:focus-visible {
                        outline: 2px solid var(--fsm-accent);
                        outline-offset: 1px;
                    }
                }
            }
        }
    }
}
</style>
