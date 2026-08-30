import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Built as a single self-contained IIFE bundle (React included) so it can be
// dropped into a wp-admin page with a plain wp_enqueue_script(), no import
// maps or module loading required.
export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'build',
    emptyOutDir: true,
    lib: {
      entry: 'src/main.jsx',
      name: 'WooPilotDashboard',
      formats: ['iife'],
      fileName: () => 'woopilot-dashboard.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'woopilot-dashboard.[ext]',
      },
    },
  },
});
