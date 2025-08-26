<template>
    <div class="logs">
        <div>
            <div v-if="!isLogsOn">
                <div class="fss_content">
                    <el-alert :closable="false" show-icon center>
                        {{ $t('__EMAIL_LOGGING_OFF') }}
                        <el-button type="text" @click="turnOnEmailLogging">{{ $t('Turn On') }}</el-button>
                        .
                    </el-alert>
                </div>
            </div>
            <div class="fss_header">
                <LogBulkAction
                    @on-bulk-action="handleBulkAction"
                    :selected="selectedLogs"
                    v-if="selectedLogs.length"
                />
                <div style="float:left;margin-top:6px;">{{ $t('Email Logs') }}</div>
                <div style="float:right;margin-left: 6px;"><el-button @click="fetch" type="success" size="small" ><i class="el-icon-refresh"></i></el-button></div>

                <LogFilter
                    :filter_query="filter_query"
                    @on-filter="fetch()"
                    @reset-page="pagination.current_page=1"
                />

                <div style="float:right;">
                    <el-input
                        clearable
                        size="small"
                        v-model="filter_query.search"
                        @clear="filter_query.search=''"
                        @keyup.enter.native="fetch"
                        :placeholder="$t('Type & press enter...')"
                    >
                        <el-button slot="append" icon="el-icon-search" @click="fetch"/>
                    </el-input>
                </div>

            </div>

            <div v-if="!loading" class="fss_content">
                <el-table
                    stripe
                    :data="logs"
                    v-loading="loading"
                    style="width:100%"
                    :row-class-name="tableRowClassName"
                    @selection-change="handleSelectionChange"
                >
                    <el-table-column type="selection" width="55"/>
                    <el-table-column :label="$t('Subject')">
                        <template slot-scope="scope">
                            <span style="cursor: pointer" @click="handleView(scope.row)">{{ scope.row.subject }}</span>
                            <span v-if="scope.row.extra && scope.row.extra.provider == 'Simulator'"
                                  style="color: #ff0000;">{{ $t(' - Simulated') }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('To')">
                        <template slot-scope="scope">
                            <span v-html="scope.row.to"></span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('Status')" width="120" align="center">
                        <template slot-scope="scope">
                            {{ scope.row.status }}
                        </template>
                    </el-table-column>

                    <el-table-column prop="created_at" :label="$t('Date-Time')" width="200px">
                        <template slot-scope="scope">
                            {{ $dateFormat(scope.row.created_at, 'DD MMM YYYY LT') }}
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('Actions')" width="200px" align="right">
                        <template slot-scope="scope">
                            <el-button
                                size="mini"
                                type="success"
                                icon="el-icon-refresh"
                                @click="handleRetry(scope.row, 'retry')"
                                :plain="true"
                                v-if="scope.row.status == 'failed'"
                            >{{ $t('Retry') }}
                            </el-button>
                            <el-button
                                size="mini"
                                type="success"
                                icon="el-icon-refresh-right"
                                @click="handleRetry(scope.row, 'resend')"
                                v-if="scope.row.status == 'sent'"
                            >
                                {{ $t('Resend') }}
                                <span v-if="scope.row.resent_count > 0">({{ scope.row.resent_count }})</span>
                            </el-button>

                            <el-button
                                size="mini"
                                type="primary"
                                icon="el-icon-view"
                                @click="handleView(scope.row)"
                            />

                            <confirm @yes="handleDelete(scope.row.id)">
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

                <el-row :gutter="20">
                    <el-col :span="12">
                        <div v-if="logs.length" style="margin-top:20px;">
                            <confirm placement="right" :message="$t('Are you sure, you want to delete all the logs?')"
                                     @yes="handleDelete(['all'])">
                                <el-button slot="reference" size="mini" type="info">{{ $t('Delete All Logs') }}</el-button>
                            </confirm>
                        </div>
                        <span v-else>&nbsp;</span>
                    </el-col>
                    <el-col :span="12">
                        <div style="margin-top:20px;text-align:right;">
                            <pagination :pagination="pagination" @fetch="pageChanged"/>
                        </div>
                    </el-col>
                </el-row>
            </div>
            <el-skeleton :animated="true" v-else class="fss_content" :rows="15"></el-skeleton>

            <LogViewer :logViewerProps="logViewerProps"/>
        </div>
    </div>
</template>

<script type="text/babel">
import Confirm from '@/Pieces/Confirm';
import Pagination from '@/Pieces/Pagination';
import LogFilter from './LogFilter';
import LogViewer from './LogViewer';
import LogBulkAction from './BulkAction';
import isEmpty from 'lodash/isEmpty'

export default {
    name: 'EmailLog',
    components: {
        Confirm,
        Pagination,
        LogFilter,
        LogViewer,
        LogBulkAction
    },
    data() {
        return {
            log: null,
            logs: [],
            saving: false,
            loading: false,
            deleting: false,
            logViewerProps: {
                log: null,
                dialogVisible: false
            },
            pagination: {
                total: 0,
                per_page: 10,
                current_page: 1
            },
            filter_query: {
                status: '',
                date_range: [],
                search: ''
            },
            selectedLogs: [],
            form: null,
            logAlertInfo: null
        };
    },
    methods: {
        tableRowClassName({row}) {
            return 'row_type_' + row.status;
        },
        pageChanged() {
            this.fetch();
        },
        fetch() {
            this.loading = true;
            const data = {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                status: this.filter_query.status,
                date_range: this.filter_query.date_range,
                search: this.filter_query.search
            };

            this.$router.replace({ query: data }).catch(err => {
              if (err.name !== 'NavigationDuplicated') {
                console.error(err);
              }
            });

            this.$get('logs', data).then(res => {
                this.logs = this.formatLogs(res.data);
                this.pagination.total = res.total;
                const page = Number(this.$route.query.page);
                this.pagination.current_page = page || this.pagination.current_page;
            }).fail(error => {
                console.log(error);
            }).always(() => {
                this.loading = false;
            });
        },
        formatLogs(logs) {
            jQuery.each(logs, (i, log) => {
                logs[i] = this.formatLog(log);
            });

            return logs;
        },
        formatLog(log) {
            log.to = this.formatAddresses(log.to);
            return log;
        },
        formatAddresses(addresses) {
            if (!addresses) {
                return '';
            }

            if (isEmpty(addresses)) {
                return '';
            }

            if(typeof addresses == 'string') {
                return addresses;
            }

            const result = [];
            jQuery.each(addresses, (i, val) => {
                if (val.name) {
                    result[i] = this.escapeHtml(
                        `${val.name} <${val.email}>`
                    );
                } else {
                    result[i] = this.escapeHtml(val.email);
                }
            });
            return result.join(', ');
        },
        onFilter(queryData) {
            this.pagination.current_page = 1;
            this.pageChanged();
        },
        onSearch(query) {
            this.query = query;
            this.pagination.current_page = 1;
            this.pageChanged();
            this.fetch();
        },
        onSearchChange(query) {
            this.query = query;
            this.fetch();
        },
        handleBulkAction({action}) {
            if (action === 'deleteall') {
                return this.handleDelete('all');
            } else if (action === 'deleteselected') {
                return this.handleDelete(this.selectedLogs);
            } else if (action === 'resend_selected') {
                return this.handleResendBulk(this.selectedLogs);
            }
        },
        handleRetry(row, type) {
            this.loading = true;
            this.$post('logs/retry', {
                id: row.id,
                type: type
            }).then(res => {
                if (!res.data.email) {
                    this.$notify.error({
                        offset: 19,
                        title: 'Oops!!',
                        message: res.data.message
                    });
                    return false;
                }
                row.status = res.data.email.status;
                row.retries = res.data.email.retries;
                row.resent_count = res.data.email.resent_count;
                row.updated_at = res.data.email.updated_at;
                this.$notify.success({
                    offset: 19,
                    title: 'Great!',
                    message: res.data.message
                });
            }).fail(error => {
                this.$notify.error({
                    offset: 19,
                    title: 'Oops!!',
                    message: error.responseJSON.data.message
                });
            }).always(() => {
                this.loading = false;
            });
        },
        handleView(row) {
            this.logViewerProps.log = row;
            this.logViewerProps.dialogVisible = true;

            this.$nextTick(() => {
                this.logViewerProps.query = this.query;
                this.logViewerProps.filterBy = this.filterBy;
                this.logViewerProps.filterByValue = this.filterByValue;

                const logViewer = this.$children.find(
                    c => c.$options._componentTag === 'LogViewer'
                );

                logViewer && logViewer.navigate();
            });
        },
        handleDelete(id) {
            this.deleting = true;
            this.$post('logs/delete', {id: id}).then(res => {
                this.fetch();
                this.$notify.success({
                    offset: 19,
                    title: 'Great!',
                    message: res.data.message
                });
            }).fail(error => {
                console.log(error);
            }).always(() => {
                this.deleting = false;
            });
        },
        handleSelectionChange(selectedRows) {
            this.selectedLogs = selectedRows.map(i => Number(i.id));
        },
        saveMisc() {
            this.loading = true;
            this.$post('misc-settings', {
                settings: this.form
            })
                .then(response => {
                    this.$notify.success(response.data.message);
                })
                .catch((error) => {
                    console.log(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        dontShowStatusInfo(key) {
            if (key === 'icons') {
                this.logAlertInfo.show_status_info = false;
            } else {
                this.logAlertInfo.show_status_warning = false;
            }

            window.localStorage.setItem(
                'log-settings',
                JSON.stringify(this.logAlertInfo)
            );
        },
        turnOnEmailLogging() {
            this.form.log_emails = 'yes';
            this.saveMisc();
        },
        handleResendBulk(selectedIds) {
            if (selectedIds.length > 20) {
                this.$notify.error({
                    offset: 19,
                    title: 'Oops!!',
                    message: 'Sorry, You can not resend more than 20 emails at once'
                });
                return false;
            }

            this.loading = true;
            this.$post('logs/retry-bulk', {
                log_ids: selectedIds
            }).then(res => {
                this.$notify.success({
                    offset: 19,
                    title: 'Result',
                    message: res.data.message
                });
                this.selectedLogs = [];
                this.fetch();
            })
                .fail(error => {
                    this.$notify.error({
                        offset: 19,
                        title: 'Oops!!',
                        message: error.responseJSON.data.message
                    });
                }).always(() => {
                this.loading = false;
            });
        }
    },
    computed: {
        isLogsOn() {
            return this.form.log_emails === 'yes';
        },
        logStatusInfo() {
            return this.logAlertInfo.show_status_info;
        },
        logStatusWarning() {
            return this.logAlertInfo.show_status_warning;
        }
    },
    created() {
        const currentPage = this.$route.query.page;

        if (currentPage) {
            this.pagination.current_page = Number(currentPage);
        }

        if(this.$route.query.status) {
            this.filter_query.status = this.$route.query.status;
        }

        if(this.$route.query.search) {
            this.filter_query.search = this.$route.query.search;
        }

        this.form = this.appVars.settings.misc;

        this.logAlertInfo = window.localStorage.getItem('log-settings');

        if (!this.logAlertInfo) {
            window.localStorage.setItem('log-settings', JSON.stringify({
                show_status_info: true,
                show_status_warning: true
            }));
        }

        this.logAlertInfo = JSON.parse(window.localStorage.getItem('log-settings'));
        this.fetch();
    }
};
</script>
