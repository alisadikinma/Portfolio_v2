import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

/**
 * Vitest configuration for Vue component + composable unit tests.
 *
 * Conventions:
 *   - Component / composable specs live next to the source as
 *     `Foo.spec.js` or in `__tests__/` directories.
 *   - Pure Node logic tests keep using the existing `.test.mjs` runner
 *     (see package.json `test:unit`). Vitest covers everything that
 *     touches Vue or DOM APIs.
 *
 * Why jsdom over happy-dom: jsdom has stronger compatibility with
 * @vue/test-utils + vue-router and matches ecosystem defaults.
 */
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    environment: 'jsdom',
    globals: false,
    include: [
      'src/**/*.spec.{js,ts}',
      'src/**/*.spec.mjs',
      'src/test/**/*.test.{js,ts}',
    ],
    setupFiles: ['./src/test/setup.js'],
    css: false,
    clearMocks: true,
    restoreMocks: true,
  },
})
