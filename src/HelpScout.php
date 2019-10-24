<?php

namespace Codeception\Module;

use Codeception\Module;
use HelpScout\Api\ApiClientFactory;
use HelpScout\Api\Conversations\ConversationFilters;
use HelpScout\Api\Conversations\ConversationRequest;
use HelpScout\Api\Conversations\EmailConversation;
use PHPUnit\Framework\Assert;

class HelpScout extends Module {

	use \Codeception\Email\TestsEmails;
	use \Codeception\Email\EmailServiceProvider;

	protected $client;

	/**
	 * Conversations
	 *
	 * @var array
	 */
	protected $fetchedEmails;

	/**
	 * Currently selected set of email headers to work with
	 *
	 * @var array
	 */
	protected $currentInbox;
	/**
	 * Starts as the same data as the current inbox, but items are removed as they're used
	 *
	 * @var array
	 */
	protected $unreadInbox;

	/**
	 * Contains the currently open email on which test operations are conducted
	 *
	 * @var mixed
	 */
	protected $openedEmail;

	/**
	 * Codeception exposed variables
	 *
	 * @var array
	 */
	protected $config = array( 'app_id', 'app_secret', 'mailbox_id' );

	public function _initialize() {
		$client       = ApiClientFactory::createClient();
		$this->client = $client->useClientCredentials( $this->config['app_id'], $this->config['app_secret'] );
	}

	/**
	 * Fetch Emails
	 *
	 * Accessible from tests, fetches all emails
	 *
	 * @param null $mailboxID
	 */
	public function fetchEmails( $mailboxID = null ) {

		$mailboxID = is_null( $mailboxID ) ? $this->config['mailbox_id'] : $mailboxID;

		$this->fetchedEmails = array();
		try {
			$request = ( new ConversationRequest )->withMailbox()->withThreads();

			$filters = ( new ConversationFilters() )->withMailbox( $mailboxID )
			                                        ->withStatus( 'open' )
			                                        ->withQuery( 'assigned:"Unassigned"' )
			                                        ->withSortField( 'createdAt' )
			                                        ->withSortOrder( 'asc' );

			$conversations       = $this->client->conversations()->list( $filters, $request );
			$this->fetchedEmails = $conversations->toArray();
		} catch ( Exception $e ) {
			$this->fail( 'Exception: ' . $e->getMessage() );
		}

		$this->fetchedEmails = $this->sortEmails( $this->fetchedEmails );
		// by default, work on all emails
		$this->setCurrentInbox( $this->fetchedEmails );
	}

	/**
	 * Sort Emails
	 *
	 * Sorts the inbox based on the timestamp
	 *
	 * @param array $emails Emails to sort
	 *
	 * @return array
	 */
	protected function sortEmails( $emails ) {
		usort( $emails, array( $this, 'sortEmailsByCreationDatePredicate' ) );

		return $emails;
	}

	/**
	 * Get Email To
	 *
	 * Returns the string containing the persons included in the To field
	 *
	 * @param EmailConversation $emailA Email
	 * @param EmailConversation $emailB Email
	 *
	 * @return int Which email should go first
	 */
	static function sortEmailsByCreationDatePredicate( $emailA, $emailB ) {
		$sortKeyA = $emailA->getCreatedAt()->getTimestamp();
		$sortKeyB = $emailB->getCreatedAt()->getTimestamp();

		return ( $sortKeyA > $sortKeyB ) ? - 1 : 1;
	}

	/**
	 * Set Current Inbox
	 *
	 * Sets the current inbox to work on, also create a copy of it to handle unread emails
	 *
	 * @param array $inbox Inbox
	 */
	protected function setCurrentInbox( $inbox ) {
		$this->currentInbox = $inbox;
		$this->unreadInbox  = $inbox;
	}

	/**
	 * Get Opened Email
	 *
	 * Main method called by the tests, providing either the currently open email or the next unread one
	 *
	 * @param bool $fetchNextUnread Goes to the next Unread Email
	 *
	 * @return mixed Returns a JSON encoded Email
	 */
	public function getOpenedEmail( $fetchNextUnread = false ) {
		if ( $fetchNextUnread || $this->openedEmail == null ) {
			$this->openNextUnreadEmail();
		}

		return $this->openedEmail;
	}

	/**
	 * Get Most Recent Unread Email
	 *
	 * Pops the most recent unread email, fails if the inbox is empty
	 *
	 * @return mixed Returns a JSON encoded Email
	 */
	protected function getMostRecentUnreadEmail() {
		if ( empty( $this->unreadInbox ) ) {
			$this->fail( 'Unread Inbox is Empty' );
		}
		$email = array_shift( $this->unreadInbox );

		return $email;
	}

	/**
	 * Open Next Unread Email
	 *
	 * Pops the most recent unread email and assigns it as the email to conduct tests on
	 */
	public function openNextUnreadEmail() {
		$this->openedEmail = $this->getMostRecentUnreadEmail();
	}

	/**
	 * Get Email Subject
	 *
	 * Returns the subject of an email
	 *
	 * @param EmailConversation $email Email
	 *
	 * @return string Subject
	 */
	protected function getEmailSubject( $email ) {
		return $email->getSubject();
	}

	/**
	 * Get Email Body
	 *
	 * Returns the body of an email
	 *
	 * @param EmailConversation $email Email
	 *
	 * @return string Body
	 */
	protected function getEmailBody( $email ) {
		return $email->getThreads()->toArray()[0]->getText();
	}

	/**
	 * Get Email To
	 *
	 * Returns the string containing the persons included in the To field
	 *
	 * @param EmailConversation $email Email
	 *
	 * @return string To
	 */
	protected function getEmailTo( $email ) {
		return $email->getMailbox()->getEmail();
	}

	/**
	 * Get Email CC
	 *
	 * Returns the string containing the persons included in the CC field
	 *
	 * @param EmailConversation $email Email
	 *
	 * @return string CC
	 */
	protected function getEmailCC( $email ) {
		return implode( ',', $email->getCC() );
	}

	/**
	 * Get Email BCC
	 *
	 * Returns the string containing the persons included in the BCC field
	 *
	 * @param EmailConversation $email Email
	 *
	 * @return string BCC
	 */
	protected function getEmailBCC( $email ) {
		return implode( ',', $email->getBCC() );
	}

	/**
	 * Get Email Sender
	 *
	 * Returns the string containing the sender of the email
	 *
	 * @param EmailConversation $email Email
	 *
	 * @return string Sender
	 */
	protected function getEmailSender( $email ) {
		return $email->getCustomer()->getFirstEmail();
	}

	/**
	 * @param EmailConversation $email
	 */
	public function dontHaveEmailEmail( $email ) {
		$this->client->conversations()->delete( $email->getId() );
	}

	/**
	 * @param int $timeout_in_second
	 * @param int $interval_in_millisecond
	 *
	 * @return ModuleWait
	 */
	protected function wait( $timeout_in_second = 30, $interval_in_millisecond = 250 ) {
		return new ModuleWait( $this, $timeout_in_second, $interval_in_millisecond );
	}

	/**
	 * Wait until an email to be received from a specific sender email address.
	 *
	 * @param  int   $mailboxID
	 * @param string $emailAddress
	 * @param int    $timeout
	 */
	public function waitForEmailFromSender( $mailboxID, $emailAddress, $timeout = 5 ) {
		$condition = function () use ( $mailboxID, $emailAddress ) {
			$this->fetchEmails( $mailboxID );
			foreach ( $this->fetchedEmails as $email ) {
				$constraint = Assert::equalTo( $emailAddress );
				if ( $constraint->evaluate( $this->getEmailSender( $email ), '', true ) ) {
					return true;
				}
			}

			return false;
		};

		$message = sprintf( 'Waited for %d secs but no email from the sender %s has arrived', $timeout, $emailAddress );

		$this->wait( $timeout )->until( $condition, $message );
	}
}