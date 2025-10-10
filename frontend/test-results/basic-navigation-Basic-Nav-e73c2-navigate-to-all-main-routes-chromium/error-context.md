# Page snapshot

```yaml
- generic [ref=e3]:
  - generic [ref=e4]: "[plugin:vite:import-analysis] Failed to resolve import \"@/layouts/DefaultLayout.vue\" from \"src/App.vue\". Does the file exist?"
  - generic [ref=e5]: C:/xampp/htdocs/Portfolio_v2/frontend/src/App.vue:10:27
  - generic [ref=e6]: "1 | import { computed } from 'vue' 2 | import { useRoute } from 'vue-router' 3 | import DefaultLayout from '@/layouts/DefaultLayout.vue' | ^ 4 | import AdminLayout from '@/layouts/AdminLayout.vue' 5 | import AuthLayout from '@/layouts/AuthLayout.vue'"
  - generic [ref=e7]: at TransformPluginContext._formatLog (file:///C:/xampp/htdocs/Portfolio_v2/frontend/node_modules/vite/dist/node/chunks/dep-ySrR9pW8.js:30456:43) at TransformPluginContext.error (file:///C:/xampp/htdocs/Portfolio_v2/frontend/node_modules/vite/dist/node/chunks/dep-ySrR9pW8.js:30453:14) at normalizeUrl (file:///C:/xampp/htdocs/Portfolio_v2/frontend/node_modules/vite/dist/node/chunks/dep-ySrR9pW8.js:28865:18) at async file:///C:/xampp/htdocs/Portfolio_v2/frontend/node_modules/vite/dist/node/chunks/dep-ySrR9pW8.js:28923:32 at async Promise.all (index 2) at async TransformPluginContext.transform (file:///C:/xampp/htdocs/Portfolio_v2/frontend/node_modules/vite/dist/node/chunks/dep-ySrR9pW8.js:28891:4) at async EnvironmentPluginContainer.transform (file:///C:/xampp/htdocs/Portfolio_v2/frontend/node_modules/vite/dist/node/chunks/dep-ySrR9pW8.js:30246:14) at async loadAndTransform (file:///C:/xampp/htdocs/Portfolio_v2/frontend/node_modules/vite/dist/node/chunks/dep-ySrR9pW8.js:25261:26) at async viteTransformMiddleware (file:///C:/xampp/htdocs/Portfolio_v2/frontend/node_modules/vite/dist/node/chunks/dep-ySrR9pW8.js:26359:20)
  - generic [ref=e8]:
    - text: Click outside, press Esc key, or fix the code to dismiss.
    - text: You can also disable this overlay by setting
    - code [ref=e9]: server.hmr.overlay
    - text: to
    - code [ref=e10]: "false"
    - text: in
    - code [ref=e11]: vite.config.js
    - text: .
```