const { app, BrowserWindow, shell, dialog, Menu, session } = require('electron');
const path = require('path');
const https = require('https');

const APP_URL = process.env.STP_APP_URL || 'https://servistakippanel.com/';
const APP_ORIGIN = new URL(APP_URL).origin;

let mainWindow;

function iconPath() {
    return path.join(__dirname, 'build', 'icon.ico');
}

function splashAc() {
    const splash = new BrowserWindow({
        width: 420,
        height: 280,
        frame: false,
        transparent: true,
        alwaysOnTop: true,
        resizable: false,
        webPreferences: { nodeIntegration: false, contextIsolation: true },
        icon: iconPath(),
    });
    splash.loadFile(path.join(__dirname, 'splash.html'));
    return splash;
}

function internetVarMi() {
    return new Promise(resolve => {
        const req = https.get(APP_URL, { timeout: 7000 }, res => {
            res.resume();
            resolve(true);
        });
        req.on('timeout', () => {
            req.destroy();
            resolve(false);
        });
        req.on('error', () => resolve(false));
    });
}

function anaEkranAc() {
    mainWindow = new BrowserWindow({
        width: 1400,
        height: 900,
        minWidth: 1100,
        minHeight: 700,
        show: false,
        autoHideMenuBar: true,
        icon: iconPath(),
        backgroundColor: '#f1f5f9',
        title: 'Servis Takip Panel',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
            webSecurity: true,
        },
    });

    Menu.setApplicationMenu(null);
    mainWindow.on('closed', () => { mainWindow = null; });

    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (url.startsWith(APP_ORIGIN)) {
            mainWindow.loadURL(url);
        } else {
            shell.openExternal(url);
        }
        return { action: 'deny' };
    });

    mainWindow.webContents.on('will-navigate', (event, url) => {
        if (!url.startsWith(APP_ORIGIN)) {
            event.preventDefault();
            shell.openExternal(url);
        }
    });

    return mainWindow;
}

function izinleriAyarla() {
    session.defaultSession.setPermissionRequestHandler((webContents, permission, callback) => {
        const url = webContents.getURL();
        callback(permission === 'geolocation' && url.startsWith(APP_ORIGIN));
    });

    session.defaultSession.setPermissionCheckHandler((webContents, permission) => {
        const url = webContents?.getURL?.() || '';
        return permission === 'geolocation' && url.startsWith(APP_ORIGIN);
    });
}

app.whenReady().then(async () => {
    const splash = splashAc();

    try {
        izinleriAyarla();
        const online = await internetVarMi();
        if (!online) {
            splash.close();
            dialog.showErrorBox(
                'Internet Baglantisi Gerekli',
                'Servis Takip Panel masaustu uygulamasi web panel ile calisir. Lutfen internet baglantinizi kontrol edip tekrar acin.'
            );
            app.quit();
            return;
        }

        const win = anaEkranAc();
        await win.loadURL(APP_URL);
        splash.close();
        win.once('ready-to-show', () => {
            win.maximize();
            win.show();
        });
    } catch (err) {
        splash.close();
        dialog.showErrorBox('Baslatma Hatasi', err.message || 'Uygulama baslatilamadi.');
        app.quit();
    }
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
});
