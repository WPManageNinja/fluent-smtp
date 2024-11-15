<template>
    <div>
        <el-row :gutter="20">
            <el-col :md="12" :sm="24">
                <el-form-item>
                    <label for="connection_name">
                        {{ $t('Connection Name ')}} <error :error="schema.errors.get('connection_name')" />
                    </label>

                    <el-tooltip effect="dark" placement="top-start">
                        <div slot="content">
                            {{ $t('A name for the connection.') }}
                        </div>
                        <i class="el-icon-info"></i>
                    </el-tooltip>

                    <el-input id="connection_name" v-model="schema.connection_name" />
                </el-form-item>
            </el-col>
        
            <el-col :md="12" :sm="24">
                <el-form-item>
                    <label for="provider_name">{{ $t('Select Provider') }}</label>

                    <el-tooltip effect="dark" placement="top-start">
                        <div slot="content">
                            {{ $t('provider for the connection.') }}
                        </div>
                        <i class="el-icon-info"></i>
                    </el-tooltip>

                    <el-select id="provider_name" v-model="schema.provider_name" :placeholder="$t('Select')">
                        <el-option
                            v-for="p in settings.providers"
                            :label="p.title"
                            :value="p.key"
                            :key="p.key"
                        />
                    </el-select>
                </el-form-item>
            </el-col>
        </el-row>

        <el-row>
            <el-col>
                <general :schema="schema" />
            </el-col>
        </el-row>
    </div>
</template>

<script>
    import general from './General';
    import Error from '@/Pieces/Error';

    export default {
        name: 'First',
        props: ['schema'],
        components: {
            Error,
            general
        },
        data() {
            return {
                
            };
        },
        watch: {
            'schema.provider_name': {
                immediate: true,
                handler: function(newProviderName, oldProviderName) {
                    this.schema.provider = this.schema.providers[newProviderName];
                    
                    if (this.schema.key) {
                        const connection = this.settings.connections[this.schema.key];
                        if (this.schema.provider_name === connection.provider_settings.provider) {
                            this.schema.provider.options = connection.provider_settings;
                        }
                    } else {
                        this.schema.provider.options.sender_name = this.schema.provider.options.sender_name || '';
                        this.schema.provider.options.sender_email = this.schema.provider.options.sender_email || '';
                    }

                    if (oldProviderName) {
                        const {
                            sender_name: name,
                            sender_email: email,
                            force_from_name: forceName,
                            force_from_email: forceEmail
                        } = this.schema.providers[oldProviderName].options;

                        this.schema.provider.options.sender_name = name;
                        this.schema.provider.options.sender_email = email;
                        this.schema.provider.options.force_from_name = forceName;
                        this.schema.provider.options.force_from_email = forceEmail;
                    }
                }
            }
        }
    };
</script>
