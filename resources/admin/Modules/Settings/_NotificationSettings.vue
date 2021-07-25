<template>
    <div v-loading="loading" class="fss_general_settings">
        <el-form class="fss_compact_form" :data="notification_settings" label-position="top">
            <el-form-item label="Enable Email Summary Notification">
                <el-checkbox
                    v-model="notification_settings.enabled"
                    true-label="yes"
                    false-label="no"
                >Enable Email Summary</el-checkbox>
            </el-form-item>
            <template v-if="notification_settings.enabled == 'yes'">
                <el-form-item label="Email Address to send">
                    <el-input size="small" v-model="notification_settings.notify_email" placeholder="Email Address" />
                </el-form-item>
                <el-form-item label="Notification Days">
                    <el-checkbox-group v-model="notification_settings.notify_days">
                        <el-checkbox v-for="(day, dayLabel) in sending_days" :key="day" :value="day" :label="dayLabel"></el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
            </template>
            <el-button
                v-loading="saving"
                @click="saveSettings()"
                type="success"
            >Save Settings</el-button>
        </el-form>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'NotificationSettings',
    data() {
        return {
            notification_settings: {},
            loading: true,
            saving: false,
            sending_days: {
                Mon: 'Monday',
                Tue: 'Tuesday',
                Wed: 'Wednesday',
                Thu: 'Thursday',
                Fri: 'Friday',
                Sat: 'Saturday',
                Sun: 'Sunday'
            }
        }
    },
    methods: {
        getSettings() {
            this.loading = true;
            this.$get('settings/notification-settings')
                .then((response) => {
                    this.notification_settings = response.data.settings;
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
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
        this.getSettings();
    }
}
</script>