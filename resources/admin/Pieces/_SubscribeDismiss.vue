<template>
    <!--
        A button, not a bare icon. Dismissing the card is a permanent choice - it posts
        subscribe-dismiss and the card never comes back - so it needs a name a screen
        reader can read out and a hit area bigger than the glyph.
    -->
    <button type="button" class="fsm_optin_dismiss" @click="dismiss()"
            :title="$t('Dismiss')" :aria-label="$t('Dismiss')">
        <el-icon><FsmIconClose/></el-icon>
    </button>
</template>

<script type="text/babel">
    export default {
        name: 'SubscribeDismiss',
        methods: {
            dismiss() {
                this.$post('settings/subscribe-dismiss')
                    .then(response => {
                        this.appVars.require_optin = 'no';
                    })
                    .catch((errors) => {
                        this.$notify.error(this.$errorMessage(errors));
                    });
            }
        }
    }
</script>

<style lang="scss">
.fsm_optin_dismiss {
    @apply flex items-center justify-center cursor-pointer bg-transparent text-ink-light;

    width: 24px;
    height: 24px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    transition: background-color .15s ease, color .15s ease;

    &:hover {
        @apply bg-surface-sunk text-ink;
    }

    .el-icon {
        font-size: 14px;
    }
}
</style>
