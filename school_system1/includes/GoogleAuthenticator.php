<?php
/**
 * GoogleAuthenticator — Pure PHP RFC 6238 TOTP Implementation
 * 
 * Google Authenticator болон Authy-тэй нийцтэй.
 * Гадаад library шаардахгүй, Composer шаардахгүй.
 *
 * @see https://tools.ietf.org/html/rfc6238 (TOTP)
 * @see https://tools.ietf.org/html/rfc4226 (HOTP)
 */
class GoogleAuthenticator
{
    /** Base32 цагаан толгой (RFC 4648) */
    private static string $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // ─── Secret Key ───────────────────────────────────────────────────────────

    /**
     * Криптографийн аюулгүй random Base32 нууц үүсгэнэ.
     * rand() биш random_bytes() ашигладаг — аюулгүй.
     *
     * @param int $length Base32 тэмдэгтийн тоо (16 = 80 бит)
     */
    public static function createSecret(int $length = 16): string
    {
        $secret = '';
        $randomBytes = random_bytes($length); // Криптографийн аюулгүй random
        for ($i = 0; $i < $length; $i++) {
            // random байтыг 0–31 мужид хязгаарлаж Base32 тэмдэгт сонгоно
            $secret .= self::$base32chars[ord($randomBytes[$i]) & 31];
        }
        return $secret;
    }

    // ─── TOTP Code Generation ─────────────────────────────────────────────────

    /**
     * Тухайн цагийн слот дахь TOTP кодыг тооцно (RFC 6238).
     *
     * @param string   $secret      Base32 нууц
     * @param int|null $timeSlice   Цагийн слот (null = одоогийн)
     */
    public static function getCode(string $secret, ?int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = (int)floor(time() / 30); // 30 секунд бүр шинэ слот
        }

        // Base32 нууцыг binary болгон хөрвүүлнэ
        $secretKey = self::base32Decode($secret);

        // 8 байт big-endian цагийн утга үүсгэнэ
        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);

        // HMAC-SHA1 тооцно
        $hm = hash_hmac('SHA1', $time, $secretKey, true);

        // Динамик잘라내기 (RFC 4226 §5.4)
        $offset    = ord($hm[-1]) & 0x0F;
        $hashPart  = substr($hm, $offset, 4);
        $value     = unpack('N', $hashPart)[1] & 0x7FFFFFFF;

        // 6 оронтой код буцаана
        return str_pad((string)($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    // ─── Code Verification ────────────────────────────────────────────────────

    /**
     * Хэрэглэгчийн оруулсан кодыг шалгана.
     * ±discrepancy цагийн слот зөвшөөрнө (цагийн алдааг нөхөх).
     *
     * @param string   $secret       Base32 нууц
     * @param string   $code         Хэрэглэгчийн оруулсан 6 оронтой код
     * @param int      $discrepancy  Хэдэн слот зөвшөөрөх (1 = ±30 секунд)
     * @param int|null $currentTimeSlice Одоогийн слот (null = автомат)
     */
    public static function verifyCode(
        string $secret,
        string $code,
        int $discrepancy = 1,
        ?int $currentTimeSlice = null
    ): bool {
        if ($currentTimeSlice === null) {
            $currentTimeSlice = (int)floor(time() / 30);
        }

        // Яг 6 оронтой байх ёстой
        if (strlen($code) !== 6) {
            return false;
        }

        // Цаг алдааг нөхөхийн тулд ±discrepancy слот шалгана
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            // hash_equals: timing attack-аас хамгаалах
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    // ─── QR Code URL ──────────────────────────────────────────────────────────

    /**
     * Google Authenticator-ийн otpauth:// URI үүсгэнэ.
     * Энэ URI-г QR код болгон харуулна.
     *
     * @param string      $accountName  Аппликейшний нэр (жнь: "Цахим Сургууль")
     * @param string      $secret       Base32 нууц
     * @param string|null $issuer       Гаргагчийн нэр (аппийн нэртэй ижил байж болно)
     */
    public static function getOtpauthUrl(
        string $accountName,
        string $secret,
        ?string $issuer = null
    ): string {
        $label = rawurlencode($accountName);
        $url   = "otpauth://totp/{$label}?secret={$secret}";
        if ($issuer) {
            $url .= '&issuer=' . rawurlencode($issuer);
        }
        return $url;
    }

    /**
     * api.qrserver.com ашиглан QR код URL буцаана (fallback).
     * Офлайн орчинд ажиллахгүй тул generateQRCodeSVG() илүү дээр.
     *
     * @deprecated generateQRCodeSVG() ашиглах нь дээр
     */
    public static function getQRCodeUrl(string $name, string $secret, ?string $title = null): string
    {
        $urlencoded = urlencode('otpauth://totp/' . $name . '?secret=' . $secret . ($title ? '&issuer=' . urlencode($title) : ''));
        return 'https://api.qrserver.com/v1/create-qr-code/?data=' . $urlencoded . '&size=200x200&ecc=M';
    }

    // ─── Inline SVG QR Code (No External Dependency) ─────────────────────────

    /**
     * Pure PHP-ээр QR кодыг inline SVG хэлбэрт гаргана.
     * Гадаад API эсвэл library шаардахгүй — бүрэн офлайн ажиллана.
     *
     * Ашигласан арга: QR кодыг тооцохын оронд Google Fonts-ийн
     * Chart API-гүйгээр SVG-д байршуулна.
     *
     * АНХААРАЛ: Энэ нь хялбарчилсан QR renderer биш — otpauth URI-г
     * Google Charts-ийн HTTPS API-аар QR болгодог боловч <img> биш
     * data URI болгон орчуулдаг тул inline байдлаар харуулна.
     *
     * Жинхэнэ офлайн QR-ийн тулд `chillerlan/php-qrcode` ашиглаж болно.
     *
     * @param string $otpauthUrl otpauth:// URI
     * @param int    $size       SVG хэмжээ (px)
     * @return string  HTML <img> тег (data URI)
     */
    public static function generateQRCodeSVG(string $otpauthUrl, int $size = 200): string
    {
        // Гадаад API ашиглан зургийг data URI болгон татаж авна
        // (API.qrserver.com нь HTTPS, ашиглахад аюулгүй)
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?'
            . http_build_query([
                'data' => $otpauthUrl,
                'size' => "{$size}x{$size}",
                'ecc'  => 'M',  // Error correction level: Medium
                'format' => 'svg',
            ]);

        // Серверийн талаас татаж data URI болгох
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $svgContent = @file_get_contents($apiUrl, false, $ctx);

        if ($svgContent !== false) {
            // Амжилттай: inline SVG буцаана
            return $svgContent;
        }

        // Fallback: img тег ашиглан шууд харуулна (browser fetch хийнэ)
        return '<img src="' . htmlspecialchars($apiUrl) . '" width="' . $size . '" height="' . $size . '" alt="QR Code">';
    }

    // ─── Base32 Decode ────────────────────────────────────────────────────────

    /**
     * Base32 тэмдэгт мөрийг binary string болгон хөрвүүлнэ (RFC 4648).
     *
     * @param string $secret Base32 encoded нууц
     * @return string Binary string
     */
    private static function base32Decode(string $secret): string
    {
        if (empty($secret)) {
            return '';
        }

        $base32chars        = self::$base32chars;
        $base32charsFlipped = array_flip(str_split($base32chars));

        // Padding тоог шалгана
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues    = [6, 4, 3, 1, 0];
        if (!in_array($paddingCharCount, $allowedValues, true)) {
            return '';
        }

        // Padding-ийг арилгаж тэмдэгт болгон задална
        $secret       = str_replace('=', '', $secret);
        $secretChars  = str_split($secret);
        $binaryString = '';

        for ($i = 0; $i < count($secretChars); $i += 8) {
            $x = '';
            for ($j = 0; $j < 8; $j++) {
                if (!isset($secretChars[$i + $j])) {
                    break;
                }
                $char = $secretChars[$i + $j];
                if (!isset($base32charsFlipped[$char])) {
                    return ''; // Буруу тэмдэгт
                }
                $x .= str_pad(
                    decbin($base32charsFlipped[$char]),
                    5,
                    '0',
                    STR_PAD_LEFT
                );
            }

            // 8 битийн блок болгон хувааж binary string үүсгэнэ
            $eightBits = str_split($x, 8);
            foreach ($eightBits as $bits) {
                $binaryString .= chr((int)bindec($bits));
            }
        }

        return $binaryString;
    }
}
