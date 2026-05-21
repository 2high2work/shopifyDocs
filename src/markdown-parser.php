<?php
/**
 * Simple Markdown Parser for the Shopify learning platform.
 * Supports basic headers, bold, code snippets, lists, and code blocks.
 */

function parseMarkdown($markdown) {
    if (empty($markdown)) {
        return '';
    }

    // Escape raw HTML for security/XSS prevention
    $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');

    // Handle code blocks first to preserve them
    $codeBlocks = [];
    $blockCounter = 0;
    
    // Find fenced code blocks: ```[lang] ... ```
    $html = preg_replace_callback('/```(\w*)\r?\n(.*?)\r?\n```/s', function($matches) use (&$codeBlocks, &$blockCounter) {
        $lang = strtolower($matches[1]);
        $codeContent = $matches[2];
        
        // Simple IDE-like highlight replacement for key Liquid tags
        if ($lang === 'liquid' || $lang === 'html') {
            $highlighted = preg_replace([
                '/(\{\%|\%\})/s',                          // liquid brackets
                '/(\{\{|\}\})/s',                          // liquid output brackets
                '/(for|endfor|if|elsif|else|endif|assign)/',// keywords
                '/(&quot;.*?&quot;|\'.*?\')/s',            // strings
                '/(\| \w+)/s',                             // filters
            ], [
                '<span class="kw">$1</span>',
                '<span class="kw">$1</span>',
                '<span class="kw">$1</span>',
                '<span class="str">$1</span>',
                '<span class="kw">$1</span>',
            ], $codeContent);
        } else {
            $highlighted = $codeContent;
        }

        $placeholder = "___CODE_BLOCK_{$blockCounter}___";
        $codeBlocks[$placeholder] = '<pre><code class="language-' . $lang . '">' . $highlighted . '</code></pre>';
        $blockCounter++;
        return $placeholder;
    }, $html);

    // Handle inline code: `code`
    $html = preg_replace_callback('/`([^`]+)`/', function($matches) {
        return '<code>' . $matches[1] . '</code>';
    }, $html);

    // Handle headers (H1 to H4)
    $html = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);

    // Handle bold text: **text**
    $html = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $html);

    // Handle lists
    // Replace lines with - or * at the beginning with <li> elements
    $html = preg_replace_callback('/^(?:\-|\*)\s+(.*?)$/m', function($matches) {
        return '<li>' . $matches[1] . '</li>';
    }, $html);

    // Wrap grouped <li> elements inside <ul>
    // We search for consecutive lines containing <li> and wrap them
    $html = preg_replace('/(<li>.*?<\/li>)+/s', '<ul>$0</ul>', $html);

    // Replace double line breaks with paragraphs, ignoring placeholders and existing block tags
    $paragraphs = explode("\n", $html);
    foreach ($paragraphs as &$p) {
        $p = trim($p);
        if (empty($p)) continue;
        
        // Skip block elements
        if (preg_match('/^(?:<h1>|<h2>|<h3>|<h4>|<ul>|<li>|<\/ul>|<\/li>|___CODE_BLOCK_\d+___)/', $p)) {
            continue;
        }
        $p = '<p>' . $p . '</p>';
    }
    $html = implode("\n", $paragraphs);

    // Restore code blocks
    foreach ($codeBlocks as $placeholder => $codeBlockMarkup) {
        $html = str_replace($placeholder, $codeBlockMarkup, $html);
    }

    return $html;
}
