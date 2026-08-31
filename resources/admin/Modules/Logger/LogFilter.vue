<template>
    <div class="fsm_log_filter">
        <el-radio-group @change="applyFilter()" v-model="filter_query.status" size="small">
            <el-radio-button value="">{{ $t('All Statuses') }}</el-radio-button>
            <el-radio-button value="sent">{{ $t('Successful') }}</el-radio-button>
            <el-radio-button value="failed">{{ $t('Failed') }}</el-radio-button>
        </el-radio-group>

        <el-date-picker
            class="fsm_log_filter_dates"
            format="DD-MM-YYYY"
            value-format="YYYY-MM-DD"
            size="small"
            :shortcuts="shortcuts"
            :disabled-date="disabledDate"
            v-model="filter_query.date_range"
            type="daterange"
            :placeholder="$t('Select date and time')"
            :range-separator="$t('To')"
            :start-placeholder="$t('Start date')"
            :end-placeholder="$t('End date')"
        />

        <el-button plain size="small" type="primary" @click="applyFilter">
            {{ $t('Filter') }}
        </el-button>
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
    },
    mounted() {
        const filterBy = this.$route.query.filterBy;
        const filterValue = this.$route.query.filterValue;

        if (filterBy) {
            this.filterBy = filterBy;
            this.filterValue = filterValue;
            this.applyFilter();
        }
    }
};
</script>

<style lang="scss">
/*
 * One flex row, where this used to be an el-row of three columns inside a floated
 * container. The date range is the only part that wants the leftover width.
 */
.fsm_log_filter {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;

    /* Qualified with .el-date-editor for the same reason .fsm_log_search is. */
    .el-date-editor.fsm_log_filter_dates {
        width: 260px;
        flex: 0 1 260px;
    }
}
</style>
