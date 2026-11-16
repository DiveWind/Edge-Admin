import { history } from '@umijs/max';
import { Button, Result } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';

const Exception404: React.FC = () => {
  const t = useT();
  return (
    <Result
      status="404"
      title="404"
      subTitle={t('pages.exception.404')}
      extra={
        <Button type="primary" onClick={() => history.push('/')}>
          {t('pages.exception.backHome')}
        </Button>
      }
    />
  );
};

export default Exception404;
