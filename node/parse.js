'use strict';

/**
 * Usage: node parse.js /path/to/file.jdl
 *
 * Parses a JDL file using jhipster-core and prints the resulting
 * entities as JSON on stdout. On failure, prints the error message
 * to stderr and exits with a non-zero code.
 *
 * This script is intentionally "dumb" - it does no interpretation
 * of the JDL data. That happens on the PHP side. Its only job is:
 * JDL file in -> JSON out.
 *
 * Two things this file works around, both discovered by testing:
 *
 * 1. jhipster-core logs warnings (e.g. reserved-keyword table names)
 *    straight to stdout via winston, which would corrupt the JSON
 *    output. We temporarily redirect stdout writes to stderr while
 *    jhipster-core is doing its work, then restore stdout to print
 *    only the final JSON.
 *
 * 2. jhipster-core writes ".jhipster/<Entity>.json" files as a side
 *    effect of import(), and on a LATER run it diffs against those
 *    files - if nothing changed, exportedEntities comes back EMPTY.
 *    That's normal behavior for JHipster's own incremental codegen,
 *    but wrong for us: every call to this script must return the
 *    full entity list. So we run the import inside a fresh, unique
 *    temp directory each time, which guarantees no prior .jhipster
 *    state exists to diff against.
 */

const path = require('path');
const fs = require('fs');
const os = require('os');

function fail(message) {
  process.stderr.write(String(message) + '\n');
  process.exit(1);
}

const jdlFilePath = process.argv[2];

if (!jdlFilePath) {
  fail('Usage: node parse.js <path-to-jdl-file>');
}

let jhiCore;
try {
  jhiCore = require('jhipster-core');
} catch (err) {
  fail(
    'Could not load "jhipster-core". Did you run "npm install" inside the node/ folder?\n' +
    err.message
  );
}

const absoluteJdlPath = path.resolve(jdlFilePath);

if (!fs.existsSync(absoluteJdlPath)) {
  fail(`JDL file not found: ${absoluteJdlPath}`);
}

// --- Work around #2: run inside a fresh scratch directory ---------------
const scratchDir = fs.mkdtempSync(path.join(os.tmpdir(), 'jdl-parse-'));
const originalCwd = process.cwd();
process.chdir(scratchDir);

// --- Work around #1: silence stdout while jhipster-core runs ------------
const originalStdoutWrite = process.stdout.write.bind(process.stdout);
process.stdout.write = (chunk, encoding, callback) => {
  // Redirect anything jhipster-core tries to print to stderr instead,
  // so it doesn't end up mixed into our JSON output on stdout.
  return process.stderr.write(chunk, encoding, callback);
};

function restoreIo() {
  process.stdout.write = originalStdoutWrite;
  process.chdir(originalCwd);
  fs.rmSync(scratchDir, { recursive: true, force: true });
}

try {
  // jhipster-core needs an application name + database type to export
  // entities to JSON when the .jdl file has no "application" block of
  // its own (which ours won't, since we only care about entities).
  // These values are placeholders - Laravel doesn't use them, they just
  // satisfy jhipster-core's internal requirements.
  const importer = jhiCore.JDLImporter.createImporterFromFiles([absoluteJdlPath], {
    applicationName: 'app',
    applicationType: 'monolith',
    databaseType: 'sql',
  });
  const importState = importer.import();

  const result = {
    entities: importState.exportedEntities || [],
  };

  restoreIo();
  process.stdout.write(JSON.stringify(result));
} catch (err) {
  restoreIo();
  fail('JDL parsing failed: ' + (err && err.message ? err.message : err));
}
