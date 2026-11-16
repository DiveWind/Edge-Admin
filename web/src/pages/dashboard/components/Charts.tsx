import { Bar, Line, Pie } from '@ant-design/plots';
import { Empty } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';
import type { DashboardData } from '../data';

const CHART_HEIGHT = 300;

const EmptyChart: React.FC = () => (
  <Empty
    image={Empty.PRESENTED_IMAGE_SIMPLE}
    style={{ height: CHART_HEIGHT, alignContent: 'center' }}
  />
);

export const LoginTrendChart: React.FC<{
  data: DashboardData['login_trend'];
}> = ({ data }) => {
  const t = useT();
  // 宽表 → 长表：每天拆成成功 / 失败两条记录
  const series = data.flatMap(({ date, success, failed }) => [
    { date, type: t('pages.dashboard.login.success'), value: success },
    { date, type: t('pages.dashboard.login.failed'), value: failed },
  ]);
  if (!data.length) return <EmptyChart />;
  return (
    <Line
      data={series}
      xField="date"
      yField="value"
      colorField="type"
      height={CHART_HEIGHT}
      point={{ shapeField: 'circle', sizeField: 3 }}
      axis={{ x: { labelAutoRotate: false } }}
    />
  );
};

export const ArticleCategoryChart: React.FC<{
  data: DashboardData['articles_by_category'];
}> = ({ data }) => {
  if (!data.length) return <EmptyChart />;
  return (
    <Pie
      data={data}
      angleField="count"
      colorField="category"
      innerRadius={0.6}
      height={CHART_HEIGHT}
      label={{ text: 'count', style: { fontWeight: 'bold' } }}
      legend={{
        color: { position: 'bottom', layout: { justifyContent: 'center' } },
      }}
    />
  );
};

/** 部门较多时只展示人数最多的前 N 个，横向条形图避免 x 轴标签竖排 */
const TOP_DEPARTMENTS = 10;

export const DepartmentUserChart: React.FC<{
  data: DashboardData['users_by_department'];
}> = ({ data }) => {
  if (!data.length) return <EmptyChart />;
  const top = [...data]
    .sort((a, b) => b.count - a.count)
    .slice(0, TOP_DEPARTMENTS);
  return (
    <Bar
      data={top}
      xField="department"
      yField="count"
      height={CHART_HEIGHT}
      label={{ text: 'count', position: 'right' }}
      axis={{ y: { tickCount: 4 } }}
    />
  );
};
