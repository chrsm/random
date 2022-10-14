<?php
require('vendor/autoload.php');
require('fn.php');

$unpacked = [];
if (is_readable('unpacked.json')) $unpacked = json_decode(file_get_contents('unpacked.json'), true);

$sync = function() use (&$unpacked) {
  $ret = file_put_contents('unpacked.json', json_encode($unpacked, JSON_PRETTY_PRINT));

  return $ret !== false;
};

$data = file_get_contents('data.json');
if (empty($data))
  die("no data");

$data = json_decode($data, true);

@mkdir('/mnt/f/unpack');

// extract all DIMables
@mkdir('/mnt/f/unpack/inst');
echo "unpacking manifest pkgs (".count($data['inst']).")..\n";
foreach ($data['inst'] as $file) {
  if (isset($unpacked[$file]))
    continue;

  $ln = ["unpack $file"];

  $arc = new Archive7z\Archive7z($file);
  $arc->setOutputDirectory('/mnt/f/unpack/inst');
  $arc->setOverwriteMode(Archive7z\Archive7z::OVERWRITE_MODE_A);

  $content = $arc->getContent('Manifest.dsx');

  $pkgfi = parse_manifest($content);
  foreach ($pkgfi as $f) {
    $arc->extractEntry($f);
  }

  $unpacked[$file] = true;
  $ln[] = "OK";

  if (rand(0, 10) > 5) {
    $sync();
    $ln[] = "SYNC!";
  }

  echo implode(' - ', $ln) . "\n";
}

@mkdir('/mnt/f/unpack/raw');
echo "unpacking raw pkgs (".count($data['raw']).")...\n";
const MAX_RAW_SIZE = 1024*1024*1024 * 500;
foreach ($data['raw'] as $file) {
  if (isset($unpacked[$file]))
    continue;

  if (filesize($file) > MAX_RAW_SIZE) {
    echo "skip unpack $file (" . number_format(filesize($file)/(1024*1024*1024)) . "MB)\n";
    continue;
  }

  $ln = ["unpack $file"];

  $arc = new Archive7z\Archive7z($file, null, 6000);
  $arc->setOutputDirectory('/mnt/f/unpack/raw');
  $arc->setOverwriteMode(Archive7z\Archive7z::OVERWRITE_MODE_A);
  $arc->extract();

  $unpacked[$file] = true;
  if (rand(0, 10) > 5) {
    $sync();
    $ln[] = "SYNC!";
  }

  echo implode(' - ', $ln) . "\n";
}

@mkdir('/mnt/f/unpack/zip');
echo "unpacking zip pkgs (".count($data['zip']).")...\n";
$skipped = 0;
foreach ($data['zip'] as $file) {
  if (isset($unpacked[$file]))
    continue;

  if (filesize($file) > MAX_RAW_SIZE) {
    echo "skip unpack $file (" . number_format(filesize($file)/(1024*1024*1024)) . "MB)\n";
    continue;
  }

  $ln = ["unpack $file"];

  $arc = new Archive7z\Archive7z($file, null, 6000);
  $arc->setOutputDirectory('/mnt/f/unpack/zip');
  $arc->setOverwriteMode(Archive7z\Archive7z::OVERWRITE_MODE_A);

  foreach ($arc->getEntries() as $ent) {
    $pi = pathinfo($ent->getUnixPath(), PATHINFO_EXTENSION);

    if (empty($pi)) {
      $skipped++;
      continue;
    }

    if (!in_array($pi, ['rar', 'zip', '7z'])) {
      $skipped++;
      continue;
    }

    $ent->extract();
  }

  $ln[] = "skipped: $skipped";
  $ln[] = "OK";
  if (rand(0, 10) > 5) {
    $sync();
    $ln[] = "SYNC!";
  }

  echo implode(' - ', $ln) . "\n";
  $skipped = 0;
}

$sync();
