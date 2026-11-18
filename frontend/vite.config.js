import { defineConfig } from "vite";
const target = process.env.API_PROXY_TARGET || "http://127.0.0.1:8000";
export default defineConfig({
  server: {
    port: 5173,
    strictPort: true,
    allowedHosts: ["shopper-reheat-earshot.ngrok-free.dev"],
    proxy: {
      "/api": { target, changeOrigin: false },
      "/sanctum": { target, changeOrigin: false },
    },
  },
  build: { target: "es2022", sourcemap: false },
});
