<template>
    <div class="log-viewer">
        <el-dialog
            v-if="log"
            :title="$t('Email Log')"
            @closed="closed"
            v-model="logViewerProps.dialogVisible"
        >
            <!--
                v-loading used to sit on el-dialog itself, and a dialog renders through a
                Teleport - so Vue had nowhere to hang the mask and dropped it. Retrying an
                email showed nothing at all and left the button live, which is one click
                away from sending the same email twice. It belongs on the content the
                dialog actually renders, and the buttons carry their own loading state.
            -->
            <div v-loading="loading || retrying">
                <ul class="fss_log_items">
                    <li>
                        <div class="item_header">{{ $t('Status') }}:</div>
                        <div class="item_content">
                            <span :class="{
                                success: log.status == 'sent',
                                resent: log.status == 'resent',
                                fail: log.status == 'failed'
                            }">
                                <span
                                    style="text-transform:capitalize;margin-right:10px;"
                                >{{ log.status }}</span>

                                <el-button
                                    size="small"
                                    type="primary"
                                    icon="FsmIconRefresh"
                                    @click="handleRetry(log, 'retry')"
                                    :plain="true"
                                    :loading="retrying"
                                    v-if="log.status == 'failed'"
                                >{{ $t('Retry') }}</el-button>

                                <el-button
                                    size="small"
                                    type="primary"
                                    icon="FsmIconRefreshRight"
                                    @click="handleResendClick"
                                    :disabled="retrying"
                                    v-if="log.status == 'sent'"
                                >
                                    {{ $t('Resend') }}
                                </el-button>
                            </span>
                        </div>
                    </li>
                    <li>
                        <div class="item_header">{{ $t('Date') }}:</div>
                        <div class="item_content">{{ $dateFormat(log.created_at, 'DD MMM YYYY LT') }}</div>
                    </li>
                    <li>
                        <div class="item_header">{{ $t('From') }}:</div>
                        <div class="item_content"><span v-html="log.from"></span></div>
                    </li>
                    <li>
                        <div class="item_header">{{ $t('To') }}:</div>
                        <div class="item_content">
                            <span v-html="formatAddresses(log.to)"></span>
                        </div>
                    </li>
                    <li v-if="sendTime">
                        <div class="item_header">{{ $t('Time to Send') }}:</div>
                        <div class="item_content">
                            <span>{{ sendTime }}</span>
                        </div>
                    </li>
                    <li v-if="log.resent_count > 0">
                        <div class="item_header">{{ $t('Times Resent') }}:</div>
                        <div class="item_content">
                            <span v-html="log.resent_count"></span>
                        </div>
                    </li>
                    <li v-if="resendHistory.length">
                        <div class="item_header">{{ $t('Resend History') }}:</div>
                        <div class="item_content">
                            <div v-for="(record, index) in resendHistory" :key="index" style="margin-bottom:2px;">
                                <span>{{ record.to }}</span>
                                <span style="color:var(--fsm-text-light);"> — {{ record.at }}</span>
                                <span v-if="record.by" style="color:var(--fsm-text-light);"> ({{ record.by }})</span>
                                <span v-if="record.ms" style="color:var(--fsm-text-light);"> · {{ record.ms }}</span>
                                <span v-if="!record.sent" style="color:var(--fsm-danger-fg);"> — {{ $t('failed') }}</span>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="item_header">{{ $t('Subject') }}:</div>
                        <div class="item_content">
                            <span>{{ log.subject }}</span>
                        </div>
                    </li>
                    <li v-if="log.extra && log.extra.provider && settings.providers[log.extra.provider]">
                        <div class="item_header">{{ $t('Email Service') }}:</div>
                        <div class="item_content">
                            <span>{{ settings.providers[log.extra.provider].title }}</span>
                        </div>
                    </li>
                    <li v-else-if="log.extra && log.extra.provider">
                        <div class="item_header">{{ $t('Email Service') }}:</div>
                        <div class="item_content">
                            <span>{{ log.extra.provider }}</span>
                        </div>
                    </li>
                </ul>

                <!--
                    The reason a send failed, up where the status is. The full server
                    response is still printed further down, but that sits under a 400px
                    preview of the message body, and the reason is what a failed log is
                    opened for.
                -->
                <div v-if="failureReason" class="fss_log_failure">
                    <strong>{{ $t('Why it failed') }}</strong>
                    <p>{{ failureReason }}</p>
                </div>

                <el-collapse v-model="activeName" style="margin-top:10px;">
                    <el-collapse-item name="email_body">
                        <template #title>
                            <strong style="color:var(--fsm-text-mid)">{{ $t('Email Body (sanitized)') }}</strong>
                        </template>
                        <hr class="log-border">
                        <EmailbodyContainer :content="sanitize(log.body)"/>
                        <hr/>
                    </el-collapse-item>
                    <p><strong>{{ $t('Server Response') }}</strong></p>
                    <el-row>
                        <el-col>
                            <pre>{{ log.response }}</pre>
                        </el-col>
                    </el-row>
                    <hr/>
                    <el-collapse-item name="tech_info">
                        <template #title>
                            <strong style="color:var(--fsm-text-mid)">{{ $t('Email Headers') }}</strong>
                        </template>
                        <div>
                            <pre>{{ log.headers }}</pre>
                            <pre v-if="log.extra.custom_headers">{{ log.extra.custom_headers }}</pre>
                        </div>
                    </el-collapse-item>


                    <el-collapse-item name="attachments">
                        <template #title>
                            <strong style="color:var(--fsm-text-mid)">
                                {{ $t('Attachments') }} ({{ getAttachments(log).length }})
                            </strong>
                        </template>
                        <hr class="log-border">
                        <div
                            v-for="(attachment, key) in getAttachments(log)"
                            :key="key"
                            style="margin:5px 0 10px 0;"
                        >
                            ({{ key + 1 }}) {{ getAttachmentName(attachment) }}
                        </div>
                    </el-collapse-item>
                </el-collapse>

                <el-row :gutter="10">
                    <el-col :span="12">
                        <el-button
                            class="prev nav"
                            icon="FsmIconArrowLeft"
                            :disabled="!prev"
                            @click="navigate('prev')"
                        >{{ $t('Prev') }}</el-button>
                    </el-col>
                    <el-col :span="12">
                        <!--
                            The icon trails the label here, which the `icon` prop cannot
                            do - so it stays in the slot and .fsm_btn_icon_right supplies
                            the spacing the prop would have.
                        -->
                        <el-button
                            class="next nav"
                            :disabled="!next"
                            @click="navigate('next')"
                        >{{ $t('Next') }}<el-icon class="fsm_btn_icon_right"><FsmIconArrowRight/></el-icon></el-button>
                    </el-col>
                </el-row>
            </div>
        </el-dialog>

        <ResendDialog
            v-model="resendDialog.visible"
            :log="resendDialog.log"
            :resending="resendDialog.resending"
            @confirm="handleResendConfirm"
            @closed="handleResendDialogClosed"
        />
    </div>
</template>

<script>
import EmailbodyContainer from './EmailbodyContainer';
import ResendDialog from './ResendDialog';

export default {
    name: 'LogViewer',
    props: ['logViewerProps'],
    components: {EmailbodyContainer, ResendDialog},
    data() {
        return {
            activeName: 'email_body',
            loading: false,
            next: false,
            prev: false,
            retrying: false,
            resendDialog: {
                visible: false,
                log: null,
                resending: false
            }
        };
    },
    methods: {
        navigate(dir) {
            const data = {
                dir: dir,
                id: this.log.id,
                query: this.logViewerProps.query,
                filter_by: this.logViewerProps.filterBy,
                filter_by_value: this.logViewerProps.filterByValue
            };

            /*
             * The date range the list was narrowed by, under the same key `logs` takes it.
             * Prev and Next are meant to walk the result set the screen behind this dialog
             * is showing, and without the range they walk the whole table instead - so
             * stepping through one week of failures lands on an email from another month.
             */
            const dateRange = this.logViewerProps.dateRange;

            if (Array.isArray(dateRange) && dateRange.length === 2) {
                data.date_range = dateRange;
            }

            this.loading = true;
            this.$get('logs/show', data).then(res => {
                if (!dir) {
                    this.next = res.data.next.length;
                    this.prev = res.data.prev.length;
                    return;
                }
                this.logViewerProps.log = res.data.log;
                this.next = res.data.next;
                this.prev = res.data.prev;
            }).fail(error => {
                this.$notify.error(this.$errorMessage(error));
            }).always(() => {
                this.loading = false;
            });
        },
        getAttachments(log) {
            if (!log) return [];
            if (!log.attachments) return [];
            if (!Array.isArray(log.attachments)) {
                return [log.attachments];
            }
            const attachments = [];

            log.attachments.forEach((attachment, key) => {
                attachments[key] = attachment;
            });
            return attachments;
        },
        closed() {
            this.next = true;
            this.prev = true;
            this.activeName = 'email_body'
        },
        getAttachmentName(name) {
            if (!name || !name[0]) return;
            name = name[0].replace(/\\/g, '/');
            return name.split('/').pop();
        },
        handleRetry(log, type, recipients = null) {
            this.retrying = true;

            const payload = {
                id: log.id,
                type: type
            };

            if (recipients && recipients.length) {
                payload.recipients = recipients;
            }

            return this.$post('logs/retry', payload).then(res => {
                this.logViewerProps.retries = res.data.email.retries;
                this.logViewerProps.log.status = res.data.email.status;
                this.logViewerProps.log.updated_at = res.data.email.updated_at;
                this.logViewerProps.log.resent_count = res.data.email.resent_count;
                // Carries the resend trail, so the history renders without a reload.
                this.logViewerProps.log.extra = res.data.email.extra;
                this.$notify.success({
                    offset: 19,
                    title: this.$t('Done'),
                    message: res.data.message
                });
            }).fail(error => {
                this.$notify.error({
                    offset: 19,
                    title: this.$t('Error'),
                    message: this.$errorMessage(error)
                });
            }).always(() => {
                this.retrying = false;
            });
        },
        handleResendClick() {
            this.resendDialog.log = this.log;
            this.resendDialog.resending = false;
            this.resendDialog.visible = true;
        },
        handleResendConfirm(payload) {
            const log = this.resendDialog.log;
            if (!log) {
                return;
            }

            const recipients = payload.target === 'original' ? null : payload.recipients;

            this.resendDialog.resending = true;
            this.handleRetry(log, 'resend', recipients).always(() => {
                this.resendDialog.resending = false;
                this.resendDialog.visible = false;
            });
        },
        handleResendDialogClosed() {
            this.resendDialog.log = null;
            this.resendDialog.resending = false;
        },
        sanitize(html) {
            return window.DOMPurify.sanitize(html);
        },
        // Milliseconds up to a second, then seconds — 1173 ms reads worse than
        // 1.17s, and 40 ms reads worse than 0.04s. Returns '' when the log
        // predates this being recorded, which hides the row entirely.
        formatSendTime(ms) {
            const value = Number(ms);

            if (!isFinite(value) || value <= 0) {
                return '';
            }

            if (value < 1000) {
                return `${Math.round(value)} ms`;
            }

            return `${(value / 1000).toFixed(2)} s`;
        },
        // The single escaping choke point for the v-html that renders `to`.
        // The display name is attacker-controllable via the To header, so
        // nothing may reach that binding unescaped. Escaping happens here, at
        // the point of render, rather than on the row itself — the row keeps
        // the raw [{name, email}] array so the resend dialog can render the
        // same data as plain text without it arriving pre-escaped.
        formatAddresses(addresses) {
            if (typeof addresses === 'string') {
                return this.escapeHtml(addresses);
            }
            if (!Array.isArray(addresses)) {
                return '';
            }
            return addresses.map((val) => {
                if (val && val.name) {
                    return this.escapeHtml(`${val.name} <${val.email}>`);
                }
                return this.escapeHtml(val && val.email ? val.email : '');
            }).join(', ');
        }
    },
    computed: {
        /*
         * The provider's own words for a failure. Handlers log a failed send as
         * {code, message, errors}; a fallback attempt adds `fallback` on top, and that
         * is the more useful line when it is there because it names the connection
         * that was tried.
         */
        failureReason() {
            if (!this.log || this.log.status !== 'failed' || !this.log.response) {
                return '';
            }

            const response = this.log.response;

            if (typeof response === 'string') {
                return response;
            }

            return [response.fallback, response.message].filter(Boolean).join(' ');
        },
        log: {
            get() {
                let log;
                if (this.logViewerProps.log) {
                    log = {...this.logViewerProps.log};
                    if (!log.headers) {
                        log.headers = {};
                    }
                    if (!log.response) {
                        log.response = {};
                    }
                    if (!log.extra) {
                        log.extra = {};
                    }
                }
                return log;
            },
            set(log) {
                this.logViewerProps.log = log;
            }
        },
        // Newest first. Rendered as text, never v-html: the original
        // recipients carry a display name that whoever sent the email chose.
        resendHistory() {
            const records = this.log && this.log.extra ? this.log.extra.resends : null;

            if (!Array.isArray(records)) {
                return [];
            }

            return records.map(record => ({
                at: record.at || '',
                by: record.by || '',
                sent: record.sent !== false,
                ms: this.formatSendTime(record.ms),
                to: Array.isArray(record.to) ? record.to.join(', ') : String(record.to || '')
            })).reverse();
        },
        sendTime() {
            return this.formatSendTime(this.log && this.log.extra ? this.log.extra.send_time_ms : null);
        }
    }
};
</script>
