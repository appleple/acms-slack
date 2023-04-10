'use strict';

const { systemCmd } = require('./lib/system.js');

const co = require('co');

// package.json
const { version } = require('../package.json');

co(function* () {
  try {
    yield systemCmd('git add -A');
    yield systemCmd(`git commit -m "v${version}"`);
    yield systemCmd(`git tag v${version}`);
    yield systemCmd('git push');
    yield systemCmd('git push --tags');
  } catch (err) {
    console.log(err);
  }
});
