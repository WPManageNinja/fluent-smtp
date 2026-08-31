<template>
    <div class="fluentmail_connections">
        <!--
            Two sections side by side, the way this screen was built before the redesign:
            the connections take the width, and the settings that apply to all of them
            sit beside the list rather than under it. Each is a section with its own
            heading - they are separate things, and the only thing they share is the page.

            `.fsm_split` is the dashboard's own two-column layout, used here rather than
            a second one of nearly the same measurements: an admin who moves between the
            two screens should find the column in the same place and the same width both
            times, and one grid means one thing to change when that width changes.

            Stacked, General Settings was a card you had to scroll past every connection
            to reach, and could never see while looking at the list it governs - log
            retention and simulation are read against the connections, not on their own.
            Beside the list it is there from the moment the screen opens.
        -->
        <div class="fsm_split">
            <div class="fsm_split_main">
                <!--
                    The heading row belongs to this column, not to the page.

                    Held above both columns it pushed General Settings down the page and
                    left the top right corner empty, and the button in it sat over two
                    things - the list it adds to, and a card of settings it has nothing
                    to do with. Inside the column it is the list's own header: the title
                    and the action line up over the connections, and General Settings
                    starts level with them at the top of its own column.
                -->
                <div class="fsm_page_head">
                    <h1 class="fsm_page_title">{{ $t('Settings') }}</h1>
                    <div class="fsm_page_actions">
                        <el-button type="primary" icon="FsmIconPlus" @click="addConnection">
                            {{ $t('Add Another Connection') }}
                        </el-button>
                    </div>
                </div>

                <!--
                    Rows rather than a table.

                    This is the screen the plugin exists for, and the question it is
                    opened to answer is "which connection actually sends my mail?" -
                    which the table could not answer. It listed provider, from address
                    and three icon buttons, while the two settings that decide the
                    routing sat in a pair of <select>s six hundred pixels away, under a
                    different heading. So the answer was on the screen and nowhere near
                    the question.

                    A row now carries the whole of what it is: whether it is working,
                    what it sends as, and whether it is the Default or the Fallback - and
                    Default and Fallback are set from the row itself, which is where you
                    are looking when you decide to change them.
                -->
                <div class="fsm_card">
                    <div class="fsm_card_head">
                        <h2>{{ $t('Active Email Connections') }}</h2>
                    </div>
                    <div class="fsm_card_body fsm_card_flush">
                        <ul class="fsm_conn_list">
                            <li v-for="connection in connections" :key="connection.unique_key"
                                class="fsm_conn">
                                <!--
                                    The provider's logo, in a fixed column so the
                                    addresses beside them still line up.

                                    Every one of these is a wordmark - the SendGrid logo
                                    says "SendGrid" - so it names the provider on its own
                                    and the title beside it would have said it twice. If
                                    an image is missing or the provider is one this build
                                    does not know, the name is printed instead.
                                -->
                                <div class="fsm_conn_provider">
                                    <img v-if="providerOf(connection) && providerOf(connection).image"
                                         :src="providerOf(connection).image"
                                         :alt="providerOf(connection).title"
                                         :title="providerOf(connection).title"/>
                                    <span v-else>
                                        {{ providerOf(connection) ? providerOf(connection).title : $t('Unknown') }}
                                    </span>
                                </div>

                                <div class="fsm_conn_main">
                                    <button type="button" class="fsm_conn_email"
                                            @click="showConnection(connection)">
                                        {{ connection.sender_email }}
                                    </button>
                                    <span class="fsm_conn_note">{{ noteFor(connection) }}</span>
                                </div>

                                <!--
                                    A connection says something about its health only when
                                    there is something to say. This was a coloured dot at
                                    the head of the row - green for working, hollow for
                                    not yet checked - which spent a permanent slot on good
                                    news and gave the reader nothing to read it by. The
                                    one state worth interrupting for now says so in words,
                                    in the same chips Default and Fallback use.
                                -->
                                <div class="fsm_conn_marks">
                                    <span v-if="health(connection).status === 'error'"
                                          class="fsm_tag is_failed">
                                        {{ health(connection).message }}
                                    </span>
                                    <span v-if="isDefault(connection)" class="fsm_tag is_default">
                                        {{ $t('Default') }}
                                    </span>
                                    <span v-if="isFallback(connection)" class="fsm_tag is_fallback">
                                        {{ $t('Fallback') }}
                                    </span>
                                </div>

                                <!--
                                    Edit is a button on the row, not an item in the menu.
                                    It is what people open a connection to do - the
                                    credentials expired, the from address changed - and
                                    the old table had it as a button too. Routing and
                                    Delete stay in the menu: one is set from the row's
                                    own marks and the other is rare and destructive.
                                -->
                                <div class="fsm_conn_actions">
                                    <el-button size="small" icon="FsmIconEdit"
                                               :title="$t('Edit')" :aria-label="$t('Edit')"
                                               @click="editConnection(connection)"/>

                                    <el-dropdown trigger="click" @command="cmd => onCommand(cmd, connection)">
                                        <el-button size="small" icon="FsmIconMore"
                                                   :title="$t('Actions')" :aria-label="$t('Actions')"/>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item command="default"
                                                                  :disabled="isDefault(connection) || !isUsable(connection)">
                                                    {{ $t('Set as Default') }}
                                                </el-dropdown-item>
                                                <el-dropdown-item command="fallback"
                                                                  :disabled="isFallback(connection) || isDefault(connection) || !isUsable(connection)">
                                                    {{ $t('Set as Fallback') }}
                                                </el-dropdown-item>
                                                <el-dropdown-item v-if="isFallback(connection)" command="clear_fallback">
                                                    {{ $t('Clear Fallback') }}
                                                </el-dropdown-item>
                                                <el-dropdown-item command="view" divided>
                                                    {{ $t('View') }}
                                                </el-dropdown-item>
                                                <el-dropdown-item command="delete">
                                                    {{ $t('Delete') }}
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!--
                    The rule the rows above are an instance of - a quiet line under the
                    list rather than the el-alert it used to be, but shown on the same
                    condition it always was: only once there is more than one connection.

                    On a site with one connection there is nothing for the rule to
                    describe. Every email goes through that connection whatever its From
                    address, the Default is the only connection there is, and a Fallback
                    cannot be set at all - so the sentence would be explaining machinery
                    the reader cannot see, has not chosen, and cannot use.
                -->
                <p v-if="connections.length > 1" class="fsm_conn_rule">
                    {{ $t('__routing_rule') }}
                </p>

                <div v-if="showing_connection" class="fsm_card">
                    <div class="fsm_card_head">
                        <h2>{{ $t('Connection Details') }}</h2>
                        <div class="fsm_card_head_actions">
                            <el-button link @click="showing_connection = ''">{{ $t('Close') }}</el-button>
                        </div>
                    </div>
                    <div class="fsm_card_body">
                        <connection-details :connection_id="showing_connection" />
                    </div>
                </div>
            </div>

            <aside class="fsm_split_aside">
                <div class="fsm_card fsm_general_settings">
                    <div class="fsm_card_head">
                        <h2>{{ $t('General Settings') }}</h2>
                    </div>
                    <div class="fsm_card_body">
                        <general-settings />
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

<script type="text/babel">
    import isEmpty from 'lodash/isEmpty';
    import GeneralSettings from './_GeneralSettings'

    import ConnectionDetails from './ConnectionDetails'

    export default {
        name: 'Connections',
        components: {
            GeneralSettings,
            ConnectionDetails
        },
        data() {
            return {
                showing_connection: '',
                /*
                 * Keyed by connection key, as ConnectionHealth stores it. A key that is
                 * absent has not been checked yet - the scheduled check has not run, or
                 * the connection was added since it last did - which is `unknown`, not
                 * healthy. Saying "working" about something never tested would be worse
                 * than saying nothing.
                 */
                health_report: {}
            };
        },
        methods: {
            async fetch() {
                const settings = await this.$get('settings');
                this.settings.mappings = settings.data.settings.mappings;
                this.settings.connections = settings.data.settings.connections;
                this.health_report = settings.data.health || {};

                if (isEmpty(this.settings.connections)) {
                    this.$router.push({
                        name: 'dashboard',
                        query: {
                            is_redirect: 'yes'
                        }
                    });
                }
            },

            providerOf(connection) {
                return this.settings.providers[connection.provider] || null;
            },

            health(connection) {
                const entry = this.health_report[connection.unique_key];

                /*
                 * Gmail connections stored before the API change carry no `version`, and
                 * need re-authenticating whatever the last check said - the table used to
                 * flag this and it is the one failure a user can act on immediately.
                 */
                if (connection.provider === 'gmail' && !connection.version) {
                    return {
                        status: 'error',
                        message: this.$t('(Re Authentication Required)')
                    };
                }

                if (!entry) {
                    return {status: 'unknown', message: this.$t('Not checked yet')};
                }

                return {
                    status: entry.status === 'error' ? 'error' : 'healthy',
                    message: entry.message || (entry.status === 'error'
                        ? this.$t('Connection needs attention')
                        : this.$t('Working'))
                };
            },

            /* A failing connection can still be edited and deleted, but not routed to. */
            isUsable(connection) {
                return this.health(connection).status !== 'error';
            },

            isDefault(connection) {
                return this.settings.misc.default_connection === connection.unique_key;
            },

            isFallback(connection) {
                return this.settings.misc.fallback_connection === connection.unique_key;
            },

            /*
             * The second line of a row. A connection's own address always routes to it,
             * which is the part of the rule that applies to this row specifically; the
             * host or region is what tells two connections on the same provider apart.
             */
            noteFor(connection) {
                /*
                 * PHP mail() has neither a host nor a region - it is whatever the server
                 * is - but a connection saved through the form carries `region: us` from
                 * the field's default anyway, and the row printed it. There is also only
                 * ever one of these, so there is nothing for a detail to tell apart.
                 */
                const detail = connection.provider === 'default'
                    ? ''
                    : (connection.host || connection.region || '');

                return detail
                    ? `${detail} · ${this.$t('__routes_own_address')}`
                    : this.$t('__routes_own_address');
            },

            onCommand(command, connection) {
                if (command === 'view') {
                    return this.showConnection(connection);
                }

                if (command === 'edit') {
                    return this.editConnection(connection);
                }

                if (command === 'delete') {
                    return this.confirmDelete(connection);
                }

                if (command === 'default') {
                    return this.setRouting({
                        default_connection: connection.unique_key,
                        /*
                         * One connection cannot be both. Promoting the fallback clears
                         * the fallback rather than leaving the pair in a state the
                         * General Settings form refuses to save.
                         */
                        fallback_connection: this.isFallback(connection)
                            ? ''
                            : this.settings.misc.fallback_connection
                    }, this.$t('Default connection updated.'));
                }

                if (command === 'fallback') {
                    return this.setRouting(
                        {fallback_connection: connection.unique_key},
                        this.$t('Fallback connection updated.')
                    );
                }

                if (command === 'clear_fallback') {
                    return this.setRouting(
                        {fallback_connection: ''},
                        this.$t('Fallback connection cleared.')
                    );
                }
            },

            /*
             * misc-settings replaces the whole misc object, so the change is merged onto
             * what is already loaded rather than posted on its own - posting only the two
             * routing keys would blank logging, simulation and the rest.
             */
            setRouting(change, message) {
                const previous = {...this.settings.misc};
                const misc = {...previous, ...change};

                this.settings.misc = misc;

                return this.$post('misc-settings', {settings: misc})
                    .then(() => {
                        this.$notify.success(message);
                    })
                    .fail(error => {
                        this.settings.misc = previous;
                        console.log(error);
                        this.$notify.error(this.$t('Could not save. Please try again.'));
                    });
            },

            addConnection() {
                this.$router.push({ name: 'connection' });
            },

            editConnection(connection) {
                this.$router.push({
                    name: 'connection',
                    query: { connection_key: connection.unique_key }
                });
            },

            confirmDelete(connection) {
                this.$confirm(
                    this.$t('__delete_connection_confirm'),
                    this.$t('Delete'),
                    {type: 'warning'}
                )
                    .then(() => this.deleteConnection(connection))
                    .catch(() => {});
            },

            async deleteConnection(connection) {
                const result = await this.$post('settings/delete', {
                    key: connection.unique_key
                });

                this.settings.connections = result.data.connections;
                this.settings.misc.default_connection = result.data.misc.default_connection;
                this.settings.misc.fallback_connection = result.data.misc.fallback_connection || '';

                this.$notify.success({
                    title: 'Great!',
                    message: this.$t('Connection deleted Successfully.'),
                    offset: 19
                });
            },

            showConnection(connection) {
                this.showing_connection = '';
                this.$nextTick(() => {
                    this.showing_connection = connection.unique_key;
                });
            }
        },
        computed: {
            connections() {
                const data = [];

                jQuery.each(this.settings.connections, (key, connection) => {
                    data.push({
                        unique_key: key,
                        title: connection.title,
                        ...connection.provider_settings
                    });
                });

                return data;
            }
        },
        created() {
            this.fetch();
        }
    };
</script>
