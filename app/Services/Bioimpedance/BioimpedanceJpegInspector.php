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

        $this->assertSafeMetadata($contents);

        return $contents;
    }

    private function assertSafeMetadata(string $contents): void
    {
        foreach ($this->metadataSegments($contents) as $segment) {
            $payload = $segment['payload'];
            $lowerPayload = strtolower($payload);

            $containsExecutableText = $segment['marker'] === 0xFE
                ? collect(['<?php', '<?=', '<script', '#!/bin/'])
                    ->contains(fn (string $marker) => str_contains($lowerPayload, $marker))
                : collect(['<?php', '<?=', '<script', '#!/bin/'])
                    ->contains(fn (string $marker) => str_starts_with(ltrim($lowerPayload), $marker));

            if ($containsExecutableText) {
                throw new RuntimeException('O arquivo contém conteúdo executável e foi rejeitado por segurança.');
            }

            $trimmedPayload = ltrim($payload);
            $hasBinarySignature = collect(["\x7FELF", "PK\x03\x04", '%PDF-', "\xCA\xFE\xBA\xBE"])
                ->contains(fn (string $signature) => str_starts_with($trimmedPayload, $signature));
            $hasWindowsExecutable = str_starts_with($trimmedPayload, 'MZ')
                && str_contains($trimmedPayload, "PE\x00\x00");

            if ($hasBinarySignature || $hasWindowsExecutable) {
                throw new RuntimeException('O arquivo contém uma assinatura binária incompatível e foi rejeitado por segurança.');
            }
        }
    }

    /**
     * Retorna somente metadados anteriores ao início dos dados comprimidos.
     * Bytes do fluxo de imagem podem coincidir casualmente com texto ou assinaturas
     * executáveis e, portanto, nunca devem ser interpretados como conteúdo anexado.
     *
     * @return array<int, array{marker: int, payload: string}>
     */
    private function metadataSegments(string $contents): array
    {
        $segments = [];
        $offset = 2;
        $size = strlen($contents);

        while ($offset < $size) {
            if (ord($contents[$offset]) !== 0xFF) {
                throw new RuntimeException('A estrutura interna do arquivo JPEG é inválida.');
            }

            while ($offset < $size && ord($contents[$offset]) === 0xFF) {
                $offset++;
            }

            if ($offset >= $size) {
                throw new RuntimeException('A estrutura interna do arquivo JPEG está incompleta.');
            }

            $marker = ord($contents[$offset]);
            $offset++;

            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }

            if ($marker === 0xD8 || $marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                continue;
            }

            if ($offset + 2 > $size) {
                throw new RuntimeException('A estrutura interna do arquivo JPEG está incompleta.');
            }

            $length = unpack('n', substr($contents, $offset, 2))[1];
            if ($length < 2 || $offset + $length > $size) {
                throw new RuntimeException('A estrutura interna do arquivo JPEG contém um segmento inválido.');
            }

            if (($marker >= 0xE0 && $marker <= 0xEF) || $marker === 0xFE) {
                $segments[] = [
                    'marker' => $marker,
                    'payload' => substr($contents, $offset + 2, $length - 2),
                ];
            }

            $offset += $length;
        }

        return $segments;
    }
}
