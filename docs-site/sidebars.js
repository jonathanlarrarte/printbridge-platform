// @ts-check
import apiSidebarModule from './docs/reference/sidebar.ts';

// Interop shim: depending on how this .ts file gets transpiled, the array we
// want ends up at either apiSidebarModule.apisidebar or
// apiSidebarModule.default.apisidebar.
// Interop across jiti/esbuild is inconsistent about *which* shape the
// namespace ends up in -- sometimes {apisidebar: [...]}, sometimes
// {default: {apisidebar: [...]}}, sometimes the array itself gets unwrapped
// as the namespace object. Cover all three rather than depend on one.
const generated = Array.isArray(apiSidebarModule)
  ? apiSidebarModule
  : apiSidebarModule.apisidebar ?? apiSidebarModule.default?.apisidebar ?? Object.values(apiSidebarModule);

/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  apiSidebar: [
    'index',
    'guides/getting-started',
    'guides/pos-integration',
    {
      type: 'category',
      label: 'API Reference',
      collapsed: false,
      items: generated,
    },
  ],
};

export default sidebars;
