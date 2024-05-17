<template>
    <div>
        <el-row :gutter="20">
            <el-col :md="12" :sm="24">
                <el-form-item>
                    <label for="sender_email">
                        {{ $t('Sender Email ')}} <error :error="schema.errors.get('sender_email')" />
                    </label>
                    
                    <el-tooltip effect="dark" placement="top-start">
                        <div slot="content">
                            {{ $t('The email address which emails are sent from.') }}
                        </div>
                        <i class="el-icon-info"></i>
                    </el-tooltip>

                    <el-input
                        id="sender_email"
                        v-model="schema.provider.options.sender_email"
                        :placeholder="$t('Sender Email Address')"
                    >
                        <template slot="append"><slot></slot></template>
                    </el-input>
                </el-form-item>
            </el-col>
        
            <el-col :md="12" :sm="24">
                <el-form-item>
                    <label for="sender_name">{{ $t('Sender Name') }}</label>

                    <el-tooltip effect="dark" placement="top-start">
                        <div slot="content">
                            {{ $t('The name which emails are sent from.') }}
                        </div>
                        <i class="el-icon-info"></i>
                    </el-tooltip>

                    <el-input
                        id="sender_name"
                        v-model="schema.provider.options.sender_name"
                        :placeholder="$t('Sender Name')"
                    />
                </el-form-item>
            </el-col>
        </el-row>

        <el-row>
            <el-col :md="12" :sm="24">
                <el-checkbox
                    v-model="schema.provider.options.force_from_name"
                    true-label="yes"
                    false-label="no"
                >{{ $t('Force Sender Name') }}</el-checkbox>

                <el-tooltip effect="dark" placement="top-start">
                    <div slot="content">
                        {{ $t('__FORCE_SENDER_NAME_TIP') }}
                    </div>
                    <i class="el-icon-info"></i>
                </el-tooltip>
            </el-col>
        </el-row>
    </div>
</template>

<script>
    import Error from '@/Pieces/Error';

    export default {
        name: 'General',
        components: {
            Error
        },
        props: ['schema'],
        data() {
            return {
                // 
            };
        }
    };
</script>
