<template>
    <div class="fss_alert_settings__header">
        <el-button @click="goBack()" size="mini" type="text" class="fss_alert_settings__back-button">
            <i class="el-icon-arrow-left"></i> {{ $t('Back to Alerts') }}
        </el-button>
        <div class="fss_alert_settings__header-content">
            <img v-if="logo" class="fss_alert_settings__header-logo" :src="logo" :alt="title"/>
            <h3 class="fss_alert_settings__title">{{ title }} {{ $t('Settings') }}</h3>
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
        }
    },
    methods: {
        goBack() {
            this.$emit('back');
        }
    }
}
</script>
