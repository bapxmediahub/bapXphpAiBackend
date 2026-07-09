<?php
namespace App\Controllers;

use App\Services\{AuthService,ReviewService};

final class ReviewController extends BaseController {
    public function __construct() {
        (new AuthService())->requireUser();
    }

    public function saveAstrologer(): void {
        $this->validateCsrf();
        try {
            (new ReviewService())->saveAstrologerReview([
                'target_slug' => $_POST['target_slug'] ?? '',
                'rating' => $_POST['rating'] ?? 0,
                'review' => $_POST['review'] ?? '',
                'customer_email' => $_SESSION['user']['email'] ?? ($_POST['customer_email'] ?? ''),
                'source_id' => $_POST['source_id'] ?? '',
            ]);
            $this->flash('Thanks. Your astrologer rating was saved.','success');
        } catch (\Throwable) {
            $this->flash('Unable to save the review. Please try again.','error');
        }
        $this->redirect($_POST['redirect'] ?? '/account/dashboard/sessions');
    }

    public function saveProduct(): void {
        $this->validateCsrf();
        try {
            (new ReviewService())->saveProductReview([
                'target_slug' => $_POST['target_slug'] ?? '',
                'rating' => $_POST['rating'] ?? 0,
                'review' => $_POST['review'] ?? '',
                'customer_email' => $_SESSION['user']['email'] ?? ($_POST['customer_email'] ?? ''),
                'source_id' => $_POST['source_id'] ?? '',
            ]);
            $this->flash('Thanks. Your product rating was saved.','success');
        } catch (\Throwable) {
            $this->flash('Unable to save the review. Please try again.','error');
        }
        $this->redirect($_POST['redirect'] ?? '/account/dashboard/orders');
    }
}
