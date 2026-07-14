import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  // Prevent Vite from clearing the screen in logs
  clearScreen: false,
  // Tauri expects a fixed port when running dev server
  server: {
    port: 1420,
    strictPort: true,
  },
  // Setup output folder configuration
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  }
})
