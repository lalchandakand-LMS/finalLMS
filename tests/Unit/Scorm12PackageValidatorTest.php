<?php

namespace Tests\Unit;

use App\Services\Scorm\Scorm12PackageValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class Scorm12PackageValidatorTest extends TestCase
{
    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZIP extension is required.');
        }
        $this->archive = tempnam(sys_get_temp_dir(), 'scorm-');
    }

    protected function tearDown(): void
    {
        if (isset($this->archive) && is_file($this->archive)) {
            unlink($this->archive);
        }
        parent::tearDown();
    }

    public function test_it_accepts_a_valid_scorm_12_sco_manifest(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->archive, ZipArchive::OVERWRITE);
        $zip->addFromString('imsmanifest.xml', $this->manifest());
        $zip->addFromString('index.html', '<!doctype html><title>Lesson</title>');
        $zip->close();

        $result = (new Scorm12PackageValidator())->inspect($this->archive);

        $this->assertSame('index.html', $result['entry_point']);
        $this->assertSame('Sample lesson', $result['title']);
        $this->assertCount(2, $result['files']);
    }

    public function test_it_rejects_zip_slip_paths(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->archive, ZipArchive::OVERWRITE);
        $zip->addFromString('../outside.txt', 'not safe');
        $zip->addFromString('imsmanifest.xml', $this->manifest());
        $zip->addFromString('index.html', 'ok');
        $zip->close();

        $this->expectException(RuntimeException::class);
        (new Scorm12PackageValidator())->inspect($this->archive);
    }

    public function test_it_rejects_manifest_without_a_scorm_sco(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->archive, ZipArchive::OVERWRITE);
        $zip->addFromString('imsmanifest.xml', '<manifest><resources/></manifest>');
        $zip->addFromString('index.html', 'ok');
        $zip->close();

        $this->expectException(RuntimeException::class);
        (new Scorm12PackageValidator())->inspect($this->archive);
    }

    private function manifest(): string
    {
        return '<?xml version="1.0"?>'
            . '<manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"'
            . ' xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2" identifier="pkg">'
            . '<organizations default="org"><organization identifier="org"><title>Sample lesson</title>'
            . '<item identifier="item" identifierref="res"/></organization></organizations>'
            . '<resources><resource identifier="res" type="webcontent" adlcp:scormtype="sco" href="index.html"/>'
            . '</resources></manifest>';
    }
}
