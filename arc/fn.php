<?php
require('vendor/autoload.php');

function walk(string $dir, callable $fn): void {
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

  foreach ($it as $f) {
    $fn($f);
  }
}

const archives = ['zip', 'rar', '7z'];
function has_zip(Archive7z\Archive7z $arc): bool {
  foreach ($arc->getEntries() as $f) {
    $pi = pathinfo($f->getPath(), PATHINFO_EXTENSION);

    if (!empty($pi)) {
      if (in_array($pi, archives))
        return true;
    }
  }

  return false;
}

const raw_dirs = [
  'Animals',
  'Camera Presets',
  'data',
  'People',
  'Props',
  'Runtime',
  'Templates',
  'Shader Presets',
  'Scripts',
  'Vehicles',
  'Presets',
  'Light Presets',
  'light presets',
  'Environments',
];
function has_raw(Archive7z\Archive7z $arc): bool {
  foreach ($arc->getEntries() as $f) {
    $p = explode("/", $f->getUnixPath());

    if (count($p) > 0 && $p[0] != 'Content') {
      foreach ($p as $sec) {
        if (in_array($sec, raw_dirs)) {
          return true;
        }
      }
    }
  }

  return false;
}

const manifest_detect = ['Supplement.dsx', 'Manifest.dsx'];
function has_manifest(Archive7z\Archive7z $arc): bool {
  foreach ($arc->getEntries() as $f) {
    if (in_array($f->getPath(), manifest_detect)) {
      return true;
    }
  }

  return false;
}

function parse_manifest(string $src) {
  $p = new Sabre\Xml\Service;
  $xml = $p->parse($src);

  $files = [];
  foreach ($xml as $i => $v) {
    if ($v['name'] != '{}File')
      continue;

    if (!isset($v['attributes']['VALUE']))
      throw new \Exception('no value on ' . print_r($v, true));

    $files[] = $v['attributes']['VALUE'];
  }

  return $files;
}
