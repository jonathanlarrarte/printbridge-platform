// @ts-check
import apiSidebarModule from './docs/reference/sidebar.ts';

// Interop shim: depending on how this .ts file gets transpiled, the array we
// want ends up at either apiSidebarModule.apisidebar or
// apiSidebarModule.default.apisidebar.
const generated = apiSidebarModule.apisidebar ?? apiSidebarModule.default?.apisidebar ?? [];

/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  apiSidebar: ['index', ...generated],
};

export default sidebars;
