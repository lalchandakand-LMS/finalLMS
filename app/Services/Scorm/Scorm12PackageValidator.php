<?php

namespace App\Services\Scorm;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class Scorm12PackageValidator
{
    public const MAX_ARCHIVE_BYTES = 104857600;
    public const MAX_EXPANDED_BYTES = 524288000;
    public const MAX_FILES = 3000;
    public const MAX_FILE_BYTES = 104857600;
    public const MAX_COMPRESSION_RATIO = 100;

    /** @return array{entry_point:string,title:string,files:array<int,array{name:string,size:int,crc:int}>} */
    public function inspect(string $archivePath): array
    {
        if (! extension_loaded('zip') || ! class_exists(DOMDocument::class) || ! is_file($archivePath)) {
            throw new RuntimeException('SCORM package processing is unavailable.');
        }

        $archiveSize = filesize($archivePath);
        if ($archiveSize === false || $archiveSize < 1 || $archiveSize > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('The SCORM archive exceeds the upload limit.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The uploaded file is not a valid ZIP archive.');
        }

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) {
                throw new RuntimeException('The SCORM archive contains an invalid number of files.');
            }

            $files = [];
            $expanded = 0;
            $manifest = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! is_array($stat) || ! isset($stat['name'], $stat['size'], $stat['crc'])) {
                    throw new RuntimeException('The SCORM archive contains an unreadable entry.');
                }

                $name = $this->safePath((string) $stat['name']);
                $size = (int) $stat['size'];
                $compressed = (int) ($stat['comp_size'] ?? 0);
                if ($size < 0 || $size > self::MAX_FILE_BYTES) {
                    throw new RuntimeException('A SCORM package file exceeds the per-file limit.');
                }
                $expanded += $size;
                if ($expanded > self::MAX_EXPANDED_BYTES) {
                    throw new RuntimeException('The expanded SCORM package exceeds the size limit.');
                }
                if ($size > 0 && ($compressed < 1 || $size / $compressed > self::MAX_COMPRESSION_RATIO)) {
                    throw new RuntimeException('The SCORM archive has an unsafe compression ratio.');
                }

                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && $opsys === ZipArchive::OPSYS_UNIX) {
                    $type = ($attributes >> 16) & 0170000;
                    if ($type !== 0 && $type !== 0100000 && $type !== 0040000) {
                        throw new RuntimeException('Symbolic links and special files are not allowed.');
                    }
                }

                if (! str_ends_with($name, '/')) {
                    if (preg_match('/\.(php[0-9]?|phtml|phar|cgi|pl|py|sh|exe|dll|so|bat|cmd|com)$/i', $name)) {
                        throw new RuntimeException('Executable files are not allowed in SCORM packages.');
                    }
                    $files[] = ['name' => $name, 'size' => $size, 'crc' => (int) $stat['crc']];
                    if (strtolower($name) === 'imsmanifest.xml') {
                        $manifest = $zip->getFromIndex($i);
                    }
                }
            }

            if (! is_string($manifest) || $manifest === '' || strlen($manifest) > 2097152) {
                throw new RuntimeException('The SCORM 1.2 manifest is missing or too large.');
            }

            return $this->parseManifest($manifest, $files);
        } finally {
            $zip->close();
        }
    }

    private function safePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)
            || str_contains($path, "\0") || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new RuntimeException('The SCORM archive contains an unsafe path.');
        }
        foreach (explode('/', rtrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('The SCORM archive contains an unsafe path.');
            }
        }

        return implode('/', explode('/', rtrim($path, '/'))).(str_ends_with($path, '/') ? '/' : '');
    }

    /** @param array<int,array{name:string,size:int,crc:int}> $files */
    private function parseManifest(string $xml, array $files): array
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new RuntimeException('External entities and document types are forbidden.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            $document->resolveExternals = false;
            $document->substituteEntities = false;
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS) || ! $document->documentElement
                || strtolower($document->documentElement->localName) !== 'manifest') {
                throw new RuntimeException('The SCORM manifest XML is invalid.');
            }

            $xpath = new DOMXPath($document);
            $resources = $xpath->query('//*[local-name()="resource" and @*[local-name()="scormtype" and namespace-uri()="http://www.adlnet.org/xsd/adlcp_rootv1p2"]="sco"]');
            if (! $resources || $resources->length < 1) {
                throw new RuntimeException('The manifest does not declare a SCORM 1.2 SCO.');
            }
            $resource = $resources->item(0);
            $href = trim((string) $resource?->getAttribute('href'));
            $identifier = trim((string) $resource?->getAttribute('identifier'));
            if ($href === '' || $identifier === '') {
                throw new RuntimeException('The SCORM launch resource is incomplete.');
            }
            $entry = $this->safePath($href);
            if (! in_array($entry, array_column($files, 'name'), true)) {
                throw new RuntimeException('The SCORM launch file is missing.');
            }

            $titleNode = $xpath->query('//*[local-name()="organization"]/*[local-name()="title"]')?->item(0);
            $title = trim((string) $titleNode?->textContent) ?: 'SCORM lesson';

            return ['entry_point' => $entry, 'title' => mb_substr($title, 0, 255), 'files' => $files];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
