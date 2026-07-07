#!/usr/bin/env node

const { spawnSync } = require('child_process');

const input = 'phpBB/styles/clarity/theme/stylesheet.css';
const output = 'phpBB/styles/clarity/theme/stylesheet.min.css';
const env = { ...process.env, MINIFY: '1' };

const result = spawnSync('postcss', [input, '-o', output, '--map'], {
	stdio: 'inherit',
	shell: true,
	env,
});

process.exit(result.status === null ? 1 : result.status);
