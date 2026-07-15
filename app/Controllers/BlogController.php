<?php
namespace App\Controllers;

use App\Services\BlogService;
use App\Services\MarkdownRenderer;

final class BlogController extends BaseController
{
    public function index(): void
    {
        $this->detectApiRequest();
        $this->seoKey = 'blog';

        $blogService = new BlogService();
        $posts = $blogService->all();
        $categories = $blogService->categories();

        $this->render('public/blog', [
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => null,
        ]);
    }

    public function show(string $slug): void
    {
        $this->detectApiRequest();
        $blogService = new BlogService();
        $post = $blogService->find($slug);

        if ($post === null) {
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
        ]);
    }

    public function category(string $slug): void
    {
        $this->detectApiRequest();
        $this->seoKey = 'blog.category';

        $blogService = new BlogService();
        $posts = $blogService->all();
        $categories = $blogService->categories();

        $filtered = array_values(array_filter($posts, fn($p) => ($p['category'] ?? '') === $slug));

        $categoryName = $slug;
        foreach ($categories as $cat) {
            if (($cat['slug'] ?? '') === $slug) {
                $categoryName = $cat['name'] ?? $slug;
                break;
            }
        }

        $this->seoOverrides = [
            'title' => $categoryName . ' — Blog',
            'description' => 'Browse ' . $categoryName . ' articles and updates.',
        ];

        $this->render('public/blog', [
            'posts' => $filtered,
            'categories' => $categories,
            'activeCategory' => $slug,
            'categoryName' => $categoryName,
        ]);
    }
}
