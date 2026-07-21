<template>
    <div class="fluentmail_connections">
        <el-row :gutter="20">
            <el-col :md="14" :sm="24">
                <div class="fss_content_box">
                    <div class="fss_header">
                        <span style="float:left;">
                            {{$t('Active Email Connections')}}
                        </span>
                        <span style="float:right;">
                            <span
                                style="color:#67C23A;cursor:pointer;margin-right:16px;"
                                @click="exportConnections"
                            >
                                <i class="el-icon-download"></i> {{$t('Export')}}
                            </span>
                            <span
                                style="color:#46A0FC;cursor:pointer;margin-right:16px;"
                                @click="showImportDialog = true"
                            >
                                <i class="el-icon-upload2"></i> {{$t('Import')}}
                            </span>
                            <span
                                style="color:#46A0FC;cursor:pointer;"
                                @click="addConnection"
                            >
                                <i class="el-icon-plus"></i> {{$t('Add Another Connection')}}
                            </span>
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
                                    <span style="color: red;" v-if="scope.row.provider == 'gmail' && !scope.row.version">{{ $t('(Re Authentication Required)') }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="sender_email" :label="$t('From Email')">
                                <template slot-scope="scope">
                                    <span style="cursor: pointer;" @click="showConnection(scope.row)">{{ scope.row.sender_email }}</span>
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
                            {{$t('Connection Details')}}
                        </span>
                        <span style="float:right;color:#46A0FC;cursor:pointer;" @click="showing_connection = ''">
                            {{$t('Close')}}
                        </span>
                    </div>
                    <div class="fss_content">
                        <connection-details :connection_id="showing_connection" />
                    </div>
                </div>
            </el-col>
            <el-col :md="10" :sm="24">
                <div :class="{ fss_box_active: active_settings == 'general' }" style="margin-bottom: 0px;" class="fss_content_box fss_box_action">
                    <div @click="active_settings = 'general'" class="fss_header">
                        {{$t('General Settings')}}
                    </div>
                    <div v-if="active_settings == 'general'" class="fss_content">
                        <general-settings />
                    </div>
                </div>
            </el-col>
        </el-row>

        <el-dialog
            :title="$t('Import Connections')"
            :visible.sync="showImportDialog"
            width="500px"
            :close-on-click-modal="false"
        >
            <div style="padding: 10px 0;">
                <p style="margin-bottom: 15px; color: #666;">
                    {{ $t('Select a JSON file exported from another FluentSMTP installation. The connections will be imported and merged with your existing ones.') }}
                </p>
                <div>
                    <el-button size="small" type="primary" @click="selectImportFile">
                        <i class="el-icon-document"></i> {{ $t('Select JSON File') }}
                    </el-button>
                    <div style="color: #999; font-size: 12px; line-height: 1; margin-top: 10px;">
                        {{ $t('Only .json files are accepted') }}
                    </div>
                </div>

                <div v-if="importPreview" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <p style="font-weight: 600; margin-bottom: 10px;">
                        <i class="el-icon-info" style="color: #409EFF;"></i>
                        {{ $t('Connections to import:') }}
                    </p>
                    <div v-for="(conn, key) in importPreview" :key="key" style="padding: 6px 0; border-bottom: 1px solid #eee; font-size: 13px;">
                        <strong>{{ conn.title }}</strong>
                        <span style="color: #999;"> — {{ conn.provider_settings?.sender_email || 'N/A' }}</span>
                    </div>
                    <el-radio-group v-model="importMode" style="margin-top: 15px;">
                        <el-radio label="merge">{{ $t('Merge with existing connections') }}</el-radio>
                        <el-radio label="replace">{{ $t('Replace all existing connections') }}</el-radio>
                    </el-radio-group>
                </div>
            </div>
            <span slot="footer">
                <el-button @click="closeImportDialog">{{ $t('Cancel') }}</el-button>
                <el-button
                    type="primary"
                    :disabled="!importFileContent"
                    :loading="importing"
                    @click="importConnections"
                >
                    {{ $t('Import') }}
                </el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
    import Confirm from '@/Pieces/Confirm';
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
                showImportDialog: false,
                importFileContent: null,
                importPreview: null,
                importing: false,
                importMode: 'merge'
            };
        },
        methods: {
            async fetch() {
                const settings = await this.$get('settings');
                this.settings.mappings = settings.data.settings.mappings;
                this.settings.connections = settings.data.settings.connections;
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
            },
            async exportConnections() {
                try {
                    const response = await this.$get('settings/export-connections');
                    const exportData = response.data.export_data;
                    const json = JSON.stringify(exportData, null, 2);
                    const blob = new Blob([json], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'fluent-smtp-connections-' + exportData.source_url.replace(/[^a-z0-9]/gi, '-') + '.json';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);

                    this.$notify.success({
                        title: this.$t('Exported'),
                        message: this.$t('Connections exported successfully.'),
                        offset: 19
                    });
                } catch (error) {
                    this.$notify.error({
                        title: this.$t('Export Failed'),
                        message: error.message || this.$t('An error occurred while exporting connections.'),
                        offset: 19
                    });
                }
            },
            selectImportFile() {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = '.json';
                input.addEventListener('change', this.onImportFileChange);
                input.click();
            },
            onImportFileChange(event) {
                const file = event.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const data = JSON.parse(e.target.result);
                        if (!data.connections || Object.keys(data.connections).length === 0) {
                            this.$notify.error({
                                title: this.$t('Invalid File'),
                                message: this.$t('The selected file does not contain any connections.'),
                                offset: 19
                            });
                            this.importFileContent = null;
                            this.importPreview = null;
                            return;
                        }
                        this.importFileContent = e.target.result;
                        this.importPreview = data.connections;
                    } catch (err) {
                        this.$notify.error({
                            title: this.$t('Invalid File'),
                            message: this.$t('The selected file is not a valid JSON file.'),
                            offset: 19
                        });
                        this.importFileContent = null;
                        this.importPreview = null;
                    }
                };
                reader.readAsText(file);
            },
            onImportFileRemove() {
                this.importFileContent = null;
                this.importPreview = null;
            },
            closeImportDialog() {
                this.showImportDialog = false;
                this.importFileContent = null;
                this.importPreview = null;
                this.importing = false;
                this.importMode = 'merge';
            },
            async importConnections() {
                if (!this.importFileContent) return;

                this.importing = true;

                try {
                    const response = await jQuery.ajax({
                        url: window.ajaxurl + '?action=' + window.FluentMail.appVars.slug + '-post-settings/import-connections&nonce=' + window.FluentMail.appVars.nonce,
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            import_data: this.importFileContent,
                            mode: this.importMode
                        }),
                        processData: false
                    });

                    this.settings.connections = response.data.connections;
                    this.settings.mappings = response.data.mappings;
                    this.settings.misc = response.data.misc;

                    this.$notify.success({
                        title: this.$t('Imported'),
                        message: response.data.message,
                        offset: 19,
                        duration: 5000
                    });

                    this.closeImportDialog();
                } catch (error) {
                    const message = error.responseJSON?.data?.message || error.statusText || this.$t('An error occurred while importing connections.');
                    this.$notify.error({
                        title: this.$t('Import Failed'),
                        message: message,
                        offset: 19,
                        duration: 5000
                    });
                } finally {
                    this.importing = false;
                }
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
