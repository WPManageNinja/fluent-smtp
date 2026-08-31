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

        <p v-html="message"></p>

        <div class="action-buttons">
            <el-button
                size="small"
                link
                @click="cancel()">
                {{$t('cancel')}}
            </el-button>

            <el-button
                type="primary"
                size="small"
                @click="confirm()">
                {{ $t('confirm') }}
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
            message: {
                default: 'Are you sure to delete this?'
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
