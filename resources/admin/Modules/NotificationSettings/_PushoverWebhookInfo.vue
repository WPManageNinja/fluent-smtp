<template>
    <div>
        <div class="fss_alert_info">
            <p class="fss_alert_info__description">
            {{ $t('__PUSHOVER_NOTIFICATION_ENABLED') }}
        </p>
        <channel-actions
            :channel_key="'pushover'"
            :channel_title="channel_config.title || 'Pushover'"
        />
        </div>
    </div>
</template>

<script type="text/babel">
import ChannelActions from './_ChannelActions.vue';

export default {
    name: 'PushoverWebhookInfo',
    components: { ChannelActions },
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
