// @ts-check
import {themes as prismThemes} from 'prism-react-renderer';

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'PrintBridge Platform',
  tagline: 'API reference for integrators',
  favicon: 'img/favicon.ico',

  url: 'https://impryxa.vekronis.com',
  baseUrl: '/developers/',

  onBrokenLinks: 'throw',
  markdown: {
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          sidebarPath: './sidebars.js',
          routeBasePath: '/',
          docItemComponent: '@theme/ApiItem',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  plugins: [
    [
      'docusaurus-plugin-openapi-docs',
      {
        id: 'api',
        docsPluginId: 'classic',
        config: {
          printbridge: {
            specPath: 'https://impryxa.vekronis.com/docs/api.json',
            outputDir: 'docs/reference',
            downloadUrl: 'https://impryxa.vekronis.com/docs/api.json',
            sidebarOptions: {
              groupPathsBy: 'tag',
              // Neither 'tag' (crashes at build time -- upstream bug in
              // useCurrentSidebarCategory with this plugin version) nor
              // 'auto' (generates a sidebar link to a /category/* page
              // Docusaurus never actually builds, i.e. a dead link) work
              // here. Omitting this makes category headers plain
              // expand/collapse toggles with no link -- every endpoint is
              // still reachable directly from the sidebar, just not via a
              // per-category landing page.
            },
          },
        },
      },
    ],
  ],

  themes: ['docusaurus-theme-openapi-docs'],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      colorMode: {
        respectPrefersColorScheme: true,
      },
      navbar: {
        title: 'PrintBridge Platform',
        items: [
          {
            type: 'docSidebar',
            sidebarId: 'apiSidebar',
            position: 'left',
            label: 'API Reference',
          },
          {
            href: 'https://impryxa.vekronis.com/#/documentacion',
            label: 'Dashboard docs',
            position: 'right',
          },
          {
            href: 'https://github.com/jonathanlarrarte/printer-agent',
            label: 'Agent (GitHub)',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [],
        copyright: `PrintBridge Platform API Reference — built with Docusaurus.`,
      },
      prism: {
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
      },
    }),
};

export default config;
