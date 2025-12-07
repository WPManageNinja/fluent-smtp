<template>
    <div class="fss_alert_settings__header">
        <el-button @click="goBack()" size="mini" type="text" class="fss_alert_settings__back-button">
            <i class="el-icon-arrow-left"></i> {{ $t('Back to Alerts') }}
        </el-button>
        <div class="fss_alert_settings__header-content">
            <img v-if="logo" class="fss_alert_settings__header-logo" :src="logo" :alt="title"/>
            <div class="fss_alert_info__header">
                <h3 class="fss_alert_settings__title">{{ title }}</h3>
                <el-tag v-if="connected" type="success" class="fss_alert_info__status-badge">
                    <i class="el-icon-success"></i> {{ $t('Connected') }}
                </el-tag>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'ChannelHeader',
    props: {
        title: {
            type: String,
            required: true
        },
        logo: {
            type: String,
            default: ''
        },
        connected: {
            type: Boolean,
            default: false
        }
    },
    methods: {
        goBack() {
            this.$emit('back');
        }
    }
}
</script>
