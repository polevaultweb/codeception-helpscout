Codeception Help Scout
==========

A Help Scout email module for Codeception.

## Installation
You need to add the repository into your composer.json file

```bash
    composer require --dev polevaultweb/codeception-helpscout
```

## Usage

You can use this module as any other Codeception module, by adding 'HelpScout' to the enabled modules in your Codeception suite configurations.

### Add Drip to your list of modules

```yml
modules:
    enabled:
        - HelpScout
 ```  

### Setup the configuration variables

```yml
    config:
        HelpScout:
            app_id: '%HELPSCOUT_APP_ID%'
            app_secret: '%HELPSCOUT_APP_SECRET%'
 ```     
 
Update Codeception build
  
  ```bash
  codecept build
  ```
  
### Supports

This Codeception Module implements the required methods to test emails using the [Codeception Email Testing Framework](https://github.com/ericmartel/codeception-email) with [Help Scout](https://www.helpscout.com/referral/?code=TzdWZDcwbU0xa2Z6Rnh6c2s4TGxHTmR6L3ptd3J2dzlpb210L0RzUCtjbWVvUT09OnJENTdzaHA2RE5XbGVDa2E)

### Added Methods
This Module adds a few public methods for the user, such as:
```
dontHaveEmailEmail()
```
Deletes an email in Help Scout

```
fetchEmails()
```
Fetches all conversations from Help Scout, and assigns them to the current and unread inboxes

```
openNextUnreadEmail()
```
Pops the most recent unread email and assigns it as the email to conduct tests on

```waitForEmailFromSender()```
Waits for an email to arrive from a specific email address

### Usage

```php
$I = new AcceptanceTester( $scenario );

$I->fetchEmails( 12345 );
$I->openNextUnreadEmail();
$I->seeInOpenedEmailSubject( 'Thank you' );
$I->seeInOpenedEmailSender( 'john@doe.com' );
$I->seeInOpenedEmailBody( 'Hey, thanks for the great product' );
```

