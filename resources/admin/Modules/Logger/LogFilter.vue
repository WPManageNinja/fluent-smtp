<template>
    <div style="float:left;margin-left:10px;">
        <el-row :gutter="10" style="margin-right:-20px;">
            <el-col :span="10">
                <el-select
                    clearable
                    size="small"
                    v-model="filterBy"
                    :placeholder="$t('Filter By')"
                    @clear="clearFilterValue"
                >
                    <el-option :label="$t('Status')" value="status" />
                    <el-option :label="$t('Date')" value="date" />
                    <el-option :label="$t('Date Range')" value="daterange" />
                </el-select>
            </el-col>

            <el-col :span="10">
                <el-select
                    clearable
                    size="small"
                    v-model="filterValue"
                    :disabled="!filterBy"
                    :placeholder="$t('Select')"
                    v-if="!filterBy || filterBy==='status'"
                    style="width:100%"
                >
                    <el-option :label="$t('Successful')" value="sent" />
                    <el-option :label="$t('Failed')" value="failed" />
                </el-select>

                <el-date-picker
                    v-show="filterBy && filterBy==='date'"
                    format="dd-MM-yyyy"
                    value-format="yyyy-MM-dd"
                    size="small"
                    v-model="filterValue"
                    type="date"
                    :placeholder="$t('Select date')"
                    style="width:100%"
                />

                <el-date-picker
                    v-show="filterBy && filterBy==='daterange'"
                    format="dd-MM-yyyy"
                    value-format="yyyy-MM-dd"
                    size="small"
                    v-model="filterValue"
                    type="daterange"
                    :placeholder="$t('Select date and time')"
                    range-separator="To"
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
                    :disabled="!filterValue"
                    @click="applyFilter"
                >{{$t('Filter')}}</el-button>
            </el-col>
        </el-row>
    </div>
</template>

<script>
    export default {
        name: 'LogFilter',
        data() {
            return {
                filterBy: '',
                filterValue: ''
            };
        },
        methods: {
            applyFilter() {
                if (this.filterValue) {
                    this.$emit('on-filter', this.filterBy, this.filterValue);
                }
            },
            clearFilterValue() {
                this.filterValue = '';
            }
        },
        watch: {
            filterBy: function(newValue, oldValue) {
                if (newValue !== oldValue) {
                    if (newValue && oldValue) {
                        this.filterValue = '';
                    }
                }
            },
            filterValue: function(newValue, oldValue) {
                if (newValue) {
                    this.$emit('on-filter-change', this.filterBy, this.filterValue);
                } else {
                    this.$emit('reset-page');
                    this.$emit('on-filter', this.filterBy, this.filterValue);
                }

                if (newValue !== oldValue) {
                    this.$emit('reset-page');
                }
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
