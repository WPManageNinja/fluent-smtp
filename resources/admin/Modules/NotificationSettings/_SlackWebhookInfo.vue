<template>
    <div>
        <channel-header
            :channel-title="channel_config.title || 'Slack'"
            :logo="channel_config.logo"
            :connected="true"
            @back="$emit('back')"
        />
        <div class="fss_alert_info">
            <p class="fss_alert_info__description">
            {{$t('__SLACK_NOTIFICATION_ENABLED')}}
        </p>
        <p class="fss_alert_info__details">{{ $t('Slack Channel Details: ') }}@{{ notification_settings.slack.slack_team }}</p>
        <channel-actions
            :channel_key="'slack'"
            :channel_title="channel_config.title || 'Slack'"
        />
    </div>
</template>

<script type="text/babel">
import ChannelHeader from './_ChannelHeader.vue';
import ChannelActions from './_ChannelActions.vue';

export default {
    name: 'SlackWebhookInfo',
    components: { ChannelHeader, ChannelActions },
    props: {
        notification_settings: {
            type: Object,
            default: () => {
                return {}
            }
        },
        channel_config: {
            type: Object,
            default: () => ({})
        }
    },
}
</script>
