<?php
namespace App\Controllers;

use App\Services\GitHubDocService;
use App\Services\MarkdownRenderer;

final class BlogController extends BaseController
{
    public function index(): void
    {
        $this->detectApiRequest();
        $this->seoKey = 'blog';
        $this->seoOverrides = [
            'title' => 'Blog & Updates',
            'description' => 'Read the latest blog posts, feature updates, and release notes from Sri Panchami Spiritual.',
        ];

        $docService = new GitHubDocService();
        $posts = $docService->fetchIndex();
        $categories = $docService->fetchCategories();

        $this->render('public/blog', [
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => null,
        ]);
    }

    public function show(string $slug): void
    {
        $this->detectApiRequest();
        $docService = new GitHubDocService();
        $markdown = $docService->fetchPost($slug);

        if ($markdown === null) {
            $this->renderNotFound();
        }

        $index = $docService->fetchIndex();
        $meta = [];
        foreach ($index as $post) {
            if (($post['slug'] ?? '') === $slug) {
                $meta = $post;
                break;
            }
        }

        $renderer = new MarkdownRenderer();
        $content = $renderer->render($markdown);

        $title = $meta['title'] ?? ucfirst(str_replace('-', ' ', $slug));
        $this->seoKey = 'blog.post';
        $this->seoOverrides = [
            'title' => $title,
            'description' => $meta['excerpt'] ?? 'Read ' . $title,
        ];

        $this->render('public/blog-post', [
            'content' => $content,
            'meta' => $meta,
            'slug' => $slug,
        ]);
    }

    public function category(string $slug): void
    {
        $this->detectApiRequest();
        $this->seoKey = 'blog.category';

        $docService = new GitHubDocService();
        $posts = $docService->fetchIndex();
        $categories = $docService->fetchCategories();

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
