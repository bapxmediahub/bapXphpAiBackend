<?php
namespace App\Services;
/** The supplied editor revision is no longer the current persisted article. */
final class BlogConflictException extends \RuntimeException {}
