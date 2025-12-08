import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: "../app/dist",   // build output goes into app/dist
    emptyOutDir: true
  }
});
