<?php
namespace App\Controllers;

use App\Services\{ProductService,CategoryService,TempleService,PublicKnowledgeService,DeploymentRevisionService};

final class ApiController extends BaseController {
    public function index(): void {
        $this->jsonResponse(['success' => true, 'endpoints' => [
            '/api/shop',
            '/api/categories',
            '/api/product/{slug}',
            '/api/temples',
            '/api/knowledge/articles',
            '/api/knowledge/products',
            '/is-sha-valid?sha={40-character-sha}',
        ]]);
    }

    /** Compare against the checkout running on this host, never GitHub's main tip. */
    public function isShaValid(): void {
        header('Cache-Control: no-store, no-cache, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        $expected = $_GET['sha'] ?? null;
        if (!is_string($expected) || preg_match('/^[0-9a-f]{40}$/D', $expected) !== 1
            || array_diff(array_keys($_GET), ['sha']) !== []) {
            $this->jsonResponse(['status'=>'invalid','error'=>'Supply exactly one lowercase 40-character Git SHA in sha.'], 400);
        }
        $deployed = (new DeploymentRevisionService())->checkoutSha();
        if ($deployed === null) {
            $this->jsonResponse([
                'status'=>'unavailable', 'match'=>null, 'expected_sha'=>$expected,
                'deployed_sha'=>null, 'source'=>null,
                'message'=>'This host cannot establish the deployed Git HEAD.',
            ], 503);
        }
        $match = hash_equals($deployed, $expected);
        $this->jsonResponse([
            'status'=>$match ? 'match' : 'mismatch', 'match'=>$match,
            'expected_sha'=>$expected, 'deployed_sha'=>$deployed,
            'source'=>'git_head',
        ]);
    }
    public function shop(): void {
        $service = new ProductService();
        // The public API is a storefront surface: hidden products stay out of it.
        $products = $service->visible();
        $categories = (new CategoryService())->all();
        $this->jsonResponse(['success' => true, 'products' => $products, 'categories' => $categories]);
    }

    /** Only published blog/help metadata, never private Markdown or JSON sessions. */
    /** Bounded public catalogue projection used by WebMCP and native MCP. */
    public function knowledgeProducts(): void {
        header('Cache-Control: no-store');
        $query=$_GET['q'] ?? '';
        $offset=$_GET['offset'] ?? '0';
        $limit=$_GET['limit'] ?? '20';
        if (!is_string($query) || !is_scalar($offset) || !is_scalar($limit)
            || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$offset)
            || !preg_match('/^[1-9][0-9]*$/D', (string)$limit)) {
            $this->jsonResponse(['error'=>'Invalid search arguments'],400);
        }
        try {
            $answer=(new PublicKnowledgeService())->products(
                $query,(int)$offset,(int)$limit
            );
        } catch (\InvalidArgumentException) {
            $this->jsonResponse(['error'=>'Invalid search arguments'],400);
        } catch (\Throwable) {
            $this->jsonResponse(['error'=>'Public products unavailable'],503);
        }
        $this->jsonResponse($answer);
    }

    public function knowledgeArticles(): void {
        header('Cache-Control: no-store');
        $query=$_GET['q'] ?? '';
        $offset=$_GET['offset'] ?? '0';
        $limit=$_GET['limit'] ?? '5';
        if (!is_string($query) || !is_scalar($offset) || !is_scalar($limit)
            || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$offset)
            || !preg_match('/^[1-9][0-9]*$/D', (string)$limit)) {
            $this->jsonResponse(['error'=>'Invalid search arguments'],400);
        }
        try {
            $answer=(new PublicKnowledgeService())->articles(
                $query, (int)$offset, (int)$limit
            );
        } catch (\InvalidArgumentException) {
            $this->jsonResponse(['error'=>'Invalid search arguments'],400);
        } catch (\Throwable) {
            $this->jsonResponse(['error'=>'Public articles unavailable'],503);
        }
        $this->jsonResponse($answer);
    }

    public function categories(): void {
        $categories = (new CategoryService())->all();
        $this->jsonResponse(['success' => true, 'categories' => $categories]);
    }

    public function product(string $slug): void {
        $service = new ProductService();
        $products = $service->visible();
        $product = null;
        foreach ($products as $p) {
            if (($p['slug'] ?? '') === $slug) { $product = $p; break; }
        }
        if ($product === null) {
            $this->jsonResponse(['success' => false, 'error' => 'Product not found'], 404);
            return;
        }
        $this->jsonResponse(['success' => true, 'product' => $product]);
    }

    public function temples(): void {
        $temples = (new TempleService())->all();
        $this->jsonResponse(['success' => true, 'temples' => $temples]);
    }
}
