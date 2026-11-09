import { defineConfig } from "astro/config";
import node from "@astrojs/node";

export default defineConfig({
  site: "https://alisadikinma.com",
  output: "server",
  trailingSlash: "never",
  adapter: node({ mode: "standalone" }),
  server: { host: "127.0.0.1", port: 4321 },
});
