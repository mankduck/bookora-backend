<?php

namespace App\Services;

class HtmlSanitizer
{
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $allowed = '<section><div><article><header><footer><main><h1><h2><h3><h4><h5><h6><p><span><strong><b><em><i><u><br><hr><ul><ol><li><a><img><figure><figcaption><blockquote><table><thead><tbody><tr><th><td>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/isu', '', $clean) ?? $clean;
        $clean = preg_replace('/\son\w+\s*=\s*[^\s>]+/isu', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/data\s*:\s*text\/html/iu', '', $clean) ?? $clean;

        return $clean;
    }
}
