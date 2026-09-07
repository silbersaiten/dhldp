<?php
/** Run with: php scripts/test-label-filenames.php */
define('_PS_VERSION_', '1.7.8.0');

class Module {}
class ObjectModel
{
    const TYPE_INT = 1;
    const TYPE_STRING = 2;
    const TYPE_DATE = 3;
    const TYPE_FLOAT = 4;
}
class Tools
{
    public static function file_get_contents($url)
    {
        throw new RuntimeException('Unexpected download: ' . $url);
    }
}

require_once dirname(__DIR__) . '/dhldp.php';

class LabelFilenameTestModule extends DhlDp
{
    private $testPath;

    public function __construct($path) { $this->testPath = $path; }
    public function getLocalPath() { return $this->testPath; }
    public function getPathUri() { return '/modules/dhldp/'; }
}

function checkLabelFilename($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$directory = sys_get_temp_dir() . '/dhldp-test-' . uniqid('', true) . '/';
if (!mkdir($directory . 'pdfs', 0700, true)) {
    throw new RuntimeException('Unable to create test directory: ' . $directory);
}
$module = new LabelFilenameTestModule($directory);

try {
    $identifiers = array(
        'return-1234567890',
        str_repeat('a', 32),
        str_repeat('a', 33),
        str_repeat('a', 100),
        'https://example.test/printShipment?token=' . str_repeat('AB%2F', 1000),
        'https://example.test/labels?token=' . str_repeat('X', 5000),
        'https://example.test/labels?token=' . str_repeat('X', 4999) . 'Y',
        'https://example.test/!!!',
    );
    $paths = array();
    foreach ($identifiers as $index => $identifier) {
        $data = '%PDF-1.4 test ' . $index;
        $uri = $module->saveLabelFile($identifier, $data);
        $path = $module->getLabelFileNameByLabelUrl($identifier);
        checkLabelFilename(strlen(basename($path)) <= 36, 'Filename exceeds 36 bytes');
        checkLabelFilename(!in_array($path, $paths, true), 'Distinct identifiers collided');
        checkLabelFilename(basename($uri) === basename($path), 'Path and URI disagree');
        checkLabelFilename($module->getLabelFilePathByLabelUrl($uri) === $path, 'Saved URI did not resolve locally');
        checkLabelFilename(file_get_contents($path) === $data, 'PDF content changed');
        checkLabelFilename($module->saveLabelFile($identifier, 'replacement') === $uri, 'Filename is unstable');
        checkLabelFilename(file_get_contents($path) === $data, 'Existing PDF overwritten');
        $paths[] = $path;
    }

    $legacyUrl = 'https://example.test/labels?token=' . str_repeat('z', 200);
    $legacyNames = array(
        '/modules/dhldp/pdfs/' . str_repeat('b', 100) . '.pdf' => str_repeat('b', 100),
        $legacyUrl => 'label_' . sha1($legacyUrl),
    );
    foreach ($legacyNames as $url => $name) {
        $oldPath = $directory . 'pdfs/' . $name . '.pdf';
        file_put_contents($oldPath, '%PDF-1.4 legacy');
        $uri = $module->getLabelFileURIByLabelUrl($url);
        $newPath = $module->getLabelFilePathByLabelUrl($url);
        checkLabelFilename(strlen(basename($newPath)) <= 36, 'Legacy filename was not shortened');
        checkLabelFilename(file_get_contents($newPath) === '%PDF-1.4 legacy', 'Legacy PDF was not copied');
        checkLabelFilename(is_file($oldPath), 'Stored legacy URL was broken');
        checkLabelFilename($module->getLabelFilePathByLabelUrl($uri) === $newPath, 'Migrated URI does not resolve');
    }
    echo "Label filename checks passed.\n";
} finally {
    foreach (glob($directory . 'pdfs/*') as $file) {
        unlink($file);
    }
    rmdir($directory . 'pdfs');
    rmdir($directory);
}
