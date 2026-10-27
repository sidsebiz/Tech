import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests run against a seeded site (wp-env in CI: `wp techvenza seed --skip-images`).
 */
const baseURL = process.env.WP_BASE_URL || 'http://localhost:8888';

export default defineConfig( {
	testDir: './specs',
	timeout: 45_000,
	retries: process.env.CI ? 1 : 0,
	workers: process.env.CI ? 2 : undefined,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL,
		// A privacy choice is pre-set so the consent banner does not cover controls; consent.spec.ts clears it.
		storageState: {
			cookies: [
				{
					name: 'tv_consent',
					value: 'essential',
					domain: new URL( baseURL ).hostname,
					path: '/',
					expires: -1,
					httpOnly: false,
					secure: false,
					sameSite: 'Lax',
				},
			],
			origins: [],
		},
		trace: 'retain-on-failure',
		// Reveal animations are skipped so contrast checks see final colours.
		contextOptions: { reducedMotion: 'reduce' },
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ], viewport: { width: 1440, height: 900 } }, grepInvert: /@mobile/ },
		{ name: 'mobile', use: { ...devices[ 'Pixel 7' ] }, grep: /@mobile/ },
	],
} );
