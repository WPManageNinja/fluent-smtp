<template>
    <div v-if="notification_settings.telegram !== undefined">
        <template v-if="selectedChannel">
            <channel-header
                :channel-title="channelConfig.title"
                :logo="channelConfig.logo"
                :connected="isChannelConnected"
                @back="goBack"
            />
            <component
                :is="getChannelComponent(selectedChannel)"
                :notification_settings="notification_settings"
                :channel_key="selectedChannel"
                :channel_config="channelConfig"
                @back="goBack"
            />
        </template>
        <alert-list-table
            v-else
            :notification_settings="notification_settings"
            @edit-channel="editChannel"
            @channel-toggled="reloadSettings"
        />
    </div>
</template>

<script type="text/babel">
import AlertListTable from './_AlertListTable.vue';
import TelegramNotification from './_TelegramNotification.vue';
import SlackNotification from './_SlackNotification.vue';
import DiscordNotification from './_DiscordNotification.vue';
import PushoverNotification from './_PushoverNotification.vue';
import ChannelHeader from './_ChannelHeader.vue';

export default {
    name: 'NotificationManager',
    components: {
        AlertListTable,
        TelegramNotification,
        SlackNotification,
        DiscordNotification,
        PushoverNotification,
        ChannelHeader
    },
    props: {
        notification_settings: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            selectedChannel: null,
            channels: {}
        }
    },
    computed: {
        channelConfig() {
            if (!this.selectedChannel) {
                return { title: '', logo: '' };
            }
            const channel = this.channels[this.selectedChannel] || {};
            return {
                title: channel.title || this.selectedChannel,
                logo: channel.logo || ''
            };
        },
        isChannelConnected() {
            if (!this.selectedChannel) {
                return false;
            }
            const channelKey = this.selectedChannel;
            const settings = this.notification_settings[channelKey];

            if (!settings) {
                return false;
            }

            // Check if channel is configured and active
            if (settings.status !== 'yes') {
                return false;
            }

            // Channel-specific configuration checks
            switch (channelKey) {
                case 'telegram':
                    return !!settings.status;
                case 'slack':
                    return !!settings.webhook_url;
                case 'discord':
                    return !!settings.webhook_url;
                case 'pushover':
                    return !!(settings.api_token && settings.user_key);
                default:
                    return false;
            }
        }
    },
    methods: {
        editChannel(channelKey) {
            this.selectedChannel = channelKey;
        },
        goBack() {
            this.selectedChannel = null;
        },
        getChannelComponent(channelKey) {
            const componentMap = {
                'telegram': 'telegram-notification',
                'slack': 'slack-notification',
                'discord': 'discord-notification',
                'pushover': 'pushover-notification'
            };
            return componentMap[channelKey] || null;
        },
        reloadSettings() {
            this.$emit('reload-settings');
        },
        loadChannels() {
            this.$get('settings/notification-channels')
                .then((response) => {
                    this.channels = response.data.channels || {};
                })
                .catch(() => {
                    // Fallback if API fails
                });
        }
    },
    mounted() {
        this.loadChannels();
    }
}
</script>
