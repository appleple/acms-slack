const cmd = require('node-cmd');
const fs = require('fs-extra');
const archiver = require('archiver');

/**
 * Run system command
 *
 * @param cmdString
 * @returns {Promise}
 */
exports.systemCmd = (cmdString) =>
  new Promise((resolve) => {
    cmd.get(cmdString, (data, err, stderr) => {
      console.log(cmdString);
      console.log(data);
      if (err) {
        console.log(err);
      }
      if (stderr) {
        console.log(stderr);
      }
      resolve(data);
    });
  });

exports.systemDirList = (directory) =>
  new Promise((resolve) => {
    fs.readdir(directory, (err, files) => {
      if (err) throw err;
      resolve(files);
    });
  });

exports.zipPromise = (src, dist) => {
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
};
