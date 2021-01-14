<template>
    <div class="logs">
        <div v-if="isLogsOn">
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
                />

                <LogSearch
                    @on_search="onSearch"
                    @on_search_change="onSearchChange"
                    @reset-page="pagination.current_page=1"
                />
            </div>

            <div class="content">

                <div v-if="logStatusInfo || logStatusWarning">
                    <el-alert style="margin-bottom:10px;" v-if="logStatusInfo">
                        <span>
                            <i class="el-icon-circle-check successful"></i>
                            <span style="vertical-align:top;">Successfully sent at the first time.</span>
                        </span>

                        <span>
                            <i class="el-icon-circle-close unsuccessful"></i>
                            <span style="vertical-align:top;">Sending failed at the first time.</span>
                        </span>

                        <span>
                            <i class="el-icon-refresh resent"></i>
                            <span style="vertical-align:top;">
                                Resent (Successfully sent after failed attempt.)
                            </span>
                        </span>

                        <span class="dont-show" @click="dontShowStatusInfo('icons')">
                            Don't show again
                        </span>
                    </el-alert>

                    <el-alert
                        show-icon
                        type="warning"
                        style="margin-bottom:10px;"
                        v-if="logStatusWarning"
                    >
                        The status successful, unsuccessful or resent doesn't mean that emails delived to the recipients, or failed to deliver. It simply means that, the plugin sent the emails to the selected mailer service provider successfully. It's upto the mailer service provider you are using to send and track the delivery status.

                        <span class="dont-show" @click="dontShowStatusInfo('message')">
                            Don't show again
                        </span>
                    </el-alert>
                </div>

                <el-table
                    :data="emailLogs"
                    v-loading="loading"
                    style="width:100%"
                    @selection-change="handleSelectionChange"
                >
                    <el-table-column type="selection" width="55" />
                    <el-table-column label="Subject" width="300">
                        <template slot-scope="scope">
                            <div>{{ scope.row.subject }}</div>
                        </template>
                    </el-table-column>
                    
                    <el-table-column label="From">
                        <template slot-scope="scope">
                            <span v-html="scope.row.from"></span>
                        </template>
                    </el-table-column>
                    
                    <el-table-column label="Status" width="120" align="center">
                        <template slot-scope="scope">
                            {{ scope.row.status }}
                        </template>
                    </el-table-column>

                    <el-table-column prop="created_at" label="Date-Time" width="200px" />

                    <!-- <el-table-column prop="retries" label="Retries" width="200px" /> -->

                    <el-table-column label="Actions" width="160px" align="right">
                        <template slot-scope="scope">
                            <el-button
                                size="mini"
                                type="success"
                                icon="el-icon-refresh"
                                @click="handleRetry(scope.row)"
                                :plain="scope.row.status!=0"
                                :disabled="scope.row.status!='failed'"
                            />

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

        <div v-else>
            <div class="header">Email Logs</div>
            <div class="content">
                <el-alert :closable="false" show-icon center>
                    Email Logging is currently turned off.
                    <el-button type="text" @click="turnOnEmailLogging">Turn On</el-button>.
                </el-alert>
            </div>
        </div>
    </div>
</template>

<script>
    import Confirm from '@/Pieces/Confirm';
    import Pagination from '@/Pieces/Pagination';
    import LogFilter from './LogFilter';
    import LogSearch from './LogSearch';
    import LogViewer from './LogViewer';
    import LogBulkAction from './BulkAction';

    export default {
        name: 'EmailLog',
        components: {
            Confirm,
            Pagination,
            LogFilter,
            LogSearch,
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
            pageChanged() {
                this.$router.push({
                    name: 'logs',
                    query: {
                        search: this.query,
                        filterBy: this.filterBy,
                        filterValue: this.filterByValue,
                        page: this.pagination.current_page
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
                jQuery.each(log.headers, (k, v) => {
                    k = k.split('-').map(s => this.ucFirst(s)).join('-');
                    headers[k] = v;
                });
                log.headers = headers;

                return log;
            },
            formatAddresses(addresses) {
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
            },
            onSearchChange(query) {
                this.query = query;
            },
            handleBulkAction({ action }) {
                if (action === 'deleteall') {
                    return this.handleDelete('all');
                } else if (action === 'deleteselected') {
                    return this.handleDelete(this.selectedLogs);
                }
            },
            handleRetry(row) {
                this.loading = true;
                this.$post('logs/retry', { id: row.id }).then(res => {
                    row.status = res.data.email.status;
                    row.retries = res.data.email.retries;
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
            }
        },
        watch: {
            $route: {
                immediate: true,
                handler: function(to, from) {
                    const currentPage = this.pagination.current_page;
                    this.query = to.query.search || this.query;
                    this.filterBy = to.query.filterBy || this.filterBy;
                    this.filterBy = to.query.filterBy || this.filterBy;
                    this.filterByValue = to.query.filterValue || this.filterByValue;
                    this.pagination.current_page = Number(to.query.page) || currentPage;
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

<style>
    .logs .successful {
        color:#409EFF;
        font-size:20px;
    }
    
    .logs .unsuccessful {
        color:#F56C6C;
        font-size:20px;
    }
    
    .logs .resent {
        color:#a4da89;
        font-size:20px;
    }

    .logs .dont-show {
        color: #409EFF;
        margin-left: 10px;
        cursor: pointer;
        vertical-align: top;
    }
</style>
