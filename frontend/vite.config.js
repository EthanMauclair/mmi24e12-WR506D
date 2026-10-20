import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // Les appels /api sont relayés vers le backend Symfony (évite les soucis CORS en dev)
    proxy: {
      '/api': process.env.API_PROXY_TARGET || 'http://localhost:8080',
    },
    // Nécessaire pour le rechargement à chaud sur un volume Docker monté depuis Windows
    watch: {
      usePolling: true,
    },
  },
})
