<?php

namespace Plantnet\UserBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use FOS\UserBundle\Command\CreateUserCommand as BaseCommand;

class CreateUserCommand extends BaseCommand
{
	/**
	 * @see Command
	 */
	protected function configure()
	{
		parent::configure();
		//add new command with 1 more InputArgument (database name)
		$this
			->setName('publish:user:create')
			->getDefinition()->addArguments(array(
			new InputArgument('dbName', InputArgument::REQUIRED, 'database name')
		));
	}

	/**
	 * @see Command
	 */
	protected function interact(InputInterface $input, OutputInterface $output)
	{
		//list available databases without prefix
		$dbs_list='Available databases:'."\n";
		$dbs_array=$this->database_list();
        foreach($dbs_array as $db)
        {
            $dbs_list.='- '.$db."\n";
        }

		parent::interact($input, $output);

		//display help
		if(!$input->getArgument('dbName'))
		{
			$dbName=$this->getHelper('dialog')->askAndValidate(
				$output,
				$dbs_list.'Please choose a database name in the list (otherwise, new database will be created):',
				function($dbName){
					if(empty($dbName))
					{
						throw new \Exception('Database name can not be empty');
					}
					return $dbName;
				}
			);
			$input->setArgument('dbName',$dbName);
		}
	}

	/**
	 * @see Command
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		//get values
		$username=$input->getArgument('username');
		$email=$input->getArgument('email');
		$password=$input->getArgument('password');
		$dbName=$input->getArgument('dbName');
		$inactive=$input->getOption('inactive');
		$superadmin=$input->getOption('super-admin');

		//check if database exists
		$dbs_array=$this->database_list();
		if(!in_array($dbName,$dbs_array))
		{
			$dbName=$this->get_prefix().$dbName;
			$connection=new \Mongo();
	        $db=$connection->$dbName;
	        $db->listCollections();
	        $db->createCollection('Collection');
	        $db->createCollection('Image');
	        $db->createCollection('Location');
	        $db->createCollection('Module');
	        $db->createCollection('Plantunit');
	        $db->Location->ensureIndex(array("coordinates"=>"2d"));
		}
		else
		{
			$dbName=$this->get_prefix().$dbName;
		}

		//add new user
		$user_manager=$this->getContainer()->get('fos_user.user_manager');
		$user=$user_manager->createUser();
		$user->setUsername($username);
		$user->setEmail($email);
		$user->setPlainPassword($password);
		$user->setEnabled((Boolean) !$inactive);
		$user->setSuperAdmin((Boolean) $superadmin);
		$user->setDbName($dbName);
		$user_manager->updateUser($user);

		//display message
		$output->writeln(sprintf('Created user <comment>%s</comment>',$username));
	}

	private function database_list()
	{
		//display databases without prefix
		$prefix=$this->get_prefix();
		$dbs_array=array();
		$connection=new \Mongo();
        $dbs=$connection->admin->command(array(
            'listDatabases'=>1
        ));
        foreach($dbs['databases'] as $db)
        {
            $db_name=$db['name'];
            if(substr_count($db_name,$prefix))
            {
            	$dbs_array[]=str_replace($prefix,'',$db_name);
            }
        }
        return $dbs_array;
	}

	private function get_prefix()
	{
		return 'bota_';
	}
}
