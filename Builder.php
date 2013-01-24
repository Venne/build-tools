<?php

class Builder
{

	/** @var string */
	protected $name;

	/** @var string */
	protected $target;

	/** @var string */
	protected $version;

	/** @var string */
	protected $status;

	/** @var array */
	protected $modules = array();


	public function __construct($name, $version, $target, $status = NULL)
	{
		$this->name = $name;
		$this->version = $version;
		$this->target = $target;
		$this->status = $status;

		if (!file_exists($this->target)) {
			$this->prepareTargetDir();
		}

		if (!file_exists($this->target . '/composer.phar')) {
			$this->downloadComposer();
		}
	}


	public function addModule($name, $version)
	{
		$this->modules[$name] = $version;
		return $this;
	}


	public function buildCms()
	{
		if (file_exists($this->target . '/' . $this->name)) {
			$this->exec("cd {$this->name} && rm -fr {$this->name}");
		}
		$this->exec("php composer.phar create-project venne/sandbox:2.0.x-dev {$this->name} --no-interaction");
		$this->exec("cd {$this->name} && php prepare");
		$this->exec("cd {$this->name} && php composer.phar require venne/cms-module:2.0.x --prefer-dist --no-interaction");

		foreach ($this->modules as $name => $version) {
			$this->exec("cd {$this->name} && php composer.phar require {$name}:{$version} --prefer-dist --no-interaction");
		}

		$this->exec("cd {$this->name} && php www/index.php venne:module:update");
		$this->exec("cd {$this->name} && php www/index.php venne:module:install cms --noconfirm");

		return $this->buildArchives();
	}


	public function buildModule($package)
	{

		$this->exec("php composer.phar create-project {$package} {$this->name}");
		if (file_exists("{$this->target}/{$this->name}/module.json")) {
			$this->exec("cd {$this->name} && COMPOSER=module.json php ../composer.phar install");
		}

		return $this->buildArchives(false);
	}


	public function upload($files, $git)
	{
		$repoPath = $this->target . '/repo';
		$this->exec("mkdir {$repoPath}");

		foreach ($files as $file) {

			if (!$file instanceof \SplFileInfo) {
				$file = new SplFileInfo($file);
			}

			$this->exec("cp {$file->getPathname()} {$repoPath}/{$file->getBasename()}");
		}

		$this->exec("cd {$repoPath} && git init && git add * && git commit -m \"first commit\" && git remote add origin $git && git push -u origin master --force");
	}


	protected function buildArchives($withArchivesWithoutSymlinks = true)
	{
		$this->exec("zip --symlinks -r {$this->getArchiveName()}.zip {$this->name}");
		$this->exec("tar czpvf {$this->getArchiveName()}.tgz {$this->name}");

		$files = array(
			"{$this->getArchiveName()}.zip",
			"{$this->getArchiveName()}.tgz",
		);

		if ($withArchivesWithoutSymlinks) {
			$this->exec("zip -r {$this->getArchiveName()}.ws.zip {$this->name}");
			$this->exec("tar hczpvf {$this->getArchiveName()}.ws.tgz {$this->name}");

			$files = array_merge($files, array(
				"{$this->getArchiveName()}.ws.zip",
				"{$this->getArchiveName()}.ws.tgz",
			));
		}

		return $files;
	}


	protected function getArchiveName()
	{
		return "{$this->name}-{$this->version}" . ($this->status ? '-' . $this->status : '');
	}


	protected function prepareTargetDir()
	{
		umask(0000);
		if (!@mkdir($this->target)) {
			throw new Exception("Target '{$this->target}' can not be created.");
		}
	}


	protected function downloadComposer()
	{
		return $this->exec('curl -s http://getcomposer.org/installer | php');
	}


	protected function exec($command)
	{
		$command = "cd {$this->target} && " . $command;
		echo "run: " . $command . "\n";
		return system("cd {$this->target} && " . $command);
	}
}
