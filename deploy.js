const cmd = require('node-cmd');
const fs = require('fs-extra');
const co = require('co');
const archiver = require('archiver');

/**
 * Run system command
 *
 * @param cmdString
 * @returns {Promise}
 */
exports.systemCmd = cmdString => {
  return new Promise((resolve) => {
    cmd.get(
      cmdString,
      (data, err, stderr) => {
        console.log(cmdString);
        console.log(data);
        if (err) {
          console.log(err);
        }
        if (stderr) {
          console.log(stderr);
        }
        resolve(data);
      }
    );
  });
}

const zipPromise = (src, dist) => {
  return new Promise((resolve, reject) => {
    const archive = archiver.create('zip', {});
    const output = fs.createWriteStream(dist);

    // listen for all archive data to be written
    output.on('close', () => {
      console.log(archive.pointer() + ' total bytes');
      console.log('Archiver has been finalized and the output file descriptor has closed.');
      resolve();
    });

    // good practice to catch this error explicitly
    archive.on('error', (err) => {
      reject(err);
    });

    archive.pipe(output);
    archive.directory(src).finalize();
  });
}

co(function* () {
  try {
    fs.mkdirsSync(`_tmp`);
    fs.copySync(`./composer.json`, '_tmp/composer.json');
    fs.copySync(`./composer.lock`, `_tmp/composer.lock`);
    fs.copySync(`./LICENSE`, `_tmp/LICENSE`);
    fs.copySync(`./README.md`, `_tmp/README.md`);
    fs.copySync(`./Engine.php`, `_tmp/Engine.php`);
    fs.copySync(`./Hook.php`, `_tmp/Hook.php`);
    fs.copySync(`./ServiceProvider.php`, `_tmp/ServiceProvider.php`);
    zipPromise(`_tmp`, `./slack.zip`);
    fs.removeSync(`_tmp`);
  } catch (err) {
    console.log(err);
  }
});
