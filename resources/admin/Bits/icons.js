/**
 * The Element Plus icons this app uses, in one place.
 *
 * Element Plus dropped the icon font that Element UI shipped, so every
 * `class="el-icon-edit"` and `icon="el-icon-delete"` in the templates became a
 * component that has to be imported. There were 47 of them across the app;
 * re-exporting from here means one import list to read rather than 47 scattered
 * ones, and one place to look when an icon needs replacing.
 *
 * The names keep the old Element UI ones after an `Fsm` prefix, so a call site
 * reads much like the class it replaced. The prefix is not decoration: the
 * `ElIcon*` namespace belongs to ElementPlusResolver, which would intercept
 * `<el-icon-info />` and try to auto-import a non-existent `Info` export
 * instead of resolving our globally registered component.
 */
export {
    InfoFilled as FsmIconInfo,
    Delete as FsmIconDelete,
    Refresh as FsmIconRefresh,
    RefreshRight as FsmIconRefreshRight,
    Plus as FsmIconPlus,
    Edit as FsmIconEdit,
    View as FsmIconView,
    SuccessFilled as FsmIconSuccess,
    Search as FsmIconSearch,
    ArrowLeft as FsmIconArrowLeft,
    ArrowRight as FsmIconArrowRight,
    WarningFilled as FsmIconWarning,
    Timer as FsmIconTimer,
    Promotion as FsmIconSPromotion,
    Message as FsmIconMessage,
    FullScreen as FsmIconFullScreen,
    Close as FsmIconClose,
    Link as FsmIconLink,
    User as FsmIconUser
} from '@element-plus/icons-vue';
