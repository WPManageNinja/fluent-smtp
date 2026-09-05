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
            A credential that is already saved is never sent to the browser - what
            arrives in its place is the mask, and this is what the mask looks like: a
            filled, untouchable field, and a button to replace it.

            The alternative - letting the mask sit in an editable password field - looks
            identical to a real key behind the dots, and a single stray keystroke in it
            would submit a truncated sentinel that the server would take for a new
            credential and save over the working one. There is nothing here to
            half-edit: replacing is a decision, and Cancel undoes it.
        -->
        <div v-if="isMasked" class="fsm_input_password_saved">
            <el-input
                :id="id"
                type="password"
                model-value="0000000000000000"
                disabled
            />
            <!-- SMTP turns its password field off when auth is set to none. -->
            <el-button size="small" :disabled="disabled" @click="startReplacing">
                {{ $t('Replace') }}
            </el-button>
        </div>

        <div v-else class="fsm_input_password_field">
            <!--
                `model-value` and `update:modelValue`, which is Vue 3's v-model contract.

                This declared a `value` prop and emitted `input` - Vue 2's - while every
                provider form binds it with `v-model`. Vue 3 passes `modelValue` and listens
                for `update:modelValue`, so the two never met: an API key or SMTP password
                already saved never appeared in the field, and anything typed into it was
                painted back out on the next render and never reached the connection.
            -->
            <el-input
                ref="field"
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
            <!--
                Only offered while replacing something that was saved. It puts the mask
                back, so someone who pressed Replace by mistake is not left having to
                remember a key they never meant to touch.
            -->
            <el-button v-if="replacing" size="small" @click="cancelReplacing">
                {{ $t('Cancel') }}
            </el-button>
        </div>

        <p v-if="isMasked" class="small-help-text" style="font-size: 80%; margin: 3px 0 0 0">
            {{ $t('__SECRET_SAVED_HELP') }}
        </p>
        <p v-else-if="!disable_help" class="small-help-text" style="font-size: 80%; margin: 3px 0 0 0">
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
                replacing: false
            };
        },
        computed: {
            /*
             * The sentinel the server sends in place of a stored credential. Read from
             * appVars rather than written out here, so the two halves of the contract
             * cannot drift apart - see SecretMasker::MASK in app/Services/SecretMasker.php.
             */
            maskedKey() {
                return this.appVars && this.appVars.masked_key;
            },

            isMasked() {
                return Boolean(this.maskedKey) && this.modelValue === this.maskedKey;
            },

            fieldName() {
                return 'fluentsmtp_' + (this.id || Math.random().toString(36).slice(2)) + '_secret';
            }
        },
        methods: {
            /*
             * Empty, not "the old value with the cursor in it". An empty field submits
             * as empty, which is what tells the server to clear the credential - and
             * every provider that requires one rejects it before anything is saved, so
             * a Replace that is started and abandoned cannot quietly break a working
             * connection.
             */
            startReplacing() {
                this.replacing = true;
                this.$emit('update:modelValue', '');
                this.$nextTick(() => {
                    if (this.$refs.field) {
                        this.$refs.field.focus();
                    }
                });
            },

            cancelReplacing() {
                this.replacing = false;
                this.$emit('update:modelValue', this.maskedKey);
            }
        }
    };
</script>

<style lang="scss">
    .fsm_input_password {
        width: 100%;

        .fsm_input_password_saved,
        .fsm_input_password_field {
            display: flex;
            align-items: center;
            gap: 8px;

            .el-input {
                flex: 1 1 auto;
                min-width: 0;
            }

            .el-button {
                flex: 0 0 auto;
            }
        }
    }
</style>
