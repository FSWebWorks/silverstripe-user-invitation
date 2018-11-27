<?php

namespace FSWebWorks\SilverStripe\UserInvitations\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use FSWebWorks\SilverStripe\UserInvitations\Model\UserInvitation;

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
        /** @var UserInvitation $joe */
        $joe = $this->objFromFixture(UserInvitation::class, 'joe');

        $sent = $joe->sendInvitation();
        $keys = array_keys($sent->getTo());
        $this->assertEquals($joe->Email, $keys[0]);
        $this->assertEquals("Invitation from {$joe->InvitedBy()->FirstName}", $sent->getSubject());
        $this->assertContains('Click here to accept this invitation', $sent->getBody());
    }

    /**
     * Tests for expired invitations
     */
    public function testIsExpired()
    {
        /** @var UserInvitation $expired */
        $expired = $this->objFromFixture(UserInvitation::class, 'expired');
        $this->assertTrue($expired->isExpired());
    }

    /**
     * Tests that the TempHash field has been removed
     */
    public function testGetCMSFields()
    {
        /** @var UserInvitation $joe */
        $joe = $this->objFromFixture(UserInvitation::class, 'joe');
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
        $this->assertFalse($result->isValid());
        $this->assertEquals('This user was already sent an invite.', $result->getMessages()[0]['message']);
    }

    /**
     * Tests that duplicate members can't be created
     */
    public function testMemberAlreadyExists()
    {
        $invite = UserInvitation::create(array(
            'FirstName' => 'Jane',
            'Email' => 'jane@doe.clone'
        ));
        $result = $invite->validate();
        $this->assertFalse($result->isValid());
        $this->assertEquals('This person is already a member of this system.', $result->getMessages()[0]['message']);
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
