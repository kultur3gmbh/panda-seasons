// Development-only formatter. Pass a directory containing the standalone
// Prettier 3.6.2 core, Babel plugin and ESTree plugin (not shipped in the plugin).
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const formatterRoot = process.argv[2];
if (!formatterRoot) throw new Error('Usage: node tools/prepare-readable-sources.cjs FORMATTER_DIRECTORY [--check]');
const prettier = require(path.join(formatterRoot, 'prettier.cjs'));
const babel = require(path.join(formatterRoot, 'prettier-babel.cjs'));
const estree = require(path.join(formatterRoot, 'prettier-estree.cjs'));
if (prettier.version !== '3.6.2') throw new Error('Expected Prettier 3.6.2');
const root = path.join(__dirname, '..', 'panda-seasons-free');
const ignored = new Set(['start','end','range','loc','extra','comments','leadingComments','trailingComments','innerComments','tokens','errors']);
function clean(value) {
 if (Array.isArray(value)) return value.filter(item => !item || item.type !== 'EmptyStatement').map(clean);
 if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).filter(([key]) => !ignored.has(key)).sort(([a],[b]) => a.localeCompare(b)).map(([key,v]) => [key,clean(v)]));
 return value;
}
const hash = text => crypto.createHash('sha256').update(text).digest('hex');
(async () => {
 const files = [];
 for (const name of fs.readdirSync(path.join(root, 'assets')).filter(name => name.endsWith('.js')).sort()) {
  const original = fs.readFileSync(path.join(root, 'assets', name), 'utf8');
  const formatted = await prettier.format(original, {parser:'babel',plugins:[babel,estree],singleQuote:true,useTabs:true,printWidth:100});
  if (JSON.stringify(clean(await babel.parsers.babel.parse(original))) !== JSON.stringify(clean(await babel.parsers.babel.parse(formatted)))) throw new Error('Syntax-tree mismatch: ' + name);
  const target = path.join(root, 'sources', name);
  if (process.argv.includes('--check')) {
   if (fs.readFileSync(target, 'utf8') !== formatted) throw new Error('Stale readable copy: ' + name);
  } else fs.writeFileSync(target, formatted);
  files.push({runtime:'assets/'+name,readable:'sources/'+name,runtime_sha256:hash(original),readable_sha256:hash(formatted),syntax_tree_matches:true});
 }
 const manifest = JSON.stringify({formatter:'Prettier 3.6.2',options:{parser:'babel',singleQuote:true,useTabs:true,printWidth:100},files},null,2)+'\n';
 const target = path.join(root, 'sources', 'manifest.json');
 if (process.argv.includes('--check')) {
  if (fs.readFileSync(target,'utf8') !== manifest) throw new Error('Stale source manifest');
 } else fs.writeFileSync(target, manifest);
 console.log(files.length + ' readable JavaScript sources verified; syntax trees match.');
})().catch(error => {console.error(error.message);process.exit(1);});
