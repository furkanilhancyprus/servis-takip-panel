const { app, BrowserWindow, shell, dialog, Menu, session } = require('electron');
const { spawn, execSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const http = require('http');

let mainWindow;
let phpProcess;
let phpPort = 8734;

function lokalOnlyMod() {
    return process.env.STP_LOCAL_ONLY === '1' || app.getName().toLowerCase().includes('lokal');
}

function resourcePath(...parts) {
    if (app.isPackaged) {
        return path.join(process.resourcesPath, ...parts);
    }
    return path.join(__dirname, 'resources', ...parts);
}

function wwwPath() {
    return resourcePath('www');
}

function phpExePath() {
    if (app.isPackaged) {
        return path.join(process.resourcesPath, 'php', 'php.exe');
    }

    const localPhp = path.join(__dirname, 'resources', 'php', 'php.exe');
    if (fs.existsSync(localPhp)) return localPhp;

    try {
        return execSync('where php').toString().trim().split('\n')[0].trim();
    } catch {
        return 'php';
    }
}

function bosPortBul(baslangic = 8734) {
    return new Promise((resolve, reject) => {
        const server = http.createServer();
        server.listen(baslangic, '127.0.0.1', () => {
            const { port } = server.address();
            server.close(() => resolve(port));
        });
        server.on('error', () => bosPortBul(baslangic + 1).then(resolve).catch(reject));
    });
}

async function phpBaslat() {
    phpPort = await bosPortBul(8734);
    const www = wwwPath();
    const phpExe = phpExePath();

    phpProcess = spawn(phpExe, [
        '-S', `127.0.0.1:${phpPort}`,
        '-t', www,
        '-c', resourcePath('php', 'php.ini'),
    ], {
        env: {
            ...process.env,
            STP_DATA_DIR: app.getPath('userData'),
            STP_LOCAL_ONLY: lokalOnlyMod() ? '1' : '0',
        },
        windowsHide: true,
    });

    phpProcess.stderr.on('data', d => {
        const msg = d.toString().trim();
        if (msg && !msg.includes('started') && !msg.includes('Listening')) {
            console.log('[PHP]', msg);
        }
    });

    phpProcess.on('close', code => {
        if (code !== 0 && mainWindow) {
            console.error('PHP sunucusu kapandi, kod:', code);
        }
    });

    await new Promise((resolve, reject) => {
        let deneme = 0;
        const kontrol = setInterval(() => {
            http.get(`http://127.0.0.1:${phpPort}/`, res => {
                clearInterval(kontrol);
                res.resume();
                resolve();
            }).on('error', () => {
                if (++deneme > 30) {
                    clearInterval(kontrol);
                    reject(new Error('PHP baslatilamadi'));
                }
            });
        }, 300);
    });
}

function splashAc() {
    const splash = new BrowserWindow({
        width: 420,
        height: 280,
        frame: false,
        transparent: true,
        alwaysOnTop: true,
        resizable: false,
        webPreferences: { nodeIntegration: false },
        icon: path.join(__dirname, 'build', 'icon.ico'),
    });
    splash.loadFile(path.join(__dirname, 'splash.html'));
    return splash;
}

function anaEkranAc() {
    mainWindow = new BrowserWindow({
        width: 1400,
        height: 900,
        minWidth: 1100,
        minHeight: 700,
        show: false,
        autoHideMenuBar: true,
        icon: path.join(__dirname, 'build', 'icon.ico'),
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
            webSecurity: true,
        },
        backgroundColor: '#f1f5f9',
        title: lokalOnlyMod() ? 'Servis Takip Panel Lokal' : 'Servis Takip Panel',
    });

    Menu.setApplicationMenu(null);
    mainWindow.on('closed', () => { mainWindow = null; });

    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        shell.openExternal(url);
        return { action: 'deny' };
    });

    return mainWindow;
}

function konumIzniAyarla() {
    session.defaultSession.setPermissionRequestHandler((webContents, permission, callback) => {
        const url = webContents.getURL();
        const yerelUygulama = url.startsWith(`http://127.0.0.1:${phpPort}/`);
        callback(permission === 'geolocation' && yerelUygulama);
    });

    session.defaultSession.setPermissionCheckHandler((webContents, permission) => {
        const url = webContents?.getURL?.() || '';
        const yerelUygulama = url.startsWith(`http://127.0.0.1:${phpPort}/`);
        return permission === 'geolocation' && yerelUygulama;
    });
}

app.whenReady().then(async () => {
    const splash = splashAc();

    try {
        await phpBaslat();
        konumIzniAyarla();
        splash.close();

        const win = anaEkranAc();
        win.loadURL(`http://127.0.0.1:${phpPort}/`);
        win.once('ready-to-show', () => {
            win.maximize();
            win.show();
        });
    } catch (err) {
        splash.close();
        dialog.showErrorBox('Baslatma Hatasi', err.message);
        app.quit();
    }
});

app.on('window-all-closed', () => {
    if (phpProcess) phpProcess.kill();
    if (process.platform !== 'darwin') app.quit();
});

app.on('before-quit', () => {
    if (phpProcess) {
        phpProcess.kill();
        phpProcess = null;
    }
});
