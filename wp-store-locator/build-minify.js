const fs = require('fs');
const path = require('path');
const { minify } = require('terser');
const CleanCSS = require('clean-css');
const chokidar = require('chokidar');

const isWatch = process.argv.includes('--watch');

// Define files to minify
const filesToMinify = [
    // Admin JS files
    { src: 'admin/js/ajax-queue.js', dest: 'admin/js/ajax-queue.min.js' },
    { src: 'admin/js/retina.js', dest: 'admin/js/retina.min.js' },
    { src: 'admin/js/wpsl-admin.js', dest: 'admin/js/wpsl-admin.min.js' },
    { src: 'admin/js/wpsl-cpt-upgrade.js', dest: 'admin/js/wpsl-cpt-upgrade.min.js' },
    { src: 'admin/js/wpsl-exit-survey.js', dest: 'admin/js/wpsl-exit-survey.min.js' },
    { src: 'admin/js/wpsl-shortcode-generator.js', dest: 'admin/js/wpsl-shortcode-generator.min.js' },
    { src: 'admin/js/wpsl-notifications.js', dest: 'admin/js/wpsl-notifications.min.js' },
    
    // Frontend JS files
    { src: 'js/wpsl-gmap.js', dest: 'js/wpsl-gmap.min.js' },
    { src: 'js/infobox.js', dest: 'js/infobox.min.js' },
    { src: 'js/markerclusterer.js', dest: 'js/markerclusterer.min.js' },
    
    // Admin CSS files
    { src: 'admin/css/micromodal.css', dest: 'admin/css/micromodal.min.css' },
    { src: 'admin/css/style.css', dest: 'admin/css/style.min.css' },
    { src: 'admin/css/style-3.8.css', dest: 'admin/css/style-3.8.min.css' },
    { src: 'admin/css/wpsl-notifications.css', dest: 'admin/css/wpsl-notifications.min.css' },
    
    // Frontend CSS files
    { src: 'css/styles.css', dest: 'css/styles.min.css' }
];

async function minifyJS(srcPath, destPath) {
    try {
        const code = fs.readFileSync(srcPath, 'utf8');
        const result = await minify(code, {
            compress: {
                dead_code: true,
                drop_console: false,
                drop_debugger: true,
                keep_classnames: false,
                keep_fargs: true,
                keep_fnames: false,
                keep_infinity: false
            },
            mangle: {
                keep_classnames: false,
                keep_fnames: false
            },
            format: {
                comments: false
            }
        });
        
        if (result.code) {
            fs.writeFileSync(destPath, result.code, 'utf8');
            console.log(`✓ Minified: ${srcPath} → ${destPath}`);
        }
    } catch (error) {
        console.error(`✗ Error minifying ${srcPath}:`, error.message);
    }
}

function minifyCSS(srcPath, destPath) {
    try {
        const css = fs.readFileSync(srcPath, 'utf8');
        const result = new CleanCSS({
            level: 2,
            format: false
        }).minify(css);
        
        if (result.styles) {
            fs.writeFileSync(destPath, result.styles, 'utf8');
            console.log(`✓ Minified: ${srcPath} → ${destPath}`);
        }
        
        if (result.errors.length > 0) {
            console.error(`✗ Errors in ${srcPath}:`, result.errors);
        }
    } catch (error) {
        console.error(`✗ Error minifying ${srcPath}:`, error.message);
    }
}

async function processFile(file) {
    const srcPath = path.join(__dirname, file.src);
    const destPath = path.join(__dirname, file.dest);
    
    if (!fs.existsSync(srcPath)) {
        console.log(`⊘ Skipping ${file.src} (file not found)`);
        return;
    }
    
    const ext = path.extname(file.src);
    
    if (ext === '.js') {
        await minifyJS(srcPath, destPath);
    } else if (ext === '.css') {
        minifyCSS(srcPath, destPath);
    }
}

async function minifyAll() {
    console.log('Starting minification...\n');
    
    for (const file of filesToMinify) {
        await processFile(file);
    }
    
    console.log('\nMinification complete!');
}

// Run minification
minifyAll();

// Watch mode
if (isWatch) {
    console.log('\n👀 Watching for file changes...\n');
    
    const watchPaths = filesToMinify.map(f => path.join(__dirname, f.src));
    
    const watcher = chokidar.watch(watchPaths, {
        persistent: true,
        ignoreInitial: true
    });
    
    watcher.on('change', async (filePath) => {
        console.log(`\n📝 File changed: ${path.relative(__dirname, filePath)}`);
        
        const file = filesToMinify.find(f => 
            path.join(__dirname, f.src) === filePath
        );
        
        if (file) {
            await processFile(file);
        }
    });
}
