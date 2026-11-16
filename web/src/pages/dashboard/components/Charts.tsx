import { Bar, Line, Pie } from '@ant-design/plots';
import { Empty } from 'antd';
import type React from 'react';
import { useEffect, useRef, useState } from 'react';
import { useThemeMode } from '@/hooks/useThemeMode';
import { useT } from '@/utils/i18n';
import type { DashboardData } from '../data';

const CHART_HEIGHT = 300;

const EmptyChart: React.FC = () => (
  <Empty
    image={Empty.PRESENTED_IMAGE_SIMPLE}
    style={{ height: CHART_HEIGHT, alignContent: 'center' }}
  />
);

/** ChartBox 传给图表的尺寸与主题，直接展开到图表组件上 */
interface ChartSizeProps {
  width: number;
  autoFit: false;
  theme: 'classic' | 'classicDark';
}

/**
 * 图表容器：用 ResizeObserver 跟踪自身宽度，把宽度显式传给图表。
 * G2 的 autoFit 只监听 window resize，布局（菜单、栅格）在挂载后才稳定时会按旧宽度绘制并溢出卡片。
 * 同时按当前明 / 暗模式传入 G2 主题。
 */
const ChartBox: React.FC<{
  children: (props: ChartSizeProps) => React.ReactNode;
}> = ({ children }) => {
  const mode = useThemeMode();
  const ref = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(0);
  useEffect(() => {
    const element = ref.current;
    if (!element) return undefined;
    const observer = new ResizeObserver(([entry]) =>
      setWidth(Math.floor(entry.contentRect.width)),
    );
    observer.observe(element);
    return () => observer.disconnect();
  }, []);
  return (
    <div ref={ref} style={{ height: CHART_HEIGHT }}>
      {width > 0 &&
        children({
          width,
          autoFit: false,
          // G2 主题跟随明 / 暗模式（坐标轴、图例文字颜色）
          theme: mode === 'dark' ? 'classicDark' : 'classic',
        })}
    </div>
  );
};

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
    <ChartBox>
      {(chart) => (
        <Line
          data={series}
          xField="date"
          yField="value"
          colorField="type"
          height={CHART_HEIGHT}
          point={{ shapeField: 'circle', sizeField: 3 }}
          axis={{ x: { labelAutoRotate: false } }}
          {...chart}
        />
      )}
    </ChartBox>
  );
};

export const ArticleCategoryChart: React.FC<{
  data: DashboardData['articles_by_category'];
}> = ({ data }) => {
  if (!data.length) return <EmptyChart />;
  return (
    <ChartBox>
      {(chart) => (
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
          {...chart}
        />
      )}
    </ChartBox>
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
    <ChartBox>
      {(chart) => (
        <Bar
          data={top}
          xField="department"
          yField="count"
          height={CHART_HEIGHT}
          label={{ text: 'count', position: 'inside' }}
          axis={{ y: { tickCount: 4 } }}
          {...chart}
        />
      )}
    </ChartBox>
  );
};
