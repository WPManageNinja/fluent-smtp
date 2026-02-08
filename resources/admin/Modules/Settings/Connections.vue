<template>
    <div class="fluentmail_connections">
        <el-row :gutter="20">
            <el-col :md="14" :sm="24">
                <div class="fss_content_box">
                    <div class="fss_header">
                        <span style="float:left;">
                            {{ $t('Active Email Connections') }}
                        </span>
                        <span
                            style="float:right;color:#46A0FC;cursor:pointer;"
                            @click="addConnection"
                        >
                            <i class="el-icon-plus"></i> {{ $t('Add Another Connection') }}
                        </span>
                    </div>
                    <div class="fss_content">
                        <el-table stripe border :data="connections">
                            <el-table-column :label="$t('Provider')">
                                <template slot-scope="scope">

                                    <span v-if="settings.providers[scope.row.provider]">
                                        <img
                                            :title="settings.providers[scope.row.provider]?.title"
                                            :src="settings.providers[scope.row.provider]?.image"
                                            :alt="settings.providers[scope.row.provider]?.title"
                                            style="max-height: 24px; vertical-align: middle; margin-right: 8px;"
                                        />
                                    </span>
                                    <span v-else>Unknown</span>
                                    <span style="color: red;"
                                          v-if="scope.row.provider == 'gmail' && !scope.row.version">{{
                                            $t('(Re Authentication Required)')
                                        }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="sender_email" :label="$t('From Email')">
                                <template slot-scope="scope">
                                    <span style="cursor: pointer;"
                                          @click="showConnection(scope.row)">{{ scope.row.sender_email }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column width="120" :label="$t('Actions')" align="center">
                                <template slot-scope="scope">
                                    <el-button
                                        type="primary"
                                        size="mini"
                                        icon="el-icon-edit"
                                        @click="editConnection(scope.row)"
                                    />
                                    <el-button
                                        type="info"
                                        size="mini"
                                        icon="el-icon-view"
                                        @click="showConnection(scope.row)"
                                    />
                                    <confirm @yes="deleteConnection(scope.row)">
                                        <el-button
                                            size="mini"
                                            type="danger"
                                            icon="el-icon-delete"
                                            slot="reference"
                                        />
                                    </confirm>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-alert :closable="false" style="margin-top: 20px" type="info" v-if="connections.length > 1">
                            {{ $t('__routing_info') }}
                        </el-alert>
                    </div>
                </div>
                <div v-if="showing_connection" class="fss_content_box">
                    <div class="fss_header">
                        <span style="float:left;">
                            {{ $t('Connection Details') }}
                        </span>
                        <span style="float:right;color:#46A0FC;cursor:pointer;" @click="showing_connection = ''">
                            {{ $t('Close') }}
                        </span>
                    </div>
                    <div class="fss_content">
                        <connection-details :connection_id="showing_connection"/>
                    </div>
                </div>
            </el-col>
            <el-col :md="10" :sm="24">
                <div :class="{ fss_box_active: active_settings == 'general' }" style="margin-bottom: 0px;"
                     class="fss_content_box fss_box_action">
                    <div @click="active_settings = 'general'" class="fss_header">
                        {{ $t('General Settings') }}
                    </div>
                    <div v-if="active_settings == 'general'" class="fss_content">
                        <general-settings/>
                    </div>
                </div>
            </el-col>
        </el-row>
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
            showing_connection: '',
            active_settings: 'general',
            loading: false,
            deleting: false
        };
    },
    methods: {
        fetch() {
            this.loading = true;
            this.$get('settings')
                .then((response) => {
                    this.settings.mappings = response.settings.mappings;
                    this.settings.connections = response.settings.connections;
                    if (isEmpty(this.settings.connections)) {
                        this.$router.push({
                            name: 'dashboard',
                            query: {
                                is_redirect: 'yes'
                            }
                        });
                    }
                })
                .catch((error) => {
                    console.log(error);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        addConnection() {
            this.$router.push({name: 'connection'});
        },
        editConnection(connection) {
            this.$router.push({
                name: 'connection',
                query: {connection_key: connection.unique_key}
            });
        },
        deleteConnection(connection) {

            this.deleting = true;

            this.$post('settings/delete', {
                key: connection.unique_key
            })
                .then((response) => {
                    this.settings.connections = response.connections;
                    this.settings.misc.default_connection = response.misc.default_connection;
                    this.$notify.success({
                        title: 'Great!',
                        message: this.$t('Connection deleted Successfully.'),
                        offset: 19
                    });
                })
                .catch((error) => {
                    console.log(error);
                })
                .finally(() => {
                    this.deleting = false;
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
