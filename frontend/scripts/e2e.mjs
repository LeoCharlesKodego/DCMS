import { spawnSync } from 'node:child_process'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const frontend = resolve(here, '..')

const prepare = spawnSync('bash', [resolve(frontend, '../backend/scripts/dev-local.sh'), '--prepare-only'], {
  stdio: 'inherit',
})
if (prepare.status !== 0) process.exit(prepare.status ?? 1)

const cli = resolve(frontend, 'node_modules/@playwright/test/cli.js')
const run = spawnSync(process.execPath, [cli, 'test', ...process.argv.slice(2)], {
  stdio: 'inherit',
  cwd: frontend,
})
process.exit(run.status ?? 1)
