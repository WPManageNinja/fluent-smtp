<template>
    <div class="fluentmail_connections">
        <div class="fsm_page_head">
            <h1 class="fsm_page_title">{{ $t('Active Email Connections') }}</h1>
            <div class="fsm_page_actions">
                <el-button type="primary" size="small" icon="FsmIconPlus" @click="addConnection">
                    {{ $t('Add Another Connection') }}
                </el-button>
            </div>
        </div>

        <div class="fsm_card">
            <div class="fsm_card_body fsm_card_flush">
                <!-- Flush and unbordered: the card already draws the frame. -->
                <el-table class="fsm_table" :data="connections">
                    <el-table-column :label="$t('Provider')">
                        <template #default="scope">
                            <span class="fsm_provider" v-if="settings.providers[scope.row.provider]">
                                <img
                                    :title="settings.providers[scope.row.provider]?.title"
                                    :src="settings.providers[scope.row.provider]?.image"
                                    :alt="settings.providers[scope.row.provider]?.title"
                                />
                                <span>{{ settings.providers[scope.row.provider]?.title }}</span>
                            </span>
                            <span v-else>{{ $t('Unknown') }}</span>
                            <span class="fsm_tag is_failed"
                                  v-if="scope.row.provider == 'gmail' && !scope.row.version">
                                {{ $t('(Re Authentication Required)') }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column prop="sender_email" :label="$t('From Email')">
                        <template #default="scope">
                            <span style="cursor: pointer;" @click="showConnection(scope.row)">{{ scope.row.sender_email }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column width="160" :label="$t('Actions')" align="right">
                        <template #default="scope">
                            <el-button
                                size="small"
                                icon="FsmIconEdit"
                                :title="$t('Edit')"
                                @click="editConnection(scope.row)"
                            />
                            <el-button
                                size="small"
                                icon="FsmIconView"
                                :title="$t('View')"
                                @click="showConnection(scope.row)"
                            />
                            <confirm @yes="deleteConnection(scope.row)">
                                <template #reference>
                                    <el-button
                                        size="small"
                                        type="danger"
                                        plain
                                        icon="FsmIconDelete"
                                        :title="$t('Delete')"
                                    />
                                </template>
                            </confirm>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>

        <el-alert :closable="false" type="info" v-if="connections.length > 1"
                  style="margin-bottom: 20px;">
            {{ $t('__routing_info') }}
        </el-alert>

        <div v-if="showing_connection" class="fsm_card">
            <div class="fsm_card_head">
                <h2>{{ $t('Connection Details') }}</h2>
                <div class="fsm_card_head_actions">
                    <el-button link @click="showing_connection = ''">{{ $t('Close') }}</el-button>
                </div>
            </div>
            <div class="fsm_card_body">
                <connection-details :connection_id="showing_connection" />
            </div>
        </div>

        <!--
            The General Settings panel. It is a separate subject from the connection list
            above it, which is why the settings sidebar names it separately and scrolls
            here - see settingsNav in Application.vue.
        -->
        <div class="fsm_card fsm_general_settings">
            <div class="fsm_card_head">
                <h2>{{ $t('General Settings') }}</h2>
            </div>
            <div class="fsm_card_body">
                <general-settings />
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
    import Confirm from '@/Pieces/Confirm';
    import isEmpty from 'lodash/isEmpty';
    import GeneralSettings from './_GeneralSettings'

    import ConnectionDetails from './ConnectionDetails'

    export default {
        name: 'Connections',
        components: {
            Confirm,
            GeneralSettings,
            ConnectionDetails
        },
        data() {
            return {
                showing_connection: ''
            };
        },
        methods: {
            async fetch() {
                const settings = await this.$get('settings');
                this.settings.mappings = settings.data.settings.mappings;
                this.settings.connections = settings.data.settings.connections;

                if (isEmpty(this.settings.connections)) {
                    this.$router.push({
                        name: 'dashboard',
                        query: {
                            is_redirect: 'yes'
                        }
                    });
                }
            },
            addConnection() {
                this.$router.push({ name: 'connection' });
            },
            editConnection(connection) {
                this.$router.push({
                    name: 'connection',
                    query: { connection_key: connection.unique_key }
                });
            },
            async deleteConnection(connection) {
                const result = await this.$post('settings/delete', {
                    key: connection.unique_key
                });

                this.settings.connections = result.data.connections;
                this.settings.misc.default_connection = result.data.misc.default_connection;

                this.$notify.success({
                    title: 'Great!',
                    message: this.$t('Connection deleted Successfully.'),
                    offset: 19
                });
            },
            showConnection(connection) {
                this.showing_connection = '';
                this.$nextTick(() => {
                    this.showing_connection = connection.unique_key;
                });
            }
        },
        computed: {
            connections() {
                const data = [];

                jQuery.each(this.settings.connections, (key, connection) => {
                    data.push({
                        unique_key: key,
                        title: connection.title,
                        ...connection.provider_settings
                    });
                });

                return data;
            }
        },
        created() {
            this.fetch();
        }
    };
</script>

<style lang="scss">
/* Logo and name together, so the column reads as a name rather than as a picture. */
.fsm_provider {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    img {
        max-height: 22px;
        max-width: 72px;
        display: block;
    }
}
</style>
