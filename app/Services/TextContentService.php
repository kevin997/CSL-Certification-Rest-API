<?php

namespace App\Services;

use App\Models\TextContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TextContentService extends ContentService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->activityType = 'text';
        $this->modelClass = TextContent::class;
        
        $this->validationRules = [
            'activity_id' => 'required|integer|exists:activities,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'format' => 'required|string|in:plain,html,markdown',
            'reading_time' => 'nullable|integer|min:1',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required|string',
            'attachments.*.url' => 'required|string',
            'attachments.*.type' => 'required|string',
            'attachments.*.size' => 'nullable|integer',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string',
            'metadata' => 'nullable|array'
        ];
    }
    
    /**
     * Process data before saving to the database
     * Encode arrays to JSON
     *
     * @param array $data
     * @return array
     */
    protected function processDataBeforeSave(array $data): array
    {
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            $data['attachments'] = json_encode($data['attachments']);
        }
        
        if (isset($data['keywords']) && is_array($data['keywords'])) {
            $data['keywords'] = json_encode($data['keywords']);
        }
        
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        // Calculate reading time if not provided
        if (!isset($data['reading_time']) && isset($data['content'])) {
            $data['reading_time'] = $this->calculateReadingTime($data['content']);
        }
        
        return $data;
    }
    
    /**
     * Process data after retrieving from the database
     * Decode JSON to arrays
     *
     * @param Model $model
     * @return Model
     */
    protected function processDataAfterRetrieve(Model $model): Model
    {
        if (isset($model->attachments) && is_string($model->attachments)) {
            $model->attachments = json_decode($model->attachments, true);
        }
        
        if (isset($model->keywords) && is_string($model->keywords)) {
            $model->keywords = json_decode($model->keywords, true);
        }
        
        if (isset($model->metadata) && is_string($model->metadata)) {
            $model->metadata = json_decode($model->metadata, true);
        }
        
        return $model;
    }
    
    /**
     * Get text content by ID with decoded data
     *
     * @param int $id
     * @return Model|null
     */
    public function getTextContent(int $id): ?Model
    {
        $content = $this->getById($id);
        
        if ($content) {
            return $this->processDataAfterRetrieve($content);
        }
        
        return null;
    }
    
    /**
     * Calculate reading time in minutes based on content length
     * Average reading speed is about 200-250 words per minute
     *
     * @param string $content
     * @param int $wordsPerMinute
     * @return int
     */
    protected function calculateReadingTime(string $content, int $wordsPerMinute = 225): int
    {
        // Remove HTML tags if present
        $text = strip_tags($content);
        
        // Count words
        $wordCount = str_word_count($text);
        
        // Calculate reading time in minutes
        $readingTime = ceil($wordCount / $wordsPerMinute);
        
        // Ensure minimum reading time is 1 minute
        return max(1, $readingTime);
    }
    
    /**
     * Add an attachment to text content
     *
     * @param int $id
     * @param array $attachment
     * @return Model|null
     */
    public function addAttachment(int $id, array $attachment): ?Model
    {
        $content = $this->getTextContent($id);
        
        if (!$content) {
            return null;
        }
        
        $attachments = $content->attachments ?? [];
        $attachments[] = $attachment;
        
        return $this->update($id, ['attachments' => $attachments]);
    }
    
    /**
     * Remove an attachment from text content
     *
     * @param int $id
     * @param string $attachmentName
     * @return Model|null
     */
    public function removeAttachment(int $id, string $attachmentName): ?Model
    {
        $content = $this->getTextContent($id);
        
        if (!$content || !isset($content->attachments)) {
            return null;
        }
        
        $attachments = array_filter($content->attachments, function ($attachment) use ($attachmentName) {
            return $attachment['name'] !== $attachmentName;
        });
        
        return $this->update($id, ['attachments' => array_values($attachments)]);
    }
    
    /**
     * Add keywords to text content
     *
     * @param int $id
     * @param array $keywords
     * @return Model|null
     */
    public function addKeywords(int $id, array $keywords): ?Model
    {
        $content = $this->getTextContent($id);
        
        if (!$content) {
            return null;
        }
        
        $existingKeywords = $content->keywords ?? [];
        $newKeywords = array_unique(array_merge($existingKeywords, $keywords));
        
        return $this->update($id, ['keywords' => $newKeywords]);
    }
    
    /**
     * Update metadata for text content
     *
     * @param int $id
     * @param array $metadata
     * @param bool $merge Whether to merge with existing metadata or replace
     * @return Model|null
     */
    public function updateMetadata(int $id, array $metadata, bool $merge = true): ?Model
    {
        $content = $this->getTextContent($id);
        
        if (!$content) {
            return null;
        }
        
        if ($merge && isset($content->metadata)) {
            $metadata = array_merge($content->metadata, $metadata);
        }
        
        return $this->update($id, ['metadata' => $metadata]);
    }
    
    /**
     * Convert content format (plain, html, markdown)
     *
     * @param int $id
     * @param string $targetFormat
     * @return Model|null
     */
    public function convertFormat(int $id, string $targetFormat): ?Model
    {
        $content = $this->getTextContent($id);
        
        if (!$content || !in_array($targetFormat, ['plain', 'html', 'markdown'])) {
            return null;
        }
        
        // If already in target format, return as is
        if ($content->format === $targetFormat) {
            return $content;
        }
        
        $convertedContent = $content->content;
        
        // Convert from current format to target format
        if ($content->format === 'html' && $targetFormat === 'plain') {
            $convertedContent = strip_tags($convertedContent);
        } elseif ($content->format === 'html' && $targetFormat === 'markdown') {
            // HTML to Markdown conversion would require a library
            // For now, just strip tags as a simple conversion
            $convertedContent = strip_tags($convertedContent);
        } elseif ($content->format === 'markdown' && $targetFormat === 'plain') {
            // Simple markdown to plain text conversion
            $convertedContent = preg_replace('/[#*_~`]/', '', $convertedContent);
        } elseif ($content->format === 'markdown' && $targetFormat === 'html') {
            // Simple markdown to HTML conversion for common elements
            $convertedContent = preg_replace('/# (.+)/', '<h1>$1</h1>', $convertedContent);
            $convertedContent = preg_replace('/## (.+)/', '<h2>$1</h2>', $convertedContent);
            $convertedContent = preg_replace('/### (.+)/', '<h3>$1</h3>', $convertedContent);
            $convertedContent = preg_replace('/\*\*(.+)\*\*/', '<strong>$1</strong>', $convertedContent);
            $convertedContent = preg_replace('/\*(.+)\*/', '<em>$1</em>', $convertedContent);
            $convertedContent = preg_replace('/`(.+)`/', '<code>$1</code>', $convertedContent);
            $convertedContent = nl2br($convertedContent);
        } elseif ($content->format === 'plain' && $targetFormat === 'html') {
            $convertedContent = nl2br(htmlspecialchars($convertedContent));
        } elseif ($content->format === 'plain' && $targetFormat === 'markdown') {
            // Plain text to markdown is essentially the same
            $convertedContent = $content->content;
        }
        
        return $this->update($id, [
            'content' => $convertedContent,
            'format' => $targetFormat,
            'reading_time' => $this->calculateReadingTime($convertedContent)
        ]);
    }
    
    /**
     * Search for text content by keywords
     *
     * @param array $keywords
     * @return array
     */
    public function searchByKeywords(array $keywords): array
    {
        $results = [];
        $allContent = TextContent::all();
        
        foreach ($allContent as $content) {
            $contentKeywords = json_decode($content->keywords ?? '[]', true);
            
            if (!empty(array_intersect($keywords, $contentKeywords))) {
                $results[] = $this->processDataAfterRetrieve($content);
            }
        }
        
        return $results;
    }
    
    /**
     * Full-text search in content
     *
     * @param string $searchTerm
     * @return array
     */
    public function searchInContent(string $searchTerm): array
    {
        return TextContent::where('content', 'like', '%' . $searchTerm . '%')
            ->orWhere('title', 'like', '%' . $searchTerm . '%')
            ->get()
            ->map(function ($content) {
                return $this->processDataAfterRetrieve($content);
            })
            ->toArray();
    }
}
