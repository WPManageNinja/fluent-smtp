<template>
    <div class="logs">
        <div>
            <div class="fsm_page_head">
                <h1 class="fsm_page_title">{{ $t('Email Logs') }}</h1>
                <!--
                    Only the bulk action, and only while rows are selected. Searching,
                    filtering and refreshing all narrow the same list, so they live
                    together in the table's own toolbar rather than up here.
                -->
                <div class="fsm_page_actions">
                    <LogBulkAction
                        @on-bulk-action="handleBulkAction"
                        :selected="selectedLogs"
                        v-if="selectedLogs.length"
                    />
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
                        @on-filter="applyFilter"
                        @on-refresh="fetch"
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

                        <!--
                            Subject is the only column that takes what is left over, and
                            it truncates rather than wraps.

                            It used to be one of two flexible columns, and El Plus lets
                            those go as narrow as the space allows - on a phone that left
                            Subject and To on 80px each, one word per line, while Status,
                            Date-Time and Actions held their fixed widths beside them. A
                            floor makes the table scroll sideways instead, which is the
                            honest thing for six columns on a 480px screen. And a subject
                            is a line to scan down, not a paragraph to read: an ellipsis
                            keeps every row one line tall, with the whole subject a hover
                            away and the full email a click away.
                        -->
                        <el-table-column :label="$t('Subject')" min-width="280"
                                         show-overflow-tooltip>
                            <template #default="scope">
                                <span style="cursor: pointer" @click="handleView(scope.row)">{{ scope.row.subject }}</span>
                                <span v-if="scope.row.extra && scope.row.extra.provider == 'Simulator'"
                                      class="fsm_log_simulated">{{ $t(' - Simulated') }}</span>
                            </template>
                        </el-table-column>

                        <!--
                            A fixed width, not a second flexible column. Two flexible
                            columns share the leftover space between them, which gave To
                            286px on a desktop to hold one address - room it has nothing
                            to do with, taken from the subject beside it.
                        -->
                        <el-table-column :label="$t('To')" width="240" show-overflow-tooltip>
                            <template #default="scope">
                                <span v-html="formatAddresses(scope.row.to)"></span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('Status')" width="90">
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

                        <el-table-column prop="created_at" :label="$t('Date-Time')" width="175">
                            <template #default="scope">
                                {{ $dateFormat(scope.row.created_at, 'DD MMM YYYY LT') }}
                            </template>
                        </el-table-column>

                        <!--
                            Quiet buttons, as on the connection and channel rows.

                            These were a solid green Resend, a solid dark View and a solid
                            red Delete, three saturated blocks per row and thirty down a
                            full page - which is more colour than the one thing on this
                            screen that is meant to be coloured, the failed status chip.
                            Nothing here is dangerous enough on its own to shout: delete
                            asks first, and resend is the reason people open this screen.
                        -->
                        <el-table-column :label="$t('Actions')" width="190" align="right">
                            <template #default="scope">
                                <div class="fsm_log_actions">
                                    <el-button
                                        size="small"
                                        icon="FsmIconRefresh"
                                        @click="handleRetry(scope.row, 'retry')"
                                        v-if="scope.row.status == 'failed'"
                                    >{{ $t('Retry') }}
                                    </el-button>
                                    <el-button
                                        size="small"
                                        icon="FsmIconRefreshRight"
                                        @click="handleResendClick(scope.row)"
                                        v-if="scope.row.status == 'sent'"
                                    >
                                        {{ $t('Resend') }}
                                        <span v-if="scope.row.resent_count > 0">({{ scope.row.resent_count }})</span>
                                    </el-button>

                                    <el-button
                                        size="small"
                                        icon="FsmIconView"
                                        :title="$t('View')"
                                        :aria-label="$t('View')"
                                        @click="handleView(scope.row)"
                                    />

                                    <confirm @yes="handleDelete(scope.row.id)">
                                        <template #reference>
                                            <el-button
                                                size="small"
                                                icon="FsmIconDelete"
                                                :title="$t('Delete')"
                                                :aria-label="$t('Delete')"
                                            />
                                        </template>
                                    </confirm>
                                </div>
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
        /*
         * Narrowing the list starts it again from the first page. Filtering while on
         * page 5 used to ask for page 5 of the new, shorter result - an empty table for
         * a filter that matches plenty.
         */
        applyFilter() {
            this.pagination.current_page = 1;
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

            /*
             * The viewer's Prev and Next walk the same list this screen is showing, so
             * they are handed the filter that produced it. These three used to read
             * `this.query`, `this.filterBy` and `this.filterByValue` - none of which are
             * declared on this component - so every navigation went out unfiltered and
             * Next off a filtered list could land on an email the list does not contain.
             */
            this.$nextTick(() => {
                this.logViewerProps.query = this.filter_query.search;
                this.logViewerProps.filterBy = this.filter_query.status ? 'status' : '';
                this.logViewerProps.filterByValue = this.filter_query.status;

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
