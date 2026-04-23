import http from 'k6/http';
import { sleep, check } from 'k6';

// binokl.az-a daxil olduqdan sonra brauzerden kopyala
const SESSION = 'SENIN_BINOKL_SESSION_COOKIE';

export let options = {
  stages: [
    { duration: '1m', target: 20 },   // 20 usera qədər artır
    { duration: '2m', target: 20 },   // 2 dəq saxla
    { duration: '1m', target: 50 },   // 50-yə qədər artır
    { duration: '2m', target: 50 },   // 2 dəq saxla
    { duration: '1m', target: 0 },    // endir
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // 95%-i 2 saniyədən az olmalı
    http_req_failed:   ['rate<0.05'],  // xəta 5%-dən az olmalı
  },
};

export default function () {
  const headers = {
    Cookie: `binokl-session=${SESSION}`,
  };

  const pages = [
    'https://binokl.az/properties',
    'https://binokl.az/customers',
    'https://binokl.az/settings',
  ];

  const url = pages[Math.floor(Math.random() * pages.length)];
  const res = http.get(url, { headers });

  check(res, {
    'status 200': (r) => r.status === 200,
    'cavab < 1s':  (r) => r.timings.duration < 1000,
  });

  sleep(Math.random() * 3 + 1);
}
