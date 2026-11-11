<?php
namespace App\Controllers;

use App\Services\BlogService;

final class BlogController extends BaseController
{
    public function index(): void
    {
        $this->detectApiRequest();
        $this->seoKey = 'blog';

        $this->renderIndex(new BlogService());
    }

    public function show(string $slug): void
    {
        $this->detectApiRequest();
        $blogService = new BlogService();
        $post = $blogService->find($slug);

        // `find()` also serves the editor, so an unpublished post must be rejected
        // here as well as omitted from the index. Otherwise a retired article stays
        // publicly available to anyone with its old URL.
        if ($post === null || empty($post['published']) || $blogService->categoryIsRetiredPublic((string)($post['category'] ?? ''))) {
            $this->renderNotFound();
            return;
        }

        $content = $post['html'] ?? '';

        $title = $post['seo_title'] ?? $post['title'] ?? ucfirst(str_replace('-', ' ', $slug));
        $this->seoKey = 'blog.post';
        $keywords = $post['keywords'] ?? '';
        $this->seoOverrides = [
            'title' => $title,
            'description' => $post['seo_description'] ?? $post['excerpt'] ?? 'Read ' . $title,
            'og_image' => $post['og_image'] ?? null,
            'keywords' => $keywords,
        ];

        $this->render('public/blog-post', [
            'content' => $content,
            'meta' => $post,
            'slug' => $slug,
            'categoryNames' => array_column($blogService->categories(false), 'name', 'slug'),
        ]);
    }

    public function category(string $slug): void
    {
        $this->detectApiRequest();
        $this->seoKey = 'blog.category';

        $blogService = new BlogService();
        if ($blogService->categoryIsRetiredPublic($slug)) {
            $this->renderNotFound();
            return;
        }

        $this->renderIndex($blogService, $slug);
    }

    private function renderIndex(BlogService $blogService, ?string $activeCategory = null): void
    {
        $posts = $blogService->all();
        $categories = $blogService->categories(false);
        $search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 120) : '';
        $categoryNames = array_column($categories, 'name', 'slug');
        $categoryName = $categoryNames[$activeCategory] ?? $activeCategory;

        if ($activeCategory !== null) {
            $posts = array_values(array_filter($posts, static fn(array $post): bool => ($post['category'] ?? '') === $activeCategory));
            $this->seoOverrides = [
                'title' => $categoryName . ' — Blog',
                'description' => 'Browse ' . $categoryName . ' articles and updates.',
            ];
        } else {
            $posts = array_values(array_filter($posts, static fn(array $post): bool => ($post['category'] ?? '') !== 'help'));
        }

        if ($search !== '') {
            $posts = $blogService->search($posts, $search);
            $this->seoOverrides['robots'] = 'noindex, follow';
        }

        $this->render('public/blog', compact('posts', 'categories', 'categoryNames', 'activeCategory', 'categoryName', 'search'));
    }
}
