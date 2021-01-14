<template>
    <div>
        <el-row :gutter="20">
            <el-col :span="12">
                <el-form-item>
                    <label for="sender_email">
                        Sender Email <error :error="schema.errors.get('sender_email')" />
                    </label>
                    
                    <el-tooltip effect="dark" placement="top-start">
                        <div slot="content">
                            The email address which emails are sent from.
                        </div>
                        <i class="el-icon-info"></i>
                    </el-tooltip>

                    <el-input
                        id="sender_email"
                        v-model="schema.provider.options.sender_email"
                        placeholder="Sender Email Address"
                    >
                        <template slot="append"><slot></slot></template>
                    </el-input>
                </el-form-item>
            </el-col>
        
            <el-col :span="12">
                <el-form-item>
                    <label for="sender_name">Sender Name</label>

                    <el-tooltip effect="dark" placement="top-start">
                        <div slot="content">
                            The name which emails are sent from.
                        </div>
                        <i class="el-icon-info"></i>
                    </el-tooltip>

                    <el-input
                        id="sender_name"
                        v-model="schema.provider.options.sender_name"
                        placeholder="Sender Name"
                    />
                </el-form-item>
            </el-col>
        </el-row>

        <el-row>
            <el-col :span="12">
                <!-- <el-checkbox
                    v-model="schema.provider.options.force_from_email"
                    true-label="yes"
                    false-label="no"
                >Force Sender Email</el-checkbox>

                <el-tooltip effect="dark" placement="top-start">
                    <div slot="content">
                        If checked, the From Email setting above will be used for all emails, ignoring values set by other plugins.
                    </div>
                    <i class="el-icon-info"></i>
                </el-tooltip> -->
                &nbsp;
            </el-col>
            
            <el-col :span="12">
                <el-checkbox
                    v-model="schema.provider.options.force_from_name"
                    true-label="yes"
                    false-label="no"
                >Force Sender Name</el-checkbox>

                <el-tooltip effect="dark" placement="top-start">
                    <div slot="content">
                        When checked, the From Name setting above will be used for all emails, ignoring values set by other plugins.
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
