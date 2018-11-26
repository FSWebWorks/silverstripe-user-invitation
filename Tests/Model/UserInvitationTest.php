<?php

namespace FSWebWorks\SilvserStripe\UserInvitations\Tests\Model;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use FSWebWorks\SilvserStripe\UserInvitations\Model\UserInvitation;

class UserInvitationTest extends SapphireTest
{
    public static $fixture_file = 'UserInvitationTest.yml';

    public function setUp()
    {
        parent::setUp();
    }

    /**
     * Tests that an invitation email was sent.
     */
    public function testSendInvitation()
    {
        Injector::inst()->registerService(new EmailTest_Mailer(), 'Mailer');
        /** @var UserInvitation $joe */
        $joe = $this->objFromFixture('UserInvitation', 'joe');

        $sent = $joe->sendInvitation();
        $this->assertEquals($joe->Email, $sent['to']);
        $this->assertEquals("Invitation from {$joe->InvitedBy()->FirstName}", $sent['subject']);
        $this->assertContains('Click here to accept this invitation', $sent['content']);
    }

    /**
     * Tests for expired invitations
     */
    public function testIsExpired()
    {
        /** @var UserInvitation $expired */
        $expired = $this->objFromFixture('UserInvitation', 'expired');
        $this->assertTrue($expired->isExpired());
    }

    /**
     * Tests that the TempHash field has been removed
     */
    public function testGetCMSFields()
    {
        /** @var UserInvitation $joe */
        $joe = $this->objFromFixture('UserInvitation', 'joe');
        $fields = $joe->getCMSFields();
        $this->assertNull($fields->dataFieldByName('TempHash'));
        $this->assertNotNull($fields->dataFieldByName('FirstName'));
        $this->assertNotNull($fields->dataFieldByName('Email'));
    }

    /**
     * Tests that invitations can't be re-sent.
     */
    public function testInvitationAlreadySent()
    {
        $invite = UserInvitation::create(array(
            'FirstName' => 'Joe',
            'Email' => 'joe@soap.person'
        ));
        $result = $invite->validate();
        $this->assertFalse($result->valid());
        $this->assertEquals('This user was already sent an invite.', $result->message());
    }

    /**
     * Tests that duplicate members can't be created
     */
    public function testMemberAlreadyExists()
    {
        $invite = UserInvitation::create(array(
            'FirstName' => 'Jane',
            'Email' => 'jane@doe.person'
        ));
        $result = $invite->validate();
        $this->assertFalse($result->valid());
        $this->assertEquals('This person is already a member of this system.', $result->message());
    }

    /**
     * Tests that a random hash and the logged in users id was added
     */
    public function testOnBeforeWrite()
    {
        $this->logInWithPermission('ADMIN');
        $invite = UserInvitation::create(array(
            'FirstName' => 'Dane',
            'Email' => 'dane@example.com'
        ));
        $invite->write();
        $this->assertNotNull($invite->TempHash);
        $this->assertNotNull($invite->InvitedByID);
    }
}
