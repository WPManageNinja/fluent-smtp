<template>
    <!--
        The one form in the aside, so it is built the way the aside's other card is: a
        label above its control, both taking the full width of the column.

        It used to be an el-form with `label-width: 100px` and right-aligned labels. That
        shape is for a settings page 900px wide - in a 380px card it spent a quarter of
        the column on two words and left the inputs starting a third of the way in, with
        nothing to line them up against.
    -->
    <div class="fsm_optin">
        <template v-if="!subscribed">
            <p class="fsm_optin_intro">{{ $t('__SUBSCRIBE_INTRO') }}</p>

            <!--
                Default size, not `small`. These two fields and the button below them are
                the whole point of the card, and a 24px input in a 380px column is a
                control you have to aim at.
            -->
            <label class="fsm_optin_field">
                <span class="fsm_optin_label">{{ $t('Your Name') }}</span>
                <el-input v-model="formData.display_name" :placeholder="$t('Your Name')"/>
            </label>

            <label class="fsm_optin_field">
                <span class="fsm_optin_label">{{ $t('Your Email') }}</span>
                <el-input type="email" v-model="formData.email"
                          :placeholder="$t('Your Email Address')"/>
            </label>

            <el-checkbox class="fsm_optin_share" true-value="yes" false-value="no"
                         v-model="share_details">
                <span class="fsm_optin_share_text">
                    {{ $t('(Optional) Share Non - Sensitive Data. It will help us to improve the integrations') }}
                    <!--
                        Inside the checkbox's own label, so the sentence and its footnote
                        wrap as one paragraph - but the click is stopped, because reading
                        what is collected should not be the same gesture as agreeing to
                        send it.
                    -->
                    <el-tooltip effect="dark" placement="top"
                                :content="$t('Access Data: Active SMTP Connection Provider, installed plugin names, php & mysql version')">
                        <el-icon class="fsm_optin_info" @click.stop.prevent><FsmIconInfo/></el-icon>
                    </el-tooltip>
                </span>
            </el-checkbox>

            <el-button class="fsm_optin_submit" v-loading="saving" :disabled="saving"
                       @click="subscribeToEmail()" type="primary">
                {{ $t('Subscribe To Updates') }}
            </el-button>
        </template>

        <p v-else class="fsm_optin_done">
            {{ $t('Awesome! Please check your email inbox and confirm your subscription.') }}
        </p>
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
                        this.$notify.error(this.$errorMessage(errors));
                    })
                    .always(() => {
                        this.saving = false
                    });
            }
        }
    }
</script>

<style lang="scss">
.fsm_optin {
    /*
     * .fsm_card_body pads 4px top and bottom, because what usually goes in one is a list
     * of rows that carry 12px of their own - 4 and 12 make the 16 the card head uses. A
     * form is not that: its first line and its last button have no padding of their own,
     * so it brings the other 12 itself and the card keeps the same rhythm.
     */
    padding: 12px 0;

    .fsm_optin_intro {
        @apply text-xs text-ink-light m-0;

        line-height: 1.5;
    }

    .fsm_optin_field {
        @apply block;

        margin-top: 12px;
    }

    .fsm_optin_label {
        @apply block text-xs font-medium text-ink;

        margin-bottom: 5px;
    }

    /*
     * Element Plus keeps a checkbox's label on one line and sets a fixed height on the
     * row, which is right for a word and wrong for a sentence: this one is two lines in
     * a 380px column, so the row grows and the box holds at the first line's height.
     */
    .fsm_optin_share {
        @apply flex items-start;

        height: auto;
        margin-top: 14px;
        margin-right: 0;

        .el-checkbox__input {
            margin-top: 1px;
        }

        .el-checkbox__label {
            @apply text-xs font-normal text-ink-mid;

            white-space: normal;
            line-height: 1.5;
            padding-left: 8px;
        }
    }

    .fsm_optin_info {
        @apply cursor-help align-text-bottom text-ink-light;

        font-size: 13px;
    }

    .fsm_optin_submit {
        width: 100%;
        margin-top: 14px;
    }

    .fsm_optin_done {
        @apply text-xs text-ink-mid m-0 text-center;

        line-height: 1.5;
    }
}
</style>
