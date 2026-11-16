import { useModel } from '@umijs/max';
import { useCallback } from 'react';

/**
 * 重新拉取当前用户（权限码）与侧边菜单并写回 initialState：
 * 菜单管理保存后、角色授权变更后调用，侧边菜单与按钮权限即时刷新。
 */
export function useRefreshAccess() {
  const { initialState, setInitialState } = useModel('@@initialState');
  return useCallback(async () => {
    if (!initialState) return;
    const [currentUser, menus] = await Promise.all([
      initialState.fetchUserInfo(),
      initialState.fetchMenus(),
    ]);
    setInitialState((state) =>
      state ? { ...state, currentUser, menus } : state,
    );
  }, [initialState, setInitialState]);
}
