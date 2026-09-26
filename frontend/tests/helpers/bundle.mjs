// Compiles a small JSX entry with the esbuild that ships with Vite, so the
// built-in node test runner can render real components (react-dom/server)
// without adding a test framework.
import { build } from 'esbuild'
import { mkdirSync, writeFileSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { pathToFileURL } from 'node:url'

const ROOT = resolve(import.meta.dirname, '..', '..')
// Inside node_modules so the external react / react-dom imports resolve to
// the project's own copies (one React instance for hooks).
const OUT_DIR = join(ROOT, 'node_modules', '.cache', 'interntrack-ui-tests')
let counter = 0

export async function importBundle(source) {
  const result = await build({
    stdin: { contents: source, resolveDir: ROOT, loader: 'jsx' },
    bundle: true,
    write: false,
    format: 'esm',
    platform: 'node',
    jsx: 'automatic',
    external: ['react', 'react/*', 'react-dom', 'react-dom/*'],
    loader: { '.css': 'empty', '.png': 'empty', '.jpg': 'empty', '.svg': 'empty' },
    define: { 'process.env.NODE_ENV': '"production"', 'import.meta.env': '{}' },
    logLevel: 'silent',
  })
  mkdirSync(OUT_DIR, { recursive: true })
  const file = join(OUT_DIR, `bundle-${process.pid}-${counter++}.mjs`)
  writeFileSync(file, result.outputFiles[0].text)
  return import(pathToFileURL(file).href)
}

export const FRONTEND_ROOT = ROOT
