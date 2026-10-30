<?php

namespace App\Services;

use App\Models\MaterialLot;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeLabelService
{
    /**
     * Generate standard Code 128 barcode as SVG.
     */
    public function generateBarcodeSvg(string $code, int $width = 2, int $height = 40): string
    {
        $generator = new BarcodeGeneratorSVG;

        return $generator->getBarcode($code, $generator::TYPE_CODE_128, $width, $height);
    }

    /**
     * Generate QR Code as SVG vector.
     */
    public function generateQrCodeSvg(string $payload, int $size = 150): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        return $writer->writeString($payload);
    }

    /**
     * Generate GS1-128 formatted string:
     * (01) GTIN / Part SKU
     * (10) Lot Number
     * (17) Expiry Date YYMMDD
     * (3102) Net weight in kg (e.g. 002500 for 25.00kg)
     */
    public function formatGs1String(MaterialLot $lot, float $netWeightKg = 25.00): string
    {
        $expiry = $lot->expiry_date ? $lot->expiry_date->format('ymd') : '991231';
        $weightInt = str_pad((string) (int) ($netWeightKg * 100), 6, '0', STR_PAD_LEFT);

        return sprintf(
            '(01)%s(10)%s(17)%s(3102)%s',
            $lot->item->sku,
            $lot->lot_number,
            $expiry,
            $weightInt
        );
    }

    /**
     * Generate raw ZPL II code streamable to thermal network printers (port 9100).
     */
    public function generateZplLabel(MaterialLot $lot, string $binCode, float $weightKg = 25.0): string
    {
        $sku = $lot->item->sku;
        $name = substr($lot->item->name, 0, 30);
        $lotNo = $lot->lot_number;
        $heat = $lot->vendor_heat_number ?? 'N/A';
        $exp = $lot->expiry_date ? $lot->expiry_date->format('Y-m-d') : 'NONE';

        return <<<ZPL
^XA
^PW800
^LL600
^FO50,40^A0N,40,40^FDPT. SMART WMS - RAW MATERIAL^FS
^FO50,90^GB700,3,3^FS
^FO50,110^A0N,30,30^FDSKU: {$sku}^FS
^FO50,145^A0N,25,25^FDDESC: {$name}^FS
^FO50,180^A0N,25,25^FDLOT: {$lotNo}^FS
^FO50,215^A0N,25,25^FDHEAT/BATCH: {$heat}^FS
^FO50,250^A0N,25,25^FDEXPIRY: {$exp} | NET: {$weightKg} KG^FS
^FO50,285^A0N,30,30^FDBIN: {$binCode}^FS
^FO50,330^BY3,3,80^BCN,80,Y,N,N^FD{$lotNo}^FS
^FO550,330^BQN,2,6^FDQA,{$lotNo}^FS
^XZ
ZPL;
    }
}
