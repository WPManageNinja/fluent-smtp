<template>
    <div>
        <div class="fss_alert_info">
            <p class="fss_alert_info__description">
                {{ $t('__WEBHOOK_NOTIFICATION_ENABLED') }}
            </p>
            <p v-if="label" class="fss_alert_info__details">{{ $t('Webhook name: ') }}{{ label }}</p>
            <channel-actions
                :channel_key="'webhook'"
                :channel_title="channel_config.title || 'Webhook'"
                :test_blocked_reason="unsaved_changes ? $t('Save your changes to test them.') : ''"
            />
        </div>
    </div>
</template>

<script type="text/babel">
import ChannelActions from './_ChannelActions.vue';

export default {
    name: 'WebhookConnectionInfo',
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
        },
        unsaved_changes: {
            type: Boolean,
            default: false
        }
    },
    computed: {
        label() {
            return (this.notification_settings.webhook || {}).label || '';
        }
    }
}
</script>
