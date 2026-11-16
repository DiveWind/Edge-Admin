import { PageContainer } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import type React from 'react';
import { useState } from 'react';
import { useT } from '@/utils/i18n';
import FailedJobTable from './components/FailedJobTable';
import ScheduledTaskTable from './components/ScheduledTaskTable';
import TaskLogTable from './components/TaskLogTable';

type TabKey = 'tasks' | 'logs' | 'failed-jobs';

/** 任务调度：定时任务 / 执行日志（monitor.scheduled-task.view），失败队列（monitor.failed-job.view） */
const TaskMonitor: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const canViewTasks = access.can('monitor.scheduled-task.view');
  const [activeKey, setActiveKey] = useState<TabKey>(
    canViewTasks ? 'tasks' : 'failed-jobs',
  );
  const [logTask, setLogTask] = useState<string>();

  const tabs = [
    canViewTasks && { key: 'tasks', tab: t('pages.monitor.tasks.tab.tasks') },
    canViewTasks && { key: 'logs', tab: t('pages.monitor.tasks.tab.logs') },
    access.can('monitor.failed-job.view') && {
      key: 'failed-jobs',
      tab: t('pages.monitor.tasks.tab.failedJobs'),
    },
  ].filter((tab) => tab !== false);

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
      {activeKey === 'failed-jobs' && <FailedJobTable />}
    </PageContainer>
  );
};

export default TaskMonitor;
