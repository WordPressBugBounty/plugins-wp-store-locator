/*
 * Minifies the plugin's hand-written stylesheets with clean-css.
 *
 * Every *.css file under assets/src/{admin,common,frontend}/css is minified
 * into the matching assets/dist/{admin,common,frontend}/css/*.min.css. The
 * source and dist trees sit at the same nesting depth relative to the plugin
 * root, and rebase is disabled below, so url() references (e.g. the relative
 * font paths in fontello.css) are copied through untouched rather than being
 * rewritten by clean-css.
 *
 * Run via `npm run build:css`.
 */
const fs = require('fs');
const path = require('path');
const CleanCSS = require('clean-css');

const ROOT = path.resolve(__dirname, '..');

const SOURCE_DIRS = [
  'assets/src/admin/css',
  'assets/src/common/css',
  'assets/src/frontend/css',
];

const cleanCss = new CleanCSS({
  level: 1,
  rebase: false,
});

let hadError = false;

for (const relSourceDir of SOURCE_DIRS) {
  const sourceDir = path.join(ROOT, relSourceDir);
  const distDir = path.join(ROOT, relSourceDir.replace('assets/src/', 'assets/dist/'));

  fs.mkdirSync(distDir, { recursive: true });

  const cssFiles = fs
    .readdirSync(sourceDir)
    .filter((file) => file.endsWith('.css'));

  for (const file of cssFiles) {
    const sourcePath = path.join(sourceDir, file);
    const distPath = path.join(distDir, file.replace(/\.css$/, '.min.css'));

    const source = fs.readFileSync(sourcePath, 'utf8');
    const output = cleanCss.minify(source);

    if (output.errors.length) {
      hadError = true;
      console.error(`Error minifying ${sourcePath}:`, output.errors);
      continue;
    }

    if (output.warnings.length) {
      console.warn(`Warnings for ${sourcePath}:`, output.warnings);
    }

    fs.writeFileSync(distPath, output.styles);
    console.log(`${path.relative(ROOT, sourcePath)} -> ${path.relative(ROOT, distPath)} (${output.styles.length} bytes)`);

    /*
     * The theme colors also ship inside a cascade layer. That copy loads when
     * "Overwrite theme styles" is off: any color the theme sets then wins over
     * these, whatever its specificity. See Frontend Assets Manager::enqueue_styles().
     */
    if (file === 'colors.css') {
      const layeredPath = path.join(distDir, 'colors-layered.min.css');

      fs.writeFileSync(layeredPath, `@layer wpsl{${output.styles}}`);
      console.log(`${path.relative(ROOT, sourcePath)} -> ${path.relative(ROOT, layeredPath)} (layered)`);
    }
  }
}

if (hadError) {
  process.exitCode = 1;
}
