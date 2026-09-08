import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createServer } from 'node:http';
import vm from 'node:vm';
import { test } from 'node:test';

const source = readFileSync(new URL('./shell-scroll-gate', import.meta.url), 'utf8');
function connector() {
    const start = source.indexOf('async function connect(');
    const end = source.indexOf('\nasync function measure(', start);
    return vm.runInNewContext(`${source.slice(start, end)}; connect`, {
        readFileSync,
        join,
        fetch,
        setTimeout,
        AbortSignal,
        Date,
        PORT: 9333,
    });
}

test('occupied legacy port is never queried; only the owned profile port is used', async () => {
    let foreignRequests = 0;
    const foreign = createServer((req, res) => {
        foreignRequests++;
        res.end('[]');
    });
    const own = createServer((req, res) =>
        res.end(JSON.stringify([{ type: 'page', webSocketDebuggerUrl: 'ws://127.0.0.1/owned' }])),
    );
    const dir = mkdtempSync(join(tmpdir(), 'shell-startup-test-'));
    try {
        await new Promise((resolve, reject) => {
            foreign.once('error', reject);
            foreign.listen(9333, '127.0.0.1', resolve);
        });
        await new Promise((resolve) => own.listen(0, '127.0.0.1', resolve));
        const profile = join(dir, 'profile');
        mkdirSync(profile);
        writeFileSync(
            join(profile, 'DevToolsActivePort'),
            `${own.address().port}\n/devtools/browser/owned`,
        );
        const result = await connector()({ exitCode: null, signalCode: null }, profile, {
            error: null,
            stderr: '',
        });
        assert.equal(result, 'ws://127.0.0.1/owned');
        assert.equal(foreignRequests, 0);
    } finally {
        await Promise.all([new Promise((r) => foreign.close(r)), new Promise((r) => own.close(r))]);
        rmSync(dir, { recursive: true, force: true });
    }
});

test('early browser exit fails immediately with a bounded diagnostic', async () => {
    const start = Date.now();
    await assert.rejects(
        connector()({ exitCode: 7, signalCode: null }, '/missing-profile', {
            error: null,
            stderr: 'sandbox startup failed',
        }),
        /7.*sandbox startup failed/s,
    );
    assert.ok(Date.now() - start < 1000);
});

test('stderr is bounded and spawn errors are reported', async () => {
    const { EventEmitter } = await import('node:events');
    const { PassThrough } = await import('node:stream');
    const start = source.indexOf('function startupDiagnostics(');
    const end = source.indexOf('\nasync function stopChrome(', start);
    const capture = vm.runInNewContext(`${source.slice(start, end)}; startupDiagnostics`);
    const chrome = new EventEmitter();
    chrome.stderr = new PassThrough();
    const state = capture(chrome);
    chrome.stderr.write('x'.repeat(9000));
    assert.equal(state.stderr.length, 4096);
    chrome.emit('error', new Error('spawn failed'));
    await assert.rejects(
        connector()({ exitCode: null, signalCode: null }, '/missing-profile', state),
        /spawn failed/,
    );
});

test('cleanup handles an already exited child and an exit during kill without waiting', async () => {
    const { EventEmitter } = await import('node:events');
    const start = source.indexOf('async function stopChrome(');
    const end = source.indexOf('\nasync function measure(', start);
    const stop = vm.runInNewContext(`${source.slice(start, end)}; stopChrome`, {
        setTimeout,
        clearTimeout,
    });
    let killed = 0;
    await stop({
        pid: 10,
        exitCode: 0,
        signalCode: null,
        kill() {
            killed++;
        },
    });
    assert.equal(killed, 0);
    const chrome = new EventEmitter();
    Object.assign(chrome, { pid: 10, exitCode: null, signalCode: null });
    chrome.kill = (signal) => {
        killed++;
        chrome.signalCode = signal;
        chrome.emit('exit');
    };
    const began = Date.now();
    await stop(chrome);
    assert.equal(killed, 1);
    assert.equal(chrome.listenerCount('exit'), 0);
    assert.ok(Date.now() - began < 1000);
});

test('startup failure exits the real gate nonzero and removes only its temporary profile', async () => {
    const { spawnSync } = await import('node:child_process');
    const { readdirSync } = await import('node:fs');
    const { fileURLToPath } = await import('node:url');
    const dir = mkdtempSync(join(tmpdir(), 'shell-startup-cli-test-'));
    writeFileSync(join(dir, 'unrelated'), 'keep');
    try {
        const run = spawnSync(
            process.execPath,
            [fileURLToPath(new URL('./shell-scroll-gate', import.meta.url)), 'file:///unused.css'],
            {
                env: { ...process.env, CHROME_PATH: '/usr/bin/false', TMPDIR: dir },
                encoding: 'utf8',
                timeout: 3000,
            },
        );
        assert.equal(run.error, undefined);
        assert.equal(run.status, 1);
        assert.match(run.stderr, /Chrome startup failed \(1\)/);
        assert.deepEqual(readdirSync(dir), ['unrelated']);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});
