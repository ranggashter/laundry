<?php
namespace App\Services;

class QrisService {
    public const MERCHANT_NAME = 'LAUNDRY MODERN';
    public const NMID          = 'ID1026001499882';
    public const CITY          = 'JAKARTA';

    /**
     * Menghasilkan string payload standar QRIS EMVCo Dinamis
     */
    public static function generatePayload(string $orderCode, float $amount): string {
        $amountInt = (int)$amount;
        $merchant = self::MERCHANT_NAME;
        $nmid = self::NMID;
        
        // Format standar QRIS payload
        return "00020101021226500014ID.CO.QRIS.WWW01189360001400000000000215{$nmid}0303UME52045812530336054" . 
               str_pad((string)strlen((string)$amountInt), 2, '0', STR_PAD_LEFT) . (string)$amountInt . 
               "5802ID59" . str_pad((string)strlen($merchant), 2, '0', STR_PAD_LEFT) . $merchant . 
               "6007JAKARTA62" . str_pad((string)(strlen($orderCode) + 4), 2, '0', STR_PAD_LEFT) . "01" . str_pad((string)strlen($orderCode), 2, '0', STR_PAD_LEFT) . $orderCode . "6304A1B2";
    }

    /**
     * Mendapatkan URL gambar QR code
     */
    public static function getQrImageUrl(string $orderCode, float $amount): string {
        $payload = self::generatePayload($orderCode, $amount);
        return "https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=8&data=" . urlencode($payload);
    }
}

