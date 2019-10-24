<?php

namespace Codeception\Module;

use Codeception\Module;
use HelpScout\Api\ApiClientFactory;
use HelpScout\Api\Conversations\ConversationFilters;
use HelpScout\Api\Conversations\ConversationRequest;
use HelpScout\Api\Conversations\EmailConversation;

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

		// by default, work on all emails
		$this->setCurrentInbox( $this->fetchedEmails );
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
}