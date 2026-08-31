<template>
    <el-dialog
        :title="$t('Resend Email')"
        v-model="visible"
        @closed="handleClosed"
        :close-on-click-modal="false"
        append-to-body
        width="480px"
        custom-class="fss_resend_dialog"
    >
        <div v-if="log" v-loading="resending">
            <p style="margin-top:0;">
                <strong>{{ $t('Subject') }}:</strong> {{ log.subject }}
            </p>
            <p>
                <strong>{{ $t('Original recipient(s)') }}:</strong>
                <span>{{ formatOriginalRecipients(log.to) }}</span>
            </p>

            <el-form
                ref="form"
                label-position="top"
                :model="form"
                @submit.prevent
            >
                <el-form-item :label="$t('Send this email to:')">
                    <el-radio-group v-model="form.target" style="display:block;">
                        <el-radio
                            value="original"
                            style="display:block;margin:6px 0;"
                        >{{ $t('Original recipient(s)') }}</el-radio>
                        <el-radio
                            value="self"
                            style="display:block;margin:6px 0;"
                        >{{ $t('My account email') }}
                            <span v-if="appVars.user_email" style="color:#909399;">
                                ({{ appVars.user_email }})
                            </span>
                        </el-radio>
                        <el-radio
                            value="custom"
                            style="display:block;margin:6px 0;"
                        >{{ $t('A different email address') }}</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item v-if="form.target === 'custom'">
                    <el-input
                        ref="customInput"
                        type="text"
                        v-model="form.customEmail"
                        :placeholder="$t('e.g. you@example.com')"
                        @keyup.enter="handleConfirm"
                        autocomplete="off"
                        data-bwignore
                        data-lpignore="true"
                        data-1p-ignore
                    />
                    <span class="small-help-text" style="display:block;margin-top:4px;">
                        {{ $t('Separate multiple email addresses with commas.') }}
                    </span>
                </el-form-item>
            </el-form>
        </div>

        <template #footer>
            <span class="dialog-footer">
                <el-button size="small" @click="visible = false" :disabled="resending">
                    {{ $t('Cancel') }}
                </el-button>
                <el-button
                    type="success"
                    size="small"
                    icon="FsmIconRefreshRight"
                    :loading="resending"
                    @click="handleConfirm"
                >
                    {{ $t('Resend') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script>
export default {
    name: 'ResendDialog',
    props: {
        value: {
            type: Boolean,
            default: false
        },
        log: {
            type: Object,
            default: null
        },
        resending: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            form: {
                target: 'original',
                customEmail: ''
            }
        };
    },
    computed: {
        visible: {
            get() {
                return this.value;
            },
            set(val) {
                this.$emit('input', val);
            }
        }
    },
    watch: {
        value(newVal) {
            if (newVal) {
                this.form.target = 'original';
                this.form.customEmail = '';
            }
        },
        'form.target'(newVal) {
            if (newVal === 'custom') {
                this.$nextTick(() => {
                    if (this.$refs.customInput) {
                        this.$refs.customInput.focus();
                    }
                });
            }
        }
    },
    methods: {
        handleClosed() {
            this.$emit('closed');
        },
        handleConfirm() {
            if (this.resending) {
                return;
            }

            const payload = { target: this.form.target };

            if (this.form.target === 'self') {
                const userEmail = this.appVars && this.appVars.user_email
                    ? this.appVars.user_email.trim()
                    : '';

                if (!userEmail) {
                    this.$notify.error({
                        offset: 19,
                        title: this.$t('Oops!'),
                        message: this.$t('No account email is available for the current user.')
                    });
                    return;
                }

                payload.recipients = [userEmail];
            }

            if (this.form.target === 'custom') {
                const parsed = this.parseEmails(this.form.customEmail);

                if (!parsed.valid.length && !parsed.invalid.length) {
                    this.$notify.error({
                        offset: 19,
                        title: this.$t('Oops!'),
                        message: this.$t('Please enter a valid email address')
                    });
                    return;
                }

                if (parsed.invalid.length) {
                    this.$notify.error({
                        offset: 19,
                        title: this.$t('Oops!'),
                        message: this.$t('Please enter a valid email address') + ': ' + parsed.invalid.join(', ')
                    });
                    return;
                }

                payload.recipients = parsed.valid;
            }

            this.$emit('confirm', payload);
        },
        parseEmails(input) {
            const tokens = (input || '')
                .split(/[\s,;]+/)
                .map(t => t.trim())
                .filter(Boolean);

            const valid = [];
            const invalid = [];
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            tokens.forEach(token => {
                if (re.test(token)) {
                    valid.push(token);
                } else {
                    invalid.push(token);
                }
            });

            return { valid, invalid };
        },
        formatOriginalRecipients(to) {
            if (!to) {
                return '—';
            }

            if (typeof to === 'string') {
                return to;
            }

            if (!Array.isArray(to)) {
                return String(to);
            }

            return to.map(val => {
                if (val && val.name) {
                    return `${val.name} <${val.email}>`;
                }
                return val && val.email ? val.email : '';
            }).join(', ');
        }
    }
};
</script>
