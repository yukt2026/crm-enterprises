<?php

namespace App\Domains\WhatsApp\AI\Knowledge;

use App\Domains\WhatsApp\AI\Providers\AiProviderManager;
use App\Domains\WhatsApp\Models\WhatsAppKnowledgeBase;
use App\Domains\WhatsApp\Models\WhatsAppKnowledgeChunk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseService
{
    protected AiProviderManager $providerManager;

    public function __construct(AiProviderManager $providerManager)
    {
        $this->providerManager = $providerManager;
    }

    /**
     * Ingest manual text or FAQs into Knowledge Base.
     */
    public function ingestText(
        int $tenantId,
        string $title,
        string $content,
        string $category = 'faqs',
        ?string $description = null
    ): WhatsAppKnowledgeBase {
        $kb = WhatsAppKnowledgeBase::create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'source_type' => 'manual_text',
            'status' => 'indexing',
        ]);

        $this->indexDocumentContent($kb, $content);
        return $kb;
    }

    /**
     * Ingest uploaded file (PDF, TXT, CSV, DOCX).
     */
    public function ingestFile(
        int $tenantId,
        UploadedFile $file,
        string $title,
        string $category = 'policies',
        ?string $description = null
    ): WhatsAppKnowledgeBase {
        $ext = strtolower($file->getClientOriginalExtension());
        $storedPath = $file->store("tenants/{$tenantId}/knowledge", 'local');

        $kb = WhatsAppKnowledgeBase::create([
            'tenant_id' => $tenantId,
            'title' => $title ?: $file->getClientOriginalName(),
            'description' => $description,
            'category' => $category,
            'source_type' => 'file',
            'file_path' => $storedPath,
            'file_type' => $ext,
            'file_size_bytes' => $file->getSize(),
            'status' => 'indexing',
        ]);

        try {
            $extractedText = $this->extractTextFromFile($storedPath, $ext);
            $this->indexDocumentContent($kb, $extractedText);
        } catch (\Exception $e) {
            $kb->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::error("Failed to index knowledge base file [{$kb->id}]: " . $e->getMessage());
        }

        return $kb;
    }

    /**
     * Extract text from various file types.
     */
    public function extractTextFromFile(string $storagePath, string $ext): string
    {
        $fullPath = Storage::disk('local')->path($storagePath);
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Stored file not found: {$storagePath}");
        }

        if (in_array($ext, ['txt', 'csv', 'json', 'md', 'html'])) {
            return file_get_contents($fullPath);
        }

        if ($ext === 'pdf') {
            // Read PDF stream and extract readable text stream
            $content = file_get_contents($fullPath);
            // Basic raw stream text extraction from PDF
            preg_match_all('/BT[\s\S]*?ET/', $content, $matches);
            $extracted = '';
            if (!empty($matches[0])) {
                foreach ($matches[0] as $block) {
                    preg_match_all('/\((.*?)\)[\s]*T[jJ]/', $block, $tMatches);
                    if (!empty($tMatches[1])) {
                        $extracted .= implode(' ', $tMatches[1]) . "\n";
                    }
                }
            }

            if (empty(trim($extracted))) {
                // Strip raw PDF tags
                $extracted = preg_replace('/[^a-zA-Z0-9\s.,;:?!\-\/]/', ' ', $content);
                $extracted = preg_replace('/\s+/', ' ', $extracted);
            }

            return trim($extracted) ?: "Indexed PDF Document Content";
        }

        if ($ext === 'docx') {
            // Basic docx zip xml extractor
            $zip = new \ZipArchive();
            if ($zip->open($fullPath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    return strip_tags(str_replace('<w:p', "\n<w:p", $xml));
                }
            }
        }

        return file_get_contents($fullPath);
    }

    /**
     * Split document into overlapping chunks and compute embeddings.
     */
    public function indexDocumentContent(WhatsAppKnowledgeBase $kb, string $text): void
    {
        $chunks = $this->chunkText($text, 450, 50);
        $provider = $this->providerManager->resolveProvider('gemini', $kb->tenant_id);

        DB::transaction(function () use ($kb, $chunks, $provider) {
            // Remove any old chunks for versioning/reindexing
            WhatsAppKnowledgeChunk::where('knowledge_base_id', $kb->id)->delete();

            $chunkRecords = [];
            foreach ($chunks as $idx => $chunkText) {
                $embedding = $provider->generateEmbedding($chunkText);
                $tokenEstimate = (int) (strlen($chunkText) / 4);

                WhatsAppKnowledgeChunk::create([
                    'tenant_id' => $kb->tenant_id,
                    'knowledge_base_id' => $kb->id,
                    'chunk_index' => $idx + 1,
                    'content' => $chunkText,
                    'embedding' => $embedding,
                    'source_citation' => "{$kb->title} (Section #" . ($idx + 1) . ")",
                    'token_count' => $tokenEstimate,
                ]);
            }

            $kb->update([
                'status' => 'ready',
                'total_chunks' => count($chunks),
                'version' => $kb->version + 1,
                'error_message' => null,
            ]);
        });
    }

    /**
     * Chunk text with target size and overlap.
     */
    protected function chunkText(string $text, int $chunkSize = 450, int $overlap = 50): array
    {
        $clean = preg_replace('/\s+/', ' ', trim($text));
        if (mb_strlen($clean) <= $chunkSize) {
            return [$clean];
        }

        $chunks = [];
        $start = 0;
        $totalLen = mb_strlen($clean);

        while ($start < $totalLen) {
            $end = min($start + $chunkSize, $totalLen);
            // Avoid breaking middle of sentence if punctuation is nearby
            if ($end < $totalLen) {
                $lastDot = mb_strrpos(mb_substr($clean, $start, $chunkSize), '.');
                $lastQuestion = mb_strrpos(mb_substr($clean, $start, $chunkSize), '?');
                $lastBreak = max($lastDot ?: -1, $lastQuestion ?: -1);
                if ($lastBreak > (int)($chunkSize * 0.7)) {
                    $end = $start + $lastBreak + 1;
                }
            }

            $chunk = trim(mb_substr($clean, $start, $end - $start));
            if (!empty($chunk)) {
                $chunks[] = $chunk;
            }

            $start += ($chunkSize - $overlap);
            if ($start >= $totalLen) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Retrieve top relevant knowledge chunks for a query strictly bounded to tenant_id.
     *
     * @param int $tenantId
     * @param string $query
     * @param int $topK
     * @return array List of scored chunks with citations
     */
    public function searchKnowledge(int $tenantId, string $query, int $topK = 3): array
    {
        $provider = $this->providerManager->resolveProvider('gemini', $tenantId);
        $queryVector = $provider->generateEmbedding($query);

        // Fetch all indexed chunks for this tenant
        $chunks = WhatsAppKnowledgeChunk::where('tenant_id', $tenantId)
            ->whereHas('knowledgeBase', function ($q) {
                $q->where('status', 'ready');
            })
            ->get();

        if ($chunks->isEmpty()) {
            return [];
        }

        $scoredChunks = [];
        $queryTerms = preg_split('/\s+/', mb_strtolower($query));

        foreach ($chunks as $chunk) {
            $chunkVector = $chunk->embedding;
            $cosineScore = 0.0;

            if (is_array($chunkVector) && !empty($chunkVector) && !empty($queryVector)) {
                $cosineScore = $this->cosineSimilarity($queryVector, $chunkVector);
            }

            // Keyword match boost
            $lowerContent = mb_strtolower($chunk->content);
            $keywordMatches = 0;
            foreach ($queryTerms as $term) {
                if (mb_strlen($term) > 2 && str_contains($lowerContent, $term)) {
                    $keywordMatches++;
                }
            }

            $keywordScore = !empty($queryTerms) ? ($keywordMatches / count($queryTerms)) : 0.0;
            $hybridScore = (0.75 * $cosineScore) + (0.25 * $keywordScore);

            $scoredChunks[] = [
                'id' => $chunk->id,
                'knowledge_base_id' => $chunk->knowledge_base_id,
                'content' => $chunk->content,
                'source_citation' => $chunk->source_citation,
                'score' => round($hybridScore, 4),
                'cosine_score' => round($cosineScore, 4),
            ];
        }

        // Sort descending by hybrid score
        usort($scoredChunks, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scoredChunks, 0, $topK);
    }

    /**
     * Calculate Cosine Similarity between two vectors.
     */
    protected function cosineSimilarity(array $vecA, array $vecB): float
    {
        $minLen = min(count($vecA), count($vecB));
        if ($minLen === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $minLen; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        $denominator = sqrt($normA) * sqrt($normB);
        if ($denominator <= 0.000001) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dotProduct / $denominator));
    }
}
