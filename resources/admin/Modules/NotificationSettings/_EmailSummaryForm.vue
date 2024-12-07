<template>
    <div class="fss_general_settings">
        <el-form class="fss_compact_form" :data="notification_settings" label-position="top">
            <p>
                {{ $t('__EMAIL_SUMMARY_INTRO') }}
            </p>
            <el-form-item>
                <el-checkbox
                    v-model="notification_settings.enabled"
                    true-label="yes"
                    false-label="no"
                >{{$t('Enable Email Summary')}}</el-checkbox>
            </el-form-item>
            <template v-if="notification_settings.enabled == 'yes'">
                <el-form-item :label="$t('Notification Email Addresses')">
                    <el-input size="small" v-model="notification_settings.notify_email" :placeholder="$t('Email Address')" />
                </el-form-item>
                <el-form-item :label="$t('Notification Days')">
                    <el-checkbox-group v-model="notification_settings.notify_days">
                        <el-checkbox v-for="(day, dayLabel) in sending_days" :key="day" :value="day" :label="$t(dayLabel)"></el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
            </template>
            <el-form-item>
                <el-button
                    :loading="saving"
                    @click="saveSettings"
                    type="primary"
                    size="small"
                >
                    {{$t('Save Settings')}}
                </el-button>
            </el-form-item>
        </el-form>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'NotificationSettings',
    props: {
        notification_settings: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            sending_days: {
                Mon: this.$t('Monday'),
                Tue: this.$t('Tuesday'),
                Wed: this.$t('Wednesday'),
                Thu: this.$t('Thursday'),
                Fri: this.$t('Friday'),
                Sat: this.$t('Saturday'),
                Sun: this.$t('Sunday')
            },
            saving: false,
        }
    },
    methods: {
        saveSettings() {
            this.saving = true;
            this.$post('settings/notification-settings', {
                settings: this.notification_settings
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        }
    },
    mounted() {
    }
}
</script>
