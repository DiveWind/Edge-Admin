import { history } from '@umijs/max';
import { Button, Result } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';

const Exception403: React.FC = () => {
  const t = useT();
  return (
    <Result
      status="403"
      title="403"
      subTitle={t('pages.exception.403')}
      extra={
        <Button type="primary" onClick={() => history.push('/')}>
          {t('pages.exception.backHome')}
        </Button>
      }
    />
  );
};

export default Exception403;
