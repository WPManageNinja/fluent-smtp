<template>
    <div v-loading="loading" class="fsm_alerts">
        <!--
            The same two columns as Settings, and the same grid: the thing the screen is
            about takes the width, and the smaller subject sits beside it.

            Which is which is the other way round from the old screen, where the summary
            email had the left half and the channels the right. Alerts is opened to
            answer "where does a failure get sent?" - that is the channel list, and it is
            also the half that grows: choosing a channel opens its setup form in place,
            and Telegram's pin, Slack's terms and Discord's webhook field all want more
            than a sidebar's width. The weekly summary is one switch, one field and a set
            of days, which a sidebar holds comfortably.
        -->
        <div class="fsm_split">
            <div class="fsm_split_main">
                <div class="fsm_page_head">
                    <h1 class="fsm_page_title">{{ $t('Alerts & Notifications') }}</h1>
                </div>

                <div class="fsm_card">
                    <div class="fsm_card_head">
                        <div class="fsm_card_head_text">
                            <h2>{{ $t('Email Sending Error Notifications') }}</h2>
                            <p>{{ $t('__REAL_NOTIFICATION_DESC') }}</p>
                        </div>
                    </div>
                    <!--
                        Flush, because what is normally inside is a list of rows that
                        draws its own edges up to the card's. The setup form that
                        replaces the list brings its own padding with it.
                    -->
                    <div class="fsm_card_body fsm_card_flush">
                        <notification-manager v-if="loaded"
                                              :notification_settings="notification_settings"
                                              @reload-settings="getSettings"/>
                    </div>
                </div>
            </div>

            <aside class="fsm_split_aside">
                <div class="fsm_card">
                    <div class="fsm_card_head">
                        <div class="fsm_card_head_text">
                            <h2>{{ $t('Summary Email') }}</h2>
                            <p>{{ $t('__EMAIL_SUMMARY_INTRO') }}</p>
                        </div>
                    </div>
                    <!--
                        The form is not rendered until the settings have arrived.

                        `notification_settings` starts empty, so a failed GET used to
                        leave a working Save button over an empty object. Pressing it
                        posted that empty object, and the controller sanitised the
                        missing `enabled` and `notify_email` keys into empty values and
                        wrote them over the stored ones - turning off a working summary
                        and clearing its recipient, with a success notice. A save may
                        only ever be built from settings that were actually read.
                    -->
                    <div class="fsm_card_body">
                        <email-summary-form v-if="loaded"
                                            :notification_settings="notification_settings"/>
                        <div v-else-if="load_error" class="fsm_load_error">
                            <p>{{ load_error }}</p>
                            <el-button size="small" @click="getSettings()">{{ $t('Retry') }}</el-button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

<script type="text/babel">
import EmailSummaryForm from './_EmailSummaryForm.vue';
import NotificationManager from './NotificationManager.vue';

export default {
    name: 'NotificationSettingsRoot',
    components: {EmailSummaryForm, NotificationManager},
    data() {
        return {
            notification_settings: {},
            /*
             * Whether the GET actually returned. Distinct from `loading`, because the
             * three states are loading / loaded / failed and the forms may only be
             * rendered in the middle one - see the comment on the summary card.
             */
            loaded: false,
            load_error: '',
            loading: false
        }
    },
    methods: {
        getSettings() {
            this.loading = true;
            this.load_error = '';
            this.$get('settings/notification-settings')
                .then((response) => {
                    this.notification_settings = response.data.settings;
                    this.loaded = true;
                })
                .fail((errors) => {
                    this.load_error = this.$errorMessage(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
    },
    mounted() {
        this.getSettings();
    }
}
</script>
