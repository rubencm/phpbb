#!/usr/bin/env node

const { spawnSync } = require('child_process');

const input = 'phpBB/styles/clarity/theme/main.css';
const output = 'phpBB/styles/clarity/theme/stylesheet.css';

const result = spawnSync('postcss', [input, '-o', output, '--map'], {
	stdio: 'inherit',
	shell: true,
	env: process.env,
});

process.exit(result.status === null ? 1 : result.status);
