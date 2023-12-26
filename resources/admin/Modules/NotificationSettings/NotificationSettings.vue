<template>
    <div v-loading="loading" class="fss_support">
        <el-row :gutter="20">
            <el-col :sm="24" :md="12">
                <div class="fss_about">
                    <div class="fss_header">{{ $t('Summary Email') }}</div>
                    <div class="fss_content">
                        <email-summary-form :notification_settings="notification_settings"/>
                    </div>
                </div>
            </el-col>
            <el-col :sm="24" :md="12">
                <div class="fss_about">
                    <div class="fss_header">{{ $t('Email Sending Error Notifications') }}</div>
                    <div class="fss_content">
                        <notification-manager :notification_settings="notification_settings"/>
                    </div>
                </div>
            </el-col>
        </el-row>
    </div>
</template>

<script type="text/babel">
import EmailSummaryForm from './_EmailSummaryForm.vue';
import NotificationManager from './NotificationManager.vue';

export default {
    name: 'NotificationSettingsRoot',
    components: {EmailSummaryForm, NotificationManager},
    data() {
        return {
            notification_settings: {},
            loading: false
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
    },
    mounted() {
        this.getSettings();
    }
}
</script>
