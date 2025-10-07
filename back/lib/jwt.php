<?php
// /aula/backend/lib/jwt.php
require_once __DIR__ . '/env.php';

function base64url_encode($data){ return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
function base64url_decode($data){ return base64_decode(strtr($data, '-_', '+/')); }

/** Crea token JWT HS256 */
function jwt_create(array $payload, int $expSeconds = JWT_EXP_SECONDS): string {
  $header = ['alg'=>'HS256','typ'=>'JWT'];
  $now = time();
  $payload = array_merge([
    'iss' => JWT_ISS,
    'iat' => $now,
    'exp' => $now + $expSeconds,
  ], $payload);

  $h = base64url_encode(json_encode($header));
  $p = base64url_encode(json_encode($payload));
  $s = base64url_encode(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
  return "$h.$p.$s";
}

function jwt_verify(string $token): ?array {
  $parts = explode('.', $token);
  if (count($parts) !== 3) return null;
  [$h,$p,$s] = $parts;
  $sig = base64url_encode(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
  if (!hash_equals($sig, $s)) return null;
  $payload = json_decode(base64url_decode($p), true);
  if (!is_array($payload)) return null;
  if (isset($payload['exp']) && time() >= (int)$payload['exp']) return null;
  return $payload;
}
