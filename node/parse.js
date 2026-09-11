'use strict';

const path = require('path');
const fs = require('fs');
const os = require('os');

function fail(message) {
  process.stderr.write(String(message) + '\n');
  process.exit(1);
}

const jdlFilePath = process.argv[2];
if (!jdlFilePath) fail('Usage: node parse.js <path-to-jdl-file>');

let jhiCore;
try {
  jhiCore = require('jhipster-core');
} catch (err) {
  fail('Could not load "jhipster-core". Did you run "npm install" inside the node/ folder?\n' + err.message);
}

const absoluteJdlPath = path.resolve(jdlFilePath);
if (!fs.existsSync(absoluteJdlPath)) fail(`JDL file not found: ${absoluteJdlPath}`);

const scratchDir = fs.mkdtempSync(path.join(os.tmpdir(), 'jdl-parse-'));
const originalCwd = process.cwd();
process.chdir(scratchDir);

const originalStdoutWrite = process.stdout.write.bind(process.stdout);
process.stdout.write = (chunk, encoding, callback) => process.stderr.write(chunk, encoding, callback);

function restoreIo() {
  process.stdout.write = originalStdoutWrite;
  process.chdir(originalCwd);
  fs.rmSync(scratchDir, { recursive: true, force: true });
}

/**
 * JHipster has historically represented some `all` options by expanding
 * them onto entity JSON, while other versions may leave them at the JDL
 * application/options level. Normalize the source-level form here so the
 * PHP mapper receives the same entity-oriented representation regardless
 * of the installed jhipster-core version.
 */
function applyGlobalAllOptions(entities, source) {
  // Remove comments before matching so commented-out options cannot enable
  // generation accidentally. This intentionally handles both // and /* */.
  const withoutComments = source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\/\/.*$/gm, '');

  const rules = [
    { pattern: /(^|\n)\s*filter\s+all\s*(?=\n|$)/i, apply: entity => { entity.jpaMetamodelFiltering = true; } },
    { pattern: /(^|\n)\s*paginate\s+all\s+with\s+([A-Za-z][A-Za-z0-9_-]*)\s*(?=\n|$)/i, apply: (entity, match) => { entity.pagination = match[2]; } },
    { pattern: /(^|\n)\s*dto\s+all\s+with\s+([A-Za-z][A-Za-z0-9_-]*)\s*(?=\n|$)/i, apply: (entity, match) => { entity.dto = match[2]; } },
    { pattern: /(^|\n)\s*service\s+all\s+with\s+([A-Za-z][A-Za-z0-9_-]*)\s*(?=\n|$)/i, apply: (entity, match) => { entity.service = match[2]; } },
  ];

  for (const rule of rules) {
    const match = withoutComments.match(rule.pattern);
    if (!match) continue;
    for (const entity of entities) rule.apply(entity, match);
  }

  return entities;
}

try {
  const importer = jhiCore.JDLImporter.createImporterFromFiles([absoluteJdlPath], {
    applicationName: 'app',
    applicationType: 'monolith',
    databaseType: 'sql',
  });
  const importState = importer.import();
  const source = fs.readFileSync(absoluteJdlPath, 'utf8');
  const entities = applyGlobalAllOptions(importState.exportedEntities || [], source);

  const result = { entities };
  restoreIo();
  process.stdout.write(JSON.stringify(result));
} catch (err) {
  restoreIo();
  fail('JDL parsing failed: ' + (err && err.message ? err.message : err));
}
