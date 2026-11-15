<?php
declare(strict_types=1);
/** Test helper: JSON object member order is not semantic; array order and scalar types are. */
function canonicalJsonSnapshot(mixed $value): mixed {
    if(!is_array($value))return $value;
    if(!array_is_list($value))ksort($value,SORT_STRING);
    foreach($value as &$entry)$entry=canonicalJsonSnapshot($entry);
    unset($entry);return $value;
}
