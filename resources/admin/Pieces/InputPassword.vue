<template>
    <!--
        A class on the root, so the field can be told to take the whole line.

        This renders a plain <div>, and an el-form-item lays its content out as a
        wrapping flex row - so where an el-input fills the row and pushes the label above
        it, this shrank to its content and sat *beside* the label instead. On every
        single-column form - the API key on toSend, SendGrid, Brevo, SparkPost, Netcore,
        Postmark, ElasticEmail, SMTP2GO and Cloudflare - the label ended up in the margin.
    -->
    <div class="fsm_input_password">
        <!--
            `model-value` and `update:modelValue`, which is Vue 3's v-model contract.

            This declared a `value` prop and emitted `input` - Vue 2's - while every
            provider form binds it with `v-model`. Vue 3 passes `modelValue` and listens
            for `update:modelValue`, so the two never met: an API key or SMTP password
            already saved never appeared in the field, and anything typed into it was
            painted back out on the next render and never reached the connection.
        -->
        <el-input
            :id="id"
            :type="type"
            :model-value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :name="fieldName"
            autocomplete="new-password"
            data-bwignore
            data-lpignore="true"
            data-1p-ignore
            data-form-type="other"
            @update:model-value="$emit('update:modelValue', $event)"
        >
        </el-input>
        <p v-if="!disable_help" class="small-help-text" style="font-size: 80%; margin: 3px 0 0 0">
            {{$t('__PASSWORD_ENCRYPT_HELP')}}
            <el-popover
                width="400"
                trigger="hover">
                <p>{{$t('__PASSWORD_ENCRYPT_TIP')}}</p>
                <template #reference>
                    <el-icon><FsmIconInfo /></el-icon>
                </template>
            </el-popover>
        </p>
    </div>
</template>

<script>
    export default {
        name: 'InputPassword',
        props: ['modelValue', 'id', 'placeholder', 'disabled', 'disable_help'],
        emits: ['update:modelValue'],
        data() {
            return {
                type: 'password',
                styleObject: {
                    'text-decoration': 'line-through'
                },
                src: window.FluentMail.appVars.image_url + '/eye-cross.png'
            };
        },
        computed: {
            fieldName() {
                return 'fluentsmtp_' + (this.id || Math.random().toString(36).slice(2)) + '_secret';
            }
        },
        methods: {
            toggle() {
                this.type = this.type === 'text' ? 'password' : 'text';
                this.styleObject['text-decoration'] = this.type === 'text' ? 'none' : 'line-through';
            }
        }
    };
</script>
