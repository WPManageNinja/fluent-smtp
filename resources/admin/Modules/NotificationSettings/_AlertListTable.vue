<template>
    <div>
        <p>{{ $t('__REAL_NOTIFCATION_DESC') }}</p>
        <el-table :data="alerts" class="fss_alert_list_table__table" v-loading="loading">
            <el-table-column :label="$t('Channel')" min-width="200">
                <template slot-scope="scope">
                    <div class="fss_alert_list_table__channel-cell">
                        <img :src="scope.row.logo" class="fss_alert_list_table__logo" :alt="scope.row.title"/>
                        <span>{{ scope.row.title }}</span>
                    </div>
                </template>
            </el-table-column>

            <el-table-column :label="$t('Status')" width="100" align="center">
                <template slot-scope="scope">
                    <el-switch
                        v-model="scope.row.is_active"
                        active-value="yes"
                        inactive-value="no"
                        @change="toggleChannel()"
                        :disabled="toggling || !scope.row.is_configured"
                        :aria-label="scope.row.is_active ? $t('Enabled') : $t('Disabled') + ' - ' + scope.row.title">
                    </el-switch>
                </template>
            </el-table-column>

            <el-table-column :label="$t('Actions')" width="150" align="right">
                <template slot-scope="scope">
                    <el-button
                        size="mini"
                        :type="scope.row.is_configured ? 'primary' : 'success'"
                        :icon="scope.row.is_configured ? 'el-icon-edit' : 'el-icon-plus'"
                        @click="editChannel(scope.row.key)"
                        :aria-label="(scope.row.is_configured ? $t('Edit') : $t('Configure')) + ' ' + scope.row.title">
                    </el-button>
                    <el-button
                        v-if="scope.row.is_configured"
                        size="mini"
                        type="danger"
                        icon="el-icon-delete"
                        @click="deactivateChannel(scope.row.key)"
                        :aria-label="$t('Deactivate') + ' ' + scope.row.title">
                    </el-button>
                </template>
            </el-table-column>
        </el-table>

        <div style="margin-top: 20px;" v-if="activatedChannelsCount > 1">
            <el-alert
                type="info"
                :closable="false"
                :title="$t('We recommend activating only one notification channel at a time.')"
                show-icon>
            </el-alert>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'AlertListTable',
    props: {
        notification_settings: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            alerts: [],
            loading: false,
            toggling: false
        }
    },
    computed: {
        activatedChannelsCount() {
            return this.alerts.filter(alert => alert.is_active === 'yes').length;
        }
    },
    methods: {
        loadChannels() {
            this.loading = true;
            this.$get('settings/notification-channels')
                .then((response) => {
                    let activeChannels = this.notification_settings.active_channel || [];
                    if (typeof activeChannels !== 'object') {
                        activeChannels = [];
                    }

                    const channels = response.data.channels || {};
                    this.alerts = Object.keys(channels).map(key => {
                        const channel = channels[key];
                        const settings = this.notification_settings[key] || {};
                        // Check if channel is configured by checking if status is 'yes' and has required settings
                        // Each channel may have different required fields, so we check if status is yes and settings exist
                        const isConfigured = settings.status === 'yes' && Object.keys(settings).length > 1;

                        console.log(activeChannels.indexOf(key), key, isConfigured);

                        return {
                            key: key,
                            title: channel.title,
                            logo: channel.logo,
                            is_active: (isConfigured && (activeChannels.indexOf(key) != -1)) ? 'yes' : 'no',
                            status: channel.status || 'no',
                            is_configured: isConfigured
                        };
                    });
                })
                .catch((errors) => {
                    console.log(errors);
                    this.$notify.error(this.$t('Failed to load notification channels'));
                })
                .always(() => {
                    this.loading = false;
                });
        },
        toggleChannel() {
            const enabledChannelKeys = [];
            this.alerts.forEach(alert => {
                if (alert.is_active === 'yes' && alert.status === 'yes') {
                    enabledChannelKeys.push(alert.key);
                }
            });

            this.toggling = true;
            this.$post('settings/notification-channels/toggle', {
                channel_keys: enabledChannelKeys
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                    // Update local state
                    this.alerts.forEach(alert => {
                        if (alert.key === channelKey) {
                            alert.is_active = enable;
                        } else if (enable) {
                            // Disable others when enabling one
                            alert.is_active = false;
                        }
                    });
                    // Emit event to parent to reload settings
                    this.$emit('channel-toggled');
                })
                .catch((errors) => {
                    // Revert the toggle
                    const alert = this.alerts.find(a => a.key === channelKey);
                    if (alert) {
                        alert.is_active = !enable;
                    }
                    this.$notify.error(errors.responseJSON?.data?.message || this.$t('Failed to toggle channel'));
                })
                .always(() => {
                    this.toggling = false;
                });
        },
        editChannel(channelKey) {
            this.$emit('edit-channel', channelKey);
        },
        deactivateChannel(channelKey) {
            this.$confirm(
                this.$t('Are you sure you want to deactivate and remove settings for this channel?'),
                this.$t('Warning'),
                {
                    confirmButtonText: this.$t('Yes, Deactivate'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning'
                }
            )
                .then(() => {
                    // Call the disconnect endpoint for the specific channel
                    this.$post(`settings/${channelKey}/disconnect`)
                        .then((response) => {
                            this.$notify.success(response.data.message);
                            // Update local state immediately
                            const alert = this.alerts.find(a => a.key === channelKey);
                            if (alert) {
                                alert.is_configured = false;
                                alert.is_active = false;
                                alert.status = 'no';
                            }
                            // Reload channels to get fresh data from server
                            this.loadChannels();
                            // Emit event to parent to reload settings
                            this.$emit('channel-toggled');
                        })
                        .catch((errors) => {
                            this.$notify.error(errors.responseJSON?.data?.message || this.$t('Failed to deactivate channel'));
                        });
                })
                .catch(() => {
                    // User cancelled
                });
        }
    },
    mounted() {
        this.loadChannels();
    },
    watch: {
        notification_settings: {
            handler() {
                this.loadChannels();
            },
            deep: true
        }
    }
}
</script>
