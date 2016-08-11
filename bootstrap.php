<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Events\Dispatcher;
use Illuminate\View\ViewServiceProvider;
use Illuminate\Pagination\Paginator;
use Jenssegers\Mongodb\MongodbServiceProvider;

class Application extends Container
{
	protected $db;
	
	public $aliases = [
		\Illuminate\Support\Facades\Facade::class  => 'Facade',
		\Illuminate\Support\Facades\App::class     => 'App',
		\Illuminate\Support\Facades\Schema::class  => 'Schema',
	];

	public function __construct(Array $db)
	{
		$this->db = $db;

		if (class_exists('\Carbon\Carbon') === true) {
			\Carbon\Carbon::setTestNow(\Carbon\Carbon::now());
		}

		$this['app'] = $this;
		$this->setupAliases();
		$this->setupDispatcher();
		//$this->setupConnection();
		Facade::setFacadeApplication($this);
		Container::setInstance($this);
	}

	public function setupDispatcher()
	{
		if (class_exists('\Illuminate\Events\Dispatcher') === false)
		{
			return;
		}
		$this['events'] = new \Illuminate\Events\Dispatcher($this);
	}

	public function setupAliases()
	{
		foreach ($this->aliases as $className => $alias)
		{
			class_alias($className, $alias);
		}
	}

	public function setupConnection()
	{
		if (class_exists('\Illuminate\Database\Capsule\Manager') === false)
		{
			return;
		}

		$connection = new \Illuminate\Database\Capsule\Manager();
		// MySQL Connection -- Default
		$connection->addConnection
		(
			[
				'driver'   => 'mysql',
				'host' => $this->db["mysql"]["host"],
				'database' => $this->db["mysql"]["database"],
				'username'  => $this->db["mysql"]["username"],
				'password'  => $this->db["mysql"]["password"],
				'charset'   => 'utf8',
				'collation' => 'utf8_general_ci',
				'prefix' => ''
			]
		);
		// MongoDB Connection
		$connection->addConnection
		(
			[
				'driver'   => 'mongodb',
			    'host'     => $this->db["mongodb"]["host"],
			    'port'     => $this->db["mongodb"]["port"],
			    'database' => $this->db["mongodb"]["database"],
			    //'username' => $this->db["mongodb"]["username"],
			    //'password' => $this->db["mongodb"]["username"],
			    'options' => [
			        'db' => 'admin' // sets the authentication database required by mongo 3
			    ]
			],
			"mongodb"
		);
		$connection->getDatabaseManager()->extend('mongodb', function($config)
		{
		    return new Jenssegers\Mongodb\Connection($config);
		});
		
		// Set
		if (isset($this['events']) === true)
		{
			$connection->setEventDispatcher($this['events']);
		}
		$connection->bootEloquent();
		$connection->setAsGlobal();

		$this['db'] = $connection;
	}

	public function migrate($method)
	{
		if (class_exists('\Illuminate\Database\Capsule\Manager') === false)
		{
			return;
		}
		if (isset($this['config']['migrations.path']))
		{
			foreach (glob($this['config']['migrations.path'].'*.php') as $file)
			{
				include_once $file;
				if (preg_match('/\d+_\d+_\d+_\d+_(.*)\.php/', $file, $m))
				{
					$className = Str::studly($m[1]);
					$migration = new $className();
					call_user_func_array([$migration, $method], []);
				}
			}
		}
		else
		{
			return false;
		}
	}

	public function environment()
	{
		return 'testing';
	}
}

if (function_exists('bcrypt') === false)
{
	/**
	 * Hash the given value.
	 *
	 * @param string $value
	 * @param array  $options
	 *
	 * @return string
	 */
	function bcrypt($value, $options = [])
	{
		return (new \Illuminate\Hashing\BcryptHasher())->make($value, $options);
	}
}

if (function_exists('app') === false)
{
	function app()
	{
		return App::getInstance();
	}
}

if (Application::getInstance() == null)
{
	$dbconfig = array
	(
		"mysql" => array
		(
			"driver" => "mysql",
			"host" => $DB_HOST,
			"username" => $DB_USER,
			"password" => $DB_PASSWD,
			"database" => $DB_USERINFO
		),
		"mongodb" => array
		(
			"driver" => "mongodb",
			"host" => "localhost",
			"username" => "",
			"password" => "",
			"database" => "local",
			"port" => 27017
		)
	);
	
	$app = new Application($dbconfig);
	$app['events'] = new Dispatcher();
	$app['config'] = new Fluent();
	$app['files'] = new Filesystem();

	/* Blade Compiled */
	$app['config']['view.compiled'] = __DIR__.'/compiled/';

	/* Init View by MongodbServiceProvider */
	$mongodbServiceProvider = new MongodbServiceProvider($app);
	$mongodbServiceProvider->register();

	/* Init View by ViewServiceProvider */
	$viewServiceProvider = new ViewServiceProvider($app);
	/* Register */
	$viewServiceProvider->register();
	/* Boot */
	Facade::setFacadeApplication($app);

	/* Set Facade View Alias */
	class_alias(View::class, 'View');
}

?>