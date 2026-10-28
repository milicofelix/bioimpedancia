<?php

namespace App\Services\Bioimpedance;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class BioimpedanceJpegInspector
{
    public function contents(UploadedFile $image): string
    {
        $contents = $image->get();
        if ($contents === false || $contents === '') {
            throw new RuntimeException('Não foi possível ler a imagem enviada.');
        }

        $actualSize = strlen($contents);
        if ($actualSize > 10 * 1024 * 1024) {
            throw new RuntimeException('O tamanho real da imagem deve ser de no máximo 10 MB.');
        }

        if (! in_array(mb_strtolower($image->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            throw new RuntimeException('A extensão do arquivo deve ser JPEG ou JPG.');
        }

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $dimensions = @getimagesizefromstring($contents);
        if ($detectedMime !== 'image/jpeg' || ! is_array($dimensions) || ($dimensions[2] ?? null) !== IMAGETYPE_JPEG) {
            throw new RuntimeException('O conteúdo do arquivo não corresponde a uma imagem JPEG válida.');
        }

        [$width, $height] = $dimensions;
        if ($width < 200 || $height < 400 || $width > 10000 || $height > 20000) {
            throw new RuntimeException('As dimensões reais da imagem são incompatíveis com o relatório.');
        }

        if (! str_starts_with($contents, "\xFF\xD8")) {
            throw new RuntimeException('A assinatura interna do arquivo JPEG é inválida.');
        }

        $endMarker = strrpos($contents, "\xFF\xD9");
        if ($endMarker === false || trim(substr($contents, $endMarker + 2), "\x00\x09\x0A\x0D\x20") !== '') {
            throw new RuntimeException('O arquivo contém conteúdo adicional incompatível após a imagem JPEG.');
        }

        $lowerContents = strtolower($contents);
        foreach (['<?php', '<?=', '<script', '#!/bin/'] as $executableMarker) {
            if (str_contains($lowerContents, $executableMarker)) {
                throw new RuntimeException('O arquivo contém conteúdo executável e foi rejeitado por segurança.');
            }
        }

        $binarySignatures = ["\x7FELF", "PK\x03\x04", '%PDF-', "\xCA\xFE\xBA\xBE"];
        if (collect($binarySignatures)->contains(fn (string $signature) => str_contains($contents, $signature))
            || (str_contains($contents, 'MZ') && str_contains($contents, "PE\x00\x00"))) {
            throw new RuntimeException('O arquivo contém uma assinatura binária incompatível e foi rejeitado por segurança.');
        }

        return $contents;
    }
}
