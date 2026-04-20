<template>
    <div>
        <el-input
            :id="id"
            :type="type"
            :value="value"
            :placeholder="placeholder"
            :disabled="disabled"
            :name="fieldName"
            autocomplete="new-password"
            data-bwignore
            data-lpignore="true"
            data-1p-ignore
            data-form-type="other"
            @input="$emit('input', $event)"
        >
        </el-input>
        <p v-if="!disable_help" class="small-help-text" style="font-size: 80%; margin: 3px 0 0 0">
            {{$t('__PASSWORD_ENCRYPT_HELP')}}
            <el-popover
                width="400"
                trigger="hover">
                <p>{{$t('__PASSWORD_ENCRYPT_TIP')}}</p>
                <i slot="reference" class="el-icon el-icon-info"></i>
            </el-popover>
        </p>
    </div>
</template>

<script>
    export default {
        name: 'InputPassword',
        props: ['value', 'id', 'placeholder', 'disabled', 'disable_help'],
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
