<template>
    <div>
        <div class="fss_alert_info">
            <p class="fss_alert_info__description">
                {{ $t('__GOTIFY_NOTIFICATION_ENABLED') }}
            </p>
            <p v-if="serverUrl" class="fss_alert_info__description">
                <b>{{ $t('Server URL') }}:</b> {{ serverUrl }}
            </p>
            <channel-actions
                :channel_key="'gotify'"
                :channel_title="channel_config.title || 'Gotify'"
            />
        </div>
    </div>
</template>

<script type="text/babel">
import ChannelActions from './_ChannelActions.vue';

export default {
    name: 'GotifyConnectionInfo',
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
    computed: {
        serverUrl() {
            return (this.notification_settings.gotify || {}).server_url || '';
        }
    }
}
</script>
