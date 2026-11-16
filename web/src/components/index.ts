/**
 * 公共组件出口。只放跨页面复用的组件，页面私有组件放在页面目录的 components/ 下。
 */

export { default as DeleteAction } from './DeleteAction';
export { default as DictTag } from './DictTag';
export { default as ErrorBoundary } from './ErrorBoundary';
export { default as FeedbackBridge, getMessageApi } from './FeedbackBridge';
export { default as Footer } from './Footer';
export { default as GlobalStyle } from './GlobalStyle';
export { default as IconPicker, MenuIcon } from './IconPicker';
export { default as ImageUpload } from './ImageUpload';
export { default as OfflineBanner } from './OfflineBanner';
export { default as PermissionTypeTag } from './PermissionTypeTag';
export { AvatarDropdown } from './RightContent/AvatarDropdown';
export { LangDropdown } from './RightContent/LangDropdown';
export { NoticeBell } from './RightContent/NoticeBell';
export { ThemeSwitch } from './RightContent/ThemeSwitch';
