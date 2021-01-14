<template>
    <div class="connections">
        <el-row :gutter="20">
            <el-col :span="12">
                <div class="fss_content_box">
                    <div class="header">
                        <span style="float:left;">
                            Active Email Connections
                        </span>
                        <span style="float:right;color:#46A0FC;cursor:pointer;" @click="addConnection">
                            <i class="el-icon-plus"></i> Add Another Connection
                        </span>
                    </div>
                    <div class="content">
                        <el-table stripe border :data="connections">

                            <el-table-column label="Provider">
                                <template slot-scope="scope">
                                    {{ settings.providers[scope.row.provider].title }}
                                </template>
                            </el-table-column>

                            <el-table-column prop="sender_email" label="From Email"/>

                            <el-table-column width="120" label="Actions" align="center">
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
                    </div>
                </div>
                <div v-if="showing_connection" class="fss_content_box">
                    <div class="header">
                        <span style="float:left;">
                            Connection Details
                        </span>
                        <span style="float:right;color:#46A0FC;cursor:pointer;" @click="showing_connection = ''">
                            Close
                        </span>
                    </div>
                    <div class="content">
                        <connection-details :connection_id="showing_connection" />
                    </div>
                </div>
            </el-col>
            <el-col :span="12">
                <div class="fss_content_box">
                    <div class="header">
                        General Settings
                    </div>
                    <div class="content">
                        <general-settings />
                    </div>
                </div>
            </el-col>
        </el-row>
    </div>
</template>

<script>
    import Confirm from '@/Pieces/Confirm';
    // import Pagination from '@/Pieces/Pagination';
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
                const connections = await this.$post('settings/delete', {
                    key: connection.unique_key
                });

                this.settings.connections = connections.data;

                this.$notify.success({
                    title: 'Great!',
                    message: 'Connection deleted Successfully.',
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
