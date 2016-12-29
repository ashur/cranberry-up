<?php

/*
 * This file is part of Fig
 */
namespace Fig;

use Cranberry\CLI\Command;
use Cranberry\CLI\Format;
use Cranberry\CLI\Shell;
use Cranberry\Core\HTTP;
use Cranberry\Core\JSON;

/**
 * @command		upgrade
 * @desc		Fetch the newest version of Fig
 * @usage		upgrade
 */
$command = new Command\Command( 'upgrade', 'Fetch the newest version of {app}', function()
{
	/* Get latest release metadata */
	$releaseURL = 'https://api.github.com/repos/ashur/cranberry-up/releases/latest';

	$request = new HTTP\Request( $releaseURL );
	$request->addHeader( 'User-Agent', 'ashur/fig' );

	$response = HTTP\HTTP::get( $request );
	$responseStatus = $response->getStatus();

	if( $responseStatus['code'] != 200 )
	{
		throw new Command\CommandInvokedException( "There was a problem: '{$responseStatus['message']}'", 1 );
	}

	try
	{
		$releaseData = JSON::decode( $response->getBody(), true );
	}
	catch( \Exception $e )
	{
		throw new Command\CommandInvokedException( $e->getMessage(), 1 );
	}

	/* Compare current version against latest release */
	$currentVersion = "v{$this->app->version}";
	$latestVersion = $releaseData['tag_name'];

	$shouldUpgrade = version_compare( $currentVersion, $latestVersion, '<' );
	if( !$shouldUpgrade )
	{
		return "You're up-to-date! {$currentVersion} is the latest version available." . PHP_EOL;
	}

	/* Compare latest PHP minimum version to local version */
	$releaseConfigURL = "https://raw.githubusercontent.com/ashur/cranberry-up/{$latestVersion}/lib/config.json";

	$request = new HTTP\Request( $releaseConfigURL );
	$request->addHeader( 'User-Agent', 'ashur/cranberry-up' );

	$response = HTTP\HTTP::get( $request );
	$responseStatus = $response->getStatus();

	if( $responseStatus['code'] != 200 )
	{
		throw new Command\CommandInvokedException( "Error: '{$responseStatus['message']}'", 1 );
	}

	try
	{
		$configData = JSON::decode( $response->getBody(), true );
	}
	catch( \Exception $e )
	{
		throw new Command\CommandInvokedException( $e->getMessage(), 1 );
	}

	$shouldUpgrade = version_compare( PHP_VERSION, $configData['php-min'], '>=' );
	if( !$shouldUpgrade )
	{
		throw new Command\CommandInvokedException( "Error: The latest update {$latestVersion} requires version {$configData['php-min']} of PHP, you have " . PHP_VERSION . ".", 1 );
	}

	/* Fetch changes */
	$formattedString = new Format\String();

	echo PHP_EOL;
	echo " • Upgrading to {$latestVersion}... ";

	chdir( $this->app->applicationDirectory );

	$result = Shell::exec( 'git rev-parse --abbrev-ref HEAD' );
	$currentBranch = $result['output']['raw'];

	/* Failed to fetch */
	$result = Shell::exec( 'git fetch', true, '  > ' );
	if( $result['exitCode'] != 0 )
	{
		throw new Command\CommandInvokedException( "Could not upgrade: Could not fetch changes" );
	}

	/* Failed to check out tag */
	$result = Shell::exec( "git checkout tags/{$latestVersion}", true, '  > ' );
	if( $result['exitCode'] != 0 )
	{
		echo 'failed.' . PHP_EOL;

		$formattedString->backgroundColor( 'red' );
		$formattedString->setString( "Could not check out to 'tags/{$latestVersion}'" );
		echo " ! {$formattedString}" . PHP_EOL;

		echo " • Reverting to '{$currentBranch}'... ";
		$result = Shell::exec( "git checkout {$currentBranch}" );
		echo 'done.' . PHP_EOL . PHP_EOL;

		echo 'Please submit a bug report regarding this failed upgrade. https://github.com/ashur/fig/issues' . PHP_EOL;

		exit( 1 );
	}

	/* Failed to update submodules */
	$result = Shell::exec( 'git submodule update --init --recursive', true );
	if( $result['exitCode'] != 0 )
	{
		echo "Could not upgrade: Could not update submodules. Reverting to '{$currentBranch}'...";

		$result = Shell::exec( "git checkout tags/{$currentBranch}" );
		$result = Shell::exec( 'git submodule update --init --recursive' );

		echo 'done.' . PHP_EOL;

		exit( 1 );
	}

	/* Upgrade succeeded, show release notes */
	echo 'done.' . PHP_EOL . PHP_EOL;
	$releaseBodyLines = explode( "\r\n", trim( $releaseData['body'] ) );
	$formattedString->foregroundColor( 'green' );
	foreach( $releaseBodyLines as $releaseBodyLine )
	{
		$formattedString->setString( "   {$releaseBodyLine}" );

		echo $formattedString . PHP_EOL;
	}

	echo PHP_EOL;
});

return $command;
