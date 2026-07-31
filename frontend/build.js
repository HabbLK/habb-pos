// Runs at Vercel build time. Reads the API_BASE_URL environment variable
// (set in the Vercel project's settings, same as HABB Stay does) and
// writes it into a plain JS file the static HTML can read at runtime —
// there's no framework/bundler here, so this is the whole "build step".
const fs = require('fs');

const apiBaseUrl = process.env.API_BASE_URL || 'https://api.habbgate.com/pos/api/v1';

const contents = `// Generated at build time by build.js — do not edit directly.
window.HABB_CONFIG = {
  API_BASE_URL: ${JSON.stringify(apiBaseUrl)}
};
`;

fs.writeFileSync(__dirname + '/config.js', contents);
console.log(`config.js written with API_BASE_URL = ${apiBaseUrl}`);
