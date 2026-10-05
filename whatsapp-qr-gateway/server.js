import pkg from 'whatsapp-web.js';
const { Client, LocalAuth, MessageMedia } = pkg;
import qrcode from 'qrcode-terminal';
import axios from 'axios';
import dotenv from 'dotenv';
import fs from 'fs';
import path from 'path';
import http from 'http';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function getMediaFromUrlOrPath(targetUrl, attachment = null) {
    if (!targetUrl && !attachment) return null;
    try {
        // 1. Ruta física canónica enviada por el Core
        let directPath = attachment?.file_path;
        if (directPath) {
            const fileName = path.basename(directPath);
            const candidates = [
                directPath,
                path.resolve(directPath),
                path.resolve('public', directPath),
                path.resolve('public/cards', fileName),
                path.resolve('public/uploads', fileName),
                path.resolve('..', directPath),
                path.resolve('../public', directPath),
                path.resolve('../public/cards', fileName),
                path.resolve('../public/uploads', fileName),
                path.join(process.cwd(), 'public', directPath),
                path.join(process.cwd(), 'public/cards', fileName),
                path.join(__dirname, '../public', directPath),
                path.join(__dirname, '../public/cards', fileName),
            ];
            for (const cand of candidates) {
                if (cand && fs.existsSync(cand) && !fs.statSync(cand).isDirectory()) {
                    const stats = fs.statSync(cand);
                    if (stats.size > 0) {
                        const ext = path.extname(cand).toLowerCase().replace('.', '');
                        const mimeType = ext === 'pdf' ? 'application/pdf' : (ext === 'png' ? 'image/png' : (ext === 'webp' ? 'image/webp' : 'image/jpeg'));
                        const dataBase64 = fs.readFileSync(cand, { encoding: 'base64' });
                        if (dataBase64 && dataBase64.length > 100) {
                            return new MessageMedia(mimeType, dataBase64, path.basename(cand));
                        }
                    }
                }
            }
        }

        // 2. Ruta relativa canónica /cards/ o /uploads/
        if (targetUrl && (targetUrl.includes('/uploads/') || targetUrl.includes('/cards/'))) {
            const marker = targetUrl.includes('/uploads/') ? '/uploads/' : '/cards/';
            const relativePath = targetUrl.substring(targetUrl.indexOf(marker));
            const fileName = path.basename(relativePath);
            const localCandidates = [
                path.resolve('public' + relativePath),
                path.resolve('public/cards/' + fileName),
                path.resolve('../public' + relativePath),
                path.resolve('../public/cards/' + fileName),
                path.join(process.cwd(), 'public' + relativePath),
                path.join(process.cwd(), 'public/cards/' + fileName),
                path.join(__dirname, '../public' + relativePath),
                path.join(__dirname, '../public/cards/' + fileName),
            ];
            for (const cand of localCandidates) {
                if (cand && fs.existsSync(cand) && !fs.statSync(cand).isDirectory()) {
                    const stats = fs.statSync(cand);
                    if (stats.size > 0) {
                        const ext = path.extname(cand).toLowerCase().replace('.', '');
                        const mimeType = ext === 'pdf' ? 'application/pdf' : (ext === 'png' ? 'image/png' : (ext === 'webp' ? 'image/webp' : 'image/jpeg'));
                        const dataBase64 = fs.readFileSync(cand, { encoding: 'base64' });
                        if (dataBase64 && dataBase64.length > 100) {
                            return new MessageMedia(mimeType, dataBase64, path.basename(cand));
                        }
                    }
                }
            }
        }

        // 3. Descarga HTTP para fallback si el servidor está en otro host
        if (targetUrl) {
            let downloadUrl = targetUrl;
            if (downloadUrl.startsWith('http://localhost/') || downloadUrl.startsWith('http://localhost:80/')) {
                downloadUrl = downloadUrl.replace(/http:\/\/localhost(?::80)?\//, `${LARAVEL_API_URL}/`);
            }
            const resp = await axios.get(downloadUrl, { responseType: 'arraybuffer', timeout: 15000 });
            if (resp.data && resp.data.length > 0) {
                let mime = resp.headers['content-type'] || 'image/png';
                if (mime.includes(';')) mime = mime.split(';')[0].trim();
                const ext = mime.includes('jpeg') ? 'jpg' : (mime.includes('webp') ? 'webp' : (mime.includes('pdf') ? 'pdf' : 'png'));
                const baseTitle = (attachment?.caption || attachment?.title || 'carta').replace(/[^a-zA-Z0-9_-]/g, '_');
                const fileName = `${baseTitle}.${ext}`;
                const b64 = Buffer.from(resp.data).toString('base64');
                return new MessageMedia(mime, b64, fileName);
            }
        }
        return null;
    } catch (err) {
        console.error('⚠️ error cargando MessageMedia:', err.message);
        return null;
    }
}

// Cargar variables de entorno (.env local o del proyecto principal AIVA)
const parentEnvPath = path.resolve('../.env');
if (fs.existsSync(parentEnvPath)) {
    dotenv.config({ path: parentEnvPath });
}
dotenv.config();

const args = process.argv.slice(2);
const isReset = args.includes('--reset') || args.includes('--logout');
const sessionArg = args.find(a => a.startsWith('--session='))?.split('=')[1] || args.find(a => a.startsWith('--tenant='))?.split('=')[1] || args.find(a => a.startsWith('--clientId='))?.split('=')[1] || process.env.GATEWAY_SESSION_ID || 'aiva-gateway-instance';
const tenantArg = sessionArg;

const LARAVEL_API_URL = (process.env.AIVA_LARAVEL_API || 'http://127.0.0.1:8000').trim();
const GATEWAY_SECRET = (process.env.AIVA_GATEWAY_SECRET || 'aiva_qr_gateway_master_secret_2026').trim();
const OUTBOUND_PORT = process.env.GATEWAY_OUTBOUND_PORT || 3000;

console.log('---------------------------------------------------------');
console.log('🚀 AIVA WHATSAPP WEB QR GATEWAY - ADAPTADOR DE TRANSPORTE');
console.log(`🔑 Sesión / Cliente Activo: [ ${sessionArg} ]`);
console.log(`📡 Conectado a Core Laravel: ${LARAVEL_API_URL}`);
console.log(`🔌 Puerto de Despacho Saliente: ${OUTBOUND_PORT}`);
console.log('---------------------------------------------------------');

// Si se solicita RESET o LOGOUT, eliminar la carpeta de sesión actual
const authDir = path.resolve(`.wwebjs_auth/session-${tenantArg}`);
const baseAuthDir = path.resolve('.wwebjs_auth');

if (isReset) {
    console.log(`\n🗑️ [RESET] Cerrando sesión y eliminando datos vinculados de '${tenantArg}'...`);
    try {
        if (fs.existsSync(authDir)) {
            fs.rmSync(authDir, { recursive: true, force: true });
        }
        if (fs.existsSync(baseAuthDir)) {
            fs.rmSync(baseAuthDir, { recursive: true, force: true });
        }
        console.log('✅ Sesión eliminada. Se mostrará un nuevo Código QR para vincular otro celular.\n');
    } catch (err) {
        console.error('⚠️ Aviso de sesión:', err.message);
    }
}

// Limpiar archivos de bloqueo SingletonLock de sesiones anteriores si existen
try {
    const sessionDir = path.resolve(`.wwebjs_auth/session-${tenantArg}`);
    const locks = ['SingletonLock', 'SingletonSocket', 'SingletonCookie'];
    locks.forEach(f => {
        const p = path.join(sessionDir, f);
        if (fs.existsSync(p)) try { fs.rmSync(p, { force: true }); } catch (e) {}
    });
} catch (e) {}

const client = new Client({
    authStrategy: new LocalAuth({ clientId: tenantArg }),
    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu',
            '--disable-session-crashed-bubble',
            '--disable-web-security',
            '--allow-running-insecure-content',
            '--disable-features=IsolateOrigins,site-per-process,AudioServiceOutOfProcess'
        ]
    }
});

client.on('qr', (qr) => {
    console.log('\n=========================================================');
    console.log('📱 ESCANEA ESTE CÓDIGO QR CON EL NUEVO NÚMERO DE WHATSAPP:');
    console.log('   (Abre WhatsApp en el celular -> Dispositivos vinculados -> Vincular)');
    console.log('=========================================================\n');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    const connectedPhone = client.info?.wid?.user || 'Desconocido';
    console.log('\n🟢 ¡ÉXITO TOTAL! CONECTADO A WHATSAPP WEB CORRECTAMENTE');
    console.log(`📱 Canal Telefónico Físico Activo: [ ${connectedPhone} ]`);
    console.log('🤖 AIVA está lista y escuchando mensajes entrantes en vivo 24/7...\n');
});

// ESCUCHADOR DE MENSAJES ENTRANTES DE WHATSAPP WEB
client.on('message', async (msg) => {
    try {
        if (msg.from === 'status@broadcast' || msg.from.includes('@g.us')) return;

        let text = msg.body || '';
        let hasMedia = false;
        let mediaBase64 = null;

        // Descarga ultra-resiliente de imágenes HD con reintentos para HD real
        if (msg.hasMedia || msg.type === 'image' || msg.type === 'sticker') {
            console.log(`📥 Procesando descarga de imagen HD/comprobante de ${msg.from}...`);

            for (let attempt = 1; attempt <= 5; attempt++) {
                try {
                    // Esperar brevemente a que WhatsApp complete la recepción de datos CDN
                    await new Promise(r => setTimeout(r, attempt === 1 ? 600 : 1200));

                    // 1. Método Estándar de whatsapp-web.js
                    let media = await msg.downloadMedia().catch(() => null);

                    // 2. Si la imagen descargada es de alta resolución (> 10 KB), la aceptamos inmediatamente
                    if (media && media.data && media.data.length > 10000) {
                        mediaBase64 = media.data;
                        console.log(`📸 ¡ÉXITO TOTAL HD! Imagen HD capturada de ${msg.from} en intento ${attempt} (${media.mimetype || 'image/jpeg'}, ${media.data.length} bytes)`);
                        break;
                    }

                    // 3. Método por Evaluación de Injected Puppeteer Script para descargas HD de cuentas @lid
                    const serializedId = msg.id?._serialized || (typeof msg.id === 'string' ? msg.id : null);
                    if (serializedId && client.pupPage) {
                        const raw = await client.pupPage.evaluate(async (msgId) => {
                            try {
                                let m = window.Store.Msg.get(msgId);
                                if (!m && window.Store.Msg.models) {
                                    m = window.Store.Msg.models.find(x => x.id && (x.id._serialized === msgId || (x.id.id && msgId.includes(x.id.id))));
                                }
                                if (m) {
                                    if (m.downloadMedia) await m.downloadMedia();
                                    if (window.Store.DownloadManager && m.mediaData && m.mediaData.mediaStage !== 'RESOLVED') {
                                        await window.Store.DownloadManager.downloadAndMaybeDecrypt({
                                            directPath: m.directPath,
                                            encFilehash: m.encFilehash,
                                            filehash: m.filehash,
                                            mediaKey: m.mediaKey,
                                            type: m.type,
                                            signal: (new AbortController()).signal
                                        }).catch(() => null);
                                    }
                                    if (window.WWebJS && window.WWebJS.downloadMedia) {
                                        return await window.WWebJS.downloadMedia(m.id._serialized);
                                    }
                                }
                            } catch (e) {
                                return null;
                            }
                            return null;
                        }, serializedId).catch(() => null);

                        if (raw && raw.data && raw.data.length > 10000) {
                            mediaBase64 = raw.data;
                            console.log(`📸 ¡ÉXITO HD PUPPETEER! Imagen HD capturada de ${msg.from} en intento ${attempt} (${raw.data.length} bytes)`);
                            break;
                        } else if (raw && raw.data && !mediaBase64) {
                            mediaBase64 = raw.data;
                        }
                    }

                    // 4. Si aún no tenemos mediaBase64 al último intento, usar fallback embebido
                    if (attempt === 5 && !mediaBase64 && msg._data && msg._data.body && msg._data.body.length > 50) {
                        mediaBase64 = msg._data.body;
                        console.log(`⚠️ Imagen Thumbnail asignada como fallback final de ${msg.from} (${mediaBase64.length} bytes)`);
                    }
                } catch (err) {
                    console.log(`⚠️ Intento ${attempt}/5 sin datos de imagen HD para ${msg.from}.`);
                }
            }
        }

        if (mediaBase64) hasMedia = true;
        if (hasMedia && (!text || !text.trim() || text === '[Imagen Adjunta / Comprobante]')) {
            text = '[Imagen Adjunta / Comprobante]';
        }

        if (!text && !hasMedia) return;

        const senderPhone = msg.from.replace('@c.us', '').replace('@s.whatsapp.net', '').replace('@lid', '');
        console.log(`📩 Mensaje recibido de ${senderPhone}: "${text}" ${hasMedia ? '[IMAGEN ADJUNTA]' : ''}`);

        const connectedPhone = client.info?.wid?.user || '';

        // Reenviar mensaje al Core Unificado de Laravel mediante API protegida
        const response = await axios.post(
            `${LARAVEL_API_URL}/api/v1/messaging/qr-inbound`,
            {
                gateway_session_id: sessionArg,
                client_id: sessionArg,
                phone: senderPhone,
                connected_phone: connectedPhone,
                name: msg._data?.notifyName || msg._data?.pushname || msg._data?.verifiedName || `Cliente ${senderPhone.slice(-4)}`,
                message: text,
                has_media: hasMedia,
                media_base64: mediaBase64
            },
            {
                headers: {
                    'Authorization': `Bearer ${GATEWAY_SECRET}`,
                    'Content-Type': 'application/json'
                },
                timeout: 20000
            }
        );

        const data = response.data;

        if (data.status === 'HANDOVER_ACTIVE') {
            console.log(`🛑 Intervención Humana activa para ${senderPhone}. La IA no respondió.`);
            return;
        }

        const replyText = data.reply_text;
        const attachments = Array.isArray(data.attachments) ? data.attachments : [];
        const mediaUrl = data.media_url || (attachments.length > 0 ? (attachments[0].display_url || attachments[0].url) : null);
        const isMediaIntent = (data.intent === 'REQUEST_MEDIA' || attachments.length > 0 || !!mediaUrl);

        if (isMediaIntent) {
            try {
                const targetMediaUrl = mediaUrl || attachments[0]?.display_url || attachments[0]?.url;
                console.log(`⏳ [Tenant ${data.tenant_id}] Preparando adjunto multimedia: ${targetMediaUrl}`);
                const media = await getMediaFromUrlOrPath(targetMediaUrl, attachments[0]);
                if (media) {
                    const cleanCaption = (replyText || '').replace(/\*\([^)]+\)\*/g, '').trim();
                    console.log(`📤 Enviando imagen a ${msg.from}...`);
                    try {
                        await client.sendMessage(msg.from, media, { 
                            caption: cleanCaption || 'Aquí tienes la carta oficial de nuestro negocio 📄✨'
                        });
                    } catch (sendHdErr) {
                        console.warn('⚠️ Reintentando envío de imagen sin caption enriquecido:', sendHdErr.message);
                        await client.sendMessage(msg.from, media);
                    }
                    console.log(`📸 [Tenant ${data.tenant_id}] ¡IMAGEN DE CARTA/MENÚ ENVIADA EN VIVO A ${senderPhone}! (${data.latency_ms}ms / ${data.model})\n`);
                    return;
                } else {
                    // PROHIBICIÓN CONSTITUCIONAL DE DOWNGRADE SILENCIOSO MEDIA -> TEXT
                    console.error(`❌ [EXECUTION_FAILED: INVALID_MEDIA_ASSET] El recurso para ${targetMediaUrl} no existe o tiene 0 bytes. Prohibido degradar a texto.`);
                    return;
                }
            } catch (mErr) {
                console.error(`❌ [EXECUTION_FAILED: MEDIA_DELIVERY_FAILED] Error procesando adjunto media: ${mErr.message}. Prohibido degradar a texto.`);
                return;
            }
        }

        if (replyText) {
            await client.sendMessage(msg.from, replyText);
            console.log(`⚡ [Tenant ${data.tenant_id}] Respuesta enviada (${data.latency_ms}ms / ${data.model}): "${replyText.substring(0, 70)}..."\n`);
        }
    } catch (error) {
        console.error('❌ Error enviando mensaje a Laravel Core:', error.response?.data || error.message);
    }
});

// SERVIDOR HTTP PARA DESPACHO DE MENSAJES SALIENTES DEL ASESOR HUMANO (DASHBOARD)
const server = http.createServer((req, res) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    if (req.method === 'OPTIONS') {
        res.writeHead(200);
        return res.end();
    }

    if (req.method === 'POST' && (req.url === '/send-message' || req.url === '/api/send-message')) {
        let body = '';
        req.on('data', chunk => { body += chunk.toString(); });
        req.on('end', async () => {
            try {
                const data = JSON.parse(body || '{}');
                const phone = data.phone || data.recipientPhone;
                const message = data.message || data.content;

                if (!phone || !message) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    return res.end(JSON.stringify({ status: 'ERROR', message: 'Faltan parámetros phone o message.' }));
                }

                let targetJid = String(phone).trim();
                if (!targetJid.includes('@')) {
                    const clean = targetJid.replace(/[^0-9]/g, '');
                    if (clean.length > 14) {
                        targetJid = `${clean}@lid`;
                    } else {
                        targetJid = `${clean}@c.us`;
                    }
                }

                console.log(`💬 [OPERADOR HUMANO] Despachando mensaje a ${targetJid}: "${message}"`);
                await safeSendMessage(targetJid, message);
                console.log(`✅ [EXITO] Mensaje del operador enviado a WhatsApp por QR Gateway.`);

                res.writeHead(200, { 'Content-Type': 'application/json' });
                return res.end(JSON.stringify({ status: 'SUCCESS', message: 'Mensaje enviado a WhatsApp.' }));
            } catch (err) {
                console.error('❌ Error enviando mensaje del operador:', err.message);
                res.writeHead(500, { 'Content-Type': 'application/json' });
                return res.end(JSON.stringify({ status: 'ERROR', message: err.message }));
            }
        });
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'NOT_FOUND' }));
});

/**
 * Despachador resiliente de mensajes salientes con 3 capas de tolerancia a fallos.
 */
async function safeSendMessage(targetJid, text) {
    // 1. Intentar envío nativo
    try {
        await client.sendMessage(targetJid, text);
        return true;
    } catch (err1) {
        console.log(`⚠️ Intento 1 de envío falló (${err1.message}). Probando con fallback de JID / Store...`);
    }

    // 2. Si el JID era @c.us o @lid y falló, alternar entre @lid y @c.us
    try {
        let altJid = targetJid;
        if (targetJid.endsWith('@c.us')) {
            altJid = targetJid.replace('@c.us', '@lid');
        } else if (targetJid.endsWith('@lid')) {
            altJid = targetJid.replace('@lid', '@c.us');
        }
        console.log(`🔄 Intentando envío con JID alternativo: ${altJid}`);
        await client.sendMessage(altJid, text);
        return true;
    } catch (err2) {
        console.log(`⚠️ Intento 2 JID alternativo falló (${err2.message}). Probando fallback Puppeteer Store...`);
    }

    // 3. Fallback directo por Puppeteer Evaluation (window.Store.SendTextMsgToChat)
    if (client.pupPage) {
        try {
            const sent = await client.pupPage.evaluate(async (jid, msgText) => {
                try {
                    let chat = window.Store.Chat.get(jid);
                    if (!chat && window.Store.Chat.models) {
                        const rawId = jid.split('@')[0];
                        chat = window.Store.Chat.models.find(c => c.id && (c.id._serialized === jid || (c.id.user && c.id.user.includes(rawId))));
                    }
                    if (chat) {
                        if (window.WWebJS && window.WWebJS.sendMessage) {
                            await window.WWebJS.sendMessage(chat, msgText);
                            return true;
                        }
                        if (window.Store.SendTextMsgToChat) {
                            await window.Store.SendTextMsgToChat(chat, msgText);
                            return true;
                        }
                    }
                } catch (e) {
                    return false;
                }
                return false;
            }, targetJid, text).catch(() => false);

            if (sent) return true;
        } catch (err3) {
            console.log(`⚠️ Intento 3 Puppeteer Store falló: ${err3.message}`);
        }
    }

    throw new Error(`No se pudo enviar el mensaje a WhatsApp tras 3 reintentos resilientes.`);
}

server.listen(OUTBOUND_PORT, () => {
    console.log(`📡 Servidor de Despacho Saliente (Asesor Humano) escuchando en http://127.0.0.1:${OUTBOUND_PORT}`);
});

client.initialize().catch(async (err) => {
    console.error('⚠️ Aviso de inicio de WhatsApp Web:', err.message);
    if (err.message.includes('Execution context was destroyed') || err.message.includes('Protocol error')) {
        console.log('🔄 Limpiando caché de perfil de navegador residual y reintentando...');
        try {
            const authDir = path.resolve(`.wwebjs_auth/session-${tenantArg}`);
            if (fs.existsSync(authDir)) {
                fs.rmSync(authDir, { recursive: true, force: true });
            }
        } catch (e) {}
        console.log('💡 Ejecute de nuevo "npm run start:gateway" o "npm run start:gateway -- --reset" para escanear el QR limpio.');
    }
});
