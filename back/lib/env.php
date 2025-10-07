<?php
// /aula/backend/lib/env.php
const APP_TZ = 'America/Lima';
const JWT_SECRET = 'pF7lYxP0Jhrn/64Zqv4ssFmrYyYzF7rA2WfXwE3kQK6nD9nbvlWcxu+YbVj2n8HsH7MgqP5s2CwD9Z7XKz5J3g==';
const JWT_ISS = 'sanignaciodeloyolaperu.com';
const JWT_EXP_SECONDS = 60*60*8;

// 👇 Lista blanca de orígenes permitidos
const ALLOWED_ORIGINS = [
  'https://sanignaciodeloyolaperu.com', // producción
  'http://localhost:5173',              // Vite dev
  'http://127.0.0.1:5173',
];

date_default_timezone_set(APP_TZ);
