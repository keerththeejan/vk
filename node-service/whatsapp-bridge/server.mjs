/**
 * VK WhatsApp bridge — open-source (whatsapp-web.js + Puppeteer/Chromium).
 *
 * 1. npm install
 * 2. Set env: VK_WHATSAPP_SECRET (must match PHP VK_WHATSAPP_BRIDGE_SECRET)
 * 3. npm start
 * 4. Scan QR in terminal once; session persists under .wwebjs_auth/
 * 5. Point PHP VK_WHATSAPP_BRIDGE_URL to http://127.0.0.1:3999/send-message
 *
 * Alternative (lighter, no browser): @whiskeysockets/baileys — swap implementation here.
 */

import express from 'express';
import pkg from 'whatsapp-web.js';
import qrcode from 'qrcode-terminal';

const { Client, LocalAuth } = pkg;

const PORT = parseInt(process.env.VK_WHATSAPP_PORT || '3999', 10);
const SECRET = process.env.VK_WHATSAPP_SECRET || '';

const app = express();
app.use(express.json({ limit: '64kb' }));

const client = new Client({
  authStrategy: new LocalAuth({ dataPath: '.wwebjs_auth' }),
  puppeteer: {
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  },
});

client.on('qr', (qr) => {
  console.log('Scan this QR with WhatsApp (linked device):');
  qrcode.generate(qr, { small: true });
});

let waReady = false;
client.on('ready', () => {
  waReady = true;
  console.log('WhatsApp client is ready.');
});

client.on('auth_failure', (m) => console.error('Auth failure', m));

client.initialize().catch((e) => console.error(e));

function normalizePhone(raw) {
  const d = String(raw || '').replace(/\D/g, '');
  if (!d || d.length < 8) return null;
  return d;
}

app.post('/send-message', async (req, res) => {
  try {
    const { phone, text, secret } = req.body || {};
    if (!SECRET || secret !== SECRET) {
      res.status(401).json({ ok: false, error: 'unauthorized' });
      return;
    }
    if (!waReady) {
      res.status(503).json({ ok: false, error: 'whatsapp_not_ready' });
      return;
    }
    const digits = normalizePhone(phone);
    if (!digits || !text || typeof text !== 'string') {
      res.status(400).json({ ok: false, error: 'phone and text required' });
      return;
    }
    const jid = `${digits}@c.us`;
    await client.sendMessage(jid, text.slice(0, 4000));
    res.json({ ok: true });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: 'send_failed' });
  }
});

app.get('/health', (_req, res) => {
  res.json({ ok: true, whatsappReady: client.info != null });
});

app.listen(PORT, '127.0.0.1', () => {
  console.log(`VK WhatsApp bridge listening on http://127.0.0.1:${PORT}`);
});
