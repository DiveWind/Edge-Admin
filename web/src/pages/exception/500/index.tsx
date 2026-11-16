import { history } from '@umijs/max';
import { Button, Result } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';

const Exception500: React.FC = () => {
  const t = useT();
  return (
    <Result
      status="500"
      title="500"
      subTitle={t('pages.exception.500')}
      extra={
        <Button type="primary" onClick={() => history.push('/')}>
          {t('pages.exception.backHome')}
        </Button>
      }
    />
  );
};

export default Exception500;
