<template>
    <div>
        <el-input
            :id="id"
            :type="type"
            :value="value"
            :placeholder="placeholder"
            :disabled="disabled"
            @input="$emit('input', $event)"
        >
        </el-input>
        <p class="small-help-text" style="font-size: 80%; margin: 3px 0 0 0">
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
        props: ['value', 'id', 'placeholder', 'disabled'],
        data() {
            return {
                type: 'password',
                styleObject: {
                    'text-decoration': 'line-through'
                },
                src: window.FluentMail.appVars.image_url + '/eye-cross.png'
            };
        },
        methods: {
            toggle() {
                this.type = this.type === 'text' ? 'password' : 'text';
                this.styleObject['text-decoration'] = this.type === 'text' ? 'none' : 'line-through';
            }
        }
    };
</script>
