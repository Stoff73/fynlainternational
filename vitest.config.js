import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import path from 'path';

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/frontend/setup.js'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      include: ['resources/js/**/*.{js,vue}'],
      exclude: ['resources/js/app.js', 'resources/js/bootstrap.js'],
    },
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
      '@gb': path.resolve(fileURLToPath(new URL('.', import.meta.url)), 'packs/country-gb/resources/js'),
      '@za': path.resolve(fileURLToPath(new URL('.', import.meta.url)), 'packs/country-za/resources/js'),
    },
  },
});
