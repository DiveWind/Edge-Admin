import { describe, expect, it } from 'vitest';
import {
  collectLeafIds,
  collectParentIds,
  disableSubtree,
  pruneEmptyChildren,
  toTreeData,
} from './tree';

interface Node {
  id: number;
  children?: Node[];
}

const tree: Node[] = [
  {
    id: 1,
    children: [
      { id: 2, children: [{ id: 3 }] },
      { id: 4, children: [] },
    ],
  },
  { id: 5 },
];

describe('tree utils', () => {
  it('collects parent and leaf ids', () => {
    expect(collectParentIds(tree)).toEqual([1, 2]);
    expect(collectLeafIds(tree)).toEqual([3, 4, 5]);
  });

  it('prunes empty children arrays', () => {
    expect(
      pruneEmptyChildren([{ id: 1, children: [{ id: 2, children: [] }] }]),
    ).toEqual([{ id: 1, children: [{ id: 2, children: undefined }] }]);
  });

  it('converts {id, name} nodes to antd tree data', () => {
    expect(
      toTreeData([{ id: 1, name: 'HQ', children: [{ id: 2, name: 'R&D' }] }]),
    ).toEqual([
      {
        key: 1,
        title: 'HQ',
        children: [{ key: 2, title: 'R&D', children: undefined }],
      },
    ]);
  });

  it('disables a node together with its descendants', () => {
    const result = disableSubtree(tree, 2);
    expect(result[0].disabled).toBe(false);
    expect(result[0].children?.[0].disabled).toBe(true);
    expect(result[0].children?.[0].children?.[0].disabled).toBe(true);
    expect(result[0].children?.[1].disabled).toBe(false);
    expect(result[1].disabled).toBe(false);
  });
});
