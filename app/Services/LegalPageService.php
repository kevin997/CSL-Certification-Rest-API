<?php

namespace App\Services;

use App\Models\LegalPage;
use App\Models\Environment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LegalPageService
{
    /**
     * Get all legal pages for an environment
     *
     * @param int $environmentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPagesForEnvironment(int $environmentId)
    {
        return LegalPage::where('environment_id', $environmentId)
            ->orderBy('page_type')
            ->get();
    }

    /**
     * Get all page types with their status for an environment
     *
     * @param int $environmentId
     * @return array
     */
    public function getPageTypesWithStatus(int $environmentId): array
    {
        $existingPages = LegalPage::where('environment_id', $environmentId)
            ->get()
            ->keyBy('page_type');

        $pageTypes = [];
        foreach (LegalPage::PAGE_TYPES as $type => $name) {
            $page = $existingPages->get($type);
            $pageTypes[] = [
                'type' => $type,
                'name' => $name,
                'description' => LegalPage::PAGE_DESCRIPTIONS[$type] ?? '',
                'is_set' => $page !== null && !empty($page->content),
                'is_published' => $page?->is_published ?? false,
                'page_id' => $page?->id,
                'updated_at' => $page?->updated_at?->toISOString(),
            ];
        }

        return $pageTypes;
    }

    /**
     * Get a legal page by ID
     *
     * @param int $id
     * @return LegalPage|null
     */
    public function getPageById(int $id): ?LegalPage
    {
        return LegalPage::find($id);
    }

    /**
     * Get a legal page by type for an environment
     *
     * @param int $environmentId
     * @param string $pageType
     * @return LegalPage|null
     */
    public function getPageByType(int $environmentId, string $pageType): ?LegalPage
    {
        return LegalPage::where('environment_id', $environmentId)
            ->where('page_type', $pageType)
            ->first();
    }

    /**
     * Get a published legal page by type for public access
     *
     * @param int $environmentId
     * @param string $pageType
     * @return LegalPage|null
     */
    public function getPublishedPageByType(int $environmentId, string $pageType): ?LegalPage
    {
        return LegalPage::where('environment_id', $environmentId)
            ->where('page_type', $pageType)
            ->where('is_published', true)
            ->first();
    }

    /**
     * Create or update a legal page
     *
     * @param array $data
     * @return LegalPage
     */
    public function createOrUpdatePage(array $data): LegalPage
    {
        $environmentId = $data['environment_id'];
        $pageType = $data['page_type'];

        // Check if page already exists
        $page = $this->getPageByType($environmentId, $pageType);

        if ($page) {
            return $this->updatePage($page->id, $data);
        }

        return $this->createPage($data);
    }

    /**
     * Create a new legal page
     *
     * @param array $data
     * @return LegalPage
     */
    public function createPage(array $data): LegalPage
    {
        $page = new LegalPage();
        $page->environment_id = $data['environment_id'];
        $page->user_id = $data['user_id'] ?? Auth::id();
        $page->page_type = $data['page_type'];
        $page->title = $data['title'] ?? LegalPage::PAGE_TYPES[$data['page_type']] ?? null;
        $page->content = $data['content'] ?? null;
        $page->seo_title = $data['seo_title'] ?? null;
        $page->seo_description = $data['seo_description'] ?? null;
        $page->is_published = $data['is_published'] ?? false;

        if ($page->is_published && !$page->published_at) {
            $page->published_at = now();
        }

        $page->save();

        Log::info('LegalPageService: Created legal page', [
            'page_id' => $page->id,
            'page_type' => $page->page_type,
            'environment_id' => $page->environment_id,
        ]);

        return $page;
    }

    /**
     * Update an existing legal page
     *
     * @param int $id
     * @param array $data
     * @return LegalPage
     */
    public function updatePage(int $id, array $data): LegalPage
    {
        $page = $this->getPageById($id);

        if (!$page) {
            throw new \Exception('Legal page not found');
        }

        if (isset($data['title'])) {
            $page->title = $data['title'];
        }

        if (isset($data['content'])) {
            $page->content = $data['content'];
        }

        if (isset($data['seo_title'])) {
            $page->seo_title = $data['seo_title'];
        }

        if (isset($data['seo_description'])) {
            $page->seo_description = $data['seo_description'];
        }

        if (isset($data['is_published'])) {
            $wasPublished = $page->is_published;
            $page->is_published = $data['is_published'];

            // Set published_at when first published
            if ($page->is_published && !$wasPublished) {
                $page->published_at = now();
            }
        }

        $page->save();

        Log::info('LegalPageService: Updated legal page', [
            'page_id' => $page->id,
            'page_type' => $page->page_type,
        ]);

        return $page;
    }

    /**
     * Publish a legal page
     *
     * @param int $id
     * @return LegalPage
     */
    public function publishPage(int $id): LegalPage
    {
        $page = $this->getPageById($id);

        if (!$page) {
            throw new \Exception('Legal page not found');
        }

        $page->is_published = true;
        if (!$page->published_at) {
            $page->published_at = now();
        }
        $page->save();

        Log::info('LegalPageService: Published legal page', [
            'page_id' => $page->id,
            'page_type' => $page->page_type,
        ]);

        return $page;
    }

    /**
     * Unpublish a legal page
     *
     * @param int $id
     * @return LegalPage
     */
    public function unpublishPage(int $id): LegalPage
    {
        $page = $this->getPageById($id);

        if (!$page) {
            throw new \Exception('Legal page not found');
        }

        $page->is_published = false;
        $page->save();

        Log::info('LegalPageService: Unpublished legal page', [
            'page_id' => $page->id,
            'page_type' => $page->page_type,
        ]);

        return $page;
    }

    /**
     * Delete a legal page
     *
     * @param int $id
     * @return bool
     */
    public function deletePage(int $id): bool
    {
        $page = $this->getPageById($id);

        if (!$page) {
            return false;
        }

        Log::info('LegalPageService: Deleting legal page', [
            'page_id' => $page->id,
            'page_type' => $page->page_type,
        ]);

        return $page->delete();
    }

    /**
     * Get dynamic tags with their descriptions
     *
     * @return array
     */
    public function getDynamicTags(): array
    {
        $tags = [];
        foreach (LegalPage::DYNAMIC_TAGS as $tag => $description) {
            $tags[] = [
                'name' => $description,
                'tag' => '{{' . $tag . '}}',
            ];
        }
        return $tags;
    }

    /**
     * Get validation rules for legal page data
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'page_type' => 'required|string|in:' . implode(',', array_keys(LegalPage::PAGE_TYPES)),
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'is_published' => 'nullable|boolean',
        ];
    }
}
