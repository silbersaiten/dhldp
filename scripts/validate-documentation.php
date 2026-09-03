<?php
/**
 * Validate generated DHL Deutschepost offline documentation.
 *
 * Usage: php scripts/validate-documentation.php
 */

$root = dirname(__DIR__);
$docs = $root . DIRECTORY_SEPARATOR . 'docs';
$expected = array(
    'user-guide-en.html' => 'en',
    'module-description-en.html' => 'en',
    'benutzerhandbuch-de.html' => 'de',
    'modulbeschreibung-de.html' => 'de',
    'manual-de-usuario-es.html' => 'es',
    'descripcion-del-modulo-es.html' => 'es',
    'podrecznik-uzytkownika-pl.html' => 'pl',
    'opis-modulu-pl.html' => 'pl',
    'manuale-utente-it.html' => 'it',
    'descrizione-modulo-it.html' => 'it',
    'guide-utilisateur-fr.html' => 'fr',
    'description-module-fr.html' => 'fr',
);
$errors = array();

function addError(&$errors, $file, $message)
{
    $errors[] = $file . ': ' . $message;
}

foreach ($expected as $filename => $language) {
    $path = $docs . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        addError($errors, $filename, 'file is missing');
        continue;
    }

    $source = file_get_contents($path);
    if (strpos($source, 'DHL Deutschepost 3.2.9') === false) {
        addError($errors, $filename, 'module version is missing or incorrect');
    }
    if (preg_match('/(^|\n)#{1,6}\s|\*\*[^*]+\*\*|\[[^\]]+\]\([^)]+\)/', $source)) {
        addError($errors, $filename, 'unprocessed Markdown syntax found');
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadHTML($source, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) {
        addError($errors, $filename, 'DOM parser could not read the document');
        continue;
    }
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $html = $dom->documentElement;
    if (!$html || $html->getAttribute('lang') !== $language) {
        addError($errors, $filename, 'incorrect html lang attribute');
    }
    $titles = $dom->getElementsByTagName('title');
    if ($titles->length !== 1 || trim($titles->item(0)->textContent) === '') {
        addError($errors, $filename, 'title must be present and non-empty');
    }
    if ($xpath->query('//main[@id="content"]')->length !== 1) {
        addError($errors, $filename, 'expected exactly one main#content');
    }
    if ($xpath->query('//h1')->length !== 1) {
        addError($errors, $filename, 'expected exactly one h1');
    }

    $ids = array();
    foreach ($xpath->query('//*[@id]') as $element) {
        $id = $element->getAttribute('id');
        if ($id === '') {
            addError($errors, $filename, 'empty id found');
        } elseif (isset($ids[$id])) {
            addError($errors, $filename, 'duplicate id #' . $id);
        }
        $ids[$id] = true;
    }
    foreach ($xpath->query('//nav[contains(concat(" ", normalize-space(@class), " "), " toc ")]//a') as $link) {
        $href = $link->getAttribute('href');
        if (substr($href, 0, 1) !== '#' || !isset($ids[substr($href, 1)])) {
            addError($errors, $filename, 'TOC target does not exist: ' . $href);
        }
    }

    foreach ($xpath->query('//a') as $link) {
        $href = trim($link->getAttribute('href'));
        if ($href === '') {
            addError($errors, $filename, 'empty link found');
            continue;
        }
        if (preg_match('#^https?://#', $href)) {
            $rel = preg_split('/\s+/', trim($link->getAttribute('rel')));
            if ($link->getAttribute('target') !== '_blank' || !in_array('noopener', $rel)) {
                addError($errors, $filename, 'external link must use target=_blank and rel=noopener: ' . $href);
            }
        } elseif (substr($href, 0, 1) !== '#' && strpos($href, 'mailto:') !== 0) {
            $localTarget = $docs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $href);
            if (!is_file($localTarget)) {
                addError($errors, $filename, 'local link target is missing: ' . $href);
            }
        }
    }

    foreach (array('link' => 'href', 'script' => 'src', 'img' => 'src') as $tag => $attribute) {
        foreach ($dom->getElementsByTagName($tag) as $resource) {
            $value = trim($resource->getAttribute($attribute));
            if ($value === '' || preg_match('#^(https?:)?//#', $value)) {
                addError($errors, $filename, $tag . ' resource must be local: ' . $value);
                continue;
            }
            $resourcePath = $docs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $value);
            if (!is_file($resourcePath)) {
                addError($errors, $filename, 'resource is missing: ' . $value);
            }
        }
    }

    $logos = $xpath->query('//a[@href="https://www.silbersaiten.de/"]//img[@src="silbersaiten-logo.jpg" and @alt="Silbersaiten"]');
    if ($logos->length !== 1) {
        addError($errors, $filename, 'local linked Silbersaiten logo is missing');
    }
    if ($xpath->query('//button[@data-print-document]')->length !== 1) {
        addError($errors, $filename, 'print button is missing');
    }
}

$script = file_get_contents($docs . DIRECTORY_SEPARATOR . 'documentation.js');
if (strpos($script, 'window.print()') === false) {
    $errors[] = 'documentation.js: window.print() is missing';
}
$css = file_get_contents($docs . DIRECTORY_SEPARATOR . 'documentation.css');
foreach (array('@media (max-width: 820px)', '@media print', 'position: sticky') as $requiredCss) {
    if (strpos($css, $requiredCss) === false) {
        $errors[] = 'documentation.css: missing ' . $requiredCss;
    }
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo 'Documentation validation passed for ' . count($expected) . ' HTML files.' . PHP_EOL;
