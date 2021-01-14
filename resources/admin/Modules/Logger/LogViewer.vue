<template>
    <div class="log-viewer">
        <el-dialog
            v-if="log"
            title="Email Log"
            @closed="closed"
            v-loading="retrying"
            :visible.sync="logViewerProps.dialogVisible"
        >
            <div v-loading="loading">
                <el-row class="log-row">
                    <el-col :span="4">
                        <strong>Status</strong>
                    </el-col>
                    <el-col :span="20">
                        : <span :class="{
                            success: log.status == 'sent',
                            resent: log.status == 'resent',
                            fail: log.status == 'failed'
                        }">
                            <span v-if="log.status=='sent'">Successful</span>
                            <span v-else-if="log.status=='resent'" style="font-size:14px;">
                                <span class="fail">Unsuccessful</span>
                                <span style="color:#606266;">&rarr;</span>
                                Resent <span style="color:#606266;">({{ log.updated_at }})</span>
                            </span>
                            <span v-else>Unsuccessful</span>
                            <span
                                v-if="log.status == 'failed'"
                                style="display: inline-block;float:right;color:#67C23A;cursor:pointer;"
                                @click="handleRetry(log)"
                            >Retry</span>
                        </span>
                    </el-col>
                </el-row>
                <hr class="log-border">

                <el-row class="log-row">
                    <el-col :span="4">
                        <strong>Date-Time</strong>
                    </el-col>
                    <el-col :span="20">
                        : {{ log.created_at }}
                    </el-col>
                </el-row>
                <hr class="log-border">

                <el-row class="log-row">
                    <el-col :span="4">
                        <strong>From</strong>
                    </el-col>
                    <el-col :span="20">
                        : <span v-html="log.from"></span>
                    </el-col>
                </el-row>
                <hr class="log-border">

                <el-row class="log-row">
                    <el-col :span="4">
                        <strong>Reply To:</strong>
                    </el-col>
                    <el-col :span="20">
                        : <span v-html="log.headers['Reply-To']"></span>
                    </el-col>
                </el-row>
                <hr class="log-border">

                <el-row class="log-row">
                    <el-col :span="4">
                        <strong>To:</strong>
                    </el-col>
                    <el-col :span="20">
                        : <span v-html="log.to"></span>
                    </el-col>
                </el-row>
                <hr class="log-border">

                <el-row class="log-row">
                    <el-col :span="4">
                        <strong>CC:</strong>
                    </el-col>
                    <el-col :span="20">
                        : <span v-html="log.headers.Cc"></span>
                    </el-col>
                </el-row>
                <hr class="log-border">

                <el-row class="log-row">
                    <el-col :span="4">
                        <strong>BCC:</strong>
                    </el-col>
                    <el-col :span="20">
                        : <span v-html="log.headers.Bcc"></span>
                    </el-col>
                </el-row>
                <hr class="log-border">

                <el-row class="log-row">
                    <el-col :span="4">
                        <strong>Subject</strong>
                    </el-col>
                    <el-col :span="20">
                        : <span v-html="log.subject"></span>
                    </el-col>
                </el-row>
                <hr class="log-border">

                <el-row class="log-row" v-if="log.extra.provider">
                    <el-col :span="4">
                        <strong>Mailer</strong>
                    </el-col>
                    <el-col :span="20">
                        : <span>{{ settings.providers[log.extra.provider].title }}</span>
                    </el-col>
                </el-row>

                <el-collapse v-model="activeName" style="margin-top:10px;">
                    <el-collapse-item name="1">
                        <template slot="title">
                            <strong style="color:#606266">Email Body</strong>
                        </template>
                        <hr class="log-border">
                        <EmailbodyContainer :content="log.body" />
                    </el-collapse-item>
                    
                    <el-collapse-item name="2">
                        <template slot="title">
                            <strong style="color:#606266">
                                Attachments ({{getAttachments(log).length}})
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

                    <el-collapse-item name="3">
                        <template slot="title">
                            <strong style="color:#606266">Technical Information</strong>
                        </template>
                        <div>
                            <hr><strong>Response
                            </strong><hr>
                            <el-row>
                                <el-col><pre v-html="log.response"></pre></el-col>
                            </el-row>
                            <hr>

                            <strong>Headers</strong><hr>
                            <el-row>
                                <el-col>
                                    <pre v-html="{ ...log.headers, ...log.extra.custom_headers }" />
                                </el-col>
                            </el-row>
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
                            <i class="el-icon-arrow-left"></i> Prev
                        </el-button>
                    </el-col>
                    <el-col :span="12">
                        <el-button
                            size="small"
                            class="next nav"
                            :disabled="!next"
                            @click="navigate('next')"
                        >
                            Next <i class="el-icon-arrow-right"></i>
                        </el-button>
                    </el-col>
                </el-row>
            </div>
        </el-dialog>
    </div>
</template>

<script>
    import EmailbodyContainer from './EmailbodyContainer';
    export default {
        name: 'LogViewer',
        props: ['logViewerProps'],
        components: { EmailbodyContainer },
        data() {
            return {
                activeName: null,
                loading: false,
                next: false,
                prev: false,
                retrying: false
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
                this.activeName = null
            },
            getAttachmentName(name) {
                if (!name) return;
                name = name[0].replace(/\\/g, '/');

                return name.split('/').pop();
            },
            handleRetry(log) {
                this.retrying = true;
                this.$post('logs/retry', { id: log.id }).then(res => {
                    this.logViewerProps.log = res.data.email.retries;
                    this.logViewerProps.log.status = res.data.email.status;
                    this.logViewerProps.log.updated_at = res.data.email.updated_at;
                }).fail(error => {
                    this.$notify.error({
                        offset: 19,
                        title: 'Oops!!',
                        message: error.responseJSON.data.message
                    });
                }).always(() => {
                    this.retrying = false;
                });
            }
        },
        computed: {
            log: {
                get() {
                    let log;

                    if (this.logViewerProps.log) {
                        log = { ...this.logViewerProps.log };

                        if (!this.moment(log.created_at, 'DD-MM-YYYY h:mm:ss A', true).isValid()) {
                            log.created_at = this.$dateFormat(
                                log.created_at, 'DD-MM-YYYY h:mm:ss A'
                            );
                        }

                        if (!this.moment(log.updated_at, 'DD-MM-YYYY h:mm:ss A', true).isValid()) {
                            log.updated_at = this.$dateFormat(
                                log.updated_at, 'DD-MM-YYYY h:mm:ss A'
                            );
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

<style scoped>
    .log-viewer pre {
        white-space: pre-wrap;       /* Since CSS 2.1 */
        white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
        white-space: -pre-wrap;      /* Opera 4-6 */
        white-space: -o-pre-wrap;    /* Opera 7 */
        word-wrap: break-word;       /* Internet Explorer 5.5+ */
    }

    .log-viewer .success {
        color: #409EFF;
    }

    .log-viewer .fail {
        color: #F56C6C;
    }

    .log-viewer .resent {
        color: #409EFF;
    }

    .log-viewer .log-row {
        margin: 15px 0;
    }

    .log-viewer .log-border {
        border-bottom: 0;
        border-color: #EBEEF5;
    }

    .log-viewer .nav {
        margin: 20px 0 10px 0;
        cursor: pointer;
    }

    .log-viewer .prev {
        float: left;
    }

    .log-viewer .next {
        float: right;
        margin-right: 8px;
    }
</style>
