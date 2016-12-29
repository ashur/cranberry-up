<?php

use Cranberry\CLI\Command;
use Cranberry\Core\File;

/**
 * @name			install
 * @description		Symlink {app} to a convenient path
 * @usage			install <dir>
 */
$command = new Command\Command( 'install', 'Symlink {app} to a convenient path', function( $dir )
{
	try
	{
		$destinationDirectory = new File\Directory( $dir );

		if( !$destinationDirectory->exists() )
		{
			throw new \Exception( "Directory '{$dir}' does not exist" );
		}
	}
	catch( \Exception $e )
	{
		throw new Command\CommandInvokedException( $e->getMessage(), 1 );
	}

	$target = $destinationDirectory->child( $this->app->name );
	$source = $this->app->applicationDirectory
		->childDir( 'bin' )
		->child( $this->app->name );

	if( !$source->exists() )
	{
		throw new Command\CommandInvokedException( "Invalid source: '{$source}' not found", 1 );
	}

	if( $target->exists() || $target->isSymlink() )
	{
		throw new Command\CommandInvokedException( "Invalid target: '{$target}' exists", 1 );
	}

	symlink( $source, $target );
	$this->output->line( "Linked to '{$target}'" );
});

return $command;
