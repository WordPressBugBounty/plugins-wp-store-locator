const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

/*
 * Writes the id of this build to frontend/js/wpsl.asset.php. The front-end
 * bundle carries the same id ( __webpack_hash__ ), so a CDN or cache that
 * serves an older wpsl.min.js under the new ?ver= shows up as a mismatch.
 * See assets/src/frontend/js/modules/wpsl-build-check.js.
 */
class WpslBuildIdPlugin {
  apply(compiler) {
    const { Compilation, sources } = compiler.webpack;

    compiler.hooks.thisCompilation.tap('WpslBuildIdPlugin', (compilation) => {
      compilation.hooks.processAssets.tap({ name: 'WpslBuildIdPlugin', stage: Compilation.PROCESS_ASSETS_STAGE_ADDITIONAL }, () => {
        const php = "<?php defined( 'ABSPATH' ) || exit;\n\nreturn [ 'build' => '" + compilation.hash.slice(0, 8) + "' ];\n";

        compilation.emitAsset('frontend/js/wpsl.asset.php', new sources.RawSource(php));
      });
    });
  }
}

/*
 * JavaScript only. Stylesheets are minified by tools/build-css.js and the
 * editor blocks by wp-scripts ( see package.json ).
 */
module.exports = {
  entry: {
    'common/wpsl-core': './assets/src/common/wpsl-core.js',
    'admin/js/wpsl-admin': './assets/src/admin/js/wpsl-admin.js',
    'admin/js/wpsl-onboarding': './assets/src/admin/js/wpsl-onboarding.js',
    'admin/js/modules/wpsl-color-picker': './assets/src/admin/js/modules/wpsl-color-picker.js',

    // Marker Studio and Map Shapes: one entry per file, because the files
    // hand off through window.* globals and wp_enqueue_script() dependencies.
    'admin/js/marker-studio/wpsl-marker-studio': './assets/src/admin/js/marker-studio/wpsl-marker-studio.js',
    'admin/js/marker-studio/wpsl-marker-studio-svg': './assets/src/admin/js/marker-studio/wpsl-marker-studio-svg.js',
    'admin/js/marker-studio/wpsl-marker-studio-panes': './assets/src/admin/js/marker-studio/wpsl-marker-studio-panes.js',
    'admin/js/marker-studio/wpsl-marker-studio-map': './assets/src/admin/js/marker-studio/wpsl-marker-studio-map.js',
    'admin/js/map-shapes/wpsl-shapes-terra-shared': './assets/src/admin/js/map-shapes/wpsl-shapes-terra-shared.js',
    'admin/js/map-shapes/wpsl-shapes-editor': './assets/src/admin/js/map-shapes/wpsl-shapes-editor.js',
    'admin/js/map-shapes/wpsl-shapes-layout': './assets/src/admin/js/map-shapes/wpsl-shapes-layout.js',
    'admin/js/map-shapes/wpsl-shapes-draw-gmaps': './assets/src/admin/js/map-shapes/wpsl-shapes-draw-gmaps.js',
    'admin/js/map-shapes/wpsl-shapes-draw-mapbox': './assets/src/admin/js/map-shapes/wpsl-shapes-draw-mapbox.js',
    'admin/js/map-shapes/wpsl-shapes-draw-osm': './assets/src/admin/js/map-shapes/wpsl-shapes-draw-osm.js',

    // Runtime marker labels, separate entries for the same reason. The names
    // are the dist paths enqueued in includes/frontend/assets/class-manager.php.
    'common/wpsl-marker-label': './assets/src/common/wpsl-marker-label.js',
    'frontend/js/wpsl-marker-labels': './assets/src/frontend/js/wpsl-marker-labels.js',

    'frontend/js/wpsl': './assets/src/frontend/js/wpsl.js',
  },

  output: {
    path: path.resolve(__dirname, 'assets/dist'),
    filename: '[name].min.js',

    // Lazy-loaded chunks are requested by the webpack runtime, not through
    // wp_enqueue_script(), so they never get WordPress's ?ver=. The content
    // hash busts stale browser/CDN copies whose module ids no longer match
    // the main bundle. The query string is left off the file written to disk.
    chunkFilename: '[name].min.js?ver=[contenthash:8]',

    // Prune stale bundles, but never the .min.css from tools/build-css.js,
    // the block bundles register_block_type() reads from admin/blocks/, or
    // the committed third-party libraries under vendor/.
    clean: {
      keep: (asset) => {
        const file = asset.replace(/\\/g, '/');

        return file.endsWith('.css') || file.startsWith('admin/blocks/') || file.startsWith('vendor/');
      },
    },
  },

  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        type: 'javascript/esm',
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', {
                modules: false,
                targets: {
                  browsers: ["> 1%", "last 2 versions"]
                }
              }]
            ]
          }
        }
      }
    ]
  },

  plugins: [
    new WpslBuildIdPlugin(),
  ],

  optimization: {
    chunkIds: 'named',
    splitChunks: false,
    minimizer: [
      new TerserPlugin({
        extractComments: false,
        terserOptions: {
          format: {
            comments: false,
          },
        },
      }),
    ],
  },
};
