<template>
    <div class="log-viewer">
        <el-dialog
            v-if="log"
            :title="$t('Email Log')"
            @closed="closed"
            v-loading="retrying"
            :visible.sync="logViewerProps.dialogVisible"
        >
            <div v-loading="loading">
                <ul class="fss_log_items">
                    <li>
                        <div class="item_header">{{ $t('Status:') }}</div>
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
                                    size="mini"
                                    type="success"
                                    icon="el-icon-refresh"
                                    @click="handleRetry(log, 'retry')"
                                    :plain="true"
                                    v-if="log.status == 'failed'"
                                >{{ $t('Retry') }}</el-button>

                                <el-button
                                    size="mini"
                                    type="success"
                                    icon="el-icon-refresh-right"
                                    @click="handleResendClick"
                                    v-if="log.status == 'sent'"
                                >
                                    {{ $t('Resend') }}
                                </el-button>
                            </span>
                        </div>
                    </li>
                    <li>
                        <div class="item_header">{{ $t('Date-Time') }}:</div>
                        <div class="item_content">{{ log.created_at }}</div>
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
                    <li v-if="log.resent_count > 0">
                        <div class="item_header">{{ $t('Resent Count') }}:</div>
                        <div class="item_content">
                            <span v-html="log.resent_count"></span>
                        </div>
                    </li>
                    <li>
                        <div class="item_header">{{ $t('Subject') }}:</div>
                        <div class="item_content">
                            <span>{{ log.subject }}</span>
                        </div>
                    </li>
                    <li v-if="log.extra && log.extra.provider && settings.providers[log.extra.provider]">
                        <div class="item_header">{{ $t('Mailer') }}:</div>
                        <div class="item_content">
                            <span>{{ settings.providers[log.extra.provider].title }}</span>
                        </div>
                    </li>
                    <li v-else-if="log.extra && log.extra.provider">
                        <div class="item_header">{{ $t('Mailer') }}:</div>
                        <div class="item_content">
                            <span>{{ log.extra.provider }}</span>
                        </div>
                    </li>
                </ul>

                <el-collapse v-model="activeName" style="margin-top:10px;">
                    <el-collapse-item name="email_body">
                        <template slot="title">
                            <strong style="color:#606266">{{ $t('Email Body') }} (sanitized)</strong>
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
                        <template slot="title">
                            <strong style="color:#606266">{{ $t('Email Headers') }}</strong>
                        </template>
                        <div>
                            <pre>{{ log.headers }}</pre>
                            <pre v-if="log.extra.custom_headers">{{ log.extra.custom_headers }}</pre>
                        </div>
                    </el-collapse-item>


                    <el-collapse-item name="attachments">
                        <template slot="title">
                            <strong style="color:#606266">
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
                            size="small"
                            class="prev nav"
                            :disabled="!prev"
                            @click="navigate('prev')"
                        >
                            <i class="el-icon-arrow-left"></i> {{ $t('Prev') }}
                        </el-button>
                    </el-col>
                    <el-col :span="12">
                        <el-button
                            size="small"
                            class="next nav"
                            :disabled="!next"
                            @click="navigate('next')"
                        >
                            {{ $t('Next') }} <i class="el-icon-arrow-right"></i>
                        </el-button>
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
                console.log(error);
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
        }
    }
};
</script>
