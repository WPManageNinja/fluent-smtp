<template>
    <!--
        The table's toolbar, in the shape FluentCart's tables use: what you are looking at
        on the left, what you do to the list on the right.

        Search and Refresh used to sit in the page heading instead, a card away from the
        status buttons and the date range that do the same job - so the controls that
        narrow this list were split across two rows with the title between them.
    -->
    <div class="fsm_log_filter">
        <el-radio-group class="fsm_log_filter_status" @change="applyFilter()"
                        v-model="filter_query.status">
            <el-radio-button value="">{{ $t('All Statuses') }}</el-radio-button>
            <el-radio-button value="sent">{{ $t('Successful') }}</el-radio-button>
            <el-radio-button value="failed">{{ $t('Failed') }}</el-radio-button>
        </el-radio-group>

        <div class="fsm_log_filter_tools">
            <el-date-picker
                class="fsm_log_filter_dates"
                format="DD-MM-YYYY"
                value-format="YYYY-MM-DD"
                :shortcuts="shortcuts"
                :disabled-date="disabledDate"
                v-model="filter_query.date_range"
                type="daterange"
                :placeholder="$t('Select date and time')"
                :range-separator="$t('To')"
                :start-placeholder="$t('Start date')"
                :end-placeholder="$t('End date')"
            />

            <el-button plain type="primary" @click="applyFilter">
                {{ $t('Filter') }}
            </el-button>

            <el-input
                class="fsm_log_filter_search"
                clearable
                v-model="filter_query.search"
                @clear="applyFilter"
                @keyup.enter="applyFilter"
                :placeholder="$t('Type & press enter...')"
            >
                <template #append>
                    <el-button icon="FsmIconSearch" @click="applyFilter"
                               :title="$t('Search')" :aria-label="$t('Search')"/>
                </template>
            </el-input>

            <!--
                Its own event, because refreshing is the one control here that does not
                narrow anything - it reloads what is already on screen, so it keeps the
                page you are on where the others send you back to the first.
            -->
            <el-button icon="FsmIconRefresh" @click="$emit('on-refresh')"
                       :title="$t('Refresh')" :aria-label="$t('Refresh')"/>
        </div>
    </div>
</template>

<script>
export default {
    name: 'LogFilter',
    props: ['filter_query'],
    data() {
        return {
            shortcuts: [
                { text: this.$t('Today'), value: () => this.daysAgoRange(0) },
                { text: this.$t('Last week'), value: () => this.daysAgoRange(7) },
                { text: this.$t('Last month'), value: () => this.daysAgoRange(30) },
                { text: this.$t('Last 3 months'), value: () => this.daysAgoRange(90) }
            ]
        };
    },
    methods: {
        /*
         * Element Plus split Element UI's `picker-options` object into separate
         * :shortcuts and :disabled-date props, and changed a shortcut's shape:
         * it now returns the range as a `value`, where the old one reached into
         * the picker instance and did `picker.$emit('pick', ...)`.
         */
        daysAgoRange(days) {
            const end = new Date();
            const start = new Date();
            start.setTime(start.getTime() - 3600 * 1000 * 24 * days);
            return [start, end];
        },
        disabledDate(date) {
            return date.getTime() > Date.now();
        },
        applyFilter() {
            this.$emit('on-filter', this.filter_query);
        }
    }
};
</script>

<style lang="scss">
/*
 * The statuses on the left, everything that acts on the list on the right. Both halves
 * wrap on their own, so a narrow window drops the tools under the statuses rather than
 * squeezing the date range.
 */
.fsm_log_filter {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 10px;
    width: 100%;

    /*
     * Element Plus draws a radio button from padding rather than a height, which lands
     * at 30px next to the 32px every other control in this row stands at - close enough
     * to read as a mistake rather than a difference.
     */
    .fsm_log_filter_status .el-radio-button__inner {
        display: inline-flex;
        align-items: center;
        height: 32px;
    }

    .fsm_log_filter_tools {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    /* Both qualified with their Element Plus class: the component sets a width of its
     * own on the root, so a single class name loses to it. */
    .el-date-editor.fsm_log_filter_dates {
        width: 240px;
        flex: 0 1 240px;
    }

    .el-input.fsm_log_filter_search {
        width: 230px;
        flex: 0 1 230px;
    }
}
</style>
