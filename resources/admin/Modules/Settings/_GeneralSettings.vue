<template>
    <!--
        Sidebar settings, so the switch sits beside the name it belongs to and the
        sentence explaining it hangs underneath.

        The label-left/control-right rows this used to be need a wide column to be worth
        it - pinning a switch to the right edge of a 312px card only puts it a finger's
        width from its own label, with the explanation wrapping over four lines to get
        there. The one control that is not a switch takes the full width instead.
    -->
    <div class="fss_general_settings">
        <div class="fsm_toggle">
            <div class="fsm_toggle_main">
                <el-switch v-model="settings.misc.log_emails" :aria-label="$t('Log Emails')"
                           active-value="yes" inactive-value="no"/>
                <span class="fsm_toggle_title" @click="toggle('log_emails')">{{ $t('Log Emails') }}</span>
            </div>
            <div class="fsm_toggle_body">
                <p>{{ $t('Log All Emails for Reporting') }}</p>
            </div>
        </div>

        <div class="fsm_toggle" v-if="settings.misc.log_emails == 'yes' && !!appVars.has_fluentcrm">
            <div class="fsm_toggle_main">
                <el-switch v-model="settings.misc.disable_fluentcrm_logs"
                           :aria-label="$t('FluentCRM Email Logging')"
                           active-value="yes" inactive-value="no"/>
                <span class="fsm_toggle_title" @click="toggle('disable_fluentcrm_logs')">
                    {{ $t('FluentCRM Email Logging') }}
                </span>
            </div>
            <div class="fsm_toggle_body">
                <p>{{ $t('Disable Logging for FluentCRM Emails') }}</p>
            </div>
        </div>

        <div class="fsm_row fsm_row_stacked" v-if="settings.misc.log_emails == 'yes'">
            <div class="fsm_row_label">
                <span class="fsm_row_title">{{ $t('Delete Logs') }}</span>
                <p>{{ $t('How long a logged email is kept before FluentSMTP deletes it automatically.') }}</p>
            </div>
            <div class="fsm_row_control">
                <el-select v-model="settings.misc.log_saved_interval_days"
                           :aria-label="$t('Delete Logs')">
                    <el-option
                        v-for="(logLabel, logValue) in logging_days"
                        :key="logValue"
                        :value="logValue"
                        :label="logLabel"
                    ></el-option>
                </el-select>
            </div>
        </div>

        <div class="fsm_toggle">
            <div class="fsm_toggle_main">
                <el-switch v-model="settings.misc.simulate_emails" :aria-label="$t('Email Simulation')"
                           active-value="yes" inactive-value="no"/>
                <span class="fsm_toggle_title" @click="toggle('simulate_emails')">{{ $t('Email Simulation') }}</span>
            </div>
            <div class="fsm_toggle_body">
                <p>{{ $t('__Email_Simulation_Label') }}</p>
                <p class="fsm_row_warning" v-if="settings.misc.simulate_emails == 'yes'">
                    {{ $t('__Email_Simulation_Yes') }}
                </p>
                <p class="fsm_row_warning" v-if="appVars.is_disabled_defined">
                    {{ $t('Emails are being simulated due to the definition of ') }}
                    <b>FLUENTMAIL_SIMULATE_EMAILS</b>{{ $t(' in your PHP code.') }}
                </p>
            </div>
        </div>

        <div class="fsm_toggle">
            <div class="fsm_toggle_main">
                <el-switch v-model="settings.misc.send_as_text"
                           :aria-label="$t('Add Multi-Part Plain Text for HTML Emails (beta)')"
                           active-value="yes" inactive-value="no"/>
                <span class="fsm_toggle_title" @click="toggle('send_as_text')">
                    {{ $t('Add Multi-Part Plain Text for HTML Emails (beta)') }}
                </span>
            </div>
            <div class="fsm_toggle_body">
                <p>{{ $t('__Email_TEXT_PART_Label') }}</p>
            </div>
        </div>

        <div class="fsm_row_actions">
            <el-button v-loading="saving" @click="saveMiscSettings()" type="primary">
                {{ $t('Save Settings') }}
            </el-button>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'FluentMailGeneralSettings',
        data() {
            return {
                saving: false,
                logging_days: {
                    7: this.$t('After 7 Days'),
                    14: this.$t('After 14 Days'),
                    30: this.$t('After 30 Days'),
                    60: this.$t('After 60 Days'),
                    90: this.$t('After 90 Days'),
                    180: this.$t('After 6 Months'),
                    365: this.$t('After 1 Year'),
                    730: this.$t('After 2 Years')
                }
            }
        },
        methods: {
            /*
             * The title beside a switch flips it, the way a <label for> would - which
             * this deliberately is not. Element Plus puts its click handler on the
             * switch's outer element and its change handler on the hidden checkbox
             * inside it, so a label's forwarded click would run both and land back on
             * the value it started from. The switch keeps the name on `aria-label`.
             */
            toggle(key) {
                this.settings.misc[key] = this.settings.misc[key] === 'yes' ? 'no' : 'yes';
            },

            /*
             * Default and Fallback are not on this form any more - they are set from the
             * connection rows beside it, which is where you can see which connection you
             * are choosing. They are still posted, because misc-settings replaces the
             * whole object; they are just not editable here, so the two-selects-agree
             * check this used to run has nothing left to catch.
             */
            saveMiscSettings() {
                this.saving = true;
                this.$post('misc-settings', {
                    settings: this.settings.misc
                })
                    .then(response => {
                        this.$notify.success(response.data.message);
                    })
                    .fail((error) => {
                        /*
                         * The form is not reset here on purpose. The value the user
                         * typed is still the value they want; telling them it did not
                         * save lets them retry without typing it again. What must not
                         * happen is silence, which reads as success until the next
                         * reload puts the old value back.
                         */
                        this.$notify.error(this.$errorMessage(error));
                    })
                    .always(() => {
                        this.saving = false;
                    });
            }
        }
    };
</script>

<style lang="scss">
.fss_general_settings {
    /* The sidebar is one column wide, so a label above its control is the only shape
     * that fits - the select takes the width rather than being pinned right. */
    .fsm_row.fsm_row_stacked {
        .fsm_row_label p {
            margin-bottom: 8px;
        }
    }

    /* Only shown when the setting is not what it ought to be, so it reads as a flag. */
    .fsm_row_warning {
        font-size: 12px;
        color: var(--fsm-warning-fg) !important;
        margin-top: 6px !important;
    }

    /* 12 here and 4 from .fsm_card_body is the 16 the card head is padded by. */
    .fsm_row_actions {
        padding-top: 16px;
        padding-bottom: 12px;

        .el-button {
            width: 100%;
        }
    }
}
</style>
