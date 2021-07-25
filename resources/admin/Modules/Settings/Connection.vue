<template>
    <div class="connection">
        <div class="header">
            {{ title }}
        </div>

        <div class="content">
            <div class="fss_connection_intro">
                <connection-wizard
                    :connection="provider"
                    :connection_key="provider_key"
                    :providers="settings.providers"
                />
            </div>
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
                title: 'Add Connection',
                provider: {},
                provider_key: ''
            };
        },
        methods: {
        },
        created() {
            const key = this.$route.query.connection_key;
            if (key && key !== '0') {
                this.title = 'Edit Connection';
                this.provider = this.settings.connections[key].provider_settings;
                this.provider_key = key;
            }
        }
    };
</script>
