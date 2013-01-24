<?php

include __DIR__ . '/Builder.php';

if ($argc != 3) {
	die("Usage: php builder.php <configFile> <target>\n");
}

$target = $argv[2];
$configFile = $argv[1];

if (substr($target, 0, 1) !== '/') {
	$target = __DIR__ . '/' . $target;
}

if (substr($target, -1, 1) === '/') {
	$target = substr($target, 0, -1);
}

if (substr($configFile, 0, 1) !== '/') {
	$configFile = __DIR__ . '/' . $configFile;
}

if (substr($configFile, -1, 1) === '/') {
	$configFile = substr($configFile, 0, -1);
}

if (!file_exists($configFile)) {
	die("File '{$configFile}' does not exist.\n");
}

$configAll = json_decode(file_get_contents($configFile), TRUE);
$files = array();

foreach ($configAll['packages'] as $name => $config) {
	$builder = new Builder($config['name'], $config['version'], $target, isset($config['status']) ? $config['status'] : NULL);

	if (isset($config['modules']) && is_array($config['modules'])) {
		foreach ($config['modules'] as $name => $version) {
			$builder->addModule($name, $version);
		}
	}

	if ($config['type'] === 'cms') {
		$f = $builder->buildCms();
	} elseif ($config['type'] === 'module') {
		$f = $builder->buildModule($name);
	}

	$files = array_merge($files, $f);
}

if (isset($configAll['repository'])) {
	$builder->upload($files, $configAll['repository']);
}
