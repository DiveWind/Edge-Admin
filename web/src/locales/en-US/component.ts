import type zh from '../zh-CN/component';

const messages: Record<keyof typeof zh, string> = {
  'component.upload.select': 'Upload',
  'component.upload.imageOnly': 'Only image files are allowed',
  'component.upload.tooLarge': 'Image must be smaller than {size}MB',
};

export default messages;
