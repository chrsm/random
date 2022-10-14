<?php
require('vendor/autoload.php');
require('fn.php');

$path = '/mnt/f/assets';
//$path = '/mnt/f/temp';

$data = [];
if (is_readable('./data.json')) $data = json_decode(file_get_contents('data.json'), true);
if (!isset($data['raw'])) $data['raw'] = [];
if (!isset($data['zip'])) $data['zip'] = [];
if (!isset($data['inst'])) $data['inst'] = [];
if (!isset($data['unknown'])) $data['unknown'] = [];
if (!isset($data['content'])) $data['content'] = [];

$dsync = function() use (&$data) {
  foreach (['zip', 'raw', 'inst', 'unknown'] as $k) {
    $data[$k] = array_unique($data[$k]);
  }

  $ret = file_put_contents('./data.json', json_encode($data, JSON_PRETTY_PRINT));
  if ($ret === false) {
    echo "failed to sync data.json!\n";

    return false;
  }

  return true;
};

$fn = function($file) use (&$data, $dsync) {
  if ($file->isDir()) return;

  if (strpos($file->getPathname(), '.part') === strlen($file->getPathname()) - 5) return;
  if ($file->getSize() == 0) return;

  $fname = $file->getPathname();
  if (isset($data['content'][$fname])) {
    return;
  }

  $ln = [$fname];
  $arc = new Archive7z\Archive7z($file);
  try {
    if (!$arc->isValid()) {
      echo $fname . " - INVALID?\n";
      $data['unknown'][] = $fname;

      return;
    }
  } catch (\Exception $e) {
    echo $fname . " - INVALID?\n";
    $data['unknown'][] = $fname;

    return;
  }

  if (has_zip($arc)) {
    $data['zip'][] = $fname;
    $ln[] = 'zip';
  } elseif (has_raw($arc)) {
    $data['raw'][] = $fname;
    $ln[] = 'raw';
  } elseif (has_manifest($arc)) {
    $data['inst'][] = $fname;
    $ln[] = 'inst';
  } else {
    $data['unknown'][] = $fname;
    $ln[] = "unknown";
  }

  $data['content'][$fname] = [];
  foreach ($arc->getEntries() as $ent)
    $data['content'][$fname][] = $ent->getUnixPath();

  echo implode(' - ', $ln) . " (" . count($data['content'][$fname]) . ")\n";

  if (rand(0, 10) > 5)
    $dsync();
};

walk($path, $fn);

$ret = $dsync(); 
if ($ret === false) {
  echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
}
