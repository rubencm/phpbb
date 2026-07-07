#!/usr/bin/env node

const { spawn } = require('child_process');

const input = 'phpBB/styles/clarity/theme/main.css';
const output = 'phpBB/styles/clarity/theme/stylesheet.css';

const processHandle = spawn('postcss', [input, '-o', output, '--watch', '--map', '--verbose'], {
	stdio: 'inherit',
	shell: true,
	env: process.env,
});

processHandle.on('close', (code) => process.exit(code));
processHandle.on('error', (error) => {
	console.error(error);
	process.exit(1);
});
