import { describe, expect, it } from 'vitest';
import { DEFAULT_SITE_SETTINGS, toSiteSettings } from './settings';

describe('toSiteSettings', () => {
  it('reads public settings', () => {
    expect(
      toSiteSettings({
        'site.name': ' My Admin ',
        'site.login_notice': 'Notice',
        'site.icp': 'ICP 123',
        'other.key': 1,
      }),
    ).toEqual({ name: 'My Admin', loginNotice: 'Notice', icp: 'ICP 123' });
  });

  it('falls back to defaults for missing, empty or non-string values', () => {
    expect(toSiteSettings(undefined)).toEqual(DEFAULT_SITE_SETTINGS);
    expect(toSiteSettings({ 'site.name': '', 'site.icp': 42 })).toEqual(
      DEFAULT_SITE_SETTINGS,
    );
  });
});
