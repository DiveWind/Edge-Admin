import type zh from '../zh-CN/component';

const messages: Record<keyof typeof zh, string> = {
  'component.upload.select': 'Upload',
  'component.upload.imageOnly': 'Only image files are allowed',
  'component.upload.tooLarge': 'Image must be smaller than {size}MB',
  'component.iconPicker.placeholder': 'Select an icon',
  'component.iconPicker.search': 'Search icons',
  'component.iconPicker.empty': 'No matching icons',
  'component.iconPicker.unknown': 'Unknown icon: {name}',
  'component.permissionType.directory': 'Directory',
  'component.permissionType.menu': 'Menu',
  'component.permissionType.action': 'Button',
};

export default messages;
