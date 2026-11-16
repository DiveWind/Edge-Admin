import '../tailwind.css';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

// 通知、最近操作等处使用 fromNow()
dayjs.extend(relativeTime);
