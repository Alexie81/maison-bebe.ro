import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';

const [baseUrl, adminEmail, adminPassword] = process.argv.slice(2);
if (!baseUrl || !adminEmail || !adminPassword) {
    console.error('Usage: node tests/browser-audit.mjs <base-url> <admin-email> <admin-password>');
    process.exit(2);
}

const edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const port = 9300 + Math.floor(Math.random() * 500);
const profile = await mkdtemp(path.join(tmpdir(), 'maison-bebe-browser-'));
const browser = spawn(edge, [
    '--headless=new',
    '--disable-gpu',
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-default-apps',
    '--disable-extensions',
    '--disable-sync',
    '--hide-scrollbars',
    '--no-first-run',
    '--no-default-browser-check',
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profile}`,
    'about:blank',
], { stdio: 'ignore', windowsHide: true });

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const endpoint = `http://127.0.0.1:${port}`;

async function waitForBrowser() {
    for (let attempt = 0; attempt < 60; attempt += 1) {
        try {
            const response = await fetch(`${endpoint}/json/version`);
            if (response.ok) {
                return;
            }
        } catch {
            // Browser startup is still in progress.
        }
        await sleep(100);
    }
    throw new Error('Browserul headless nu a pornit.');
}

function updateCookies(jar, headers) {
    const values = typeof headers.getSetCookie === 'function'
        ? headers.getSetCookie()
        : (headers.get('set-cookie') ? [headers.get('set-cookie')] : []);
    for (const value of values) {
        const [pair, ...attributes] = value.split(';');
        const separator = pair.indexOf('=');
        if (separator < 1) continue;
        const name = pair.slice(0, separator).trim();
        const cookie = {
            name,
            value: pair.slice(separator + 1).trim(),
            domain: 'localhost',
            path: '/',
        };
        for (const attribute of attributes) {
            const [rawKey, rawValue = ''] = attribute.trim().split('=', 2);
            if (rawKey.toLowerCase() === 'path' && rawValue) {
                cookie.path = rawValue;
            }
        }
        jar.set(name, cookie);
    }
}

async function authenticate() {
    const jar = new Map();
    const loginUrl = `${baseUrl}/cont/autentificare`;
    const login = await fetch(loginUrl, { redirect: 'manual' });
    updateCookies(jar, login.headers);
    const html = await login.text();
    const token = html.match(/name="_csrf"\s+value="([^"]+)"/)?.[1];
    if (!token) {
        throw new Error('Tokenul CSRF pentru login nu a fost găsit.');
    }
    const body = new URLSearchParams({
        _csrf: token,
        email: adminEmail,
        password: adminPassword,
    });
    const response = await fetch(loginUrl, {
        method: 'POST',
        redirect: 'manual',
        headers: {
            'content-type': 'application/x-www-form-urlencoded',
            cookie: [...jar.values()].map((cookie) => `${cookie.name}=${cookie.value}`).join('; '),
        },
        body,
    });
    updateCookies(jar, response.headers);
    if (![302, 303].includes(response.status) || !(response.headers.get('location') || '').includes('/admin')) {
        throw new Error(`Autentificarea browser QA a eșuat (${response.status}).`);
    }
    return [...jar.values()];
}

class Cdp {
    constructor(socket) {
        this.socket = socket;
        this.nextId = 1;
        this.pending = new Map();
        this.listeners = new Map();
        socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            if (message.id) {
                const handler = this.pending.get(message.id);
                if (!handler) return;
                this.pending.delete(message.id);
                if (message.error) handler.reject(new Error(message.error.message));
                else handler.resolve(message.result);
                return;
            }
            for (const listener of this.listeners.get(message.method) || []) {
                listener(message.params || {});
            }
        });
    }

    send(method, params = {}) {
        const id = this.nextId++;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    on(method, listener) {
        const listeners = this.listeners.get(method) || [];
        listeners.push(listener);
        this.listeners.set(method, listeners);
        return () => {
            this.listeners.set(method, (this.listeners.get(method) || []).filter((item) => item !== listener));
        };
    }
}

async function createPage(cookies) {
    const response = await fetch(`${endpoint}/json/new?about:blank`, { method: 'PUT' });
    const target = await response.json();
    const socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    const cdp = new Cdp(socket);
    await Promise.all([
        cdp.send('Page.enable'),
        cdp.send('Runtime.enable'),
        cdp.send('Network.enable'),
        cdp.send('Log.enable'),
    ]);
    await cdp.send('Network.setCookies', { cookies });
    return { cdp, socket, targetId: target.id };
}

async function auditRoute(cdp, route, viewport) {
    const targetUrl = `${baseUrl}${route}`;
    const errors = [];
    const failedResources = [];
    let documentStatus = 0;
    const offConsole = cdp.on('Runtime.consoleAPICalled', ({ type, args = [] }) => {
        if (type !== 'error' && type !== 'assert') return;
        errors.push(args.map((arg) => arg.value ?? arg.description ?? '').join(' ').slice(0, 240));
    });
    const offLog = cdp.on('Log.entryAdded', ({ entry }) => {
        if (entry?.level === 'error') errors.push(String(entry.text || '').slice(0, 240));
    });
    const offFailed = cdp.on('Network.loadingFailed', ({ errorText, canceled, type }) => {
        if (!canceled && errorText !== 'net::ERR_ABORTED' && type !== 'Ping') {
            failedResources.push(`${type || 'Resource'}: ${errorText}`);
        }
    });
    const offResponse = cdp.on('Network.responseReceived', ({ type, response }) => {
        if (type === 'Document') {
            documentStatus = response.status;
        }
    });

    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: viewport.width,
        height: viewport.height,
        deviceScaleFactor: 1,
        mobile: viewport.mobile,
    });
    const loaded = new Promise((resolve) => {
        const timer = setTimeout(resolve, 12000);
        const off = cdp.on('Page.loadEventFired', () => {
            clearTimeout(timer);
            off();
            resolve();
        });
    });
    await cdp.send('Page.navigate', { url: targetUrl });
    await loaded;
    await sleep(900);

    const result = await cdp.send('Runtime.evaluate', {
        returnByValue: true,
        expression: `(() => {
            const visible = (element) => {
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                return style.display !== 'none'
                    && style.visibility !== 'hidden'
                    && Number(style.opacity || 1) > 0
                    && rect.width > 0
                    && rect.height > 0;
            };
            const selector = (element) => {
                if (element.id) return '#' + CSS.escape(element.id);
                const classes = [...element.classList].slice(0, 3).map((name) => '.' + CSS.escape(name)).join('');
                return element.tagName.toLowerCase() + classes;
            };
            const width = document.documentElement.clientWidth;
            const overflow = document.documentElement.scrollWidth - width;
            const overflowElements = [...document.body.querySelectorAll('*')]
                .filter(visible)
                .map((element) => ({ element, rect: element.getBoundingClientRect() }))
                .filter(({ element, rect }) => {
                    if (element.closest('[aria-hidden="true"], [hidden], [inert]')) return false;
                    const parent = element.parentElement;
                    const parentStyle = parent ? getComputedStyle(parent) : null;
                    if (parentStyle && ['auto', 'scroll'].includes(parentStyle.overflowX)) return false;
                    return rect.left < -2 || rect.right > width + 2;
                })
                .slice(0, 8)
                .map(({ element, rect }) => selector(element) + ':' + Math.round(rect.left) + '..' + Math.round(rect.right));
            const brokenImages = [...document.images]
                .filter((image) => image.complete && image.naturalWidth === 0)
                .map((image) => image.currentSrc || image.src || selector(image))
                .slice(0, 8);
            const smallControls = [...document.querySelectorAll(
                'button, input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea, [role="button"]'
            )]
                .filter(visible)
                .map((element) => ({ element, rect: element.getBoundingClientRect() }))
                .filter(({ rect }) => rect.width < 36 || rect.height < 36)
                .slice(0, 12)
                .map(({ element, rect }) => {
                    const label = (element.getAttribute('aria-label') || element.textContent || '').trim().replace(/\\s+/g, ' ').slice(0, 40);
                    return selector(element) + ':' + Math.round(rect.width) + 'x' + Math.round(rect.height) + (label ? ' "' + label + '"' : '');
                });
            return {
                title: document.title,
                href: location.href,
                bodyText: (document.body.innerText || '').trim().slice(0, 80),
                overflow,
                overflowElements,
                brokenImages,
                smallControls,
            };
        })()`,
    });

    offConsole();
    offLog();
    offFailed();
    offResponse();
    const details = result.result.value;
    const fatalConsole = [...new Set(errors)].filter((error) =>
        error && !error.includes('favicon') && !error.includes('ResizeObserver loop')
    );
    const failures = [];
    if (documentStatus < 200 || documentStatus >= 400) failures.push(`HTTP ${documentStatus || '?'}`);
    if (!details.bodyText) failures.push('pagină fără conținut');
    if (details.overflow > 2) failures.push(`overflow orizontal ${details.overflow}px (${details.overflowElements.join(', ')})`);
    if (details.brokenImages.length) failures.push(`imagini lipsă: ${details.brokenImages.join(', ')}`);
    if (failedResources.length) failures.push(`resurse eșuate: ${[...new Set(failedResources)].slice(0, 5).join(', ')}`);
    if (fatalConsole.length) failures.push(`consolă: ${fatalConsole.slice(0, 5).join(' | ')}`);
    if (viewport.mobile && details.smallControls.length) {
        failures.push(`controale sub 36px: ${details.smallControls.join(', ')}`);
    }
    return { failures, details, documentStatus };
}

const publicMobile = [
    '/',
    '/shop',
    '/produs/salopeta-bumbac-organic',
    '/gift-box',
    '/favorite',
    '/cos',
    '/cont/autentificare',
    '/cont/inregistrare',
    '/urmarire-comanda',
    '/contact',
];
const publicDesktop = ['/', '/shop', '/produs/salopeta-bumbac-organic', '/gift-box'];
const adminMobile = [
    '/admin',
    '/admin/comenzi',
    '/admin/produse',
    '/admin/produse/creare',
    '/admin/categorii',
    '/admin/categorii/creare',
    '/admin/colectii/creare',
    '/admin/gift-box',
    '/admin/gift-box/cutii/creare',
    '/admin/cupoane',
    '/admin/cms/homepage',
    '/admin/atelier',
    '/admin/atelier/creare',
    '/admin/setari/email',
    '/admin/setari/plati/stripe',
    '/admin/utilizatori',
    '/admin/utilizatori/creare',
];
const adminDesktop = ['/admin', '/admin/produse', '/admin/cupoane', '/admin/produse/creare'];

let failed = false;
try {
    await waitForBrowser();
    const cookies = await authenticate();
    const { cdp, socket, targetId } = await createPage([]);
    const suites = [
        ['mobile public', publicMobile, { width: 390, height: 844, mobile: true }],
        ['desktop public', publicDesktop, { width: 1440, height: 1000, mobile: false }],
        ['mobile admin', adminMobile, { width: 390, height: 844, mobile: true }],
        ['desktop admin', adminDesktop, { width: 1440, height: 1000, mobile: false }],
    ];
    for (const [label, routes, viewport] of suites) {
        if (label === 'mobile admin') {
            await cdp.send('Network.setCookies', { cookies });
        }
        for (const route of routes) {
            const result = await auditRoute(cdp, route, viewport);
            const ok = result.failures.length === 0;
            console.log(`[${ok ? 'OK' : 'FAIL'}] ${label} ${route} -> ${result.documentStatus || '?'}`);
            for (const failure of result.failures) {
                console.log(`  - ${failure}`);
            }
            if (!ok) failed = true;
        }
    }
    socket.close();
    await fetch(`${endpoint}/json/close/${targetId}`);
} finally {
    browser.kill();
    for (let attempt = 0; attempt < 10; attempt += 1) {
        try {
            await rm(profile, { recursive: true, force: true });
            break;
        } catch (error) {
            if (error?.code !== 'EBUSY' || attempt === 9) throw error;
            await sleep(150);
        }
    }
}

process.exit(failed ? 1 : 0);
