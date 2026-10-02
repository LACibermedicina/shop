<?php
/**
 * Cliente HTTP para o microsserviço Python (IA de triagem, PDFs, webhooks, LiveKit).
 * Comunicação interno-servidor autenticada por token de serviço (header X-Service-Token).
 * Suporta cURL (preferencial) e fallback para stream contexts (hospedagens sem cURL).
 */
declare(strict_types=1);

class IntegrationClient
{
    private static function token(): string { return env('PY_SERVICE_TOKEN', 'dev-token-troque'); }

    public static function post(string $path, array $payload): array
    {
        $url  = rtrim(env('PY_SERVICE_URL', 'http://127.0.0.1:8001'), '/') . $path;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json', 'X-Service-Token: ' . self::token()];

        // Caminho 1: cURL (preferencial)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($err || $code >= 400) {
                return ['ok' => false, 'error' => ($err ?: "HTTP $code"), 'http_code' => $code];
            }
        } else {
            // Caminho 2: stream contexts (hospedagens sem a extensão cURL)
            $ctx = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 30,
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($url, false, $ctx);
            $code = 0;
            foreach (($http_response_header ?? []) as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
            }
            if ($resp === false || $code >= 400) {
                return ['ok' => false, 'error' => ($resp === false ? 'sem resposta' : "HTTP $code"), 'http_code' => $code];
            }
        }

        $data = json_decode((string)$resp, true);
        return ['ok' => true, 'data' => is_array($data) ? $data : []];
    }

    /** Roteia chamadas de IA/PDF/livekit; devolve payload de erro padronizado em falha. */
    public static function postOrError(string $path, array $payload, string $errorStatus): array
    {
        $res = self::post($path, $payload);
        if (!$res['ok']) {
            http_response_code(502);
            return ['error' => 'Serviço de IA/Documentos indisponível', 'detail' => $res['error'], 'status' => $errorStatus];
        }
        return $res['data'];
    }
}
