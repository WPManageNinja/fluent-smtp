<template>
    <!--
        `trigger="click"` is not a preference, it is the default that changed underneath
        this component. Element UI's popover opened on click; Element Plus's opens on
        hover, and neither this nor `v-model` said otherwise - so every delete
        confirmation in the admin armed itself when the pointer crossed the button and
        did nothing at all when you pressed it.

        The visibility is the popover's own, reached through a ref. `v-model` bound
        `modelValue`, which el-popover does not have (it takes `v-model:visible`), so it
        was inert - and passing `visible` at all would put the popover in controlled
        mode, where `trigger` is ignored and nothing would open it.
    -->
    <el-popover
        ref="popover"
        trigger="click"
        width="170"
        :placement="placement">

        <p v-html="messageText"></p>

        <div class="action-buttons">
            <el-button
                size="small"
                link
                @click="cancel()">
                {{ $t('Cancel') }}
            </el-button>

            <el-button
                type="primary"
                size="small"
                @click="confirm()">
                {{ $t('Confirm') }}
            </el-button>
        </div>

        <template #reference>
            <slot name="reference">
                <el-icon><FsmIconDelete /></el-icon>
            </slot>
        </template>
    </el-popover>
</template>

<script>
    export default {
        name: 'Confirm',
        props: {
            placement: {
                default: 'top-end'
            },
            /*
             * Empty by default rather than carrying English: a prop default cannot
             * reach $t(), so the fallback wording is resolved in messageText instead.
             */
            message: {
                default: ''
            }
        },
        computed: {
            messageText() {
                return this.message || this.$t('Delete this?');
            }
        },
        methods: {
            hide() {
                this.$refs.popover && this.$refs.popover.hide();
            },
            confirm() {
                this.hide();

                this.$emit('yes');
            },
            cancel() {
                this.hide();

                this.$emit('no');
            }
        }
    }
</script>
