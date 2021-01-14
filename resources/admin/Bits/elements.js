import Vue from 'vue';
import lang from 'element-ui/lib/locale/lang/en';
import locale from 'element-ui/lib/locale';

import {
    Tag,
    Row,
    Col,
    Tabs,
    Card,
    Menu,
    Form,
    Link,
    Step,
    Alert,
    Table,
    Input,
    InputNumber,
    Steps,
    Radio,
    Upload,
    Option,
    Button,
    Select,
    Dialog,
    TabPane,
    Popover,
    Loading,
    Tooltip,
    Progress,
    MenuItem,
    Dropdown,
    Checkbox,
    FormItem,
    Pagination,
    DatePicker,
    TimePicker,
    RadioGroup,
    MessageBox,
    Breadcrumb,
    OptionGroup,
    ButtonGroup,
    TableColumn,
    DropdownMenu,
    Notification,
    DropdownItem,
    CheckboxGroup,
    BreadcrumbItem,
    Submenu,
    Container,
    Aside,
    Main,
    Collapse,
    CollapseItem,
    ColorPicker,
    Slider,
    RadioButton,
    Switch,
    Badge,
    Timeline,
    TimelineItem,
    Image
} from 'element-ui';

Vue.use(Collapse);
Vue.use(CollapseItem);
Vue.use(Container);
Vue.use(ColorPicker);
Vue.use(Slider);
Vue.use(Aside);
Vue.use(Main);
Vue.use(Tag);
Vue.use(Submenu);
Vue.use(Row);
Vue.use(Col);
Vue.use(Tabs);
Vue.use(Card);
Vue.use(Menu);
Vue.use(Form);
Vue.use(Link);
Vue.use(Step);
Vue.use(Alert);
Vue.use(Table);
Vue.use(Input);
Vue.use(InputNumber);
Vue.use(Steps);
Vue.use(Radio);
Vue.use(RadioButton);
Vue.use(Button);
Vue.use(Select);
Vue.use(Switch);
Vue.use(Option);
Vue.use(Dialog);
Vue.use(Upload);
Vue.use(TabPane);
Vue.use(Popover);
Vue.use(Tooltip);
Vue.use(Progress);
Vue.use(MenuItem);
Vue.use(Dropdown);
Vue.use(Checkbox);
Vue.use(FormItem);
Vue.use(Pagination);
Vue.use(DatePicker);
Vue.use(TimePicker);
Vue.use(RadioGroup);
Vue.use(Breadcrumb);
Vue.use(OptionGroup);
Vue.use(ButtonGroup);
Vue.use(TableColumn);
Vue.use(DropdownMenu);
Vue.use(DropdownItem);
Vue.use(CheckboxGroup);
Vue.use(BreadcrumbItem);
Vue.use(Badge);
Vue.use(Timeline);
Vue.use(Image);
Vue.use(TimelineItem);
Vue.use(Loading.directive);

Vue.prototype.$message = MessageBox.alert;
Vue.prototype.$notify = Notification;

locale.use(lang);

export default Vue;
