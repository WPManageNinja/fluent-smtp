<template>
    <div v-loading="loading" style="min-height: 200px" element-loading-text="Loading Details..." class="fss_connection_details">
        <div v-html="connection_content"></div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'connection_details',
        props: ['connection_id'],
        data() {
            return {
                loading: false,
                connection_content: ''
            }
        },
        methods: {
            async fetchDetails() {
                this.loading = true;
                const settings = await this.$get('settings/connection_info', {
                    connection_id: this.connection_id
                });
                this.connection_content = settings.data.info;
                this.loading = false;
            }
        },
        created() {
            this.fetchDetails();
        }
    }
</script>
