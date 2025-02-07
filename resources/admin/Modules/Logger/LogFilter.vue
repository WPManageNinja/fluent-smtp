<template>
    <div style="float:left;margin-left:10px;">
        <el-row :gutter="10" style="margin-right:-20px;">
            <el-col :span="10">
                <el-radio-group @change="applyFilter()" v-model="filter_query.status" size="small">
                    <el-radio-button label="">{{ $t('All Statuses') }}</el-radio-button>
                    <el-radio-button label="sent">{{ $t('Successful') }}</el-radio-button>
                    <el-radio-button label="failed">{{ $t('Failed') }}</el-radio-button>
                </el-radio-group>
            </el-col>

            <el-col :span="10">
                <el-date-picker
                    format="dd-MM-yyyy"
                    value-format="yyyy-MM-dd"
                    size="small"
                    :picker-options="pickerOptions"
                    v-model="filter_query.date_range"
                    type="daterange"
                    :placeholder="$t('Select date and time')"
                    :range-separator="$t('To')"
                    :start-placeholder="$t('Start date')"
                    :end-placeholder="$t('End date')"
                    style="width:100%"
                />
            </el-col>

            <el-col :span="4">
                <el-button
                    plain
                    size="small"
                    type="primary"
                    @click="applyFilter"
                >{{ $t('Filter') }}
                </el-button>
            </el-col>
        </el-row>
    </div>
</template>

<script>
export default {
    name: 'LogFilter',
    props: ['filter_query'],
    data() {
        return {
            pickerOptions: {
                disabledDate(time) {
                    return time.getTime() > Date.now();
                },
                shortcuts: [
                    {
                        text: this.$t('Today'),
                        onClick(picker) {
                            const today = new Date();
                            picker.$emit('pick', [today, today]);
                        }
                    },
                    {
                        text: this.$t('Last week'),
                        onClick(picker) {
                            const end = new Date();
                            const start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
                            picker.$emit('pick', [start, end]);
                        }
                    }, {
                        text: this.$t('Last month'),
                        onClick(picker) {
                            const end = new Date();
                            const start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24 * 30);
                            picker.$emit('pick', [start, end]);
                        }
                    }, {
                        text: this.$t('Last 3 months'),
                        onClick(picker) {
                            const end = new Date();
                            const start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24 * 90);
                            picker.$emit('pick', [start, end]);
                        }
                    }]
            }
        };
    },
    methods: {
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
