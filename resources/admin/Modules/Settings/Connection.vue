<template>
    <div class="connection">
        <!--
            One column, heading included.

            The heading carries what the connection *is*: the title on the left, the
            provider you picked and the way to change it on the right - and it is capped
            to the same measure as the form under it, so the two ends of that line sit
            over the two edges of the fields rather than out at the edges of the window.

            The way back is on the screen, not only in the bar. This is reached from the
            connections list by pressing Add or Edit, and until now the only way back to
            it was to press Settings in the nav - which reads as leaving rather than as
            cancelling what you started.
        -->
        <div class="fsm_form_column">
            <div class="fsm_page_head">
                <div>
                    <router-link :to="{name: 'connections'}" class="fsm_page_back">
                        <el-icon><FsmIconArrowLeft/></el-icon>{{ $t('Back to Settings') }}
                    </router-link>
                    <h1 class="fsm_page_title">{{ title }}</h1>
                </div>

                <!--
                    The button first, the logo last: the logo is what this connection is,
                    so it sits on the corner, over the right edge of the fields below,
                    and the way to change it leads up to it.
                -->
                <div v-if="chosen" class="fsm_page_actions">
                    <el-button size="small" icon="FsmIconEdit" @click="changeProvider">
                        {{ $t('change') }}
                    </el-button>
                    <span class="fsm_provider_chosen_mark">
                        <img :src="chosen.image" :alt="chosen.title" :title="chosen.title"/>
                    </span>
                </div>
            </div>

            <connection-wizard
                ref="wizard"
                :connection="provider"
                :connection_key="provider_key"
                :providers="settings.providers"
                :connections="settings.connections"
                :hide_chosen="true"
            />
        </div>
    </div>
</template>

<script>
    import ConnectionWizard from './ConnectionWizard';
    export default {
        name: 'Connection',
        components: {
            ConnectionWizard
        },
        data() {
            return {
                active: 1,
                title: this.$t('Add Connection'),
                provider: {},
                provider_key: ''
            };
        },
        computed: {
            /* The provider's entry in the registry, once one has been picked. */
            chosen() {
                const key = this.provider.provider;

                return key ? (this.settings.providers[key] || null) : null;
            }
        },
        methods: {
            /* The plate lives up here, so the button that reopens the picker does too. */
            changeProvider() {
                this.$refs.wizard.openPicker();
            }
        },
        created() {
            const key = this.$route.query.connection_key;
            if (key && key !== '0') {
                const connection = this.settings.connections[key];

                // A bookmarked or back-buttoned edit URL for a connection deleted since.
                if (!connection) {
                    this.$router.replace({name: 'connections'});
                    return;
                }

                this.title = this.$t('Edit Connection');
                this.provider = connection.provider_settings;
                this.provider_key = key;
            }
        }
    };
</script>
