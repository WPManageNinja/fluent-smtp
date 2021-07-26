<template>
    <div class="logs">
        <div>
            <div class="header">
                <div style="float:left;margin-top:6px;">Email Logs</div>
                
                <LogFilter
                    @on-filter="onFilter"
                    @on-filter-change="onFilterChange"
                    @reset-page="pagination.current_page=1"
                />

                <LogBulkAction
                    @on-bulk-action="handleBulkAction"
                    :selected="selectedLogs"
                    :haslogs="logs.length"
                    :filterBy="filterBy"
                    :filterByValue="filterByValue"
                />

                <div style="float:right;">
                    <el-input
                        clearable
                        size="small"
                        v-model="query"
                        @clear="query=''"
                        @keyup.enter.native="fetch"
                        placeholder="Type & press enter..."
                    >
                        <el-button slot="append" icon="el-icon-search" @click="fetch" />
                    </el-input>
                </div>
            </div>

            <div class="content">

                <el-table
                    stripe
                    :data="emailLogs"
                    v-loading="loading"
                    style="width:100%"
                    :row-class-name="tableRowClassName"
                    @selection-change="handleSelectionChange"
                >
                    <el-table-column type="selection" width="55" />
                    <el-table-column label="Subject">
                        <template slot-scope="scope">
                            <div>{{ scope.row.subject }}</div>
                        </template>
                    </el-table-column>
                    
                    <el-table-column label="To">
                        <template slot-scope="scope">
                            <span v-html="scope.row.to"></span>
                        </template>
                    </el-table-column>
                    
                    <el-table-column label="Status" width="120" align="center">
                        <template slot-scope="scope">
                            {{ scope.row.status }}
                        </template>
                    </el-table-column>

                    <el-table-column prop="created_at" label="Date-Time" width="200px" />

                    <!-- <el-table-column prop="retries" label="Retries" width="200px" /> -->

                    <el-table-column label="Actions" width="190px" align="right">
                        <template slot-scope="scope">
                            <el-button
                                size="mini"
                                type="success"
                                icon="el-icon-refresh"
                                @click="handleRetry(scope.row, 'retry')"
                                :plain="true"
                                v-if="scope.row.status == 'failed'"
                            >Retry</el-button>
                            <el-button
                                size="mini"
                                type="success"
                                icon="el-icon-refresh-right"
                                @click="handleRetry(scope.row, 'resend')"
                                v-if="scope.row.status == 'sent'"
                            >
                                Resend
                                <span v-if="scope.row.resent_count > 0">({{scope.row.resent_count}})</span>
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

                <div style="margin-top:20px;text-align:right;">
                    <pagination :pagination="pagination" @fetch="pageChanged" />
                </div>
            </div>

            <LogViewer :logViewerProps="logViewerProps" />
        </div>
        <div v-if="!isLogsOn">
            <div class="content">
                <el-alert :closable="false" show-icon center>
                    Email Logging is currently turned off. Only Failed and resent emails will be shown here
                    <el-button type="text" @click="turnOnEmailLogging">Turn On</el-button>.
                </el-alert>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
    import Confirm from '@/Pieces/Confirm';
    import Pagination from '@/Pieces/Pagination';
    import LogFilter from './LogFilter';
    import LogViewer from './LogViewer';
    import LogBulkAction from './BulkAction';

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
                query: '',
                filterBy: '',
                filterByValue: '',
                logViewerProps: {
                    log: null,
                    dialogVisible: false
                },
                pagination: {
                    total: 0,
                    per_page: 10,
                    current_page: 1
                },
                selectedLogs: [],
                form: null,
                logAlertInfo: null
            };
        },
        methods: {
            tableRowClassName({ row }) {
                return 'row_type_' + row.status;
            },
            pageChanged() {
                this.$router.push({
                    name: 'logs',
                    query: {
                        search: this.query,
                        filterBy: this.filterBy,
                        filterValue: this.filterByValue,
                        page: this.pagination.current_page,
                        per_page: this.pagination.per_page
                    }
                }).catch(e => {
                    if (e.name !== 'NavigationDuplicated') {
                        console.log(e.message);
                    }
                });
            },
            fetch() {
                this.loading = true;

                const data = {
                    per_page: this.pagination.per_page,
                    page: this.pagination.current_page,
                    filter_by_value: this.filterByValue,
                    filter_by: this.filterBy,
                    query: this.query
                };

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
                log.headers.cc = this.formatAddresses(log.headers.cc);
                log.headers.bcc = this.formatAddresses(log.headers.bcc);
                log.headers['reply-to'] = this.formatAddresses(log.headers['reply-to']);
                
                const headers = {};
                if(log.headers) {
                    jQuery.each(log.headers, (k, v) => {
                        if(k) {
                            k = k.split('-').map(s => this.ucFirst(s)).join('-');
                            headers[k] = v;
                        }
                    });
                }
                log.headers = headers;

                return log;
            },
            formatAddresses(addresses) {
                if(!addresses) {
                    return '';
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
            onFilter(filterBy, value) {
                this.filterBy = filterBy;
                this.filterByValue = value;
                this.pageChanged();
            },
            onFilterChange(filterBy, value) {
                this.filterBy = filterBy;
                this.filterByValue = value;
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
            handleBulkAction({ action }) {
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
                this.$post('logs/delete', { id: id }).then(res => {
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
        watch: {
            $route: {
                immediate: true,
                handler: function(to, from) {
                    const currentPage = this.pagination.current_page;
                    const perPage = this.pagination.per_page;
                    this.query = to.query.search || this.query;
                    this.filterBy = to.query.filterBy || this.filterBy;
                    this.filterBy = to.query.filterBy || this.filterBy;
                    this.filterByValue = to.query.filterValue || this.filterByValue;
                    this.pagination.current_page = Number(to.query.page) || currentPage;
                    this.pagination.per_page = Number(to.query.per_page) || perPage;
                    this.fetch();
                }
            }
        },
        computed: {
            isLogsOn() {
                return this.form.log_emails === 'yes';
            },
            emailLogs() {
                return this.logs.map(log => {
                    log.created_at = this.$dateFormat(
                        log.created_at,
                        'DD-MM-YYYY h:mm:ss A'
                    );
                    return log;
                });
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

            this.form = this.appVars.settings.misc;

            this.logAlertInfo = window.localStorage.getItem('log-settings');

            if (!this.logAlertInfo) {
                window.localStorage.setItem('log-settings', JSON.stringify({
                    show_status_info: true,
                    show_status_warning: true
                }));
            }

            this.logAlertInfo = JSON.parse(window.localStorage.getItem('log-settings'));
        }
    };
</script>
