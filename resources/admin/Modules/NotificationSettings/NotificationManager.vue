<template>
    <div v-if="notification_settings.telegram !== undefined">
        <template v-if="selectedChannel">
            <channel-header
                :title="channelConfig.title"
                :logo="channelConfig.logo"
                @back="goBack"
            />
            <component
                :is="getChannelComponent(selectedChannel)"
                :notification_settings="notification_settings"
                :channel_key="selectedChannel"
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
import ChannelHeader from './_ChannelHeader.vue';

export default {
    name: 'NotificationManager',
    components: {
        AlertListTable,
        TelegramNotification,
        SlackNotification,
        DiscordNotification,
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
                'discord': 'discord-notification'
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
