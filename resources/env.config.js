import { readFileSync, writeFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Get the mode from environment variable or command line argument
const mode = process.env.NODE_ENV === 'production' || process.argv.includes('--prod') ? 'prod' : 'dev';
const devMode = mode === 'dev';

// Path to constants.php
const constantsPath = join(__dirname, '../config/constants.php');

try {
    // Read the constants file
    let content = readFileSync(constantsPath, 'utf8');
    
    // Replace the SATAI_DEV_MODE definition
    const regex = /define\('SATAI_DEV_MODE',\s*(true|false)\);/;
    
    if (regex.test(content)) {
        content = content.replace(regex, `define('SATAI_DEV_MODE', ${devMode});`);
        writeFileSync(constantsPath, content, 'utf8');
        console.log(`✓ Updated SATAI_DEV_MODE to ${devMode} (${mode} mode)`);
    } else {
        console.warn('⚠ Could not find SATAI_DEV_MODE definition in constants.php');
    }
} catch (error) {
    console.error('✗ Error updating constants.php:', error.message);
    process.exit(1);
}
