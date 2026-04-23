import http from 'k6/http';
import { WebSocket } from 'k6/experimental/websockets';
import { sleep, check } from 'k6';
import { Counter } from 'k6/metrics';

const SESSION = 'SENIN_BINOKL_SESSION_COOKIE';

const eventsReceived   = new Counter('socket_events_received');
const connectSuccess   = new Counter('socket_connect_success');
const connectFailed    = new Counter('socket_connect_failed');

export let options = {
  stages: [
    { duration: '1m', target: 20 },
    { duration: '2m', target: 20 },
    { duration: '1m', target: 50 },
    { duration: '2m', target: 50 },
    { duration: '1m', target: 0 },
  ],
  thresholds: {
    http_req_duration:   ['p(95)<2000'],
    http_req_failed:     ['rate<0.05'],
    socket_connect_failed: ['count<5'],
  },
};

const pages = [
  'https://binokl.az/properties',
  'https://binokl.az/customers',
  'https://binokl.az/settings',
];

export default function () {
  const headers = { Cookie: `binokl-session=${SESSION}` };

  // ── 1. Socket.IO handshake (polling) ──────────────────────────────
  const pollRes = http.get(
    'https://binokl.az/socket.io/?EIO=4&transport=polling',
    { headers, tags: { name: 'socket_handshake' } }
  );

  const handshakeOk = check(pollRes, { 'socket handshake 200': (r) => r.status === 200 });

  let ws = null;

  if (handshakeOk) {
    const match = (pollRes.body || '').match(/"sid":"([^"]+)"/);

    if (match) {
      const sid   = match[1];
      const wsUrl = `wss://binokl.az/socket.io/?EIO=4&transport=websocket&sid=${sid}`;

      ws = new WebSocket(wsUrl, null, { headers });

      ws.onopen = () => {
        connectSuccess.add(1);
        ws.send('2probe');
      };

      ws.onmessage = (e) => {
        const msg = e.data;
        if (msg === '3probe')      ws.send('5');   // upgrade təsdiqi
        if (msg === '2')           ws.send('3');   // ping → pong
        if (msg.startsWith('42')) {
          try {
            const payload = JSON.parse(msg.slice(2));
            if (payload[0] === 'property.created') eventsReceived.add(1);
          } catch (_) {}
        }
      };

      ws.onerror = () => connectFailed.add(1);
    } else {
      connectFailed.add(1);
    }
  } else {
    connectFailed.add(1);
  }

  // ── 2. İstifadəçi kimi səhifələrə bax ───────────────────────────
  // Socket açıq ikən HTTP requestlər gedir — real istifadəçi davranışı
  const iterations = 3 + Math.floor(Math.random() * 3); // 3-5 click
  for (let i = 0; i < iterations; i++) {
    const url = pages[Math.floor(Math.random() * pages.length)];
    const res  = http.get(url, { headers });

    check(res, {
      'status 200':  (r) => r.status === 200,
      'cavab < 1s':  (r) => r.timings.duration < 1000,
    });

    sleep(3 + Math.random() * 7); // hər click arası 3-10s
  }

  // ── 3. Socket bağlantısını bağla ────────────────────────────────
  if (ws) {
    try { ws.close(); } catch (_) {}
  }
}
