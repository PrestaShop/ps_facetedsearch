/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
import path from 'node:path';
import * as esbuild from 'esbuild';
import * as sass from 'sass';

/**
 * Compiles the .scss files imported from the JS entry points.
 * esbuild then extracts the resulting CSS next to the bundle, as [name].css.
 */
const sassPlugin = {
  name: 'sass',
  setup(build) {
    build.onLoad({filter: /\.scss$/}, (args) => {
      const {css, loadedUrls} = sass.compile(args.path, {
        loadPaths: [path.dirname(args.path)],
      });

      return {
        contents: css,
        loader: 'css',
        watchFiles: loadedUrls.map((url) => url.pathname),
      };
    });
  },
};

const options = {
  entryPoints: {
    front: './_dev/front/index.js',
    back: './_dev/back/index.js',
  },
  outdir: './views/dist',
  bundle: true,
  minify: true,
  sourcemap: true,
  // Browsers supported by PrestaShop 8 and 9.
  target: ['chrome80', 'firefox78', 'safari14', 'edge88'],
  // Keeps third-party license notices (jQuery UI Touch Punch, lodash) in a sidecar file,
  // the way webpack used to emit views/dist/*.LICENSE.txt.
  legalComments: 'eof',
  plugins: [sassPlugin],
  logLevel: 'info',
};

if (process.argv.includes('--watch')) {
  const context = await esbuild.context({...options, minify: false});
  await context.watch();
} else {
  await esbuild.build(options);
}
