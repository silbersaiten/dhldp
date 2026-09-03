<?php
/**
 * Build the offline HTML documentation shipped with DHL Deutschepost.
 *
 * Usage: php scripts/build-html-docs.php
 */

$moduleRoot = dirname(__DIR__);
$docsDir = $moduleRoot . DIRECTORY_SEPARATOR . 'docs';
$version = '3.2.9';
$moduleName = 'DHL Deutschepost';

$documents = array(
    'en' => array(
        'guide' => 'user-guide-en',
        'description' => 'module-description-en',
        'toc' => 'Contents',
        'guide_label' => 'User guide',
        'description_label' => 'Module description',
        'print' => 'Print / save as PDF',
        'skip' => 'Skip to main content',
        'top' => 'Back to top',
    ),
    'de' => array(
        'guide' => 'benutzerhandbuch-de',
        'description' => 'modulbeschreibung-de',
        'toc' => 'Inhaltsverzeichnis',
        'guide_label' => 'Benutzerhandbuch',
        'description_label' => 'Modulbeschreibung',
        'print' => 'Drucken / als PDF speichern',
        'skip' => 'Zum Hauptinhalt springen',
        'top' => 'Nach oben',
    ),
    'es' => array(
        'guide' => 'manual-de-usuario-es',
        'description' => 'descripcion-del-modulo-es',
        'toc' => 'Índice',
        'guide_label' => 'Manual de usuario',
        'description_label' => 'Descripción del módulo',
        'print' => 'Imprimir / guardar como PDF',
        'skip' => 'Saltar al contenido principal',
        'top' => 'Volver arriba',
    ),
    'pl' => array(
        'guide' => 'podrecznik-uzytkownika-pl',
        'description' => 'opis-modulu-pl',
        'toc' => 'Spis treści',
        'guide_label' => 'Podręcznik użytkownika',
        'description_label' => 'Opis modułu',
        'print' => 'Drukuj / zapisz jako PDF',
        'skip' => 'Przejdź do treści głównej',
        'top' => 'Wróć na górę',
    ),
    'it' => array(
        'guide' => 'manuale-utente-it',
        'description' => 'descrizione-modulo-it',
        'toc' => 'Indice',
        'guide_label' => 'Manuale utente',
        'description_label' => 'Descrizione del modulo',
        'print' => 'Stampa / salva come PDF',
        'skip' => 'Vai al contenuto principale',
        'top' => 'Torna in alto',
    ),
    'fr' => array(
        'guide' => 'guide-utilisateur-fr',
        'description' => 'description-module-fr',
        'toc' => 'Sommaire',
        'guide_label' => 'Guide utilisateur',
        'description_label' => 'Description du module',
        'print' => 'Imprimer / enregistrer en PDF',
        'skip' => 'Aller au contenu principal',
        'top' => 'Retour en haut',
    ),
);

function escapeHtml($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function stripInlineMarkdown($value)
{
    $value = preg_replace('/`([^`]+)`/', '$1', $value);
    $value = preg_replace('/\*\*([^*]+)\*\*/', '$1', $value);
    $value = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $value);
    return trim($value);
}

function renderInline($value)
{
    $value = escapeHtml($value);
    $tokens = array();
    $value = preg_replace_callback('/`([^`]+)`/', function ($matches) use (&$tokens) {
        $token = '@@CODE' . count($tokens) . '@@';
        $tokens[$token] = '<code>' . $matches[1] . '</code>';
        return $token;
    }, $value);
    $value = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $value);
    $value = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) {
        $url = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');
        if (!preg_match('#^(https?://|mailto:|\#|[A-Za-z0-9._/-]+$)#', $url)) {
            return $matches[1];
        }
        $external = preg_match('#^https?://#', $url) ? ' target="_blank" rel="noopener"' : '';
        return '<a href="' . escapeHtml($url) . '"' . $external . '>' . $matches[1] . '</a>';
    }, $value);
    return strtr($value, $tokens);
}

function slugify($value, $fallback)
{
    $value = stripInlineMarkdown($value);
    $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : $value;
    if ($ascii === false) {
        $ascii = $value;
    }
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii), '-'));
    return $slug !== '' ? $slug : $fallback;
}

function parseTableRow($line)
{
    $line = trim($line);
    $line = trim($line, '|');
    return array_map('trim', explode('|', $line));
}

function renderMarkdown($markdown)
{
    $lines = preg_split('/\r\n|\r|\n/', $markdown);
    $html = array();
    $headings = array();
    $usedIds = array();
    $title = '';
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        $line = rtrim($lines[$i]);
        if (trim($line) === '') {
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
            $level = strlen($matches[1]);
            $text = trim($matches[2]);
            if ($level === 1 && $title === '') {
                $title = stripInlineMarkdown($text);
                continue;
            }
            $baseId = slugify($text, 'section-' . ($i + 1));
            $id = $baseId;
            $suffix = 2;
            while (isset($usedIds[$id])) {
                $id = $baseId . '-' . $suffix++;
            }
            $usedIds[$id] = true;
            $html[] = '<h' . $level . ' id="' . escapeHtml($id) . '">' . renderInline($text) . '</h' . $level . '>';
            if ($level === 2 || $level === 3) {
                $headings[] = array('level' => $level, 'id' => $id, 'text' => stripInlineMarkdown($text));
            }
            continue;
        }

        if (substr(trim($line), 0, 3) === '```') {
            $code = array();
            $i++;
            while ($i < $count && substr(trim($lines[$i]), 0, 3) !== '```') {
                $code[] = $lines[$i];
                $i++;
            }
            $html[] = '<pre><code>' . escapeHtml(implode("\n", $code)) . '</code></pre>';
            continue;
        }

        if ($i + 1 < $count && strpos($line, '|') !== false && preg_match('/^\s*\|?\s*:?-{3,}/', $lines[$i + 1])) {
            $headers = parseTableRow($line);
            $rows = array();
            $i += 2;
            while ($i < $count && trim($lines[$i]) !== '' && strpos($lines[$i], '|') !== false) {
                $rows[] = parseTableRow($lines[$i]);
                $i++;
            }
            $i--;
            $table = '<div class="table-wrap"><table><thead><tr>';
            foreach ($headers as $header) {
                $table .= '<th scope="col">' . renderInline($header) . '</th>';
            }
            $table .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $table .= '<tr>';
                foreach ($headers as $column => $unused) {
                    $cell = isset($row[$column]) ? $row[$column] : '';
                    $table .= '<td>' . renderInline($cell) . '</td>';
                }
                $table .= '</tr>';
            }
            $html[] = $table . '</tbody></table></div>';
            continue;
        }

        if (preg_match('/^\s*-\s+(.+)$/', $line)) {
            $items = array();
            while ($i < $count && preg_match('/^\s*-\s+(.+)$/', $lines[$i], $matches)) {
                $items[] = '<li>' . renderInline(trim($matches[1])) . '</li>';
                $i++;
            }
            $i--;
            $html[] = '<ul>' . implode('', $items) . '</ul>';
            continue;
        }

        if (preg_match('/^\s*\d+\.\s+(.+)$/', $line)) {
            $items = array();
            while ($i < $count && preg_match('/^\s*\d+\.\s+(.+)$/', $lines[$i], $matches)) {
                $items[] = '<li>' . renderInline(trim($matches[1])) . '</li>';
                $i++;
            }
            $i--;
            $html[] = '<ol>' . implode('', $items) . '</ol>';
            continue;
        }

        $paragraph = array(trim($line));
        while ($i + 1 < $count) {
            $next = trim($lines[$i + 1]);
            if ($next === '' || preg_match('/^(#{1,6})\s+/', $next) || substr($next, 0, 3) === '```' || preg_match('/^(-|\d+\.)\s+/', $next)) {
                break;
            }
            if (strpos($next, '|') !== false && $i + 2 < $count && preg_match('/^\s*\|?\s*:?-{3,}/', $lines[$i + 2])) {
                break;
            }
            $paragraph[] = $next;
            $i++;
        }
        $html[] = '<p>' . renderInline(implode(' ', $paragraph)) . '</p>';
    }

    if ($title === '') {
        throw new RuntimeException('The document must start with a level-one heading.');
    }

    return array('title' => $title, 'html' => implode("\n", $html), 'headings' => $headings);
}

function buildToc($headings)
{
    $items = array();
    foreach ($headings as $heading) {
        $items[] = '<li class="toc-level-' . (int)$heading['level'] . '"><a href="#' . escapeHtml($heading['id']) . '">' . escapeHtml($heading['text']) . '</a></li>';
    }
    return '<ul>' . implode("\n", $items) . '</ul>';
}

foreach ($documents as $lang => $labels) {
    foreach (array('guide', 'description') as $type) {
        $base = $labels[$type];
        $source = $docsDir . DIRECTORY_SEPARATOR . $base . '.md';
        $target = $docsDir . DIRECTORY_SEPARATOR . $base . '.html';
        if (!is_file($source)) {
            throw new RuntimeException('Missing source: ' . $source);
        }

        $parsed = renderMarkdown(file_get_contents($source));
        $guideCurrent = $type === 'guide' ? ' aria-current="page"' : '';
        $descriptionCurrent = $type === 'description' ? ' aria-current="page"' : '';
        $html = '<!doctype html>' . "\n"
            . '<html lang="' . escapeHtml($lang) . '">' . "\n"
            . '<head>' . "\n"
            . '  <meta charset="utf-8">' . "\n"
            . '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '  <meta name="robots" content="noindex, nofollow">' . "\n"
            . '  <title>' . escapeHtml($parsed['title'] . ' · ' . $moduleName) . '</title>' . "\n"
            . '  <link rel="stylesheet" href="documentation.css">' . "\n"
            . '</head>' . "\n"
            . '<body id="top">' . "\n"
            . '  <a class="skip-link" href="#content">' . escapeHtml($labels['skip']) . '</a>' . "\n"
            . '  <header class="hero">' . "\n"
            . '    <div class="hero__inner">' . "\n"
            . '      <a class="brand-link" href="https://www.silbersaiten.de/" target="_blank" rel="noopener"><img src="silbersaiten-logo.jpg" width="179" height="41" alt="Silbersaiten"></a>' . "\n"
            . '      <p class="hero__module">' . escapeHtml($moduleName . ' ' . $version) . '</p>' . "\n"
            . '      <h1>' . escapeHtml($parsed['title']) . '</h1>' . "\n"
            . '    </div><div class="hero__stripe" aria-hidden="true"></div>' . "\n"
            . '  </header>' . "\n"
            . '  <main id="content" class="page-shell">' . "\n"
            . '    <nav class="document-nav" aria-label="' . escapeHtml($moduleName) . '">' . "\n"
            . '      <a class="button" href="' . escapeHtml($labels['guide'] . '.html') . '"' . $guideCurrent . '>' . escapeHtml($labels['guide_label']) . '</a>' . "\n"
            . '      <a class="button" href="' . escapeHtml($labels['description'] . '.html') . '"' . $descriptionCurrent . '>' . escapeHtml($labels['description_label']) . '</a>' . "\n"
            . '      <button class="button button--print" type="button" data-print-document>' . escapeHtml($labels['print']) . '</button>' . "\n"
            . '    </nav>' . "\n"
            . '    <div class="layout">' . "\n"
            . '      <nav class="toc" aria-label="' . escapeHtml($labels['toc']) . '"><h2 class="toc__title">' . escapeHtml($labels['toc']) . '</h2>' . buildToc($parsed['headings']) . '</nav>' . "\n"
            . '      <article class="document-card">' . $parsed['html'] . '<a class="back-to-top" href="#top">' . escapeHtml($labels['top']) . '</a></article>' . "\n"
            . '    </div>' . "\n"
            . '  </main>' . "\n"
            . '  <footer class="footer"><div class="footer__inner"><p>Silbersaiten · ' . escapeHtml($moduleName . ' ' . $version) . '</p></div></footer>' . "\n"
            . '  <script src="documentation.js"></script>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";

        if (file_put_contents($target, $html) === false) {
            throw new RuntimeException('Unable to write: ' . $target);
        }
        echo 'Built ' . basename($target) . PHP_EOL;
    }
}
