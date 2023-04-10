'use strict';

const fs = require('fs-extra');

// package.json
const { version } = require('../package.json');
const serviceProvider = 'ServiceProvider.php';

try {
  /**
   * Sync plugin version.
   */
  let appCode = fs.readFileSync(serviceProvider, 'utf-8');
  appCode = appCode.replace(/\$version =\s*'[\d\.]+';/, `$version = '${version}';`);
  fs.writeFileSync(serviceProvider, appCode);
} catch (err) {
  console.log(err);
}
