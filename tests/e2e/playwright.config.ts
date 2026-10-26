import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests run against a seeded site (wp-env in CI: `wp techvenza seed --skip-images`).
 */
export default defineConfig( {
	testDir: './specs',
	timeout: 45_000,
	retries: process.env.CI ? 1 : 0,
	workers: process.env.CI ? 2 : undefined,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
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
