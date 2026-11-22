<?php
namespace App\Controllers;

use App\Services\{ProductService,CategoryService,TempleService,PublicKnowledgeService,DeploymentRevisionService,PublicA2uiService};

final class ApiController extends BaseController {
    public function index(): void {
        $this->jsonResponse(['success' => true, 'endpoints' => [
            '/api/shop',
            '/api/categories',
            '/api/product/{slug}',
            '/api/temples',
            '/api/knowledge/articles',
            '/api/knowledge/products',
            '/api/knowledge/contact',
            '/api/knowledge/services',
            '/api/knowledge/a2ui',
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

    /** Explicit public contact facts only; this is never a settings export. */
    public function knowledgeContact(): void {
        header('Cache-Control: no-store');
        if ($_GET !== []) $this->jsonResponse(['error'=>'Contact lookup accepts no arguments.'],400);
        $this->jsonResponse((new PublicKnowledgeService())->contact());
    }

    /** Existing enquiry offerings, not booking or commerce actions. */
    public function knowledgeServices(): void {
        header('Cache-Control: no-store');
        [$query, $offset, $limit] = $this->knowledgeSearchArguments(20);
        try {
            $answer = (new PublicKnowledgeService())->services($query, $offset, $limit);
        } catch (\InvalidArgumentException) {
            $this->jsonResponse(['error'=>'Invalid search arguments'],400);
        }
        $this->jsonResponse($answer);
    }

    /** Validate the HTTP adapter before casting, so overflow cannot become a valid bound. */
    private function knowledgeSearchArguments(int $defaultLimit): array {
        $query = $_GET['q'] ?? '';
        $offset = $_GET['offset'] ?? '0';
        $limit = $_GET['limit'] ?? (string)$defaultLimit;
        if (array_diff(array_keys($_GET), ['q', 'offset', 'limit'])
            || !is_string($query) || !mb_check_encoding($query, 'UTF-8') || mb_strlen($query) > 120
            || !is_scalar($offset) || !is_scalar($limit)
            || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', (string)$offset)
            || (float)$offset > 2147483647
            || !preg_match('/^(?:[1-9]|1[0-9]|20)$/D', (string)$limit)) {
            $this->jsonResponse(['error'=>'Invalid search arguments'],400);
        }
        return [$query, (int)$offset, (int)$limit];
    }

    /** Read-only A2UI v1 Basic-catalog surface from the published source. */
    public function knowledgeA2ui(): void {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        $view=$_GET['view'] ?? null;
        $query=$_GET['query'] ?? '';
        $limit=$_GET['limit'] ?? '4';
        if (!is_string($view) || !in_array($view,['products','articles'],true)
            || !is_string($query) || mb_strlen($query)>120
            || !is_scalar($limit) || !preg_match('/^[1-4]$/D',(string)$limit)
            || array_diff(array_keys($_GET),['view','query','limit'])) {
            $this->jsonResponse(['error'=>'Choose a public view and optional bounded query.'],400);
        }
        try {
            $surface=(new PublicA2uiService())->present($view,$query,(int)$limit);
        } catch (\InvalidArgumentException) {
            $this->jsonResponse(['error'=>'Invalid presentation request.'],400);
        } catch (\Throwable $error) {
            error_log('Public A2UI presentation failed ('.$error::class.')');
            $this->jsonResponse(['error'=>'Public presentation unavailable.'],503);
        }
        $this->jsonResponse($surface);
    }

    public function knowledgeProducts(): void {
        header('Cache-Control: no-store');
        [$query, $offset, $limit] = $this->knowledgeSearchArguments(20);
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
        [$query, $offset, $limit] = $this->knowledgeSearchArguments(5);
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
