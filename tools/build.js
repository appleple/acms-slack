/**
 * 配布バージョン作成プログラム
 */

const fs = require('fs-extra');
const co = require('co');
const { zipPromise } = require('./lib/system.js');

const { version } = require('../package.json');

const ignores = [
  '.git',
  '.gitignore',
  '.gitattributes',
  'node_modules',
  '.editorconfig',
  '.eslintrc.js',
  '.node-version',
  '.husky',
  'build',
  '.prettierrc.js',
  'composer.json',
  'composer.lock',
  'package-lock.json',
  'package.json',
  'phpcs.xml',
  'phpmd.xml',
  '.phplint-cache',
  'phpmd.log',
  'tools',
];

co(function* () {
  try {
    /**
     * ready plugins files
     */
    const copyFiles = fs.readdirSync('.');
    fs.mkdirsSync('GoogleSheets');
    fs.mkdirsSync(`build/v${version}`);

    /**
     * copy plugins files
     */
    copyFiles.forEach((file) => {
      fs.copySync(`./${file}`, `GoogleSheets/${file}`);
    });

    /**
     * Ignore files
     */
    console.log('Remove unused files.');
    console.log(ignores);
    ignores.forEach((path) => {
      fs.removeSync(`GoogleSheets/${path}`);
    });

    yield zipPromise('GoogleSheets', `./build/v${version}/GoogleSheets.zip`);
    fs.copySync(`./build/v${version}/GoogleSheets.zip`, './build/GoogleSheets.zip');
  } catch (err) {
    console.log(err);
  } finally {
    fs.removeSync('GoogleSheets');
  }
});
