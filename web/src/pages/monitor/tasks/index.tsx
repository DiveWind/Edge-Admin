import { PageContainer } from '@ant-design/pro-components';
import type React from 'react';
import { useState } from 'react';
import { useT } from '@/utils/i18n';
import ScheduledTaskTable from './components/ScheduledTaskTable';
import TaskLogTable from './components/TaskLogTable';

type TabKey = 'tasks' | 'logs';

/** 定时任务：任务列表 / 执行日志（monitor.scheduled-task.view）；失败队列为独立页面 /monitor/failed-jobs */
const TaskMonitor: React.FC = () => {
  const t = useT();
  const [activeKey, setActiveKey] = useState<TabKey>('tasks');
  const [logTask, setLogTask] = useState<string>();

  const tabs = [
    { key: 'tasks', tab: t('pages.monitor.tasks.tab.tasks') },
    { key: 'logs', tab: t('pages.monitor.tasks.tab.logs') },
  ];

  const viewLogs = (task: string) => {
    setLogTask(task);
    setActiveKey('logs');
  };

  return (
    <PageContainer
      tabList={tabs}
      tabActiveKey={activeKey}
      onTabChange={(key) => setActiveKey(key as TabKey)}
    >
      {activeKey === 'tasks' && <ScheduledTaskTable onViewLogs={viewLogs} />}
      {/* key 变化时重建，让预置的任务过滤条件生效 */}
      {activeKey === 'logs' && <TaskLogTable key={logTask} task={logTask} />}
    </PageContainer>
  );
};

export default TaskMonitor;
