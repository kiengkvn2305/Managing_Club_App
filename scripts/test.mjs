import { join } from 'node:path';
import { root,phpPath,run } from './runtime.mjs';
run(phpPath(),['artisan','test','--no-ansi'],join(root,'backend'));
run(process.execPath,['--test'],join(root,'frontend'));
run(process.execPath,['--test','scripts/start.test.mjs'],root);
