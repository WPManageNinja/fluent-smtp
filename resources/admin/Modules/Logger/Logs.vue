<template>
    <div class="logs">
        <div>
            <div class="fsm_page_head">
                <h1 class="fsm_page_title">{{ $t('Email Logs') }}</h1>
                <div class="fsm_page_actions">
                    <LogBulkAction
                        @on-bulk-action="handleBulkAction"
                        :selected="selectedLogs"
                        v-if="selectedLogs.length"
                    />
                    <el-input
                        class="fsm_log_search"
                        clearable
                        size="small"
                        v-model="filter_query.search"
                        @clear="filter_query.search=''"
                        @keyup.enter="fetch"
                        :placeholder="$t('Type & press enter...')"
                    >
                        <template #append>
                            <el-button icon="FsmIconSearch" @click="fetch"/>
                        </template>
                    </el-input>
                    <el-button @click="fetch" size="small" :title="$t('Refresh')">
                        <el-icon><FsmIconRefresh /></el-icon>
                    </el-button>
                </div>
            </div>

            <el-alert v-if="!isLogsOn" :closable="false" show-icon type="warning"
                      style="margin-bottom: 20px;">
                {{ $t('__EMAIL_LOGGING_OFF') }}
                <el-button link @click="turnOnEmailLogging">{{ $t('Turn On') }}</el-button>
            </el-alert>

            <div class="fsm_card">
                <div class="fsm_card_head">
                    <LogFilter
                        :filter_query="filter_query"
                        @on-filter="fetch()"
                        @reset-page="pagination.current_page=1"
                    />
                </div>

                <div v-if="!loading" class="fsm_card_body fsm_card_flush">
                    <!--
                        `border` is gone and the table sits flush: the card already draws
                        the frame, and a bordered table inside a bordered card is two
                        boxes for one thing.
                    -->
                    <el-table
                        class="fsm_table"
                        :data="logs"
                        v-loading="loading"
                        style="width:100%"
                        @selection-change="handleSelectionChange"
                    >
                        <el-table-column type="selection" width="55"/>
                        <el-table-column :label="$t('Subject')">
                            <template #default="scope">
                                <span style="cursor: pointer" @click="handleView(scope.row)">{{ scope.row.subject }}</span>
                                <span v-if="scope.row.extra && scope.row.extra.provider == 'Simulator'"
                                      class="fsm_log_simulated">{{ $t(' - Simulated') }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('To')">
                            <template #default="scope">
                                <span v-html="formatAddresses(scope.row.to)"></span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('Status')" width="120">
                            <template #default="scope">
                                <!--
                                    One word, tinted, instead of the whole row. A failed
                                    row used to be painted pink edge to edge, which is a
                                    lot of colour for a fact that fits in a chip - and it
                                    left no way to colour anything else in the row.
                                -->
                                <span class="fsm_tag" :class="statusClass(scope.row.status)">
                                    {{ scope.row.status }}
                                </span>
                            </template>
                        </el-table-column>

                        <el-table-column prop="created_at" :label="$t('Date-Time')" width="200px">
                            <template #default="scope">
                                {{ $dateFormat(scope.row.created_at, 'DD MMM YYYY LT') }}
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('Actions')" width="220px" align="right">
                            <template #default="scope">
                                <el-button
                                    size="small"
                                    type="success"
                                    icon="FsmIconRefresh"
                                    @click="handleRetry(scope.row, 'retry')"
                                    :plain="true"
                                    v-if="scope.row.status == 'failed'"
                                >{{ $t('Retry') }}
                                </el-button>
                                <el-button
                                    size="small"
                                    type="success"
                                    icon="FsmIconRefreshRight"
                                    @click="handleResendClick(scope.row)"
                                    v-if="scope.row.status == 'sent'"
                                >
                                    {{ $t('Resend') }}
                                    <span v-if="scope.row.resent_count > 0">({{ scope.row.resent_count }})</span>
                                </el-button>

                                <el-button
                                    size="small"
                                    type="primary"
                                    icon="FsmIconView"
                                    @click="handleView(scope.row)"
                                />

                                <confirm @yes="handleDelete(scope.row.id)">
                                    <template #reference>
                                        <el-button
                                            size="small"
                                            type="danger"
                                            icon="FsmIconDelete"
                                        />
                                    </template>
                                </confirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="fsm_pager">
                        <div>
                            <confirm v-if="logs.length" placement="right"
                                     :message="$t('Are you sure, you want to delete all the logs?')"
                                     @yes="handleDelete(['all'])">
                                <template #reference>
                                    <el-button size="small" type="info" plain>
                                        {{ $t('Delete All Logs') }}
                                    </el-button>
                                </template>
                            </confirm>
                        </div>
                        <pagination :pagination="pagination" @fetch="pageChanged"/>
                    </div>
                </div>
                <el-skeleton :animated="true" v-else class="fsm_card_body" :rows="15"></el-skeleton>
            </div>

            <LogViewer ref="logViewer" :logViewerProps="logViewerProps"/>

            <ResendDialog
                v-model="resendDialog.visible"
                :log="resendDialog.log"
                :resending="resendDialog.resending"
                @confirm="handleResendConfirm"
                @closed="handleResendDialogClosed"
            />
        </div>
    </div>
</template>

<script type="text/babel">
import Confirm from '@/Pieces/Confirm';
import Pagination from '@/Pieces/Pagination';
import LogFilter from './LogFilter';
import LogViewer from './LogViewer';
import LogBulkAction from './BulkAction';
import ResendDialog from './ResendDialog';
import isEmpty from 'lodash/isEmpty'

export default {
    name: 'EmailLog',
    components: {
        Confirm,
        Pagination,
        LogFilter,
        LogViewer,
        LogBulkAction,
        ResendDialog
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
            resendDialog: {
                visible: false,
                log: null,
                resending: false
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
        /*
         * The status column's chip. `sent` and `failed` are the two the logger writes;
         * anything else is a state this build does not know about, and a neutral chip
         * says that better than a colour picked at random would.
         */
        statusClass(status) {
            return {
                sent: 'is_sent',
                failed: 'is_failed',
                pending: 'is_pending'
            }[status] || 'is_neutral';
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
                this.logs = res.data;
                this.pagination.total = res.total;
                const page = Number(this.$route.query.page);
                this.pagination.current_page = page || this.pagination.current_page;
            }).fail(error => {
                console.log(error);
            }).always(() => {
                this.loading = false;
            });
        },
        // Formats the recipient list for display. Called from the template
        // rather than stored back onto the row: the row's `to` has to stay the
        // raw [{name, email}] array so that everything downstream — the log
        // viewer, the resend dialog — can decide for itself how to render it.
        // Escaping the row in place used to leave the dialog showing
        // "John &lt;john@example.com&gt;", since it renders text, not HTML.
        formatAddresses(addresses) {
            if (!addresses) {
                return '';
            }

            if (isEmpty(addresses)) {
                return '';
            }

            if(typeof addresses == 'string') {
                return this.escapeHtml(addresses);
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
        handleRetry(row, type, recipients = null) {
            this.loading = true;

            const payload = {
                id: row.id,
                type: type
            };

            if (recipients && recipients.length) {
                payload.recipients = recipients;
            }

            return this.$post('logs/retry', payload).then(res => {
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
                row.extra = res.data.email.extra;
                this.$notify.success({
                    offset: 19,
                    title: 'Great!',
                    message: res.data.message
                });
                return true;
            }).fail(error => {
                this.$notify.error({
                    offset: 19,
                    title: 'Oops!!',
                    message: error.responseJSON.data.message
                });
                return false;
            }).always(() => {
                this.loading = false;
            });
        },
        handleResendClick(row) {
            this.resendDialog.log = row;
            this.resendDialog.resending = false;
            this.resendDialog.visible = true;
        },
        handleResendConfirm(payload) {
            const row = this.resendDialog.log;
            if (!row) {
                return;
            }

            const recipients = payload.target === 'original' ? null : payload.recipients;

            this.resendDialog.resending = true;
            this.handleRetry(row, 'resend', recipients).always(() => {
                this.resendDialog.resending = false;
                this.resendDialog.visible = false;
            });
        },
        handleResendDialogClosed() {
            this.resendDialog.log = null;
            this.resendDialog.resending = false;
        },
        handleView(row) {
            this.logViewerProps.log = row;
            this.logViewerProps.dialogVisible = true;

            this.$nextTick(() => {
                this.logViewerProps.query = this.query;
                this.logViewerProps.filterBy = this.filterBy;
                this.logViewerProps.filterByValue = this.filterByValue;

                // Vue 3 removed $children, which is what this used to walk to
                // find the viewer by its component tag. A ref names it directly.
                this.$refs.logViewer && this.$refs.logViewer.navigate();
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
