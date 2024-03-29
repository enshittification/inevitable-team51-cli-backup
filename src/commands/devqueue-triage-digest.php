<?php

namespace Team51\Command;

use Team51\Helper\API_Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class DevQueue_Triage_Digest extends Command {
	protected static $defaultName = 'triage';

	protected function configure() {
		$this
		->setDescription( 'Generates a Digest Post of what upcoming Triage issues we have.' )
		->setHelp( 'Scans the triage column to find due dates in the near future.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		if ( ! defined( 'GITHUB_DEVQUEUE_TRIAGE_COLUMN' ) ) {
			$output->writeln( '<error>GITHUB_DEVQUEUE_TRIAGE_COLUMN not set in config.</error>' );
			return;
		}

		$api_helper = new API_Helper();

		$output->writeln( '<info>Grabbing Triage Column Items...</info>' );

		$cards = $api_helper->call_github_api(
			sprintf( 'projects/columns/%s/cards', GITHUB_DEVQUEUE_TRIAGE_COLUMN ),
			'',
			'GET'
		);

		$output->writeln( sprintf( '<comment>Triage currently has %d cards.</comment>', sizeof( $cards ) ) );

		$issues = array();

		$progress_bar = new ProgressBar( $output, sizeof( $cards ) );
		$progress_bar->start();

		foreach ( $cards as $card ) {
			$path = substr( parse_url( $card->content_url, PHP_URL_PATH ), 1 );

			$issue = $api_helper->call_github_api(
				$path,
				'',
				'GET'
			);

			$issue->due_in = 9999;
			$issue->random_tags = array();

			if ( sizeof( $issue->labels ) ) {
				foreach ( $issue->labels as $issue_label ) {
					if ( '[DUE DATE]' === strtoupper( substr( $issue_label->name, 0, 10 ) ) ) {
						// If we have a due date, set how many days until then.
						$duedate_timestamp = strtotime( substr( $issue_label->name, 11 ) );
						$issue->due_in = ( $duedate_timestamp - strtotime( 'today' ) ) / ( 24 * 60 * 60 );
					} else {
						$issue->random_tags[] = $issue_label->name;
					}
				}
			}

			// Add it to the issues array.
			$issues[ $issue->id ] = $issue;

			$progress_bar->advance();
		}

		$progress_bar->finish();
		$output->writeln( '' );
		$output->writeln( '' );

		usort( $issues, function( $a, $b ) {
			return $a->due_in - $b->due_in;
		});

		foreach ( $issues as $issue ) {
			$type = 'comment';
			if ( $issue->due_in <= 0 ) {
				$type = 'error';
			}

			switch( $issue->due_in ) {
				case -1:
					$how_long = 'Due YESTERDAY!';
					break;
				case '0':
					$how_long = 'Due TODAY';
					break;
				case '1':
					$how_long = 'Due Tomorrow';
					break;
				case 9999:
					$how_long = 'No Due Date Specified';
					break;
				default:
					$how_long = "Due in {$issue->due_in} days";
					break;
			}

			$tags = '';
			if ( sizeof ( $issue->random_tags ) ) {
				foreach ( $issue->random_tags as $tag_name ) {
					$tags .= "*{$tag_name}* ";
				}
			}

			$output->writeln(
				sprintf(
					'<%1$s>* %2$d: %6$s[%3$s](%5$s) (%4$s)</%1$s>',
					$type,
					$issue->number,
					$issue->title,
					$how_long,
					$issue->html_url,
					$tags
				)
			);
		}
	}
}
