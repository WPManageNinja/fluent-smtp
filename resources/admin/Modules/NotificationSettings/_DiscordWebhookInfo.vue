<template>
    <div>
        <channel-header
            :channel-title="channel_config.title || 'Discord'"
            :logo="channel_config.logo"
            :connected="true"
            @back="$emit('back')"
        />
        <div class="fss_alert_info">
            <p class="fss_alert_info__description">
            {{ $t('__DISCORD_NOTIFICATION_ENABLED') }}
        </p>
        <p class="fss_alert_info__details">{{ $t('Discord Channel Details: ') }}{{ notification_settings.discord.channel_name }}</p>
        <channel-actions
            :channel_key="'discord'"
            :channel_title="channel_config.title || 'Discord'"
        />
        </div>
    </div>
</template>

<script type="text/babel">
import ChannelHeader from './_ChannelHeader.vue';
import ChannelActions from './_ChannelActions.vue';

export default {
    name: 'DiscordWebhookInfo',
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
