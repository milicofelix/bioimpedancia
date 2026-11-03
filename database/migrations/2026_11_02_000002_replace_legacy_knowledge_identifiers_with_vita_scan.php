<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DOCUMENT_TITLES = [
        'Protocolo de medição Ricosty' => 'Protocolo de medição Vita Scan',
        'Política de segurança das orientações Ricosty' => 'Política de segurança das orientações Vita Scan',
    ];

    private const CHUNK_KEYS = [
        'ricosty-measurement-protocol' => 'vita-scan-measurement-protocol',
        'ricosty-assistant-safety-policy' => 'vita-scan-assistant-safety-policy',
        'ricosty-care-guidance-safety' => 'vita-scan-care-guidance-safety',
    ];

    public function up(): void
    {
        $this->replace(self::DOCUMENT_TITLES, self::CHUNK_KEYS);
    }

    public function down(): void
    {
        $this->replace(array_flip(self::DOCUMENT_TITLES), array_flip(self::CHUNK_KEYS));
    }

    private function replace(array $documentTitles, array $chunkKeys): void
    {
        DB::transaction(function () use ($documentTitles, $chunkKeys): void {
            foreach ($documentTitles as $current => $replacement) {
                DB::table('bioimpedance_knowledge_documents')
                    ->where('title', $current)
                    ->update(['title' => $replacement, 'updated_at' => now()]);
            }

            foreach ($chunkKeys as $current => $replacement) {
                DB::table('bioimpedance_knowledge_chunks')
                    ->where('chunk_key', $current)
                    ->update(['chunk_key' => $replacement, 'updated_at' => now()]);
            }
        });
    }
};
