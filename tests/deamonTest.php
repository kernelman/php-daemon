<?php namespace longmon\Php\tests;

class daemonTest
{
	public function main($name)
	{
		echo "halo {$name}\n";
		sleep(10);
	}
}