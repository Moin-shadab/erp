<?php

namespace App\Services\Email;

class MimeParser
{
    // Charset aliases covering 60+ encodings
    private static array $CS_ALIASES = [
        'UTF8'=>'UTF-8','UTF-8-BOM'=>'UTF-8',
        'LATIN1'=>'ISO-8859-1','LATIN2'=>'ISO-8859-2','LATIN3'=>'ISO-8859-3',
        'LATIN4'=>'ISO-8859-4','LATIN5'=>'ISO-8859-9','LATIN6'=>'ISO-8859-10',
        'LATIN7'=>'ISO-8859-13','LATIN8'=>'ISO-8859-14','LATIN9'=>'ISO-8859-15',
        'WIN1250'=>'Windows-1250','WIN1251'=>'Windows-1251','WIN1252'=>'Windows-1252',
        'WIN1253'=>'Windows-1253','WIN1254'=>'Windows-1254','WIN1255'=>'Windows-1255',
        'WIN1256'=>'Windows-1256','WIN1257'=>'Windows-1257','WIN1258'=>'Windows-1258',
        'CP1250'=>'Windows-1250','CP1251'=>'Windows-1251','CP1252'=>'Windows-1252',
        'CP1253'=>'Windows-1253','CP1254'=>'Windows-1254','CP1255'=>'Windows-1255',
        'CP1256'=>'Windows-1256','CP1257'=>'Windows-1257','CP1258'=>'Windows-1258',
        'CP932'=>'Shift_JIS','CP936'=>'GBK','CP949'=>'EUC-KR','CP950'=>'Big5',
        'KSC5601'=>'EUC-KR','KS_C_5601-1987'=>'EUC-KR','KS_C_5601-1989'=>'EUC-KR',
        'X-SJIS'=>'Shift_JIS','SJIS'=>'Shift_JIS','SHIFT-JIS'=>'Shift_JIS',
        'ISO-2022-JP'=>'ISO-2022-JP','ISO-2022-KR'=>'ISO-2022-KR','ISO-2022-CN'=>'ISO-2022-CN',
        'GB_2312-80'=>'GB2312','CSGB2312'=>'GB2312','GB2312'=>'GB2312','GBK'=>'GBK','GB18030'=>'GB18030',
        'BIG5'=>'Big5','BIG-5'=>'Big5','BIG5-HKSCS'=>'Big5-HKSCS',
        'TIS-620'=>'TIS-620','ISO-8859-11'=>'TIS-620',
        'KOI8-R'=>'KOI8-R','KOI8-U'=>'KOI8-U','KOI8R'=>'KOI8-R',
        'VISCII'=>'VISCII','VIQR'=>'VIQR',
        'ISO-8859-6'=>'ISO-8859-6','ISO-8859-8'=>'ISO-8859-8',
        'WINDOWS-874'=>'TIS-620','X-WINDOWS-874'=>'TIS-620',
        'MACINTOSH'=>'MacRoman','MAC'=>'MacRoman','X-MAC-ROMAN'=>'MacRoman',
        'US-ASCII'=>'ASCII','ASCII'=>'ASCII','USASCII'=>'ASCII',
    ];

    /**
     * Convert encoding of string safely to UTF-8
     */
    public static function toUtf8(string $text, string $charset = 'UTF-8'): string
    {
        $cs = strtoupper(trim($charset));
        $cs = self::$CS_ALIASES[$cs] ?? $cs;

        if ($cs === 'UTF-8' || $cs === '') {
            // Strip BOM if present
            if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
                $text = substr($text, 3);
            }
            return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $result = @iconv($cs, 'UTF-8//TRANSLIT//IGNORE', $text);
        if ($result === false || $result === '') {
            $result = @mb_convert_encoding($text, 'UTF-8', $cs);
        }
        $utf8Str = $result ?: $text;
        return mb_convert_encoding($utf8Str, 'UTF-8', 'UTF-8');
    }

    /**
     * Decode RFC 2047 encoded-words (=?charset?B/Q?...?=)
     */
    public static function decodeHeader(?string $s): string
    {
        if (!$s) return '';
        // Unfold
        $s = preg_replace('/\r?\n\s+/', ' ', $s);
        // Merge adjacent encoded-words
        $s = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $s);

        $out = preg_replace_callback('/=\?([^?]+)\?([BQbq])\?([^?]*)\?=/', function ($m) {
            $cs   = $m[1];
            $enc  = strtoupper($m[2]);
            $data = $m[3];
            if ($enc === 'B') {
                $raw = base64_decode($data);
            } else {
                $raw = quoted_printable_decode(str_replace('_', ' ', $data));
            }
            return self::toUtf8($raw, $cs);
        }, $s);

        if ($out === $s) {
            // No encoded words — detect charset
            if (!mb_check_encoding($s, 'UTF-8')) {
                $enc = mb_detect_encoding($s, ['UTF-8','ISO-8859-1','Windows-1252','KOI8-R'], true);
                if ($enc) $s = mb_convert_encoding($s, 'UTF-8', $enc);
            }
            return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        return mb_convert_encoding($out, 'UTF-8', 'UTF-8');
    }

    /**
     * Parse raw header block into associative array
     */
    public static function parseHeaders(string $raw): array
    {
        // Unfold
        $raw = preg_replace('/\r?\n([ \t])/', ' ', $raw);
        $headers = [];
        foreach (explode("\n", $raw) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $key = strtolower(trim($k));
                $headers[$key] = ($headers[$key] ?? '') . ' ' . trim($v);
            }
        }
        // Trim headers
        foreach ($headers as $k => $v) {
            $headers[$k] = trim($v);
        }
        return $headers;
    }

    /**
     * Parse MIME content-type value + params
     */
    public static function parseContentType(string $ct): array
    {
        $parts  = explode(';', $ct);
        $type   = strtolower(trim(array_shift($parts)));
        $params = [];
        $accumulator = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (preg_match('/^([^*=]+)\*(\d+)\*?=(.*)$/', $p, $m)) {
                $accumulator[$m[1]][(int)$m[2]] = $m[3];
                continue;
            }
            if (str_contains($p, '=')) {
                [$k, $v] = explode('=', $p, 2);
                $v = trim($v, '"');
                if (preg_match("/^([^']*)'[^']*'(.+)$/", $v, $m2)) {
                    $v = rawurldecode($m2[2]);
                    if ($m2[1]) $v = self::toUtf8($v, $m2[1]);
                }
                $params[strtolower(trim($k))] = $v;
            }
        }
        foreach ($accumulator as $k => $segs) {
            ksort($segs);
            $val = implode('', $segs);
            if (preg_match("/^([^']*)'[^']*'(.+)$/", $val, $m)) {
                $val = rawurldecode($m[2]);
                if ($m[1]) $val = self::toUtf8($val, $m[1]);
            } else {
                $val = rawurldecode($val);
            }
            $params[strtolower($k)] = $val;
        }
        return ['type' => $type, 'params' => $params];
    }

    /**
     * Decode transfer encoding
     */
    public static function decodeTransfer(string $data, string $encoding): string
    {
        $enc = strtolower(trim($encoding));
        if ($enc === 'base64') {
            return base64_decode(str_replace(["\r", "\n", " "], '', $data));
        }
        if ($enc === 'quoted-printable') {
            return quoted_printable_decode($data);
        }
        return $data;
    }

    /**
     * Parse date header string
     */
    public static function parseDate(string $d): string
    {
        if (!$d) return '';
        $ts = @strtotime($d);
        if (!$ts) return $d;
        $diff = time() - $ts;
        if ($diff < 3600)   return round($diff / 60) . 'm ago';
        if ($diff < 86400)  return date('H:i', $ts);
        if ($diff < 604800) return date('D H:i', $ts);
        return date('M j, Y', $ts);
    }

    public static function parseDateTs(string $d): int
    {
        return (int)(@strtotime($d) ?: 0);
    }

    public static function extractName(string $from): string
    {
        if (preg_match('/^"?([^"<]+)"?\s*</', $from, $m)) return trim($m[1], ' "');
        if (preg_match('/^([^@<\s]+)/', $from, $m)) return $m[1];
        return $from;
    }

    public static function extractEmail(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) return $m[1];
        if (preg_match('/[\w._%+\-]+@[\w.\-]+\.[a-z]{2,}/i', $from, $m)) return $m[0];
        return $from;
    }

    public static function initial(string $from): string
    {
        $name = self::extractName($from);
        $ch   = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        if (!$ch || !preg_match('/\p{L}/u', $ch)) $ch = '✉';
        return $ch;
    }

    /**
     * Parse raw MIME email string
     */
    public static function parse(string $raw): array
    {
        [$headerRaw, $body] = self::splitHeaderBody($raw);
        $headers            = self::parseHeaders($headerRaw);
        $ct                 = self::parseContentType($headers['content-type'] ?? 'text/plain');
        $te                 = $headers['content-transfer-encoding'] ?? '7bit';
        $parts              = [];

        if (str_starts_with($ct['type'], 'multipart/')) {
            $boundary = $ct['params']['boundary'] ?? '';
            if ($boundary) {
                $subparts = self::splitMultipart($body, $boundary);
                foreach ($subparts as $sp) {
                    $parts[] = self::parse($sp);
                }
            }
        }

        return [
            'headers'  => $headers,
            'type'     => $ct['type'],
            'params'   => $ct['params'],
            'encoding' => strtolower(trim($te)),
            'body'     => $body,
            'parts'    => $parts,
        ];
    }

    private static function splitHeaderBody(string $raw): array
    {
        $pos = strpos($raw, "\r\n\r\n");
        if ($pos !== false) return [substr($raw, 0, $pos), substr($raw, $pos + 4)];
        $pos = strpos($raw, "\n\n");
        if ($pos !== false) return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
        return [$raw, ''];
    }

    private static function splitMultipart(string $body, string $boundary): array
    {
        $parts = [];
        $delim = '--' . $boundary;
        $end   = '--' . $boundary . '--';
        $lines = explode("\n", $body);
        $cur   = null;
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if ($line === $end) {
                if ($cur !== null) $parts[] = implode("\n", $cur);
                break;
            }
            if ($line === $delim) {
                if ($cur !== null) $parts[] = implode("\n", $cur);
                $cur = [];
                continue;
            }
            if ($cur !== null) $cur[] = $line;
        }
        if ($cur !== null && count($cur) > 0) $parts[] = implode("\n", $cur);
        return $parts;
    }

    /**
     * Flatten MIME parts
     */
    public static function flatten(array $part, string $baseUrl = '', int &$partIdx = 0): array
    {
        $result = ['text' => '', 'html' => '', 'attachments' => [], 'inlines' => []];
        self::flattenPart($part, $result, $baseUrl, $partIdx);
        return $result;
    }

    private static function flattenPart(array $part, array &$result, string $baseUrl, int &$partIdx): void
    {
        $type    = $part['type']     ?? 'text/plain';
        $parts   = $part['parts']    ?? [];
        $headers = $part['headers']  ?? [];
        $params  = $part['params']   ?? [];
        $enc     = $part['encoding'] ?? '7bit';
        $body    = $part['body']     ?? '';

        $cd      = $headers['content-disposition'] ?? '';
        $cid     = trim($headers['content-id'] ?? '', ' <>');
        $disp    = strtolower(explode(';', $cd)[0] ?? '');

        if (str_starts_with($type, 'multipart/')) {
            foreach ($parts as $sp) {
                self::flattenPart($sp, $result, $baseUrl, $partIdx);
            }
            return;
        }

        if ($type === 'message/rfc822') {
            foreach ($parts as $sp) {
                self::flattenPart($sp, $result, $baseUrl, $partIdx);
            }
            return;
        }

        $partIdx++;
        $decoded = self::decodeTransfer($body, $enc);
        $filename = self::getFilename($params, $cd);

        // Check if inline image
        if ($cid && str_starts_with($type, 'image/')) {
            $result['inlines'][$cid] = base64_encode($decoded);
            $result['inlines']['type:'.$cid] = $type;
            return;
        }

        // Check if attachment
        if ($disp === 'attachment' || ($filename && !in_array($type, ['text/plain','text/html']))) {
            $result['attachments'][] = [
                'filename' => $filename ?: ('part' . $partIdx . '.' . self::extFromMime($type)),
                'mime'     => $type,
                'size'     => strlen($decoded),
                'data'     => base64_encode($decoded),
                'partIdx'  => $partIdx,
            ];
            return;
        }

        if ($type === 'text/html') {
            $cs = $params['charset'] ?? 'UTF-8';
            $result['html'] = self::toUtf8($decoded, $cs);
        } elseif ($type === 'text/plain' && !$result['html']) {
            $cs = $params['charset'] ?? 'UTF-8';
            $result['text'] = self::toUtf8($decoded, $cs);
        }
    }

    private static function getFilename(array $params, string $cd): string
    {
        if (!empty($params['name'])) return self::decodeHeader($params['name']);
        
        if (preg_match('/filename\*=([^;]+)/i', $cd, $m)) {
            $v = trim($m[1]);
            if (preg_match("/^([^']*)'[^']*'(.+)$/", $v, $m2)) {
                return rawurldecode($m2[2]);
            }
            return rawurldecode($v);
        }
        if (preg_match('/filename="([^"]+)"/i', $cd, $m)) return self::decodeHeader($m[1]);
        if (preg_match('/filename=([^;\s]+)/i', $cd, $m)) return self::decodeHeader($m[1]);
        return '';
    }

    private static function extFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/gif'        => 'gif',
            'image/webp'       => 'webp',
            'application/pdf'  => 'pdf',
            'application/zip'  => 'zip',
            'text/html'        => 'html',
            'text/plain'       => 'txt',
            default            => 'bin',
        };
    }

    /**
     * Replace inline cid references with base64 data URIs
     */
    public static function inlineCids(string $html, array $inlines): string
    {
        foreach ($inlines as $cid => $b64) {
            if (str_starts_with($cid, 'type:')) continue;
            $type = $inlines['type:' . $cid] ?? 'image/jpeg';
            $dataUri = 'data:' . $type . ';base64,' . $b64;
            $html = str_replace('cid:' . $cid, $dataUri, $html);
        }
        return $html;
    }
}
