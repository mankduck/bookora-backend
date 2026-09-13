<?php
namespace App\Services;
class CssSanitizer {
 public function sanitize(?string $css): ?string {
  if ($css===null || trim($css)==='') return $css;
  $clean=preg_replace('/<\/?style\b[^>]*>/iu','',$css) ?? $css;
  $clean=preg_replace('/@import\b[^;]*;?/iu','',$clean) ?? $clean;
  $clean=preg_replace('/expression\s*\([^)]*\)/iu','',$clean) ?? $clean;
  $clean=preg_replace('/javascript\s*:/iu','',$clean) ?? $clean;
  $clean=preg_replace('/url\s*\(\s*["\']?\s*data\s*:\s*text\/html[^)]*\)/iu','',$clean) ?? $clean;
  return trim($clean);
 }
}
