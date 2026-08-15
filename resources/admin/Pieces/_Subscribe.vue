<template>
    <div class="fst_subscribe_form">
        <template v-if="!subscribed">
            <p style="margin-top: 0;">
                {{ $t('__SUBSCRIBE_INTRO') }}
            </p>
            <div class="fsmtp_subscribe">
                <el-form label-position="right" label-width="100px">
                    <el-form-item style="margin-bottom: 0px;" :label="$t('Your Name')">
                        <el-input size="small" v-model="formData.display_name" :placeholder="$t('Your Name')" />
                    </el-form-item>
                    <el-form-item style="margin-bottom: 0px;" :label="$t('Your Email')">
                        <el-input size="small" v-model="formData.email" :placeholder="$t('Your Email Address')"/>
                    </el-form-item>
                </el-form>

                <el-checkbox true-label="yes" false-label="no" v-model="share_details">
                    {{ $t('(Optional) Share Non - Sensitive Data. It will help us to improve the integrations') }}
                    <el-tooltip class="item" effect="dark" :content="$t('Access Data: Active SMTP Connection Provider, installed plugin names, php & mysql version')" placement="top-end">
                        <i class="el-icon el-icon-info"></i>
                    </el-tooltip>
                </el-checkbox>
                <el-button style="margin-top: 10px;" v-loading="saving" :disabled="saving" @click="subscribeToEmail()" type="success" size="small">
                    {{ $t('Subscribe To Updates') }}
                </el-button>
            </div>
        </template>
        <div style="text-align: center;" v-else>
            <p>{{ $t('Awesome! Please check your email inbox and confirm your subscription.') }}</p>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'SubscriberForm',
        data() {
            return {
                formData: {
                    email: window.FluentMailAdmin.user_email,
                    display_name: window.FluentMailAdmin.user_display_name
                },
                share_details: 'yes',
                saving: false,
                subscribed: false
            }
        },
        methods: {
            subscribeToEmail() {
                if (!this.formData.email) {
                    this.$notify.error(this.$t('Please Provide an email'));
                    return false;
                }

                this.saving = true;
                this.$post('settings/subscribe', {
                    email: this.formData.email,
                    display_name: this.formData.display_name,
                    share_essentials: this.share_details
                })
                    .then(response => {
                        this.subscribed = true;

                        setTimeout(() => {
                            this.appVars.require_optin = 'no';
                        }, 15000);

                        this.$notify.success(response.data.message);
                    })
                    .catch((errors) => {
                        this.$notify.error(errors.responseJSON.data.message);
                    })
                    .always(() => {
                        this.saving = false
                    });
            }
        }
    }
</script>
