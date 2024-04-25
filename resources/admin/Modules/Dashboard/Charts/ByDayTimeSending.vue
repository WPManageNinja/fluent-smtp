<template>
    <div style="margin: 20px 0; background: white;" class="fss_wid_widget fss_wid_day_by_day">
        <div class="fss_header fss_widget_header">
            <h3>{{ $t('Sending by time of day') }}</h3>
            <div class="widget_actions fss_to_right">
                <el-select @change="fetchStats" size="small" v-model="last_day">
                    <el-option :value="7" :label="$t('Last 7 Days')"></el-option>
                    <el-option :value="30" :label="$t('Last 30 Days')"></el-option>
                    <el-option :value="0" :label="$t('All Time')"></el-option>
                </el-select>
            </div>
        </div>
        <div class="fss_content">
            <div v-if="appReady" class="fcraft_time_widget">
                <div class="fcraft_time_widget_header">
                    <div class="fcraft_time_day"></div>
                    <div v-for="day in days" :key="day" class="fcraft_time_day">{{ day }}</div>
                </div>
                <div class="fcraft_time_widget_body">
                    <div class="fss_wid_sub_headers">
                        <div v-for="tipIndex in tipIndexes" :key="tipIndex" class="fss_wid_sub_header">
                            {{ tipIndex }}
                        </div>
                    </div>
                    <div v-for="day in days" :key="day" class="fcraft_time_day">
                        <div v-for="keyItem in filledSlots" :key="keyItem"
                             :class="'fss_wid_' + getLevel(dataItems[day][keyItem])"
                             class="fcraft_time_hour">

                            <el-tooltip v-if="dataItems[day][keyItem]"
                                        :content="dataItems[day][keyItem] + ' emails sent '"
                                        placement="top">
                                <div class="fcraft_time_hour_value">
                                    <span>{{ dataItems[day][keyItem] }}</span>
                                </div>
                            </el-tooltip>
                        </div>
                    </div>
                </div>
            </div>
            <el-skeleton v-else class="fss_content" :rows="5"></el-skeleton>
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
            dataItems: {},
            days: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
            filledSlots: ['0:00', '1:00', '2:00', '3:00', '4:00', '5:00', '6:00', '7:00', '8:00', '9:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'],
            tipIndexes: ['1am', '4am', '7am', '10am', '1pm', '4pm', '7pm', '10pm'],
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
    methods: {
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
            this.$get('day-time-stats', {
                last_day: this.last_day
            })
                .then(res => {
                    this.dataItems = res.stats;
                })
                .fail(error => {
                    console.log(error);
                })
                .always(() => {
                    this.appReady = true;
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

    .fss_wid_widget_body {
        min-width: 750px;
    }

    .fss_wid_label_info {
        margin-top: 20px;
        display: flex;
        align-items: center;
        color: #6080a0;
        gap: 10px;

        .fss_wid_level {
            width: 20px;
            height: 20px;
        }
    }

    .fss_wid_sub_headers {
        display: block;
        text-align: center;
        margin-bottom: 5px;

        .fss_wid_sub_header {
            width: 12.5%;
            float: left;
            text-align: left;
            font-size: 11px;

            &:first-child {
                text-align: center;
            }
        }
    }


    .fss_wid_level_5 {
        background: #1c00a6;
    }

    .fss_wid_level_4 {
        background: #2900f3;
    }

    .fss_wid_level_3 {
        background: #6040ff;
    }

    .fss_wid_level_2 {
        background: #a08dff;
    }

    .fss_wid_level_1 {
        background: #dfd9ff;
    }

    .fss_wid_level_0 {
        background: #f0f0f0;
    }
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
            border: 2px solid white;
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
                border: 3px solid white;
                opacity: 0.9;

                .fcraft_time_hour_value {
                    font-size: 10px;
                    font-weight: 300;
                    opacity: 0;
                    cursor: pointer;
                }
            }
        }
    }
}
</style>
